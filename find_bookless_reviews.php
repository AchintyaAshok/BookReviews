<?php
/*
 * 	Author:			Achintya Ashok
 * 	Date-Created:	06/17/13
 * 
 * 	Purpose:		This script is used to find all the Book Reviews from a given json file of book reviews that do not have bodies stored in the metadata of the file.
 */

require 'json_functions.php';

/*
 * 	This function checks if the given URL(referring to a book review) has content in the entry's body. The function will return true if the body has information and false otherwise.
 */
function has_body_information($url){
	
	$to_encode = "web_url:";
	$to_encode .= '"' . $url . '"';
	$encoded_url = urlencode($to_encode);
	
	//print "encoded url:\t" . $encoded_url . "\n";
	
	$json_url = get_jsonURL_from_query($encoded_url);
	
	print "Extraction URL:\t" . $json_url . "\n";
	//exit(1);
	
	$decoded = extractInformation($json_url);
	//print "\nvar_exporting...:\t" . var_export($decoded) . "\n\n";
	
	//print "\n\nLegacy size:\t" . count($decoded['response']['docs'][0]['legacy']);
	
	$body = $decoded['response']['docs'][0]['legacy']['txt'];			//	This is the heirarchy of elements needed to be traversed to get the body of the review
	//print $body;
	if (strlen($body) == 0){
		return false;
	}
	return true;
}

	
$testURL = "http://www.nytimes.com/1990/12/23/books/children-s-books-952890.html";
if (has_body_information($testURL)){
	print "true\n";
}
else{
	print "false\n";
}


?>
