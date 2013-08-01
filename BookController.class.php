<?php

/*
	Author:			Achintya Ashok
	Date-Created:	08/01/13

	Purpose:		This is a Controller Class that implements the SectionController Interface. 
					It's used to handle messages pertaining to Book Reviews that are popped from the
					Amazon SQS Queue of Scoop Articles.

					The Controller updates the VenderContent Books Database with the Book Review Information.
					It updates the book table with the review's title, author, url-link and BTRN(bowker title...number)
					In addition, the isbns table is updated with the pairing of the review's book_id(from the book table)
					and all the associated ISBNs for the book. 
*/

require_once('SectionController.interface.php');
require_once('Books/amazon_search.class.php');

class BookController implements SectionController{

	// Member Variables
	// Keeping them public, because there's no sense in making getters for them, the caller can access these values freely
	public $url;
	public $author;
	public $title;
	public $isbn;
	public $pubDate;

	// Database Connection Vars
	private $db_config_file = '/opt/nyt/etc/feeds-dbconfig.ini';
	private $force_update = false;
	private $environment = "development";
	private $config_section = 'VendorContent_Books-development';

	private $db;	//	This is the database handle that is opened in the constructor and closed in the destructor


	public function __construct($url, $glassData){
		$this->url 		= $url;
		$this->pubDate 	= $glassData['cms']['article']['publication_dt'];

		$reviewData 	= $glassData['cms']['article']['article_review'];
		//	Retrive the author, title, isbn from the Glass JSON
		$this->author 	= $reviewData['author'];
		$this->title 	= $reviewData['review_title'];
		$this->isbn 	= $reviewData['isbn'];
		//	Setup our database handle that will be used across all the methods
		$this->db 		= $this->setupBooksDatabaseHandle();
	}


	/*
		This Method handles the message that was received from the SQS Queue by using the Glass Data of the 
		review and updating the book and isbn tables in Vender Books to reflect the newly created
		Book Review.

		In the case of Book Reviews, our method deals with the metadata stored about Author, Title, and ISBN for a review.
		The two cases for updating the Databases:
			1. If a book review has an ISBN, the method will use author/title or retrieve it from Amazon and update the tables.
			2. If a book review has Author & Title (but not an ISBN), it will retrieve the ISBN from Amazon and update the tables.

		If the Method fails to update the tables and/or retrieve information from Amazon, it will return false.
	*/
	public function handleMessage(){
		if (strlen($this->isbn)>0){
		// We have an ISBN, either use the provided Title/Author or get this from Amazon
			if (strlen($this->title) == 0 || strlen($this->author) == 0){
				$attempt = $titleAuthorArray = $this->getAmazonTitleAuthor($this->isbn);		// Retrive Title/Author from Amazon
				if (!$attempt){
					return false; 	
				}
				$this->title = $titleAuthorArray['title'];
				$this->author = $titleAuthorArray['author'];
			}
			$this->updateDatabase();
		}
		else if(strlen($this->author)>0 && strlen($this->title)>0){ 
			$matchedISBN = $this->getAmazonISBN($this->title, $this->author);	// Get the ISBN from Amazon
			if (!$matchedISBN){
				return false;
			}
			else{
				$this->isbn = $matchedISBN;
				$this->updateDatabase();
			}
		}
		else{
		//	If we reach this case, we don't have the ISBN or we dont have the Author & Title -- we can't do anything but log the problem.
			print "Minimum Information Requirements not met -- Needs ISBN (title, author [optional]) or Title & Author\n";
		}
	}

	/*
	This Method updates the Vendor Books Database with the Book Review.

	Order of things to do:
		1. Get the Bowker Title Record Number from temp_newstech_books
		2. Insert the record with title, author, btrn, book_review/sunday_review_url into books
		3. Using the btrn, get all the isbns for a book_id (taken from books) and insert all of them into isbn
	*/
	private function updateDatabase(){
		$btrn = $this->findBTRN();
		if (!$btrn){
			return false;
		}
		print "Review Information:\n\tTitle: $this->title\n\tAuthor: $this->author\n\tISBN: $this->isbn\n\tBTRN: $btrn\n";	


		// INSERT THE REVIEW INTO THE book TABLE
		date_default_timezone_set('America/New_York');
		$dayOfWeek = strftime("%A", strtotime($this->pubDate));	
		print "$dayOfWeek Review\n";

		$sundayReviewLink = $bookReviewLink = NULL;
		//	Find out and set whether it was a Sunday Review or just a Book Review, set the variable to reference the Book Review URL
		if ($dayOfWeek == 'Sunday'){
			$sundayReviewLink = $this->url;
		}	
		else{
			$bookReviewLink = $this->url;
		}
		
		$bookID = $this->insertIntoBooksTable($btrn, $sundayReviewLink, $bookReviewLink);
		if (!$bookID){
			return false;	//	Our insert attempt was not successful and we can't move on
		}
		print "Inserted into Book. This is our Book-ID:\t$bookID\n";
		
		
		// FIND ALL THE ISBNs FOR THE BOOK & INSERT INTO THE isbn TABLE
		$isbns = $this->findISBNwithBTRN($btrn);
		if (!$isbns){
			return false;
		}
		$this->insertIntoISBNTable($bookID, $isbns);
	}

	/*
		This method retrieves the Bowker Title Record Number(BTRN) for a Book given the member variable, the ISBN.
		The method returns the BTRN of the Book. If no BTRN can be found given the ISBN, it will return false.
	*/
	private function findBTRN(){
		//$db = $this->setupBooksDatabaseHandle();
		$db = $this->db;
		$isbn = $this->isbn;

		$btrnQuery = "SELECT bowker_title_record_number as btrn FROM temp_newstech_books WHERE isbn10='$isbn' OR isbn13='$isbn' ";
       	$queryResult = $db->query($btrnQuery);

        if ($queryResult->num_rows == 0){
			printf("No BTRN found for isbn: %s -- %s\n", $isbn, $db->error);           
        //   	$db->close();
            return false;
        }

		$firstRow = $queryResult->fetch_assoc();
        $btrn = $firstRow['btrn'];
		
		//$db->close();
		return $btrn;
	}


	/*
		The method uses the BTRN to retrive a collection of result rows consisting of ISBN10s and ISBN13s (tuple in each row), 
		and returns this mysqli result set back to the caller. If no ISBN results are found given the BTRN, returns false. 
	*/
	private function findISBNwithBTRN($btrn){
		//$db = $this->setupBooksDatabaseHandle();
		$db = $this->db;

		$query = "SELECT isbn13, isbn10 FROM temp_newstech_books WHERE bowker_title_record_number='$btrn'";
		$result = $db->query($query);		
		if (!$result){
			printf("No ISBNs found for BTRN: %s -- %s\n", $btrn, $db->error);
		//	$db->close();
			return false;		//	If our query did not work notify the caller		
		}
		//$db->close();
		return $result;	//	return the tuples of isbn13s and isbn10s (some of these values -- for one or the other -- will be null)
	}


	/*
		Inserts the bowker_title_record_number, review link (either a Sunday Review Link or Book Review Link), author and title
		into the book table. The method requires the BTRN of the Book, and either a Sunday Review or Book Review Link. 
		If the insertion fails, the method will return false.
	*/
	private function insertIntoBooksTable($btrn, $sundayReviewLink = NULL, $bookReviewLink = NULL){
		if (!$sundayReviewLink && !$bookReviewLink) return false;

		//$db = $this->setupBooksDatabaseHandle();
		$db = $this->db;
		$title = $this->title;
		$author = $this->author;

		$query = "	INSERT IGNORE INTO book
						(bowker_title_record_number, title, author, sunday_review_link, book_review_link, data_source)
					VALUES
						('$btrn', '$title', '$author', '$sundayReviewLink', '$bookReviewLink', 'SCOOP')";		

		$isSuccessful = $db->query($query); 			// Insert our new record into the database
		if (!$isSuccessful){
			printf("Did not insert into books table -- %s\n", $db->error);
		//	$db->close();
			return false;								// This means the insert attempt was not successful, so we exit the function
		}

		$bookID = $db->insert_id;						// Gives us the id (primary key) of the last insert that was made, in this case the book id of the latest insert

		//$db->close();
		return $bookID;
	}

	/*
		Inserts a result set of ISBNS (provided as the second parameter) for a given book (identified by the bookID parameter) into
		the isbn table. 
		If the insertion fails, the method will return false.
	*/
	private function insertIntoISBNTable($bookID, $isbns){
		print "Number of isbn10/isbn13 tuples: " . $isbns->num_rows . "\n";
		
		//$db = $this->setupBooksDatabaseHandle();
		$db = $this->db;

		$query = "	INSERT IGNORE INTO isbn 
						(book_id, isbn13, isbn10)
					VALUES";
		// Let's do a batch insert for all the isbn13, isbn10 tuples into the isbn table
		$prefix = "";
		while ($row = $isbns->fetch_assoc()){
			$query .= $prefix;
			$isbn13 = $row['isbn13'];
			$isbn10 = $row['isbn10'];
			$query .= "($bookID, '$isbn13', '$isbn10')";
			$prefix = ", ";
		}		
		
		$attempt = $db->query($query);	//	execute our query
		if (!$attempt){
			printf("Did not insert into isbn table -- %s\n", $db->error);
		//	$db->close();
			return false;
		}
		//$db->close();
	}

	/*
		Usage:	$isbn = $this->getAmazonISBN(TITLE, AUTHOR);

		This method uses Amazon ItemSearch to find the ISBN of a Book given the title and author that are passed in as parameters.
		It utilizes the AmazonSearch Class that is defined in the amazon_search.class.php file. If all goes well, the method
		will return the ISBN.
		If no ISBN can be found using the data, the function will return false after printing out the url of the API call it made to Amazon. 
	*/
	private function getAmazonISBN($title, $author){
		$searchIndex = 'Books';
		$responseGroup = 'ItemAttributes';		

		$params = array(
		'Title'=>$title,
		'Author'=>$author
		);

		$lookupObject = new AmazonSearch($searchIndex, $params, $responseGroup, SearchType::ITEM_SEARCH);	//	Create a new AmazonSearch
		$attempt = $lookupObject->execute();										//	Execute the object to make the api call and pull results

		if ($attempt == false){
			return false;	//	If the search could not be performed, the object returns false.
		}
		$firstMatchedItem = $lookupObject->get_item_data(1);		//	Returns an associative array of information about the first results amazon returned
		$isbn = $firstMatchedItem['attributes']['ISBN'];			//	Get the ISBN
		if (strlen($isbn)==0){
			print "No ISBN found from Amazon for Title: $title, Author: $author\n";
			printf("\tAmazon API Call:\t%s\n\n", $lookupObject->get_api_url());
			return false;
		}

		return $isbn;
	}

	/*
		Usage:	$titleAuthorArray = $this->getAmazonTitleAuthor(SOME_ISBN);

		Gets the Title and Author using Amazon ItemLookup by utilizing the AmazonSearch class defined in amazon_search.class.php. The
		method accepts an ISBN as the parameter. The method returns an associative array containing the Title and Author:
			'title' =>	MATCHED_TITLE
			'author' => MATCHED_AUTHOR
		If no title/author information is found given the isbn, the method returns false after printing out the URL of the API call it made
		to Amazon.
	*/
	private function getAmazonTitleAuthor($isbn){
		$searchIndex = 'Books';
		$responseGroup = 'ItemAttributes';

		$params = array(
			'IdType'	=>	'ISBN',
			'ItemId' 	=>	(string)$isbn, 
		);

		$lookupObject = new AmazonSearch($searchIndex, $params, $responseGroup, SearchType::ITEM_LOOKUP);    //  Create a new AmazonSearch
		$attempt = $lookupObject->execute();                                        //  Execute the object to make the api call and pull results

		if ($attempt == false){
		    return false;   //  If the search could not be performed, the object returns false.
		}
		$firstMatchedItem = $lookupObject->get_item_data(1);        //  Returns an associative array of information about the first results amazon returned
		$itemAttributes = $firstMatchedItem['attributes'];
		if (!array_key_exists('Title', $itemAttributes) || !array_key_exists('Author', $itemAttributes)){
			print "No Author/Title found from Amazon using ISBN: $isbn\n";
			printf("\tAmazon API Call:\t%s\n\n", $lookupObject->get_api_url());
			return false;
		}
		$title = $itemAttributes['Title'];         	//  Get the Title
		$author = $itemAttributes['Author'];		// 	Get the Author

		$toReturn = array(
			'title' 	=> 	$title,
			'author' 	=> 	$author
		);

		return $toReturn;
	}

	/*
		This method sets up a database handle to the Vender Books Database, which is used to update or query book information
	*/
	private function setupBooksDatabaseHandle(){
		$config_settings = parse_ini_file($this->db_config_file, true);
		$host = $config_settings[$this->config_section]["host"];
		$database = $config_settings[$this->config_section]["database"];
		$username = $config_settings[$this->config_section]["username"];
		$password = $config_settings[$this->config_section]["password"];

		$mysqli = @mysqli_connect($host, $username, $password, $database);

		if (!$mysqli) {
			$logger->write2Log("Problem connecting to the database.  " . mysqli_connect_error(), NYTD_Feeds_Logger::Error, "GlassBrokerContoller.class.php");
			exit(3);
		}
		$mysqli->set_charset("utf8");
		
		return $mysqli;	
	} 


	private function __destruct(){
		$this->db->close();	//	Close our Database Handle
	}

}
?>
