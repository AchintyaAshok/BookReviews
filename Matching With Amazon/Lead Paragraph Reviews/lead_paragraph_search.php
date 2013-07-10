<?php
/*
	Author:			Achintya Ashok
	Date-Created:	07/09/13

	Purpose:		This Script will contain a series of functions devoted to retrieving ISBNs for book reviews using the lead paragraph
					entry of each review in it's ADD form. Essentially, the thought is that the lead paragraph in many of the reviews
					begins with a sentence in the form [Title] by [Author]. If we're able to extract the title and author from the 
					lead paragraph, we can either use Amazon's Itemsearch API to find a matching ISBN or Balker's Book Database.

					1. Get a JSON file of all Sunday Book Reviews consisting of web_url, author, title, (id?)
						-	Extract the Title & Author from lead paragraph
					2. Use Amazon's Search API to get the matching ISBNs
*/

$basePath = dirname(__FILE__);
require_once($basePath . '/Library/data_functions.php');
require_once($basePath . '/Library/json_functions.php');

define(LEAD_PARAGRAPH_QUERY, '(taxonomy_nodes:"Top/Features/Books/Book Reviews" OR  subject:"Book Reviews" OR ((subject:"Reviews" OR  type_of_material:"Review") AND subject:"Books and Literature")) AND document_format:"fast" AND day_of_week:"Sunday"');

/*	This function checks if the majority of words in a given 'author' string begin with uppercase letters. This is important
	because if the author is not correct, we can usually rule it out because of a lower case beginning letter. This also
	accounts for cases in which we have multiple authors in the author string, separated by articles like 'and'; ideally, the function
	will check if most of the words begin in uppercase. If this is not the case, the function returns false.	*/
function check_author_case($author){
	$strArray = explode(' ', $author);
	$numWords = count($strArray);
	// We can adjust strictness, right now let's default it to check that at least 50% of words begin with capital letters.
	$numCaps = 0;
	for($i=0; $i<$numWords; $i++){
		$word = $strArray[$i];
		if (ctype_upper($word[0]))	$numCaps++;
	}

	$percentUpper = ((float)$numCaps)/((float)$numWords) * 100;
	if ($percentUpper < 50) return false;
	return true;
}


/*	This function is used to extract the title and author, separated in the fashion '[Title] By [Author]'. 
	The string will return an associative array of Title => 'matched title', Author => 'matched author' 
	If no separator is found, the function will return false indicating that the string could not be parsed 
	for a Title, Author. */
function extract_title_author($string){
	//	FIND THE POSITION WHERE THE WORD 'By' IS
	$byPosition = stripos($string, 'By');
	if (!$byPosition){
		//print "Unable to extract.";
		return false;
	}
	$left = substr($string, 0, $byPosition);	//	Starting at the string's beginning until 'By'
	if (strlen($left) > 100){
		// If the length of the "title" is above a certain number, we can assume that the "by" that stripos found was not 
		// referencing the by we expect to, it may be referencing the word 'by' as it exists in the review. Sometimes
		// the lead paragraph contains an exerpt of the actual review, and we don't want to get a massive title that 
		// really isn't the title.
		return false;
	}
	$right = substr($string, $byPosition + 3);		//	Starting at 'By', until the end, this will hold the author

	//	GET THE POSITION OF WHERE THE AUTHOR's NAME ENDS
	$commaPos = stripos($right, ',');
	// With Periods we have to be careful in case this period comes after an Initial in a person's name.
	$periodPos = stripos($right, '.');
	$nextChar = $right[$periodPos-2];	//	If the character 2 spaces before the period is a space, it is almost always the period following an initial.
										//	for example, If the Person's name is Arthur C. Clarke, we can see that the period here is incorrect.
	if ($nextChar == ' ')	$periodPos = false;

	//	COMPARE THE RELATIVE POSITIONS OF THE COMMA AND PERIOD
	if (!$commaPos && !$periodPos){
		//	If there is no position found with either a comma or period, we just return the author untruncated
		return array("Title"=>$left, "Author"=>$right);	
	}
	else if ($commaPos && !$periodPos){
		$right = substr($right, 0, $commaPos);
	}
	else if (!$commaPos && $periodPos){
		$right = substr($right, 0, $periodPos);
	}
	else{
		$trunkPos = ($commaPos < $periodPos) ? ($commaPos):($periodPos);	//	Get the position of whichever comes first
		$right = substr($right, 0, $trunkPos);	//	truncate the author at the point where a comma or a period appears
	}

	$correctCase = check_author_case($right);
	if (!$correctCase) return false;
	return array("Title"=>$left, "Author"=>$right);
}

function get_all_valid_entries($outputFile){

	$outFileHandle = fopen($outputFile, "w+");
	fwrite($outFileHandle, "[");

	// Setup
	$filters = array();
	$filters['q'] = rawurlencode("sunday book review");	//	This additional filter restricts the results to sunday book reviews
	$filters['sort'] = 'newest';
	$filters['facet'] = 'true';
	$filters['begin_date'] = 19810101;
	$filters['end_date'] = 20131231;
	//$filters['offset'] = 30;

	$url = construct_search_url(LEAD_PARAGRAPH_QUERY, $filters);
	//print $testurl . "\n\n";
	$offset = 0;
	$fixedUrl = $url;	//	This is the url that will get modified to append the offset each iteration of the loop
	
	$total = 0;
	$matched = 0;
	$startTime = microtime(true);
	$prefix = "";
	
	while (true){
		$data = extractInformation($fixedUrl);
		if (!$data) continue;

		$docs = $data['response']['docs'];
		if (count($docs) == 0) break;	//	This means we are querying a url that doesn't have any entries, any subsequent
										//	ones will not either.
		foreach($docs as $entry){
			$total++;
			$leadParaStr = $entry['lead_paragraph'];
			$authTitleArray = extract_title_author($leadParaStr);
			if ($authTitleArray){
				$author = $authTitleArray['Author'];
				$author = str_replace('"', '', $author);
				$title = $authTitleArray['Title'];
				$title = str_replace('"', '', $title);
				//print "Title: $title" . " -- Author: $author" . " -- Offset: $offset" ."\n";
				$web_url = $entry['web_url'];
				$id = get_value_recursive($entry, "_id");
				$toEncode = array(array('url', $web_url), array('title', $title), array('author', $author), array('_id', $id));
				$encodedStr = encodeInJSON($toEncode);
				fwrite($outFileHandle, $prefix);
				fwrite($outFileHandle, $encodedStr);
				$prefix = ",\n";
				$matched++;
			}
		}

		$offset += 10;
		$fixedUrl = $url . "&offset=$offset";	//	Add the new offset to the url
	}
	fwrite($outFileHandle, "]");
	fclose($outFileHandle);

	$endTime = microtime(true);
	$timeElapsed = $endTime - $startTime;
	print "\nTime Elapsed: $timeElapsed\nNumber Entries Attempted: $total\tNumber Matched: $matched\n\n";
}
//var_export($data);

get_all_valid_entries("lead_para_extraction.txt");


?>