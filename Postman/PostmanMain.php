<?php

// setup the main entry point
if (! class_exists ( 'PostmanMain' )) {
	
	require_once 'PostmanAuthorizationToken.php';
	require_once 'PostmanOptions.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'AdminController.php';
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanMain {
		/**
		 * The main entry point for Postman
		 */
		public function main($basename) {
			
			// create a Logger
			$logger = new PostmanLogger ( get_class ( $this ) );
			
			// load the options and the auth token
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanAuthorizationToken::getInstance ();
			
			// create a message handler
			$messageHandler = new PostmanMessageHandler ( $options, $authToken );
			
			// bind to wp_mail()
			new PostmanWpMailBinder ( $basename, $options, $authToken, $messageHandler );
			
			if (is_admin ()) {
				// fire up the AdminController
				new PostmanAdminController ( $basename, $options, $authToken, $messageHandler );
			}
		}
	}
}

?>