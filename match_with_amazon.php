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
	if ($attempt = false){
		print "Problem ~ Auth: $author\tTitle: $title\n";
		return false;	//	If the search could not be performed, the object returns false.
	}

	$firstMatchedItem = $lookupObject->get_item_data(1);
	$isbn = $firstMatchedItem['attributes']['ISBN'];

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
		if(!$data)	continue;	//	This means the function was unable to extract info for this line, so we continue

		// var_export($data);
		// return;

		$url = $data['url'];
		$title = $data['title'];
		$author = $data['author'];

		if (strlen($title)>0 && strlen($author)>0){
			$numberProcessed++;

			$newLine = match_single_book($url, $title, $author);
			if (!$newLine){
				print "*\t";
				continue;
			}	
			
			fwrite($outHandle, $prefix);
			fwrite($outHandle, $newLine);
			$numberMatched++;
		}
		$prefix = ",\n";
	}

	fwrite($outHandle, "]");

	$timeEnd = microtime(true);
	$elapsed = $timeEnd - $timeStart;
	print "\nTotal Number Processed: $numberProcessed\tMatched:\t$numberMatched\nTime Elapsed: $elapsed\n\n";

}


//	MAIN FUNCTIONALITY

match_all_books("glass_matches.txt", "isbn_matches.txt");



// //	Specify what you're doing the amazon search for
// $index = 'Books';
// $params = array(
// 	'Title'=>'Inferno',
// 	'Author'=>'Dan Brown'
// 	);
// $resGroup = 'ItemAttributes';

// //	Instantiate an AmazonSearch Object
// $obj = new AmazonSearch($index, $params, $resGroup);
// print $obj->get_query_url() . "\n\n";

// $obj->execute();
// $firstItem = $obj->get_item_data(1);
// var_export($firstItem);

?>