#!/usr/bin/php
<?php

/*
Takes the URL(json_encoded webpage from the Search API), decodes the information obtained from the webpage and returns the associative array containing all the information.
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
	if ($http_code >= 400){
		return $http_code;		//	If there is a HTTP Error, we terminate execution 
	}

	$decoded = json_decode($data, TRUE);	//	'true' makes it an associative array

	curl_close($curl_handle);
	return $decoded;
}

/*
This function takes a json_decoded associative array of information on a page. 
Compiles the relevant metadata (url's) and outputs the list of URLs in the given section.
The function returns an array. The first index of the array is an array of URLs that was compiled from the page and the second index holds the number of entries that were recorded.
*/
function getMetadata($infoArray){

	$urlArray = array();

	/*
	The information in the 'response' portion of the associative array is structured as such:
	response => docs(multiple entries) => legacy => web_url
	*/

	$docArray = $infoArray['response']['docs'];
	$numberOfEntries = count($docArray);
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
Takes a URL as a parameter and keeps incrementing the search offset to iterate through all entries.
The purpose of the function is to print out a list of all the URLs pertaining to the search.
It also returns the total number of entries that were found and outputted.
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
		$result = getMetadata($decoded);				//	extract the URLs
	
		$offset += 10;									//	increment the offset to get the next 10 entries
		$modifiedURL = $url . "&offset=" . $offset;		//	construct the new URL with the new offset
		print "Modified-URL:\t" . $modifiedURL;
		
		$numberOfArticles += $result[1];
		$URLarray = array_merge($URLarray, $result[0]);	//	append the new URLs to our URL array
	}
}


//$url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=book%20review&sort=newest&type=article";
$url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=tolkien&sort=newest&type=article";

getAllData($url);

?>