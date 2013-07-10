<?php
/*
 * 	Author:			Achintya Ashok
 * 	Creation-Date:	06/18/13
 * 
 * 	Purpose:		Use this script to compile information, connecting Book Reviews to their asset id's (and optionally storing this related information in a file).
 * 					The file finds metainformation about a times article. Specifically, it uses the ADD Index to retrieve information. 
 * 					
 * 					[Future Additions:
 * 						1) Finding the ISBN of a book review
 * 					]
 */

require_once 'data_functions.php';
require_once 'json_functions.php';


function get_all_reviews($file_name, $out_file){
	
	$line_array = file($file_name);
	$fileHandleOut = fopen($out_file, "a+");
	$counter = 0;
	
	$numberURLs = count($line_array)-2;
	print "\n-- Checking File: '$file_name' --\nNumber of URLs:\t$numberURLs\n";
	
	foreach($line_array as $line){
		$data = parse_json_get_data($line);
		if (!$data)	continue;			//	If the returned value was false, the function was unable to fetch the elements from the line.
		$url = $data['URL'];			//	The key for the url field in the json string is 'URL' (eg. { "URL" : "http://....", Date:...}
		
		//	Now we have to determine what metadata we want returned back to us. In this case, we want the web_url, headline, persons, pub_date, _id
		$keys = array('web_url', '_id', 'pub_date', 'headline', 'persons', 'creative_works', 'lead_paragraphs');
		$associations = get_data_from_tags($url, $keys);
		if(!$associations)	continue;	//	If the get_data... function returns false, it was unable to return data, so we skip encoding it
		
		$encoded = encodeInJSON($associations);
		fwrite($fileHandleOut, $encoded);
		fwrite($fileHandleOut, ",\n");
		
		$counter++;
		if ($counter % 100 == 0){
			print "Processed: $counter entries...\n";
		}
	}
	
	fclose($fileHandleOut);
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




//	MAIN CODE

$filename = $argv[1];
$outputFile = $argv[2];
if ( !isset($argv[1]) || !isset($argv[1]) ){
	print "Function Usage:\tphp get_review_information.php [input file] [output file]\n";
	exit(1);
}
get_all_reviews($filename, "review_info_output.txt");

/*
$tags = array();
array_push($tags, "_id");
array_push($tags, "pub_date");
get_data_from_tags("http://www.nytimes.com/2003/09/14/books/boox.html", $tags);
*/

/*
print "\n\n";
$arr = array('1'=>'hello', '2'=>array('3'=>'goodbye'));
//var_export($arr);
print "testing..." . $arr['2'] . "\n";
$value = get_value_recursive($arr, 3);
print "value:\t$value\n";
*/
?>
