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


function match_single_book($url, $title, $author){
	$amazonSearchURL = amazon_get_signed_url($title, $author);
	$isbn = extract_isbn($amazonSearchURL);
	if (!$isbn) return false;		//	If we're unable to extract the isbn given the title/author,
									//	there could be either an error in accessing the api or that the title/author combination simply doesn't yield any results.

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
			if (!$newLine) continue;	
			
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

//print amazon_get_signed_url("Return of the King", "Tolkien") . "\n\n";
//match_all_books("glass_matches.txt", "isbn_matches.txt");

$index = 'Books';
$params = array(
	'Title'=>'Inferno',
	'Author'=>'Dan Brown'
	);
$resGroup = 'ItemAttributes';
$obj = new AmazonSearch($index, $params, $resGroup);
print $obj->get_query_url() . "\n\n";

$obj->execute();
print "The Amazon Link:\t" . $obj->get_amazon_link() . "\tNumber Of Results:\t" . $obj->get_number_results() . "\n\n";


?>