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
	// $amazonSearchURL = amazon_get_signed_url($title, $author);
	// $isbn = extract_isbn($amazonSearchURL);
	// if (!$isbn) return false;		//	If we're unable to extract the isbn given the title/author,
	// 								//	there could be either an error in accessing the api or that the title/author combination simply doesn't yield any results.
	$params = array(
	'Title'=>$title,
	'Author'=>$author
	);

	$lookupObject = new AmazonSearch(SEARCH_INDEX, $params, RESPONSE_GROUP);
	$attempt = $lookupObject->execute();
	// TESTING
	//print "Looking UP:\t" . $lookupObject->get_api_url() . "\n";

	if ($attempt == false){
		//print "\nProblem ~ Auth: $author\tTitle: $title\n";
		//print $lookupObject->get_api_url() . "\n--------------------";
		return false;	//	If the search could not be performed, the object returns false.
	}

	$firstMatchedItem = $lookupObject->get_item_data(1);
	$isbn = $firstMatchedItem['attributes']['ISBN'];
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
		//print "processing line...\n";
		$data = parse_json_get_data($line);
		if(!$data){
			//print "Did not decode correctly...\n";
			continue;	//	This means the function was unable to extract info for this line, so we continue
		}

		// $url = $data['url'];
		// $title = $data['title'];
		// $author = $data['author'];
		$url = $data['web_url'];
		$title = $data['Title'];
		$author = $data['Author'];

		if (strlen($title)>0 && strlen($author)>0){
			$numberProcessed++;
			$newLine = match_single_book($url, $title, $author);
			if (!$newLine){
				//print "--No Matches Found--$title/$author\n";
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