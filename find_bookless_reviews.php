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
	
	//print "Extraction URL:\t" . $json_url . "\n";
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


function check_all_urls($file_in, $file_out=NULL){
	
	$lineArray = file($file_in);
	$numberURLs = count($lineArray)-2;
	print "\nNumber of URLs:\t$numberURLs\n";
	
	$bodyless = array();		//	Our array that holds
	
	for ($i=1; $i<count($lineArray)-1; $i++){
		
		$stringToDecode = $lineArray[$i];
		$parsed = str_replace("\t", "", $stringToDecode);		//	Remove the tab space at the beginning of the string
		$parsed = substr($parsed, 0, strlen($parsed)-2);		//	Remove the trailing comma at the end of the line & the newline character
		
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
		print "\t$elem\n";
		if ($file_out_handle){
			fwrite($file_out_handle, $elem);
			fwrite($file_out_handle, "\n");	
		}
	}
	
	fclose($file_out_handle);
}


$url = $argv[1];
check_all_urls($url, "missing_URLs.txt");


?>
