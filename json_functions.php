<?php
/*
 * 	Author:	Achintya Ashok
 * 	
 * 	Purpose:	This file includes a collection of functions that get the json stored information that is present in the NYT Search API.
 * 				The global variables defined in the file are associated with the url that is used to search the RAW_RESULT page of an entry and the ADD Index storage of entries.
 */

require_once 'data_functions.php';


$JSON_RAW_RESULT_URL = "http://search-add-api-a.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&sort=relavence&fq=";
$JSON_ADDINDEX_URL= "http://search-add-api.prd.use1.nytimes.com/svc/indexmanager/v1/convert.json?collection=articles&_id=";
$JSON_GLASS_PREFIX= "http://glass-output.prd.use1.nytimes.com/glass/outputmanager/V1/add.json?";


/*
*	The function takes the url of an article which has a glass entry. It then returns an associative array of information relating to the metadata of the article as it exists on glass.
*	If the article was not inputted into Glass, the function returns false, indicating that no information could be retrieved for the url.
*/
function get_glassInformation_from_url($url){
	global $JSON_GLASS_PREFIX;
	$glassURL = $JSON_GLASS_PREFIX;
	$encodedUrl = 'url=' . urlencode($url);	#	encode the url and prefix it with the filter you're searching with glass, in this case, the url of the article.
	
	$glassURL .= $encodedUrl . "&source=scoop&type=article";	#	The suffix that makes the Search API return articles in glass inputted through scoop
	//print "Glass URL:\t$glassURL\n";
	$data = extractInformation($glassURL);
	if (is_int($data)) return false;		#	If it is unable to extract any information from the constructed glass url, we return false.

	return $data;
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
 * 	This function takes a HTML encoded query string (in the form 'filter:"http...") and returns a URL that points to the JSON URL of the raw results in the Search API.
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
* The two parameters are self-explanatory, it must be a valid date range inputted in the format YYYYMMDD. It will return this URL.
 */
function get_dated_jsonURL($begin_date, $end_date, $opt_filter = NULL){
    $fq = "(taxonomy_nodes%3A%22Top%2FFeatures%2FBooks%2FBook%20Reviews%22%20OR%20%20subject%3A%22Book%20Reviews%22%20OR%20((subject%3A%22Reviews%22%20OR%20%20type_of_material%3A%22Review%22)%20AND%20%20subject%3A%22Books%20and%20Literature%22))";
    if ($opt_filter){
    	$fq .= $opt_filter;
    }
    $fq .= "&sort=newest&type=article";
	global $JSON_RAW_RESULT_URL;
	$json_url = $JSON_RAW_RESULT_URL;
	$json_url .= $fq;
	$json_url .= "&begin_date=" . $begin_date;
    $json_url .= "&end_date=" . $end_date;


    return $json_url;
}

?>
