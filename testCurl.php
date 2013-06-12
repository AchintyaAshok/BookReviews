<?php
require 'json_functions.php';
?>

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
	print "Number of Entries:\t" . $numberOfEntries . "\n\n";
	if ($numberOfEntries == 0) return -1;

	for ($i = 0; $i < $numberOfEntries; $i++){
		
		$url = $docArray[$i]['web_url'];
		$pub_date = $docArray[$i]['pub_date'];		//	The Date needs a little trimming, to get rid of the exact time (which is in the majority of cases 00:00:00Z)
		$pub_date = substr($pub_date, 0, 10);		//	The first 10 characters of the string hold the YYYY-MM-DD
		
		$toEncode = array();
		array_push($toEncode, array("URL", $url));
		array_push($toEncode, array("Date" , $pub_date));
		$json_encoded_str = encodeInJSON($toEncode);
		
		if ($fp){	// If the file-handle was initialized, we write to the document
			fwrite($fp, "\t");
			fwrite($fp, $json_encoded_str);
			if($i < $numberOfEntries-1){		//	If this is not the last element, there are more to come so we append a comma
				fwrite($fp, ",");
			}
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
	
	$url = get_jsonURL_from_queryURL($url);
	
	$offset = 0;
	$numberOfArticles = 0;
	//$URLarray = array();
	$modifiedURL = $url;
	
	while(true){
		$decoded = extractInformation($modifiedURL);
		if (is_int($decoded))	break;									//	This means the function has returned a HTTP ErrorCode
		
		//print "\n\nExtracting URLs. Offset = " . $offset . "\n";
		print "Extraction URL:\t" . $modifiedURL . ":\n";
		$number_URLs_written = getMetadata($decoded, $fp);				//	extract the URLs
		if ($number_URLs_written == -1)	break;									//	-1 return value indicates there are no articles 
	
		$offset += 10;													//	increment the offset to get the next 10 entries
		$modifiedURL = $url . "&offset=" . $offset;						//	construct the new URL with the new offset
		
		
		$numberOfArticles += $number_URLs_written;
		//$numberOfArticles += $result[1];
		//$URLarray = array_merge($URLarray, $result[0]);	//	append the new URLs to our URL array
		usleep(500000);
	}
	
	//return $URLarray;
	return $numberOfArticles;
}

/*
	The constructURL function takes the URL from the address bar of the search API, modifies it to get the Raw Result JSON encoding of the result data and returns that URL back.
*/
function get_jsonURL_from_queryURL($url){
	/*	We construct the initial portion of the URL used to get the json encoding of the search	*/
	$json_url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq="; 

	/*	Extract only the query from the given URL to append to the initial part of our json url	*/
	$query_position = stripos($url, "lookup//");
	$query_string = substr($url, $query_position + 8);	//	We remove 'lookup//' and get the truncated query string we're looking for
	
	$json_url .= $query_string;
	return $json_url;
}


// MAIN PROCEDURE

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
		
		fwrite($fp, "[\n");			// JSON encoded file, we open a new bracket and close it after the data has been inputted
		$size = getAllData($argv[1], $fp);
		fwrite($fp, "\n]");
		
		fclose($fp);
	}	
	else{
		$size = getAllData($argv[1]);
	}
		
	$size = "Number of URLs:\t" . $size . "\n";
	print $size;
}

else { 	
	print "\nNo URL provided. Script Use: 'php testCurl.php [URL...] [output file name (optional)]'\n";
	print "\t-> Provide the URL in 'quotes'\n";
	print "\t-> If you included a file name, provide it in 'quotes'\n";
	print "\t-> Don't include the 'offset' filter in the URL, the script takes care of it\n\n";
}

?>