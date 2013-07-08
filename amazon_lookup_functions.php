<?php

/*
*	Original Code authored on http://www.internetammo.com/how-to-connect-to-get-amazon-products-from-the-amazon-api-with-php-and-curl/
*/

define('AWS_ACCESS_KEY_ID', 'AKIAIRSGIQGEIMJKFU7Q'); 
define('AWS_SECRET_ACCESS_KEY', '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'); 		
define('AMAZON_ASSOC_TAG', 'thenewyorktim-20'); 			//	Tag that lets Amazon know from which of their affiliates they were redirected to to purchase a product


class AmazonItem{
	public $asin;			//	Amazon's Identification Number for Items
	public $itemURL;		//	The URL for the Item's Detail Page
	public $attributes = array();		//	Any Attributes about the Item, ISBN, Title, Author, etc.
	
	public function __construct($asin, $url, $attr = NULL){
		$this->asin = $asin;
		$this->itemURL = $url;

		if(!$attr) return;
		//	The attributes, given as key/value pairs, is an optional argument for the constructor, if any were specified we take care of it.
		foreach($attr as $key=>$value){
			if (strlen($value)==0) continue;
			$this->attributes[$key] = $value;
		}
	}

	public function get_item_information(){
		$toReturn['asin'] = $this->asin;
		$toReturn['url'] = $this->itemURL;
		$toReturn['attributes'] = $this->attributes;
		return $toReturn;
	}
}


class AmazonSearch{

//	Member Variables
	//	Information for Query:
	private $aws_access_key = AWS_ACCESS_KEY_ID;				//	The Amazon Affiliate Key required for the Amazon Lookup API
	private $aws_secret_access_key = AWS_SECRET_ACCESS_KEY;	//	This, along with our query gets hashed as a request signature
	private $associate_tag = AMAZON_ASSOC_TAG;

	private $searchIndex;							//	A Specific Category of items (documented on Amazon), which the query is restricted to
	private $params = array();						//	A collection of search parameters to further specify the query
													//	-- Each Search Index has a set of parameters that can be used with it, so
													//	-- be careful to have correct combinations.
	private $responseGroup;
	private $itemPage = 1;									//	Each lookup can have results that span multiple pages, this parameter specifies
													//	-- which page gets returned.
	private $url = NULL;
	private $executed = false;

	//	Information derived from Response:
	private $amazonLink;				//	The link linking to the search on Amazon
	private $numResults;
	private $numPages;
	private $resultItems = array();		//	This array holds all the items that have been processed. It is indexed in a way such that
										//	-- The key is the match number (in terms of relavency, such as the first item returned by the api),
										//	-- and the value is an AmazonItem Object.

	
	public function __construct($search_index, $parameters, $resGroup){
		$this->searchIndex = $search_index;
		
		foreach($parameters as $key=>$value){
			//	Add any given parameters to the itemsearch parameters
			$this->params[$key] = $value;
		}

		//	The Response Group of information we want to get back
		$this->responseGroup = $resGroup;

		//	Construct our Search Call for when the object's execute() function gets called.
		$this->construct_search_url();				
	}

	public function execute(){
		if (!$this->url) $this->construct_search_url();
		
		//	Curl the url and pull the xml from the page
		$resultData = $this->curl_url();
		if ($resultData == false){
			//print "Something went wrong... \n\t- Check your Search Index and Parameters to make sure they're valid.\n\t- Check your Response Group.\n";
			return false;
		}
		$this->executed=true;					//	This indicates that the url request was executed and we can succesfully get information from the xml

		//var_export($resultData);
		$resultObject = new SimpleXMLElement($resultData);
		//var_export($resultObject);

		$ItemsData = $resultObject->Items;		//	This element is the encapsulating xml that contains data about how many items there are,
												//	-- how many pages there are, the link on amazon for the search results, and finally
												//	-- it encapsulates item objects which represent each item element.
		$this->numResults = $ItemsData->TotalResults;
		if ($this->numResults == 0){
			print "AmazonItem::execute()::No Results Found\n";
			return false;	//	If we do not get any results, the execution did not yield anything.
		}
		$this->numPages = $ItemsData->TotalPages;
		$this->amazonLink = $ItemsData->MoreSearchResultsUrl;

		foreach($ItemsData->Item as $elem){
			$asin 			= (string)$elem->ASIN;				//	Amazon's Standard Identification Number for a result
			$detailURL 		= (string)$elem->DetailPageURL;		//	The url linking to the Item's Detail Page
			$responseGroup 	= (string)$this->responseGroup;	
			$infoToRetrive 	= (string)$elem->$responseGroup;	//	The Response Group, as specified on instantiation, relates in XML to the data we get back

			//	Process the Item's Attributes 
			$data = array();
			foreach($elem->ItemAttributes->children() as $child){
				$data[$child->getName()] = (string)$child;
			}

			//	Add each new single Item (with its attributes intact), to this object's resultItems array
			$constructedItem = new AmazonItem($asin, $detailURL, $data);
			array_push($this->resultItems, $constructedItem);
		}

		return true;	//	indicates success
	}

	private function create_array_from_simplexml($xml){
		return NULL;
		// SECOND ATTEMPT
		// if (count($xml) == 0){
		// 	print "No Children.\n";
		// 	//	If this xmlelement has no children, we return the value of the object.
		// 	return ($xml);
		// 	$key = $xml->getName();
		// 	$value = 
		// }

		// $toReturn = array();

		// foreach($xml->children() as $element){
		// 	//$value = $this->create_array_from_simplexml($element);
		// 	$value = $this->create_array_from_simplexml($element);
		// 	if (count($value) == 1){

		// 	}
		// 	$toReturn[$element->getName()] = $value;
		// }

		// return $toReturn;

		// FIRST ATTEMPT:
		// $elems = $xml->children();
		// if (count($elems)==0){
		// 	print "No Children\n";
		// //	If there is only one element, we return an array with the key/value binding, we have no way of knowing
		// //	the name of the key so we must use this foreach construct.
		// 	$arrayToReturn = array();
		// 	$arrayToReturn[$xml->getName()] = 
		// 	return $arrayToReturn;
		// }

		// $arrayToReturn = array();
		// foreach ($elems as $parent=>$child){
		// 	//var_export($parent);
		// 	$value = $this->create_array_from_simplexml($child);
		// 	if ($value != false){
		// 		$arrayToReturn[$parent] = $value;

		// 	}
		// 	else{
		// 		print "It was false\n";
		// 		$arrayToReturn[$parent] = '$child';
		// 	}
		// }
		// return $arrayToReturn;

	}

	/*	This method returns the data about an item. The result number parameter is in reference to the 
		item's relavence in terms of the search. For example, if $result_number was '1', the function would
		return the data about the first item that was processed from the search. If a given item number 
		exceeds the number of results this object holds, the method will return false.	*/
	public function get_item_data($resultNumber){
		$numItems = count($this->resultItems);
		if ($resultNumber > $numItems) return false;
		return ($this->resultItems[$resultNumber-1]->get_item_information());
	}

	public function add_parameter($newParam){
		foreach($newParam as $key=>$value){
			$this->$params[$key] = $value;
		}
		$this->url = NULL;	//	The previously set url does not reflect the changes, by setting it to null, we indicate to the object that it needs to recalculate the url.
	} 

	public function get_query_url(){
		$this->construct_search_url();
		return $this->url;
	}

	public function get_amazon_link(){
		if($this->executed){
			return $this->amazonLink;
		}
		print "The object has not executed the API Call, call $thisObject->execute() then recall this function";	//	executing the api call manually ensures the caller is
																													//	-- notified of any errors on execution
		return false;
	}

	public function get_number_results(){
		if ($this->executed)	return $this->numResults;
		return -1;				//	-1 indicates the the object has yet to be executed.
	}

	public function get_api_url(){
		if (!$this->url){
			$this->construct_search_url();
		}
		return $this->url;
	}

	/*	
	*	This function constucts the url for the api call to amazon's itemsearch. This happens by analyzing the itemsearch parameters
	*	, the search Index and the ReponseGroup specified upon instantiation and afterwards. The function sets the member variable, $url,
	*	to the generated url.
	*
	*	Note that this construction function is a derivation of the amazon_get_signed_url function as specified on:
	*	http://www.internetammo.com/how-to-connect-to-get-amazon-products-from-the-amazon-api-with-php-and-curl/
	*/
	private function construct_search_url(){

		$base_url = "http://ecs.amazonaws.com/onca/xml";
		$urlParameters = array(
			'AWSAccessKeyId' => $this->aws_access_key,
			'AssociateTag' => $this->associate_tag,
			'Version' => "2010-11-01",
			'Operation' => "ItemSearch",
			'Service' => "AWSECommerceService",
			'SearchIndex' => $this->searchIndex,
			'ResponseGroup' => $this->responseGroup,
			'ItemPage' => $this->itemPage,
		);

		foreach($this->params as $key=>$value){
		//	Include all our ItemSearch parameters
			$urlParameters[$key] = $value;
		}

		if(empty($urlParameters['AssociateTag'])) { 
		//	We do not include the association if it's empty. The association tells amazon from whom the request was made
			unset($urlParameters['AssociateTag']); 
		} 

		$urlParameters['Timestamp'] = gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());

		// Now we need to encode these parameters correctly:
		$urlParts = array();
		foreach($urlParameters as $key=>$value){
			$encodedKey = rawurlencode($value);
			$encodedKey = str_replace('%7E', '~', $encodedKey);	//	Amazon accepts '~' in the search query, so we replace any changes that might have been made
			$urlParts[] = $key . '=' . $encodedKey;
		}
		sort($urlParts);

		$urlString = implode('&', $urlParts);	//	Construct the url string by inserting the ampersand, &, in between all search parameters

		$string_to_sign = "GET\necs.amazonaws.com\n/onca/xml\n" . $urlString;
		// Sign the request
		$signature = hash_hmac("sha256", $string_to_sign, $this->aws_secret_access_key, TRUE);
		// Base64 encode the signature and make it URL safe 
		$signature = urlencode(base64_encode($signature));

		$this->url = $base_url . '?' . $urlString . "&Signature=" . $signature;
	}

	private function curl_url(){
		if (!$this->url){
			print "curl_url::url not set\n";
			return false;	//	If the URL has not been configured, we cannot curl anything
		}
		$curl_handle = curl_init();
		// Configure the curl_handle
		curl_setopt($curl_handle,CURLOPT_URL, $this->url);
		curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);

		// Execution:
		$data = curl_exec($curl_handle);

		//SANITY CHECK
		$http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
		if ($http_code != 200){
			print "curl_url::http error - $http_code\n";
			return false;	//	Something went wrong, we could not retrieve results
		}

		return $data;
	}

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


/*
*	This function takes a preconfigured amazon itemsearch url and searches the items for the first 
*	occurence of an ISBN. It returns this value if found. If no isbn could be extracted, the function will
*	return false.
*/
function extract_isbn($url){
	$curl_handle = curl_init();
	// Configure the curl_handle
	curl_setopt($curl_handle,CURLOPT_URL, $url);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl_handle,CURLOPT_FOLLOWLOCATION, 1);

	// Execution:
	$data = curl_exec($curl_handle);

	//SANITY CHECK
	$http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);

	$result = new SimpleXMLElement($data);
	$items = $result->Items;	//	Get the Items out of the XML

	$isbn;

	if ($items->count() > 0){
		foreach($items->children() as $child){
			
			if ($child->getName() == "Item"){
				$attributes = $child->ItemAttributes;
				$isbn = $attributes->ISBN;

				if (isset($isbn)){
					break;
				}
			}

		}
	}
	if (!isset($isbn)) return false;
	return $isbn;
}



// $url = amazon_get_signed_url('Inferno', 'Dan Brown');
// print "Amazon ItemSearch URL:\t" . $url . "\n\n";
// print "Found ISBN:\t" . extract_isbn($url) . "\n\n";






