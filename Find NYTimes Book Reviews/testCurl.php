<?php
require_once 'json_functions.php';
require_once 'data_functions.php';
?>

<?php
/*
Use:	$number_of_URLs = getMetadata($decodedContent, $file_handle[optional]);

This function takes a json_decoded associative array of information on a page and optionally a filehandle referencing the file to which the URLs will be written to. 
Compiles the relevant metadata (url's) and outputs the list of URLs in the given section.
If there is no data that can be extracted from the given URL, the function will return -1.

-->Disabled: The function returns an array. The first index of the array is an array of URLs that was compiled from the page and the second index holds the number of entries that were recorded.
Return value:	The function returns the number of URLs that were outputted and/or written to the file

*/
function getMetadata($infoArray, $fp = NULL){

	/*
	The information in the 'response' portion of the associative array is structured as such:
	response => docs(multiple entries) => legacy => web_url
	*/

	$docArray = $infoArray['response']['docs'];
	$numberOfEntries = count($docArray);
	print "Number of Entries:\t" . $numberOfEntries . "\n";
	if ($numberOfEntries == 0) return -1;

	for ($i = 0; $i < $numberOfEntries; $i++){
		
		$url = $docArray[$i]['web_url'];
		$pub_date = $docArray[$i]['pub_date'];		//	The Date needs a little trimming, to get rid of the exact time (which is in the majority of cases 00:00:00Z)
		$pub_date = substr($pub_date, 0, 10);		//	The first 10 characters of the string hold the YYYY-MM-DD
		
		$toEncode = array();
		array_push($toEncode, array("URL", $url));
		array_push($toEncode, array("Date" , $pub_date));
		$json_encoded_str = encodeInJSON($toEncode);
		
		if ($fp){	// If the file-handle was initialized, we write to the document
			//fwrite($fp, "\t");
			fwrite($fp, $json_encoded_str);
            fwrite($fp, ",");
			fwrite($fp, "\n");
		}
		
	}

	return $numberOfEntries;
}

/*
Use:	$number_of_URLs = getAllData($my_URL, $file_handle[optional]);

Takes a URL as a parameter and keeps incrementing the search offset to iterate through all entries.
The purpose of the function is to print out a list of all the URLs pertaining to the search. In addition, if a valid filehandle is given, it will write the URLs to the file.

-->Disabled: It returns the array of URLs that were found and outputted.

The function returns the total number of URLs that were outputted and/or written to the file.
*/
function getAllData($url, $fp = NULL){
	
	$offset = 0;
	$numberOfArticles = 0;
	$modifiedURL = $url;
	
	while(true){
		$decoded = extractInformation($modifiedURL);
		//if (is_int($decoded))	break;					//	This means the function has returned a HTTP ErrorCode
		
		print "Extraction URL:\t" . $modifiedURL . ":\n";
		$number_URLs_written = getMetadata($decoded, $fp);  //	extract the URLs
		if ($number_URLs_written == -1)	break;				//	-1 return value indicates there are no articles 
	
		$offset += 10;										//	increment the offset to get the next 10 entries
		$modifiedURL = $url . "&offset=" . $offset;			//	construct the new URL with the new offset
		
		//  We aggregate the number of articles we have after the latest execution & figure out how many there are left to pull
		$numberOfArticles += $number_URLs_written;
                $totalNumber = $decoded['response']['meta']['hits'];
                $numberLeft = $totalNumber - $numberOfArticles;
                print "Number of pieces left to get:\t" . $numberLeft . "\n\n";
                
		usleep(100000);
	}

	return $numberOfArticles;
}


/*
	Our original method of pulling URLs. This is used when the person runs the script by providing a URL and an optional file-name to send output to.
*/
function proceed_using_URL(){
	if (isset($argv[1])){
	
		$url = get_jsonURL_from_queryURL($argv[1]);

		if (!is_string($argv[1])){
			print "\nProvide the URL in 'quotes'\n";
			exit(1);
		}
	
		$size = 0;
		$fp = NULL;						//	The uninitialized file handle
	
		if (isset($argv[2])){			//	Here, we check if a file name was given to output the URLs to
			$fileName = $argv[2];
			$fp = fopen($fileName , "w+");
		
			fwrite($fp, "[\n");			// JSON encoded file, we open a new bracket and close it after the data has been inputted
			$size = getAllData($url, $fp);
			fwrite($fp, "\n]");
		
			fclose($fp);
		}	
		else{
			$size = getAllData($url);
		}
		
		$size = "Number of URLs:\t" . $size . "\n";
		print $size;
	}

	else { 	
		print "\nNo URL provided. Script Use: 'php testCurl.php [URL...] [output file name (optional)]'\n";
		print "\t-> Provide the URL in 'quotes'\n";
		print "\t-> If you included a file name, provide it in 'quotes'\n";
		print "\t-> Don't include the 'offset' filter in the URL, the script takes care of it\n\n";
	}
}

function proceed_using_date_range(){
	
	global $argv;		//	use the global argv array so we can access its values

	if( !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) ){
		print "\nFormat:\n"; 
		print "A filename and two dates need to be entered\n";
		print "php testCurl.php [filename] [start] [end]\n\n";
		//exit(1);
	}
	
	$begin_date = $argv[2];
	$end_date = $argv[3];
	
	$url = get_dated_jsonURL($begin_date, $end_date);
	
	$fileName = $argv[1];
	$fp = fopen($fileName , "w+");
	fwrite($fp, "[\n");			// JSON encoded file, we open a new bracket and close it after the data has been inputted
	$size = getAllData($url, $fp);
	fwrite($fp, "\n]");
	fclose($fp);
	
	print "\nNumber of URLs:\t" . $size . "\n";	
}


// MAIN PROCEDURE
//global $argv;
proceed_using_date_range();

?>
