<?php
/*
 * 	Author:			Achintya Ashok
 * 	Date-Created:	06/19/13
 * 
 * 	Purpose:		A collection of functions that help mine data. These functions are generally related to encoding strings in json or parsing it for values.
 */


/*
 * 	The function takes a string that represents a json encoding in the form { key:value, key:value, etc. } and returns an associative array of key/value pairs.
*/
function parse_json_get_data($stringToDecode){

	$leftPosition = strripos($stringToDecode, '{');
	$rightPosition = strripos($stringToDecode, '}');
	if(is_bool($leftPosition) || is_bool($rightPosition))	return false;						//	If strripos returns false, that means we don't have a bracket and this isn't a valid line to parse, but 0 also == false, and we avoid this problem

	$parsed = substr($stringToDecode, $leftPosition, $rightPosition-$leftPosition + 1);	//	Just get the content within the brackets {...}
	$decoded_json = json_decode($parsed, true);

	return $decoded_json;
}



/*
 The encodeInJSON function takes an array of tuples(stored as arrays) and then encodes them as a single JSON entry.
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
			if (is_array($value)) $value = implode(" ", $value);
			$encode_str .= '"' . $key . '":"' . $value . '", ';
		}
	}

	// The last element does not have a comma, we treat it differently
	$tuple = $array[count($array) - 1];
	if (count($tuple) >= 2){
		//	This means the tuple is valid because it has a key/value pair
		$key = $tuple[0];
		$value = $tuple[1];
		if (is_array($value)) $value = implode(" ", $value);
		$encode_str .= '"' . $key . '":"' . $value . '"';
	}

	$encode_str .= " }";
	return $encode_str;
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

	if ($http_code != 200){
		$decoded = extractInformation($url, $tries + 1);
		curl_close($curl_handle);
		return $decoded;
	}

	$decoded = json_decode($data, TRUE);	//	'true' makes it an associative array

	curl_close($curl_handle);
	return $decoded;
}




/*
 * 	The wrapper function for get_value_recursive_depth(...) which takes an associative array, the key for which we're trying to find a value, and an optional value, depth_limit which limits the depth to which the function will search for the key.
* 	If the key is not found, the function will return false. If a depth_limit is not specified, it will check to a depth of 10 levels by default.
*/
function get_value_recursive($arr, $search_key, $depth_limit=NULL){
	if (!$depth_limit){
		return get_value_recursive_depth($arr, $search_key, 10, 0);
	}
	return get_value_recursive_depth($arr, $search_key, $depth_limit, 0);
}



/*
 * Recursively searches an array of key,value associations. The Depth is limited to the $depth_limit parameter.
* If the key exists, it will return the value associated with it otherwise returning false.
*/
function get_value_recursive_depth($arr, $search_key, $depth_limit, $current_depth){
	if ($current_depth > $depth_limit){
		return false;
	}
	if (array_key_exists("$search_key", $arr)){
		return $arr[$search_key];
	}

	foreach($arr as $key=>$elem){
		if (is_array($elem)){
			$value = get_value_recursive($elem, $search_key, $depth_limit, $current_depth+1);	//	Recurse another level
			if (!is_bool($value)){
				return $value;
			}
		}
	}
	return false;
}



?>
