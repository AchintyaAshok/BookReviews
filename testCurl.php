#!/usr/bin/php
<?php

/*
Use:	$decoded_data = extractInformation($my_URL);

Takes the URL(json_encoded webpage from the Search API), decodes the information obtained from the webpage and returns the associative array containing all the information.
If there is an HTTP error code that gets returned when requesting the URL, it will be returned as the return value.
*/
function extractInformation($url){

	$curl_handle = curl_init();
	// Configure the curl_handle 
	curl_setopt($curl_handle,CURLOPT_URL, $url);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);


	// Execution:
	$data = curl_exec($curl_handle);

	
	//SANITY CHECK
	$http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	if ($http_code != 200){
		return $http_code;		//	If there is a HTTP Error, we terminate execution 
	}

	$decoded = json_decode($data, TRUE);	//	'true' makes it an associative array

	curl_close($curl_handle);
	return $decoded;
}

/*
Use:	$array = getMetadata($decodedContent);

This function takes a json_decoded associative array of information on a page. 
Compiles the relevant metadata (url's) and outputs the list of URLs in the given section.
The function returns an array. The first index of the array is an array of URLs that was compiled from the page and the second index holds the number of entries that were recorded.
If there is no data that can be extracted from the given URL, the function will return -1.
*/
function getMetadata($infoArray){

	$urlArray = array();

	/*
	The information in the 'response' portion of the associative array is structured as such:
	response => docs(multiple entries) => legacy => web_url
	*/

	$docArray = $infoArray['response']['docs'];
	$numberOfEntries = count($docArray);
	if ($numberOfEntries == 0) return -1;;
	//print "\nNumber of docs:\t" . $numberOfEntries . "\n\n";

	for ($i = 0; $i < $numberOfEntries; $i++){
		//var_export($docArray[$i]) . "\n";
		$url = $docArray[$i]['legacy']['web_url'];
		print $url . "\n";
		array_push($urlArray, $url);
	}

	return array($urlArray, $numberOfEntries);
}

/*
Use:	$array_of_urls = getAllData($my_URL);

Takes a URL as a parameter and keeps incrementing the search offset to iterate through all entries.
The purpose of the function is to print out a list of all the URLs pertaining to the search.
It returns the array of URLs that were found and outputted.
*/
function getAllData($url){
	
	$offset = 0;
	$numberOfArticles = 0;
	$URLarray = array();
	$modifiedURL = $url;
	
	while(true){
		$decoded = extractInformation($modifiedURL);
		if (is_int($decoded))	break;					//	This means the function has returned a HTTP ErrorCode
		
		print "\n\nExtracting URLs. Offset = " . $offset . "\n";
		print "Modified-URL:\t" . $modifiedURL . ":\n";
		//print "Cumulative # of URLs:\t" . count($URLarray) . "\n";
		$result = getMetadata($decoded);				//	extract the URLs
		if (is_int($result))	break;					//	-1 return value indicates there are no articles 
	
		$offset += 10;									//	increment the offset to get the next 10 entries
		$modifiedURL = $url . "&offset=" . $offset;		//	construct the new URL with the new offset
		
		
		$numberOfArticles += $result[1];
		$URLarray = array_merge($URLarray, $result[0]);	//	append the new URLs to our URL array
	}
	
	return $URLarray;
}


$url_array = array();

if (isset($argv[1])){
	if (!is_string($argv[1])){
		print "\nProvide the URL in 'quotes'\n";
		exit(1);
	}
	$url_array = getAllData($argv[1]);
	print "Total Number of URLs:\t" . count($url_array) . "\n\n";		
}
else { 	
	print "\nNo URL provided. Script Use: 'php testCurl.php [URL...]'\n";
	print "\t-> Provide the URL in 'quotes'\n";
	print "\t-> Don't include the 'offset' filter in the URL, the script takes care of it\n\n";
}

?>