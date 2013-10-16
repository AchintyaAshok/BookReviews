<?php
/*
	Author:			Achintya Ashok
	Date-Created:	07/03/13

	Purpose:		This file contains two classes that can be used to facilitate an Amazon ItemSearch.
					The AmazonSearch Class is used to instantiate an amazon search and will contain returned
					search results encapsulated in AmazonItem Objects. Follow the AmazonSearch Documentation
					to retrieve result information.
*/

define('AWS_ACCESS_KEY_ID', 'XXXXXX'); 
define('AWS_SECRET_ACCESS_KEY', 'XXXXXX'); 		
define('AMAZON_ASSOC_TAG', 'XXXXXX'); 			//	Tag that lets Amazon know from which of their affiliates they were redirected to to purchase a product


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



class SearchType{
// Enumerated Type to explicity choose what type of Amazon Search you want
	const ITEM_SEARCH = true;
	const ITEM_LOOKUP = false;
}


class AmazonSearch{

//	Member Variables
	//	Information for Query:
	private $itemSearch;											//	You can either do an Amazon ItemLookup or Amazon ItemSearch
	private $aws_access_key = AWS_ACCESS_KEY_ID;				//	The Amazon Affiliate Key required for the Amazon Lookup API
	private $aws_secret_access_key = AWS_SECRET_ACCESS_KEY;		//	This, along with our query gets hashed as a request signature
	private $associate_tag = AMAZON_ASSOC_TAG;					//	This association tag tells amazon who this api call was made from (eg. The New York Times)

	private $searchIndex;							//	A Specific Category of items (documented on Amazon), which the query is restricted to
	private $params = array();						//	A collection of search parameters to further specify the query
													//	-- Each Search Index has a set of parameters that can be used with it, so
													//	-- be careful to have correct combinations.
	private $responseGroup;
	private $itemPage = 1;							//	Each lookup can have results that span multiple pages, this parameter specifies
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


	// -- CONSTRUCTOR -- 

	/*	Constructor for the AmazonSearch Class. The constructor expects 3 parameters: a search index,
		an array of parameters (there must be at least one key/value entry), and a Response Group for 
		results that are wished. The parameters must correspond correctly with the given search index.
		Correct Search Index/Parameter combinations can be found on Amazon's ItemSearch Page.		*/
	public function __construct($search_index, $parameters, $resGroup, $searchType = true){
		$this->searchIndex = $search_index;
		$this->itemSearch = $searchType;
		foreach($parameters as $key=>$value){
			$this->params[$key] = $value;	//	Add any given parameters to the itemsearch parameters
		}
		//	The Response Group of information we want to get back
		$this->responseGroup = $resGroup;
		//	Construct our Search Call for when the object's execute() function gets called.
		$this->construct_search_url();	 			
	}


	//	-- PUBLIC METHODS -- 

	/*	The execute() method, is the quintessential method for this class, it constructs the api url, curls the 
		amazon response, and creates AmazonItem elements that it stores in a result array. If anything goes wrong
		in the aforementioned steps, it will return false and print out an error message.	*/
	public function execute(){
		if (!$this->url) $this->construct_search_url();
		
		//	Curl the url and pull the xml from the page
		$resultData = $this->curl_url();
		if ($resultData == false){
			//print "Something went wrong... \n\t- Check your Search Index and Parameters to make sure they're valid.\n\t- Check your Response Group.\n";
			return false;
		}
		$this->executed=true;					//	This indicates that the url request was executed and we can succesfully get information from the xml

		$resultObject = new SimpleXMLElement($resultData);
		$ItemsData = $resultObject->Items;		//	This element is the encapsulating xml that contains data about how many items there are,
												//	-- how many pages there are, the link on amazon for the search results, and finally
												//	-- it encapsulates item objects which represent each item element.
		if ($this->itemSearch){
			$this->numResults = $ItemsData->TotalResults;
		}
		else{
			$this->numResults = $ItemsData->count();
		}
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

	/*	IMPORTANT:	This method returns the data about an item. The result number parameter is in reference to the 
		item's relavence in terms of the search. For example, if $result_number was '1', the function would
		return the data about the first item that was processed from the search. If a given item number 
		exceeds the number of results this object holds, the method will return false.	*/
	public function get_item_data($resultNumber){
		if (!$this->executed) return false;		//	If the object was not executed, it returns false.

		$numItems = count($this->resultItems);
		if ($resultNumber > $numItems) return false;
		return ($this->resultItems[$resultNumber-1]->get_item_information());
	}

	/*	This returns an array of AmazonItem Objects each containing, in relavancy order (from most relavant to least),
		item information for a given result. If the AmazonSearch was not executed, the method will return false.	*/
	public function get_all_items(){
		if (!$this->executed)	return false;
		return $resultItems;
	}

	/*	Method to add ItemSearch parameters to your query. The parameters included must correspond correctly to
		the search index that was given upon instantiation.	*/
	public function add_parameter($newParam){
		foreach($newParam as $key=>$value){
			$this->$params[$key] = $value;
		}
		$this->url = NULL;	//	The previously set url does not reflect the changes, by setting it to null, we indicate to the object that it needs to recalculate the url.
		$this->executed = false;
	} 

	/*	Method that returns the api query url that is called to retrieve information from Amazon's API.	*/
	public function get_api_url(){
		$this->construct_search_url();
		return $this->url;
	}

	/*	This is Amazon's link for the search query you are trying to perform. This link is not an API Call but rather
		a link to the search results page on Amazon's Website. If the object has not been executed(), this method
		will return false indicating that this must be done first (this ensures that any problems with execution
		are dealt with prior to getting this link).		*/
	public function get_amazon_link(){
		if($this->executed){
			return $this->amazonLink;
		}
		print "The object has not executed the API Call, call $thisObject->execute() then recall this function";	//	executing the api call manually ensures the caller is
																													//	-- notified of any errors on execution
		return false;
	}

	/*	Returns the total number of results that Amazon's Search API found given the query. If the object was not
		executed(), the function will return -1.	*/
	public function get_number_results(){
		if ($this->executed)	return $this->numResults;
		return -1;				//	-1 indicates the the object has yet to be executed.
	}


	//	-- PRIVATE METHODS -- 

	/*	This function constucts the url for the api call to amazon's itemsearch. This happens by analyzing the itemsearch parameters,
		the search Index and the ReponseGroup specified upon instantiation and afterwards. The function sets the member variable, $url,
		to the generated url.

		Note that this construction function is a derivation of the amazon_get_signed_url function as specified on:
		http://www.internetammo.com/how-to-connect-to-get-amazon-products-from-the-amazon-api-with-php-and-curl		*/
	private function construct_search_url(){

		$base_url = "http://ecs.amazonaws.com/onca/xml";
		$urlParameters = array(
			'AWSAccessKeyId' => $this->aws_access_key,
			'AssociateTag' => $this->associate_tag,
			'Version' => "2010-11-01",
			'Service' => "AWSECommerceService",
			'SearchIndex' => $this->searchIndex,
			'ResponseGroup' => $this->responseGroup,
			'ItemPage' => $this->itemPage,
		);
		
		if ($this->itemSearch){
			$urlParameters['Operation'] = "ItemSearch";
		}
		else{
			$urlParameters['Operation'] = "ItemLookup";	
		}

		foreach($this->params as $key=>$value){
		//	Include all our ItemSearch parameters
			$urlParameters[$key] = $value;
			//print "Params: $key => $value\n";
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

	/*	This method curls the results given from the auto-generated api-url that the object creates given the search parameters.
		If the curl did not execute correctly (did not recieve 200:OK Http Response), it will return false indicating an error.
		If the URL was not configured explictly by calling construct_search_url(), it will also return false. */
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
