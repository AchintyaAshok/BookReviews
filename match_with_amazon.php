<?php

/*
*	Author:	Achintya Ashok
*	Purpose:	The purpose of this file is to read entries from a json file, matching the author/title combinations of books
*				to their ISBNs. To accomplish this, we will make use of Amazon's ItemSearch API using the title & author as search parameters.
*	
*/

require 'amazon_lookup_functions.php';
require_once 'json_functions.php';
require_once 'data_functions.php';


define('SEARCH_INDEX', 'Books');
define('RESPONSE_GROUP', 'ItemAttributes');

function match_single_book($url, $title, $author){
	// ItemSearch Parameters we are including to whittle down our search
	$params = array(
	'Title'=>$title,
	'Author'=>$author
	);

	$lookupObject = new AmazonSearch(SEARCH_INDEX, $params, RESPONSE_GROUP);	//	Create a new AmazonSearch
	$attempt = $lookupObject->execute();										//	Execute the object to make the api call and pull results

	if ($attempt == false){
		return false;	//	If the search could not be performed, the object returns false.
	}

	$firstMatchedItem = $lookupObject->get_item_data(1);		//	Returns an associative array of information about the first results amazon returned
	$isbn = $firstMatchedItem['attributes']['ISBN'];			//	Get the ISBN
	if (strlen($isbn)==0){
		print "match_single_book::ISBN Field is Empty\n";
		return false;
	}

	$toEncode = array(array("web_url", $url), array("Title", $title), array("Author", $author), array("ISBN", $isbn) );
	$encodedJSONStr = encodeInJSON($toEncode);
	return $encodedJSONStr;
}

function match_all_books($filename, $outfile = NULL){

	$timeStart = microtime(true);

	$lineArray = file($filename);
	$outHandle = fopen($outfile, "w+");
	fwrite($outHandle, "[");

	$numberProcessed = 0;
	$numberMatched = 0;
	$prefix = "";

	foreach($lineArray as $line){
		$data = parse_json_get_data($line);
		if(!$data){
			continue;	//	This means the function was unable to extract info for this line, so we continue
		}

		$url = $data['url'];
		$title = $data['title'];
		$author = $data['author'];
		// $url = $data['web_url'];
		// $title = $data['Title'];
		// $author = $data['Author'];

		if (strlen($title)>0 && strlen($author)>0){
			$numberProcessed++;
			$newLine = match_single_book($url, $title, $author);
			if (!$newLine){
				usleep(1000000);
				continue;
			}	
			
			fwrite($outHandle, $prefix);
			fwrite($outHandle, $newLine);
			$numberMatched++;
		}
		$prefix = ",\n";
		usleep(1000000);
	}

	fwrite($outHandle, "]");

	$timeEnd = microtime(true);
	$elapsed = $timeEnd - $timeStart;
	print "\nTotal Number Processed: $numberProcessed\tMatched:\t$numberMatched\nTime Elapsed: $elapsed\n\n";
}


//	MAIN FUNCTIONALITY
$readFile = $argv[1];
$outFile = $argv[2];
//print "READ FILE:\t$readFile\nOUTPUT FILE:\t$outFile\n";
//match_all_books($readFile, $outFile);
match_all_books("glass_matches_revised.txt", "isbn_matches2.txt");


//match_all_books("glass_matches.txt", "isbn_matches.txt");
?>