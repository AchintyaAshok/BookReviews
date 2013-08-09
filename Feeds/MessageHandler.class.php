<?php
/*

	Author:			Achintya Ashok
	Date-Created:	08/01/13

	Description:	This is the Abstract Parent Class of all Message Handlers that get created in the GlassBrokerController Class. 
					All children will be instantiated using the url of the message and the json-decoded data of the article retrieved from Glass.
					Finally, all messages handled by MessageHandler(s) (regardless of section), take care of their data appropriately when the 
					GlassBrokerController invokes the handleMessage() method as prototyped below. 
*/


abstract class NYTD_DU_GlassBroker_MessageHandler{

	/*
		Each subclass of Section Controller will receive the same data when being instantiated
		and can handle this data any way that is required. 
	*/
	abstract public function __construct($url, $glassData);

	/*
		The handleMessage() method is invoked after instantiation of the class. It will be used
		to resolve and use the data acquired from the message.
		For example, in the case of the BooksController, handleMessage will be used to update tables in
		Vendor Books to reflect the addition of a new Book Review. 
	*/
	abstract public function handleMessage();

}

?>
