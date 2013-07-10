<?php
/*
*	Author:		Achintya Ashok
*	Date-Created:	07/01/13
*
*	Purpose:	This finds all the Book Reviews that have exist in Glass that contain author/title metadata.
*/

require_once 'data_functions.php';
require_once 'json_functions.php';

/*
*	This function takes the url of an article that presumably has a Glass Entry and retrieves title/author information about the article.
*	If the article is validly present in Glass, the function returns a JSON entry containing the url, title and author of the article. 
*	If both the author and title are missing, the function will return false. In addition, if the url does not have a Glass entry, the function
*	will return false.
*/
function process_single_url($url){
	$decodedData = get_glassInformation_from_url($url);
	if(!$decodedData) return false;

	$articleInformation = $decodedData['cms']['article']['article_review'];

	$author = $articleInformation['author'];
	$title = $articleInformation['review_title'];

	if (strlen($author) == 0 && strlen($title) == 0) return false;		//	If neither the author nor the title could be derived, we return false.

	//	Clean the strings by stripping them of quotes to make them json-readable
	$author = str_replace('"', "", $author);
	$title = str_replace('"', "", $title);

	$arrayToEncode = array(array('url',$url), array('author',$author), array('title', $title));
	$jsonEncodedStr = encodeInJSON($arrayToEncode);

	return $jsonEncodedStr;
}



function get_all_glass_entries(){
	$timeStart = microtime(true);

	$outHandle = fopen('glass_matches.txt', 'w');
	if (!$outHandle) return;
	fwrite($outHandle, "[");
	$prefix = "";

	$additionalFilter = '%20AND%20' . urlencode('document_format:') . '"glass"';
	$datedADDURL = get_dated_jsonURL(19810101, 20131231, $additionalFilter);

	$total = 0;				//	Counter that keeps track of how many glass entries were inputted successfully
	$tries = 0;
	$url = $datedADDURL;	
	$offset = "";			//	This offset is a suffix to the url that pulls the subsequent 10x entries everytimes it gets updated in the loop
	$offsetCounter = 0;		//	Keeps track of the offset value that gets updated

	while (true){
		$offsetCounter += 10;
		$url = $url . $offset;	//	Append the offset filter to the url

		$data = extractInformation($url);
		if (is_int($data)) break;	// this means that we've processed all the entries we can and the offset no longer retrieves any more entries
		$docs = get_value_recursive($data, 'docs');		//	Docs is the element in which query results are stored in the Raw Results Page
		if (count($docs) == 0) break;

		foreach ($docs as $elem){
			$tries++;
			//	get the url of each entry from the page and then process it to get the glass metadata
			$elem_url = get_value_recursive($elem, 'web_url');
			$processedElement = process_single_url($elem_url);

			if (!$processedElement) continue;	//	If the function returns false, it means that the url does not have a glass entry or the script could not extract any information about it
			
			$total++;
			//print "$processedElement\n";
			fwrite($outHandle, $prefix);
			fwrite($outHandle, $processedElement);
			$prefix = ",\n";
		}	

		$offset = "&offset=$offsetCounter";
		if ($tries%50 == 0){
			print "Number of Processed Entries:\t$tries\n";
		}
	}

	fwrite($outHandle, "]");
	fclose($outHandle);

	$timeEnd = microtime(true);
	$computeTime = $timeEnd - $timeStart;
	print "\n\nTotal Entries Processed:\t$total\nTotal Time Elapsed:\t$computeTime\n";
}

//	MAIN PROCEDURE

get_all_glass_entries();

?>