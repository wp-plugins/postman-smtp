<?php

// setup the main entry point
if (! class_exists ( 'PostmanMain' )) {
	
	require_once 'PostmanAuthorizationToken.php';
	require_once 'PostmanOptions.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'PostmanAdminController.php';
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanMain {
		const POSTMAN_TCP_READ_TIMEOUT = 60;
		const POSTMAN_TCP_CONNECTION_TIMEOUT = 10;
		
		/**
		 *
		 * @param unknown $postmanPhpFile        	
		 */
		public function __construct($postmanPhpFile) {

			// calculate the basename
			$basename = plugin_basename ( $postmanPhpFile );
			
			// handle plugin activation/deactivation
			require_once 'PostmanActivationHandler.php';
			$upgrader = new PostmanActivationHandler ();
			register_activation_hook ( $postmanPhpFile, array (
					$upgrader,
					'activatePostman' 
			) );
			
			// load the options and the auth token
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanAuthorizationToken::getInstance ();
			
			// create a message handler
			$messageHandler = new PostmanMessageHandler ( $options, $authToken );
			
			// bind to wp_mail()
			new PostmanWpMailBinder ( $basename, $options, $authToken, $messageHandler );
			
			if (is_admin ()) {
				// fire up the AdminController
				$adminController = new PostmanAdminController ( $basename, $options, $authToken, $messageHandler );
			}
			
			// add the version shortcode
			// register WordPress hooks
			add_shortcode ( 'postman-version', array (
					$this,
					'version_shortcode' 
			) );
		}
		/**
		 * Shortcode to return the current plugin version.
		 * From http://code.garyjones.co.uk/get-wordpress-plugin-version/
		 *
		 * @return string Plugin version
		 */
		function version_shortcode() {
			return POSTMAN_PLUGIN_VERSION;
		}
	}
}
