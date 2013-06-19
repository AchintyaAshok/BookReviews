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
 * 	This function takes a file that has been encoded in json, retrieves each entry individually, and uses the author and title fields as search queries for any book reviews that have been made.
 * 	If a book review is found to be matching to the author/title combination, the function creates a tuple that maps the URL of the article to the ISBN of the book the review was made on.
 */
function find_matching_reviews($filename){
	$line_array = file($filename);
	foreach($line_array as $line){
		//print "Decoding Line:\t$line\n";
		$data = parse_json_get_data($line);
		//var_export($data);
		if(!$data)		continue;	//	If the parse_json function is unable to extract information from the line, it returns false and we skip this entry
		
		$author = $data['author'];
		$title = $data['title'];
		$isbn = $data['isbns'];
		
		$valuesToGet = array('web_url', '_id');		//	These are the elements we want to get from the Raw Result of our Book Review
		
		$results = search_for_review($title, $author, $valuesToGet);
		if (!$results)	continue;	//	If it was unable to find results, we skip the entry.
		array_push($results, array("ISBN", $isbn));
		$encodedJsonString = encodeInJSON($results);
		
		print "\n\nEncoded Result:\t$encodedJsonString\n\n";
	}
}

/*
 * 	This function takes an author and title string and constructs a SearchAPI search query around the information, looking for book reviews. The third parameter is an array in which return value fields are specified.
 * 	If a matching book review is found, the function returns an array containing tuples of all the values of which the fields were specified in the $toReturn parameter. Eg. if $toReturn = ('web_url', '_id'), the function will return
 * 	array( 'http://theurl', '124ThisIsTheID1231'). If the function is unable to find any matching information, it will return false.
 */
function search_for_review($title, $author, $toReturn){
	
	$encodedAuthorTitle = urlencode('"'.$title.'" "'.$author.'"');
	$encodedFilter = urlencode('(taxonomy_nodes:"Top/Features/Books/Book Reviews" OR  subject:"Book Reviews" OR ((subject:"Reviews" OR  type_of_material:"Review") AND  subject:"Books and Literature"))');
	$queryStr = $encodedFilter . "&q=$encodedAuthorTitle";
	
	$json_url = get_jsonURL_from_query($queryStr);
	
	$extractedData = extractInformation($json_url);
	if (!extractedData)					return false;
	
	$possibleMatches = $extractedData['response']['docs'];
	if (count($possibleMatches) == 0) 	return false;
	
	//If we have multiple matches, we will return the best result [future addition]
	$firstResult = $possibleMatches[0];
	
	$valuesToReturn = array();
	foreach($toReturn as $key_name){
		$value = get_value_recursive($firstResult, $key_name);	//	Find the value for the key that was given
		$tuple = array($key_name, $value);
		array_push($valuesToReturn, $tuple);
	}
	
	return $valuesToReturn;
}




/*
$map = array();
$array = array(array("key"=>111, "value"=>"http://www.google.com"), array("key"=>999, "value"=>"http://www.facebook.com"));
hash_entries($map, $array, "key", "value");
var_export($map);
*/

//$map = get_id_map($start, $end);
//var_export($map);

//search_for_review("Steve Jobs", "Isaacson, Walter", array('a', 'b'));
find_matching_reviews("testAuthorTitle.txt");

?>
