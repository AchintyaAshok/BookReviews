<?php
/*
    Authors:        Achintya Ashok, Alfred Pang
    Date-Modified:  08/08/13

    This is the Glass Broker Controller that receives a message from the Glass Broker and then
    delegates the message to a specific handler based on the section of the article.

    For example, when the section is books, the controller instantiates a BookHandler that then
    deals with the message. 
*/

nytd_require("NYTD/DU/Config.class.php");
nytd_require("NYTD/GlassBroker/Feeds/Config.class.php");
nytd_require("NYTD/GlassBroker/Feeds/jsonpath-0.8.3_a.php");

require('BookHandler.class.php');	// The Message Handler that handles Book Reviews 


class NYTD_DU_GlassBroker_GlassBrokerController
{
    private $logger;
    
    /*
     * @see: http://wiki.em.nytimes.com/dev/doku.php?id=glass_notifications
     */
    private $glassSubjects;
    
    // for given section, handle any DU side updates
    private $sectionHandlers;

    function __construct($logger)
    {
        $this->logger = $logger;

        $this->glassSubjects = array(
            "publish:scoop_article",
            "update:scoop_article"
        );
     
		$this->sectionHandlers = array(
			"books"		=>	"NYTD_DU_GlassBroker_BookHandler",
			//TODO: Implement the Dining and Movies Handlers
			//"dining"	=>	"DiningHandler",
			//"movies"	=>	"MovieHandler",
		);
    }
    
    function handleNotification($json, $sentTimestamp, $messageID)
    {
        // unwrap the SQS notification to get at the message
		$data = json_decode($json, TRUE);
        if (!$data) {
            throw new Exception("Cannot Decode JSON post: " . $json);
        }
		

        // We make sure that the message fits the qualifications specified in glassSubjects -- 
        // It's a scoop article that's being published or updated
        if (array_key_exists("Subject", $data) && in_array($data["Subject"], $this->glassSubjects)) {
			$message = json_decode($data["Message"]);
			$messageURL = (string)$message->url;
            $messageSource = (string)$message->source;
            $messageType = (string)$message->type;
            $messageSection = (string)$message->section;
      

			$this->handleGlassMessage($messageSource, $messageType, $messageSection, $messageURL);
		}
        else{
            return;   // this is a subject we are not interested in
        }
    }

    function handleGlassMessage($source, $type, $section, $url)
    {
        // Check if the section specified in the message is something we handle
        if (!isset($this->sectionHandlers[$section])) {
            // we are not interested in updates for these other sections
            $this->logger->write2Log("Skipping " . $section. " " . $url, NYTD_Feeds_Logger::Debug,
                "GlassBrokerController.class.php:" . __LINE__ . "\n");
            return;
        }

        $this->logger->write2Log(sprintf("Handling the Message with => %s\n", $this->sectionHandlers[$section]), 
            NYTD_Feeds_Logger::Debug, "GlassBrokerController.class.php:" . __LINE__) ;

        $glassOutput = $this->getGlassOutput($url, $source, $type); // Takes the URL & Gets Article Data from Glass
        if(empty($glassOutput)){
            throw new Exception("Cannot Get Glass Output for: " . $url);
        }

        $this->logger->write2Log("Received Message & Glass Data => " . "Section: " . $section . ", URL: " . $url,
            NYTD_Feeds_Logger::Debug,
            "");


		//	Instantiate a Message Handler specific for the section and make it handle the SQS Message
		$handlerName = $this->sectionHandlers[$section];
		$messageHandler = new $handlerName($url, $glassOutput);
		try{
			$messageHandler->handleMessage();
		}catch (Exception $e){
			$exceptMessage = $e->getMessage();
			$this->logger->write2log("$handlerName Notification\n\tSection: $section\n\tURL: $url\n$exceptMessage", NYTD_Feeds_Logger::Debug, "");
		}
		
	}
	

    private function getGlassOutput($assetURL, $messageSource, $messageType)
    {
        $glassURL = NYTD_GlassBroker_Feeds_Config::getGlassApiUrl() . "?url=" . $assetURL . "&source=" . $messageSource . "&type=" . $messageType;

        $ch = curl_init($glassURL);
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_USERAGENT, "DU-GlassBrokerController/1.0");
        $data = curl_exec($ch);
        $retries=0;

        while((empty($data)|| curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404||curl_errno($ch) > 0) && ++$retries<=3){
            if($retries==3) return null;
            $data = curl_exec($ch);
        }
        return json_decode($data, TRUE);
    }
	
/*

	TODO:: These Handlers will be implemented in their own classes in the future

    private function handleDiningReviews($url, $data)
    {
        // a restaurant review
        $restaurant_asset_id = jsonPath($data, "$..relatedAssets[?(@['externalType']=='restaurants')]['external_id']");

        if (!$restaurant_asset_id) {
            $this->logger->write2Log("Expected restaurant asset id but did not find it glass json " . $url,  NYTD_Feeds_Logger::Warning,
                "GlassBrokerController.class.php:" . __LINE__);
            return false;   /// not necessarily an error, just return
        }

        $restaurant_asset_id = array_shift($restaurant_asset_id);
        $this->logger->write2Log("Restaurant asset id " . $restaurant_asset_id . " " . $url,  NYTD_Feeds_Logger::Debug,
            "GlassBrokerController.class.php:" . __LINE__);

        // update as appropriate
        $host = NYTD_GlassBroker_Feeds_Config::getInternalApiHost();
        $serviceUrl = "http://$host/svc/dining/v3/restaurants/$restaurant_asset_id.json";
        $asset_json = self::retrieveUrl($serviceUrl);
        $response = @json_decode($asset_json, true);

        // If we didn't get back a response, error out
        if (!$response) {
            $this->logger->write2Log("No response from $serviceUrl",  NYTD_Feeds_Logger::Error,
                "GlassBrokerController.class.php:" . __LINE__);
            throw new Exception("No response from " . $serviceUrl);
        }

        $restaurant = array_pop($response['results']);

        if (!$restaurant) {
            $this->logger->write2Log("Error retrieving restaurant $restaurant_asset_id from $serviceUrl", NYTD_Feeds_Logger::Error,
                "GlassBrokerController.class.php:" . __LINE__);
            throw new Exception("Unable to retrieve existing restaurant data for " . $serviceUrl);
        }
          
        // Update our restaurant with the new review url
        $restaurant['review'] = $url;

        // PUT it back to the dining service
        $put = new HttpRequest();
        $put->setUrl($serviceUrl);
        $put->setMethod(HTTP_METH_PUT);
        $put->setContentType('application/json');
        $put->setPutData(json_encode($restaurant));
        $result = $put->send();  // throws HttpException on error

        $this->logger->write2Log("Updated review URL for restaurant $restaurant_asset_id", NYTD_Feeds_Logger::Info,
                "GlassBrokerController.class.php:" . __LINE__);
        return true;
    }

    private function handleRecipes($url, $data)
    {
        // article with recipes
        $recipe_asset_ids = jsonPath($data, "$..relatedAssets[?(@['externalType']=='recipe')]['external_id']");

        if (!$recipe_asset_ids) {
            $this->logger->write2Log("Expected recipe asset id but did not find it glass json " . $url,  NYTD_Feeds_Logger::Warning,
                "GlassBrokerController.class.php:" . __LINE__);
            return false;   /// not necessarily an error, just return
        }

        $recipe_asset_ids = array_unique($recipe_asset_ids);
        $this->logger->write2Log("Recipe asset id " . $recipe_asset_ids. " " . $url,  NYTD_Feeds_Logger::Debug,
            "GlassBrokerController.class.php:" . __LINE__);

        // update as appropriate
        $host = NYTD_GlassBroker_Feeds_Config::getInternalApiHost();

        foreach ($recipe_asset_ids as $id) {
            $serviceUrl = "http://$host/svc/dining/v3/recipes/$id.json";
            $asset_json = self::retrieveUrl($serviceUrl);
            $response = @json_decode($asset_json, true);

            if (!$response) {
                $this->logger->write2Log("No response from $serviceUrl",  NYTD_Feeds_Logger::Error,
                    "GlassBrokerController.class.php:" . __LINE__);
                throw new Exception("No response from " . $serviceUrl);
            }

            $recipe = array_pop($response['results']);
            if(!$recipe) {
                $this->logger->write2Log("Error retrieving from $serviceUrl",  NYTD_Feeds_Logger::Error,
                    "GlassBrokerController.class.php:" . __LINE__);
                return false;
            }

            $recipe['byline'] = array_shift(jsonPath($data, "$['cms']['article']['byline']"));
            $recipe['headline'] = array_shift(jsonPath($data, "$['cms']['article']['headline']"));
            $recipe['publish_date'] = array_shift(jsonPath($data, "$['cms']['article']['publication_dt']"));
            //$recipe['seo_name'] = array_shift(jsonPath($data, "$['cms']['article']['seo_headline']"));
            //$recipe['article_desk'] = array_shift(jsonPath($data, "$['cms']['article']['desk']"));
            $recipe['article_url'] = array_shift(jsonPath($data, "$['cms']['article']['sub_domain']")) . array_shift(jsonPath($data, "$['cms']['article']['publish_url']"));
            $recipe['recipe_source'] = array_shift(jsonPath($data, "$['cms']['article']['desk']"));
            $recipe['status'] = 'APPROVED';
         
            // PUT it back to the dining service
            $put = new HttpRequest();
            $put->setUrl($serviceUrl);
            $put->setMethod(HTTP_METH_PUT);
            $put->setContentType('application/json');
            $put->setPutData(json_encode($recipe));
            $result = $put->send();

            $this->logger->write2Log("Updated recipe $id", NYTD_Feeds_Logger::Info,
                "GlassBrokerController.class.php:" . __LINE__);
 
        }

        return true;
    }

    private function handleMovies($url, $data)
    {
        $movie_asset_id = jsonPath($data, "$..relatedAssets[?(@['externalType']=='movie')]['external_id']");

        if (!$movie_asset_id) {
            $this->logger->write2Log("Expected movie asset id but did not find it glass json " . $url,  NYTD_Feeds_Logger::Warning,
                "GlassBrokerController.class.php:" . __LINE__);
            return false;   /// not necessarily an error, just return
        }

        $movie_asset_id = array_shift($movie_asset_id);
        $this->logger->write2Log("Movie asset id " . $movie_asset_id . " " . $url,  NYTD_Feeds_Logger::Debug,
            "GlassBrokerController.class.php:" . __LINE__);

        // update as appropriate
        $host = NYTD_GlassBroker_Feeds_Config::getInternalApiHost();
        $serviceUrl = "http://$host/svc/movies/v2/cms/dubroker-edit/$movie_asset_id.json";
        $asset_json = self::retrieveUrl($serviceUrl);
        $response = @json_decode($asset_json, true);

        // If we didn't get back a response, error out
        if (!$response) {
            $this->logger->write2Log("No response from $serviceUrl",  NYTD_Feeds_Logger::Error,
                "GlassBrokerController.class.php:" . __LINE__);
            throw new Exception("No response from " . $serviceUrl);
        }

        $movie = array_pop($response['results']);

        if (!$movie) {
            $this->logger->write2Log("Error retrieving movie $movie_asset_id from $serviceUrl", NYTD_Feeds_Logger::Error,
                "GlassBrokerController.class.php:" . __LINE__);
            throw new Exception("Unable to retrieve existing restaurant data for " . $serviceUrl);
        }
          
        // Update 
        $pub_date = array_shift(jsonPath($data, "$['cms']['article']['publication_dt']"));
        $review_headline = array_shift(jsonPath($data, "$['cms']['article']['headline']"));
        $tols_title = array_shift(jsonPath($data, "$['cms']['article']['article_review']['tols_title']"));
        $tols_author = array_shift(jsonPath($data, "$['cms']['article']['article_review']['tols_author']"));
        $article_keywords = (String) array_shift(jsonPath($data, "$['cms']['article']['keywords'][?(@['type']=='des')]['content']"));
        $artist = array_shift(jsonPath($data, "$['cms']['article']..[?(@['name']=='ArticleImages')]['collection'][0]['relatedAssets'][0]['credit']"));

        $thumb_obj = array_shift(jsonPath($data, "$['cms']['article']..[?(@['name']=='ArticleImages')]['collection'][0]['relatedAssets'][0]['crops'][?(@['type']=='thumbStandard')]"));
        $url_thumb = (String) $thumb_obj['content'];
        $thumb_height = (integer) $thumb_obj['height'];
        $thumb_width = (integer) $thumb_obj['width'];

        $normal_obj = array_shift(jsonPath($data, "$['cms']['article']..[?(@['name']=='ArticleImages')]['collection'][0]['relatedAssets'][0]['crops'][?(@['type']=='articleLarge')]"));
        $url_normal = (String) $normal_obj['content'];
        $normal_height = (integer) $normal_obj['height'];
        $normal_width = (integer) $normal_obj['width'];

        $movie['review_headline'] = $review_headline;
        $movie['tols_title'] = $tols_title;
        $movie['tols_author'] = $tols_author;
        $movie['article_keywords'] = $article_keywords;
        $movie['artist'] = $artist;
        $movie['url_thumb'] = $url_thumb;
        $movie['thumb_height'] = $thumb_height;
        $movie['thumb_width'] = $thumb_width;
        $movie['url_normal'] = $url_normal;
        $movie['normal_height'] = $normal_height;
        $movie['normal_width'] = $normal_width;

        //html_review_path that we get should be a full url and we only need the path part, not the host part.
        $address = parse_url(trim($url));
        if (!isset($address['path']) || !isset($address['host']) ) { 
            $this->logger->write2Log("Expecting full url http://host/path but got this instead = " . $url, NYTD_Feeds_Logger::Error,
                "GlassBrokerController.class.php:" . __LINE__);
            throw new Exception("Could not update article data: ArticleUrl is incorrect.  Must be a full url - http://host/path");
        }   
        $movie['html_review_path'] = substr($address['path'],1);
        if (isset($address['query'])) {
            $movie['html_review_path'] .= '?' . $address['query'];
        }   

        //We only set the pub_date if it's not already set and the publication_dt node is given (which should always be the case).
        if ((!$movie['publication_date'] || $movie['publication_date'] == '') && ($pub_date && $pub_date != '')) {
            //We expect the format of YYYY-MM-DD
            $movie['publication_date'] = substr($pub_date, 0 , 4) . substr($pub_date, 5 , 2) . substr($pub_date, 8 , 2); 
        }   

        // Update the movie through CRUD service call
        $put = new HttpRequest();
        $put->setUrl($serviceUrl);
        $put->setMethod(HTTP_METH_PUT);
        $put->setContentType('application/json');
        $put->setPutData(json_encode($movie));
        $result = $put->send();  // throws HttpException on error

        if( $result->getResponseCode() > 201 ) {
            $this->logger->write2Log("Error returned by PUT $serviceUrl " . (string)$result->getResponseCode() . $result->getBody(),
                NYTD_Feeds_Logger::Error, "GlassBrokerController.class.php" . __LINE__);
            throw new Exception("Error returned by PUT $serviceUrl");
        }

        $this->logger->write2Log("Updated movie $movie_asset_id", NYTD_Feeds_Logger::Info,
                "GlassBrokerController.class.php:" . __LINE__);
        return true;
    }
	

    static function retrieveUrl($url)
    {
        $get = new HttpRequest();
        $get->setUrl($url);
        $get->setMethod(HTTP_METH_GET);
        $get->send();
        $resp = $get->getResponseBody();
        return $resp;
    }
	*/
   
}
?>
