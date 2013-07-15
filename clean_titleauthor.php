<?php

/*
	Author:			Achintya Ashok
	Date-Created:	07/15/13

	Purpose:		This file is used to update the author/title tuples in the lead-paragraph extraction of book-review ISBNs that
					was performed. Essentially, we began by extracting the Title & Author from ADD from the lead-paragraph entry
					of Sunday Book Reviews. Then, we used these extracted values and queried Amazon for the matching ISBN for the
					entry. However, this file will be used to query Amazon using our original Title/Author keywords to get a "clean" version of the
					title and author so that we don't have any stray or unneeded characters when using the file to insert into the database.

					Additionally, once the real title and author for an entry is found, we want to convert the entries to a .csv
					format for easy entry into the Book Database. Values are pipe de-limited in this form:

						btrn  | title | author | bookReviewURL | sundayReviewURL | asin | amazonURL

					An extra check must be done (by parsing the web_url of each entry) to check if the book review is a Sunday Book Review. If the entry is indeed a SBR, we put the web_url in the sunday_review_url field, and if not, in the book_review_url field.
*/

/*	INCLUDES */
$basePath = dirname(__FILE__);
require_once($basePath . '/Library/data_functions.php');
require_once($basePath . '/Library/amazon_lookup_functions.php');

/*	GLOBAL CONSTANTS */
define(SEARCH_INDEX, 'Books');
define(RESPONSE_GROUP, 'ItemAttributes');

date_default_timezone_set('America/New_York');




function read_and_convert_file($fileIn, $fileOut = NULL){

	$timeStart = microtime(true);

	$encodedFile = file_get_contents($fileIn);
	$decodedData = json_decode($encodedFile, true);
	if(!$decodedData){
		print "Could not decode file. It needs to be valid JSON\n\n";
		exit();
	}

	foreach($decodedData as $elem){
		$csvFormattedLine = clean_and_construct_csv($elem);
		print "\n" . $csvFormattedLine . "\n---------------------\n";
		usleep(1000000);
	}

	$timeEnd = microtime(true);
	$elapsed = $timeEnd - $timeStart;
	print "\nTotal Number Processed: $numberProcessed\tMatched:\t$numberMatched\nTime Elapsed: $elapsed\n\n";
}



/*
	This function takes an array consisting of BowkerID, Title, Author, web_url and ISBN key/value bindings,
	finds the cleaned format of the Title and Author entries as they exist on Amazon for the Book Review
	and additionally pulls the Amazon ASIN and Product Link for the book. It takes this information
	and creates a CSV format of the values delimited by pipes | . 

	The resulting format of the csv:
		btrn  | title | author | bookReviewURL | sundayReviewURL | asin | amazonURL

	If the Book Review is identified as a Sunday Book Review, the web_url gets moved to the sundayReviewURL column,
	otherwise it will be present in the bookReviewURL column. Finally, if Amazon Information can be found given the
	original Title and Author, the asin and amazonURL fields will be populated appropriately.
*/
function clean_and_construct_csv($data){
	$oldTitle = $data['Title'];
	$oldAuthor = $data['Author'];
	$params = array(
		"Title"=>$oldTitle, 
		"Author"=>$oldAuthor
	);

	//	We give the keys these letter names, so that we can smartly sort the array by keys before converting it
	//	into a csv string

	$cleanAttempt = $cleanedData = get_clean_information($params);	//	get the new title, author, asin & amazon url
	if (!$cleanAttempt){
		$csvParams['a'] = $oldTitle;					//	a references the title field
		$csvParams['b'] = $oldAuthor;					//	b references the author field
	}
	else{
		$csvParams['a'] = $cleanedData['title'];
		$csvParams['b'] = $cleanedData['author'];
	}

	// Now check if it was a Sunday Review:
	$csvParams['c'] = "";								//	c references the bookReviewURL field
	$csvParams['d'] = "";								//	d references the sundayReviewURL field
	$isSundayReview = check_if_sunday_review($data['web_url']);
	if ($isSundayReview){
		//print "Is Sunday\n";
		$csvParams['d'] = $data['web_url'];
	}
	else{
		//print "Not Sunday\n";
		$csvParams['c'] = $data['web_url'];	
	}

	// Now append the ISBN:
	$csvParams['e'] = $data['ISBN'];					//	e references the Book's ISBN


	if($cleanAttempt){	//	Add the ASIN and Amazon URL to the csv parameter array if we received it
		// In case we don't receive the asin or amazonURL, we still want to maintain the ordering of values in the csv file
		$csvParams['f'] = "";
		$csvParams['g'] = "";
		$csvParams['f'] = $cleanedData['asin'];			//	f references the asin number
		$csvParams['g'] = $cleanedData['amazonURL'];	//	g references the amazonURL
	}

	foreach($csvParams as $key=>$value){
	//	Remove any double-quotes from any of the values then prefix and append the double quote to the value.
		$value = str_replace('"', '', $value);
		$value = ' "' . $value . '" ';
	}

	ksort($csvParams);
	var_export($csvParams);
	$csvString = implode('|', $csvParams);
	return $csvString;
}



/*	
	This function takes the New York Times URL for a book review which has been formatted in the manner:
	http://www.nytimes.com/2012/07/30/ . It checks the Date given trailing the nytimes.com prefix. If the given
	date is a Sunday, the function returns true. In the case that it is not a Sunday OR if the url is not properly
	formatted, the function will return false.
*/
function check_if_sunday_review($url){
	//$testurl = "http://www.nytimes.com/2012/08/27/books/nw-by-zadie-smith.html";
	//$url = $testurl;

	$prefix = strpos($url, "www.nytimes.com/");
	if (!$prefix){
		print "Uninterpretable\n";
		return false;		//	We check if the url begins with the prefix, and if it doesn't we're 
							//	quite confident that the date cannot be extracted from the url
	}
	//	Sample URL Format:	http://www.nytimes.com/2012/05/13/books/review/planet-tad-by-tim-carvell.html 
	$startPos = strpos($url, "com/");
	$startPos += 4; 							// Get to the position right after nytimes.com/ (excluding the slash)
	$endPos = strpos($url, "/books/");	//	This is what the Date is followed by in the url
	$endPos -= 1;

	$dateStr = substr($url, $startPos, $endPos - $startPos + 1); // Get the entire date string, now the string is formatted
																	// like this: YYYY/MM/DD
	print "Date-String:\t\t$dateStr\n";
	
	
	$attemptSuccess = $timestamp = strtotime($dateStr);	//	This converts our date string into a valid unix timestamp
	if (!$attemptSuccess) return false;
	$day = strftime("%A", $timestamp);	//	Use the strftime function to capture what day of the week the article was published
	print "Interpreted Day:\t$day\n";
	//exit();

	if ($day == "Sunday") 	return true;
	else 					return false;
}


/*
	Takes an associative array containing a Title, Author, web_url and isbn and then looks up the Amazon Equivalent of the
	book, given the title and author. This function retrieves a clean and acceptable version (without any stray characters), of
	the title and author from Amazon (given the original Title and Author). It will return the Title, Author, the ASIN (Amazon's Standard
	Identification Number), and the Amazon URL for the book that was retrieved from Amazon; All in an associative array.
	If the search query could not be executed, the function will return false. 

	Returned Value:	array("Title"=>"Inferno", "Author"=>"Dan Brown", "asin"=>112312311, "amazonURL"=>"www.amazon.com/asdfadsf")
*/
function get_clean_information($params){
	$search = new AmazonSearch(SEARCH_INDEX, $params, RESPONSE_GROUP);
	$attempt = $search->execute();
	if (!$attempt)	return false;
	
	$firstResult = $search->get_item_data(1);
	$data['title'] = $firstResult['attributes']['Title'];
	$data['author'] = $firstResult['attributes']['Author'];
	$data['asin'] = $firstResult['asin'];			//	Amazon's Standard Identification Number
	$data['amazonURL'] = $firstResult['url'];		//	The Amazon Link for the Book Information

	return $data;
}


read_and_convert_file("test_data.txt");


// TESTING
// $parameters = array("Author"=>"Dan Brown", "Title"=>"Inferno");


// $search = new AmazonSearch(SEARCH_INDEX, $parameters, RESPONSE_GROUP);
// $search->execute();
// $data = $search->get_item_data(1);

// var_export($data['attributes']);


// if(check_if_sunday_review("asdfasdfa")){
// 	print "It was a Sunday Review!!\n";
// }





?>