#!/usr/bin/php
<?php

/*
Takes the URL(json_encoded webpage from the Search API), decodes the information obtained from the webpage and returns the associative array containing all the information on the webpage.
*/
function extractInformation($url){

	$curl_handle = curl_init();
	// Configure the curl_handle 
	curl_setopt($curl_handle,CURLOPT_URL, $url);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);


	// Execution:
	$data = curl_exec($curl_handle);

	/*
	SANITY CHECK
	$info = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	print $info;
	*/

	$decoded = json_decode($data, TRUE);	//	'true' makes it an associative array

	curl_close($curl_handle);
	return $decoded;
}

/*
This function takes a json_decoded associative array of information on a page. 
Compiles the relevant metadata (url's) and outputs the list of URLs in the given section.
*/
function getMetadata($infoArray){

$urlArray = array();

/*
The information in the 'response' portion of the associative array is structured as such:
response => docs(multiple entries) => legacy => web_url
*/

$docArray = $infoArray['response']['docs'];
print "\nNumber of docs:\t" . count($docArray) . "\n\n";

for ($i = 0; $i < count($docArray); $i++){
	//var_export($docArray[$i]) . "\n";
	$url = $docArray[$i]['legacy']['web_url'];
	print $url . "\n";
	array_push($urlArray, $url);
}

return $urlArray;
}

$url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=book%20review&sort=newest&type=article&offset=0";

$decoded = extractInformation($url);
getMetadata($decoded);

?>