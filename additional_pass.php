<?php

require_once 'data_functions.php';


function parse_for_correct_entries($filename, $outfile = NULL){
	$lineArray = file($filename);
	$outfileHandle = fopen($outfile, 'w+');
	$numberCorrect = 0;
	$numberProcessed = 0;
	fwrite($outfileHandle, "[");
	foreach($lineArray as $line){
		$numberProcessed++;
		$data = parse_json_get_data($line);
		$matchedISBN = $data['ISBN'];
		if (strlen($matchedISBN) != 0){
			//fwrite($outfileHandle, $prefix);
			fwrite($outfileHandle, $line);
			$numberCorrect++;
		}
	}
	fwrite($outfileHandle, "]");
	fclose($outfileHandle);
	print "\n\nNumber Processed: $numberProcessed\tNumber Correct: $numberCorrect\n";
}


function parse_for_additional_pass($filename, $outfile = NULL){
	$lineArray = file($filename);
	$outfileHandle = fopen($outfile, 'w+');
	$numberAdded = 0;
	$prefix = "";
	fwrite($outfileHandle, "[");
	foreach($lineArray as $line){
		$data = parse_json_get_data($line);
		$matchedISBN = $data['ISBN'];
		if (strlen($matchedISBN) == 0){
			//fwrite($outfileHandle, $prefix);
			fwrite($outfileHandle, $line);
			$numberAdded++;
		}
		$prefix = ",\n";
	}
	fwrite($outfileHandle, "]");
	fclose($outfileHandle);
	print "\nTotal Number of entries without ISBNs:\t$numberAdded\n\n";
}

//parse_for_additional_pass('isbn_matches.txt', 'glass_matches_revised.txt');
parse_for_correct_entries('isbn_matches.txt', 'isbn_matches_correct.txt');

?>