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

?>
