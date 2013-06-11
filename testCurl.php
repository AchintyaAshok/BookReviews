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
Use:	$number_of_URLs = getMetadata($decodedContent, $file_handle[optional]);

This function takes a json_decoded associative array of information on a page and optionally a filehandle referencing the file to which the URLs will be written to. 
Compiles the relevant metadata (url's) and outputs the list of URLs in the given section.
If there is no data that can be extracted from the given URL, the function will return -1.

-->Disabled: The function returns an array. The first index of the array is an array of URLs that was compiled from the page and the second index holds the number of entries that were recorded.
Return value:	The function returns the number of URLs that were outputted and/or written to the file

*/
function getMetadata($infoArray, $fp = NULL){

	//$urlArray = array();

	/*
	The information in the 'response' portion of the associative array is structured as such:
	response => docs(multiple entries) => legacy => web_url
	*/

	$docArray = $infoArray['response']['docs'];
	$numberOfEntries = count($docArray);
	if ($numberOfEntries == 0) return -1;;

	for ($i = 0; $i < $numberOfEntries; $i++){
		
		$url = $docArray[$i]['web_url'];
		$pub_date = $docArray[$i]['pub_date'];
		
		$toEncode = array();
		array_push($toEncode, array($url, $pub_date));
		$json_encoded_str = encodeInJSON($toEncode);
		//print $json_encoded_str . "\n";
		
		//array_push($urlArray, $url);
		
		if ($fp){	// If the file-handle was initialized, we write to the document
			fwrite($fp, $json_encoded_str);
			fwrite($fp, "\n");
		}
		
	}

	//return array($urlArray, $numberOfEntries);
	return $numberOfEntries;
}

/*
Use:	$number_of_URLs = getAllData($my_URL, $file_handle[optional]);

Takes a URL as a parameter and keeps incrementing the search offset to iterate through all entries.
The purpose of the function is to print out a list of all the URLs pertaining to the search. In addition, if a valid filehandle is given, it will write the URLs to the file.

-->Disabled: It returns the array of URLs that were found and outputted.

The function returns the total number of URLs that were outputted and/or written to the file.
*/
function getAllData($url, $fp = NULL){
	
	$offset = 0;
	$numberOfArticles = 0;
	//$URLarray = array();
	$modifiedURL = $url;
	
	while(true){
		$decoded = extractInformation($modifiedURL);
		if (is_int($decoded))	break;									//	This means the function has returned a HTTP ErrorCode
		
		print "\n\nExtracting URLs. Offset = " . $offset . "\n";
		print "Modified-URL:\t" . $modifiedURL . ":\n";
		$number_URLs_written = getMetadata($decoded, $fp);				//	extract the URLs
		if (is_int($result))	break;									//	-1 return value indicates there are no articles 
	
		$offset += 10;													//	increment the offset to get the next 10 entries
		$modifiedURL = $url . "&offset=" . $offset;						//	construct the new URL with the new offset
		
		
		$numberOfArticles += $number_URLs_written;
		//$numberOfArticles += $result[1];
		//$URLarray = array_merge($URLarray, $result[0]);	//	append the new URLs to our URL array
		usleep(100000);
	}
	
	//return $URLarray;
	return $numberOfArticles;
}


/*
The encodeInJSON function takes an array of values and then encodes them as a single JSON entry.
eg. array = { (key1, value1), (key2, value2) }

JSON Encoding:	{ "key1" : value1, "key2" : value2, "key3" : value2 }

The encoding is built as a string and returned as a string.
*/
function encodeInJSON($array){

	$encode_str = "{ ";

	for ($i = 0; $i < count($array) - 1; $i++){
		$tuple = $array[$i];
		if (count($tuple) >= 2){
			//	the tuple is valid because it has, at the minimum a key/value pair
			$key = $tuple[0];
			$value = $tuple[1];
			$encode_str .= "'" . $key . "':'" . $value . "', ";
		}
	}
	
	// The last element does not have a comma, we treat it differently
	$tuple = $array[count($array) - 1];
	if (count($tuple) >= 2){
		//	This means the tuple is valid because it has a key/value pair
		$key = $tuple[0];
		$value = $tuple[1];
		$encode_str .= "'" . $key . "':'" . $value . "'";
		
		/*
		print "key:" . $key;
		print "\tvalue" . $value;
		exit(1);
		print $encode_str;
		exit(1);*/
	}
	
	$encode_str .= " }";
	/*
	print $encode_str;
	exit(1);*/
	return $encode_str;
}


if (isset($argv[1])){
	if (!is_string($argv[1])){
		print "\nProvide the URL in 'quotes'\n";
		exit(1);
	}
	
	$size = 0;
	$fp = NULL;						//	The uninitialized file handle
	
	if (isset($argv[2])){			//	Here, we check if a file name was given to output the URLs to
		$fileName = $argv[2];
		$fp = fopen($fileName , "w+");
		$size = getAllData($argv[1], $fp);
	}	
	else{
		$size = getAllData($argv[1]);
	}
		
	$size = "Number of URLs:\t" . $size . "\n";
	print $size;
	
	if ($fp){						//	terminate the file-pointer
		fwrite($fp, "\n\n");
		fwrite($fp, $size);
		fclose($fp);
	}
}

else { 	
	print "\nNo URL provided. Script Use: 'php testCurl.php [URL...] [output file name (optional)]'\n";
	print "\t-> Provide the URL in 'quotes'\n";
	print "\t-> If you included a file name, provide it in 'quotes'\n";
	print "\t-> Don't include the 'offset' filter in the URL, the script takes care of it\n\n";
}

?>