<?php

/*
*	Original Code authored on http://www.internetammo.com/how-to-connect-to-get-amazon-products-from-the-amazon-api-with-php-and-curl/
*/

define('AWS_ACCESS_KEY_ID', 'AKIAIRSGIQGEIMJKFU7Q'); 
define('AWS_SECRET_ACCESS_KEY', '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'); 		
define('AMAZON_ASSOC_TAG', 'thenewyorktim-20'); 			//	Tag that lets Amazon know from which of their affiliates they were redirected to to purchase a product





function amazon_get_itemsearch_url($searchIndex, $itemSearchParams, $responseGroup){

	$base_url = "http://webservices.amazon.com/onca/xml?Service=AWSECommerceService";
	$base_url .= "&AWSAccessKeyId=" . AWS_ACCESS_KEY_ID;		//	Add your access-key into the url
	$base_url .= "&Operation=ItemSearch";						//	Specifies that you're using the Amazon ItemSearch API

	$paramArray = array();	//	later addition to strengthten code? use implode to put the ampersand in between pairs
	foreach($itemSearchParams as $key=>$value){
	//	Append the key/value pairs for itemsearch parameters to the url
		$base_url .= "&" . $key . "=" . $value;
	}

	//	Now we append the Search Index
	$base_url .= "&SearchIndex=" . $searchIndex;

	//	Specify a Response Group from which you want to retrieve information, eg. ItemAttributes (which include ISBN)
	$base_url .- "&ReponseGroup=" . $responseGroup;


}



function amazon_get_signed_url($title, $author){

	$base_url = "http://ecs.amazonaws.com/onca/xml";
	$params = array(
		'AWSAccessKeyId' => AWS_ACCESS_KEY_ID,
		'AssociateTag' => AMAZON_ASSOC_TAG,
		'Version' => "2010-11-01",
		'Operation' => "ItemSearch",
		'Service' => "AWSECommerceService",
		'ResponseGroup' => 'ItemAttributes',
		// 'ResponseGroup' => "ItemAttributes,Images",
		// 'Availability' => "Available",
		// 'Condition' => "All",
		'Operation' => "ItemSearch",
		'SearchIndex' => 'Books',
		//'SearchIndex' => 'All', //Change search index if required, you can also accept it as a parameter for the current method like $searchTerm
		'Title' => $title,
		'Author' => $author,
		// 'Keywords' => $searchTerm
		//'ItemPage'=>"1", 
		//'ResponseGroup'=>"Images,ItemAttributes,EditorialReview",
	);


	if(empty($params['AssociateTag'])) { 
		unset($params['AssociateTag']); 
	} 

	$params['Timestamp'] = gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());

	// Sort the URL parameters 
	$url_parts = array();
	foreach(array_keys($params) as $key){
		$url_parts[] = $key . "=" . str_replace('%7E', '~', rawurlencode($params[$key]));
	}
	sort($url_parts);

	// Construct the string to sign
	$url_string = implode("&", $url_parts); 
	$string_to_sign = "GET\necs.amazonaws.com\n/onca/xml\n" . $url_string;

	// Sign the request
	$signature = hash_hmac("sha256", $string_to_sign, AWS_SECRET_ACCESS_KEY, TRUE);

	// Base64 encode the signature and make it URL safe 
	$signature = urlencode(base64_encode($signature));


	$url = $base_url . '?' . $url_string . "&Signature=" . $signature;
	return $url;
}



print "Amazon ItemSearch URL:\t" . amazon_get_signed_url('Inferno', 'Dan Brown') . "\n\n";
