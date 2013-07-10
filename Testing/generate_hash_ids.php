<?php
require_once 'data_functions.php';
require_once 'json_functions.php';

/*
 * 	Author:			Achintya Ashok
 * 	Date-Created:	06/19/13
 */


/*
 * 	This function returns an array containing tuples of id's matched with the url of the article. The function requires a start date and end date for the book reviews that are being searched.
 */
function get_id_map($start, $end){
	$json_url = get_dated_jsonURL($start, $end);
	$data = extractInformation($json_url);	//	All the documents with a 0 offset
	$url = $json_url;	//	We have to keep modifying this element by changing the offset to get more entries
	$offset = 0;
	
	$idMap = array();
	
	while(true){
		$data = extractInformation($url);
		$docs = $data['response']['docs'];
		if (count($docs) == 0)	break;		//	This means we have no more entries to check
		
		hash_entries($idMap, $docs, "_id", "web_url");
		
		$offset += 10;
		$url = $json_url . "&offset=$offset";
	}
	
	return $idMap;
}


/*
 * 	The hash_entries function takes a map (an associative array representing a hash-map), an array containing entries(arrays) with a $key field and a $value field.
 * 	The function then generates a key/value pair based on the values the array gives, creates an associative $key=>$value entry, and then adds it to the map.
 */
function hash_entries(&$map, &$array, $key_name, $value_name){
	foreach($array as $piece){
		$key = $piece["$key_name"];
		$value = $piece["$value_name"];
		$map[$key] = $value;
	}
}


/*
$map = array();
$array = array(array("key"=>111, "value"=>"http://www.google.com"), array("key"=>999, "value"=>"http://www.facebook.com"));
hash_entries($map, $array, "key", "value");
var_export($map);
*/

$start = $argv[1];
$end = $argv[2];

//$map = get_id_map($start, $end);
//var_export($map);

//search_for_review("Steve Jobs", "Isaacson, Walter", array('a', 'b'));
find_matching_reviews("testAuthorTitle.txt");

?>
