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
 * 	It checks the ADD Index of the given URL to see if there is any content.
 */
function has_body_information($url){
	
	$to_encode = "web_url:";
	$to_encode .= '"' . $url . '"';
	$encoded_url = urlencode($to_encode);
	$json_url = get_jsonURL_from_query($encoded_url);

	$decoded = extractInformation($json_url);
	if (is_int($decoded)){
		print "Unable to extract from Raw-URL:\t$json_url\n";
		return false;
	}
	$id = $decoded['response']['docs'][0]['_id'];
	//print "id=$id\n";
	
	$addIndexURL = get_ADDIndexURL_from_id($id);
	$ADDdecode = extractInformation($addIndexURL);
	if (is_int($decoded)){
		print "Unable to extract from ADD-URL:\t$addIndexURL\n";
		return false;
	}
	
	$body = $ADDdecode['body'];
	if($body){
		//print "YESSSSSS\n";
		return true;
	}
	else{
		//print "No :(\n";
		return false;
	}
}

/*
 * 	This function is used for checking an entire file that is encoded in JSON and determining which URLs have valid bodies associated with them in the ADD Index.
 * 	If a link does not have a body with it, it is outputted to standard out and additionally written to a file who's name is optionally specified in the second parameter.
 * 	The json file must be structured in a specific format, namely:
 * 	1:		[\n
 * 	2:		{ json-string },
 * 			...
 * 	n:		{ json-string }
 * 	n+1:	] 
 */
function check_all_urls($file_in, $file_out=NULL){
	
	$lineArray = file($file_in);
	$numberURLs = count($lineArray)-2;
	print "\n-- Checking File: '$file_in' --\nNumber of URLs:\t$numberURLs\n";
	
	$bodyless = array();		//	Our array that holds
	
	for ($i=1; $i<count($lineArray)-1; $i++){
		
		
		$stringToDecode = $lineArray[$i];
		/*
		$parsed = str_replace("\t", "", $stringToDecode);		//	Remove the tab space at the beginning of the string
		$parsed = substr($parsed, 0, strlen($parsed)-2);		//	Remove the trailing comma at the end of the line & the newline character
		*/
		$leftPosition = strripos($stringToDecode, '{');
		if(!$leftPosition)	continue;
		$rightPosition = strripos($stringToDecode, '}');
		
		$parsed = substr($stringToDecode, $leftPosition, $rightPosition-$leftPosition + 1);	//	Just get the content within the brackets {...}
		//print "Parsed json:\t$parsed\n";
		//exit(1);
		
		$decodedInformation = json_decode($parsed, true);		//	Decode the string into an array derived from the JSON
		$url = $decodedInformation['URL'];
		
		if (!has_body_information($url)){
			array_push($bodyless, $url);
		}
	
		if($i%100 == 0){
			print "Processed:\t$i\tRemaining:\t" . ($numberURLs - $i) . "\n";
		}
		//usleep(50000);
	}
	
	if ($file_out){
		$file_out_handle = fopen($file_out, "a+");
	}
	else{
		$file_out_handle = NULL;
	}
	
	print "\nTotal Number of URLs without a body:\t" . count($bodyless) . ":\n";
	foreach($bodyless as $elem){
	//	Output each url that doesn't have  valid body and additionally write it to the output file
		print "\t$elem\n";
		if ($file_out_handle){
			fwrite($file_out_handle, $elem);
			fwrite($file_out_handle, "\n");	
		}
	}
	fclose($file_out_handle);
}


$url = $argv[1];
check_all_urls($url, "missing_URLs_revised.txt");


?>
