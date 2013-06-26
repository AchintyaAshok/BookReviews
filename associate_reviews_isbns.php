<?php
require_once 'data_functions.php';
require_once 'json_functions.php';

/*
 * 	Author:			Achintya Ashok
 * 	Date-Created:	06/19/13
 */


/*
 * 	This function takes a file that has been encoded in json, retrieves each entry individually, and uses the author and title fields as search queries for any book reviews that have been made.
 * 	If a book review is found to be matching to the author/title combination, the function creates a tuple that maps the URL of the article to the ISBN of the book the review was made on.
 */
function find_matching_reviews($filename, $outfile){
	
	$timeStart = microtime(true);
	$numberResults = 0;				//	This identifies the number of JSON strings that were successfully parsed and processed.
	$numberMatched = 0;
	
	$fileOutHandle = fopen($outfile, "w+");
	$line_array = file($filename);
	
	$prefix = " ";
	
	fwrite($fileOutHandle, "[");
	
	foreach($line_array as $line){
		//print "Decoding Line:\t$line\n";
		$data = parse_json_get_data($line);
		//var_export($data);
		if(!$data)		continue;	//	If the parse_json function is unable to extract information from the line, it returns false and we skip this entry
		$numberResults++;
		
		$author = $data['author'];
		$title = $data['title'];
		$isbn = $data['isbns'];
		
		$valuesToGet = array('web_url', 'score');		//	These are the elements we want to get from the Raw Result of our Book Review
		
		$results = search_for_review($title, $author, $valuesToGet);
		if (!$results)	continue;	//	If it was unable to find results, we skip the entry.
		array_push($results, array("Author", $author));
		array_push($results, array("Title", $title));
		array_push($results, array("ISBN", $isbn));
		$encodedJsonString = encodeInJSON($results);
		$numberMatched++;
		
		print "\nEncoded Result:\t$encodedJsonString\n";
		fwrite($fileOutHandle, $prefix);
		fwrite($fileOutHandle, $encodedJsonString);
		$prefix = ",\n";
	}
	
	fwrite($fileOutHandle, "]");
	
	$timeEnd = microtime(true);
	$totalTime = $timeEnd - $timeStart;
	print "\n\nProcessed: $numberResults -- Found: $numberMatched -- Time Elapsed: $totalTime seconds.\n\n"; 
	
	fclose($fileOutHandle);
}

/*
 * 	This function takes an author and title string and constructs a SearchAPI search query around the information, looking for book reviews. The third parameter is an array in which return value fields are specified.
 * 	If a matching book review is found, the function returns an array containing tuples of all the values of which the fields were specified in the $toReturn parameter. Eg. if $toReturn = ('web_url', '_id'), the function will return
 * 	array( 'http://theurl', '124ThisIsTheID1231'). If the function is unable to find any matching information, it will return false.
 */
function search_for_review($title, $author, $toReturn){
	
	$encodedAuthorTitle = urlencode('"'.$title.'" "'.$author.'"');
	/*$encodedFilter = urlencode('(taxonomy_nodes:"Top/Features/Books/Book Reviews" OR  subject:"Book Reviews" OR ((subject:"Reviews" OR  type_of_material:"Review") AND  subject:"Books and Literature"))');
	$queryStr = $encodedFilter . "&q=$encodedAuthorTitle";
	
	$json_url = get_jsonURL_from_query($queryStr);*/
	
	$json_url = get_dated_jsonURL("19810101", "20131231");
	$json_url .= "&q=$encodedAuthorTitle";
	
	$extractedData = extractInformation($json_url);
	if (!$extractedData)					return false;
	
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
$filename = $argv[1];
$out_filename = "matched_from_$filename";
find_matching_reviews($filename, $out_filename);

?>
