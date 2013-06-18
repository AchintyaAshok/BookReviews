<?php
/*
 * 	Author:			Achintya Ashok
 * 	Creation-Date:	06/18/13
 * 
 * 	Purpose:		Use this script to compile information, connecting Book Reviews to their asset id's (and optionally storing this related information in a file).
 * 					The file constructs objects that cumulatively create information, finding the author, title and text.
 * 					
 * 					[Future Additions:
 * 						1) Finding the ISBN of a book review
 * 					]
 */

require 'json_functions.php';
require 'book_review_class.php';


function get_all_reviews($file_name){
	
	$line_array = fill($file_name);
	
	foreach($line_array as $line){
		$data = parse_json_get_data($line);
		if (!$data)	continue;		//	If the returned value was false, the function was unable to fetch the elements from the line.
		$url = $data['URL'];		//	The key for the url field in the json string is 'URL' (eg. { "URL" : "http://....", Date:...}
		
		//	Now we have to determine what metadata we want returned back to us. In this case, we want the web_url, headline, persons, pub_date, _id
		$keys = array('web_url', 'headline', 'persons', 'pub_date','_id');
		$associations = get_data_from_tags($url, $keys);
		if(!$associations)	continue;
		var_export($associations);
		exit(1);
	}
}


/*
 * 	This function gets data from an array which has the tag identifiers that act as keys for the document. The function will return an array containing of tuples
 * 	of which the first element is the key name, as specified in the tag array, and the value that it was found to be associated with in the ADD Index system. 
 * 	If a key is not identified (spelling?) it will be associated with the NULL value. 
 * 
 * 	The function takes the url of document from which metadata needs to be retrieved and the array of tags. It will return an array of tuples. If the URL cannot be resolved,
 * 	the function will return false.
 */
function get_data_from_tags($url, $tagArray){
	$data = get_ADDIndexInformation_from_url($url);
	if (!$data)		return false;	//	If it could not retrieve the data, we return false.
	
	$result = array();
	foreach($tagArray as $key){
		$tuple = array();
		array_push($tuple, $key);
		$value = get_value_recursive($data, $key);
		array_push($tuple, $value);
		array_push($result, $tuple);
	}
	
	return $result;
}


/*
 * 	The wrapper function for get_value_recursive_depth(...) which takes the array, the key for which we're trying to find a value, and an optional value, depth_limit which limits the depth to which the function will search for the key.
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
	if ($depth_limit < $current_depth){
		return false;
	}
	if (array_key_exists("$search_key", $arr)){
		return $arr[$search_key];
	}
	
	foreach($arr as $key=>$elem){
		if (is_array($elem)){
			$value = get_value_recursive($elem, $search_key, $depth_limit, $current_depth+1);
			if (!is_bool($value)){
				return $value;
			}
		}
	}
	return false;
}




//	MAIN CODE

$tags = array();
array_push($tags, "_id");
array_push($tags, "pub_date");
get_data_from_tags("http://www.nytimes.com/2003/09/14/books/boox.html", $tags);


/*
print "\n\n";
$arr = array('1'=>'hello', '2'=>array('3'=>'goodbye'));
//var_export($arr);
print "testing..." . $arr['2'] . "\n";
$value = get_value_recursive($arr, 3);
print "value:\t$value\n";
*/
?>
