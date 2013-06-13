<?php
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
	}
	
	$encode_str .= " }";
	return $encode_str;
}

/*
	The function takes the URL from the address bar of the search API, modifies it to get the Raw Result JSON URL.
*/
function get_jsonURL_from_queryURL($url){
	//	We construct the initial portion of the URL used to get the json encoding of the search	*/
	
        //  This means the input URL is the URL from the search API, not the Raw Results URL, which means we need to parse it and make some changes to get the JSON url
            
	$json_url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq="; 
	//	Extract only the query from the given URL to append to the initial part of our json url	
	$query_start_pos = stripos($url, "lookup//");
        $query_string = substr($url, $query_start_pos + 8);	//	We remove 'lookup//' and get the truncated query string we're looking for
	$json_url .= $query_string . "&sort=newest&type=article";
	
	return $json_url;
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

?>
