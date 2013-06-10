#!/usr/bin/php
<?php

$url = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/lookup.json?_showQuery=true&fq=book%20review&sort=newest&type=article&offset=0";


$curl_handle = curl_init();


// Configure the curl_handle 
curl_setopt($curl_handle,CURLOPT_URL, $url);
curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);


// Execution:
$data = curl_exec($curl_handle);

/*
SANITY CHECK
$info = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
print $info;
*/

$decoded_data = json_decode($data, TRUE);	//	'true' makes it an associative array
var_export($decoded_data);


//echo "\n\n\nELEMENT COUNT:\t" . count($decoded_data) . "\n\n\n";

curl_close($curl_handle);

?>
