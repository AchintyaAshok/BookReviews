<?php

interface SectionController{

	/*
		Each subclass of Section Controller will receive the same data when being instantiated
		and can handle this data any way that is required. 
	*/
	public function __construct($url, $glassData);

	/*
		The handleMessage() method is invoked after instantiation of the class. It will be used
		to resolve and use the data acquired from the message.
		For example, in the case of the BooksController, handleMessage will be used to update tables in
		Vendor Books to reflect the addition of a new Book Review. 
	*/
	public function handleMessage();

}

?>