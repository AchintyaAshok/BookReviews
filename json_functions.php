<?php
/*
 * 	Author:	Achintya Ashok
 * 	
 * 	Purpose:	This file includes a collection of functions that get the json stored information that is present in the NYT Search API.
 * 				The global variables defined in the file are associated with the url that is used to search the RAW_RESULT page of an entry and the ADD Index storage of entries.
 */


$JSON_RAW_RESULT_URL = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=";
$JSON_ADDINDEX_URL= "http://search-add-api.prd.use1.nytimes.com/svc/indexmanager/v1/convert.json?collection=articles&_id=";

/*
The encodeInJSON function takes an array of values and then encodes them as a single JSON entry.
eg. array = { (key1, value1), (key2, value2) }

JSON Encoding:	{ 'key1':'value1', 'key2':'value2', 'key3':'value2' }

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
			$encode_str .= '"' . $key . '":"' . $value . '", ';
		}
	} 
	
	// The last element does not have a comma, we treat it differently
	$tuple = $array[count($array) - 1];
	if (count($tuple) >= 2){
		//	This means the tuple is valid because it has a key/value pair
		$key = $tuple[0]; 
		$value = $tuple[1];
		$encode_str .= '"' . $key . '":"' . $value . '", ';	
	}
	
	$encode_str .= " }";
	return $encode_str;
}



/*
 * 	This function takes as a paramter, a url to the New York Times article. It then compiles all the information that exists in its ADD Index entry and returns it in the form of an associative array.
 * 	The function will return false if it is unable to extract the ADD Index entry from the given URL.
 */
function get_ADDIndexInformation_from_url($url){
	$to_encode = "web_url:";				//	Construct the query string, which is composed of the filter, 'web_url:' and the element, the url
	$to_encode .= '"' . $url . '"';
	$encoded_url = urlencode($to_encode);
	$json_url = get_jsonURL_from_query($encoded_url);
	
	$decodedRawInformation = extractInformation($json_url);
	if (is_int($decodedRawInformation)){
		print "Unable to extract from Raw-URL:\t$json_url\n";
		return false;
	}
	$id = $decodedRawInformation['response']['docs'][0]['_id'];		//	The asset id is the unique identifier that distinguishes an article in the ADD Index Database
	
	$addIndexURL = get_ADDIndexURL_from_id($id);
	$decodedADDInformation = extractInformation($addIndexURL);
	if (is_int($decodedADDInformation)){
		print "Unable to extract from ADD-URL:\t$addIndexURL\n";
		return false;
	}
	
	return $decodedADDInformation;
}



/*
 * 	This function takes a HTML encoded query string (in the form 'filter:"http...") and returns a URL that refers to the JSON URL of the raw results.
*/
function get_jsonURL_from_query($query_str){
	global $JSON_RAW_RESULT_URL;
	$json_url_str = $JSON_RAW_RESULT_URL . $query_str;
	return $json_url_str;
}


/*
 * 	Use this function to retrieve the ADD Index (json-encoded) URL from the asset_id of data retreived from the Search API.
 */
function get_ADDIndexURL_from_id($id){
	global $JSON_ADDINDEX_URL;
	$url = $JSON_ADDINDEX_URL;
	$url .= $id;
	return $url;
}



/*
 * This function uses a pre-defined raw result URL (which returns results in json encoding) and appends the date filter to it.
* The two parameters are self-explanatory, it must be a valid date range inputted in the format YYYYMMDD
 */
function get_dated_jsonURL($begin_date, $end_date){
    $json_url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=(taxonomy_nodes%3A%22Top%2FFeatures%2FBooks%2FBook%20Reviews%22%20OR%20%20subject%3A%22Book%20Reviews%22%20OR%20((subject%3A%22Reviews%22%20OR%20%20type_of_material%3A%22Review%22)%20AND%20%20subject%3A%22Books%20and%20Literature%22))&sort=newest&type=article";	
    $json_url .= "&begin_date=" . $begin_date;
    $json_url .= "&end_date=" . $end_date;
    return $json_url;
}


/*
 * 	The function takes a string that represents a json encoding in the form { key:value, key:value, etc. } and returns an associative array of key/value pairs.
 */
function parse_json_get_data($stringToDecode){
	
	$leftPosition = strripos($stringToDecode, '{');
	if(!$leftPosition)	return false;						//	If strripos returns false, that means we don't have a left bracket and this isn't a valid line to parse
	$rightPosition = strripos($stringToDecode, '}');
	
	$parsed = substr($stringToDecode, $leftPosition, $rightPosition-$leftPosition + 1);	//	Just get the content within the brackets {...}
	$decoded_json = json_decode($parsed, true);
	
	return $decoded_json;
}



/*
Use:	$decoded_data = extractInformation($my_URL);

Takes the URL(json_encoded webpage from the Search API), decodes the information obtained from the webpage and returns the associative array containing all the information.
If there is an HTTP error code that gets returned when requesting the URL, it will be returned as the return value.
*/
function extractInformation($url, $tries = 0){
    
	if ($tries > 20){
		print "-- Multiple tries attempted, unable to extract from URL. --\n\n";
		return -1;
	}

	$curl_handle = curl_init();
	// Configure the curl_handle 
	curl_setopt($curl_handle,CURLOPT_URL, $url);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);

	// Execution:
	$data = curl_exec($curl_handle);
	
	//SANITY CHECK
	$http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        
    //print "HTTP CODE:\t" . $http_code . "\n";
        
	if ($http_code != 200){
            $decoded = extractInformation($url, $tries + 1);
            curl_close($curl_handle);
            return $decoded; 
	}
        
	$decoded = json_decode($data, TRUE);	//	'true' makes it an associative array

	curl_close($curl_handle);
	return $decoded;
}

?>
