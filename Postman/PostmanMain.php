<?php

// setup the main entry point
if (! class_exists ( 'Postman' )) {
	
	require_once 'PostmanOAuthToken.php';
	require_once 'PostmanOptions.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'PostmanAdminController.php';
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class Postman {
		const POSTMAN_TCP_READ_TIMEOUT = 60;
		const POSTMAN_TCP_CONNECTION_TIMEOUT = 10;
		private $postmanPhpFile;
		private $logger;
		/**
		 *
		 * @param unknown $postmanPhpFile        	
		 */
		public function __construct($postmanPhpFile) {
			
			// calculate the basename
			$basename = plugin_basename ( $postmanPhpFile );
			$this->postmanPhpFile = $postmanPhpFile;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			
			// handle plugin activation/deactivation
			require_once 'PostmanActivationHandler.php';
			$upgrader = new PostmanActivationHandler ();
			register_activation_hook ( $postmanPhpFile, array (
					$upgrader,
					'activatePostman' 
			) );
			
			// load the text domain
			add_action ( 'plugins_loaded', array (
					$this,
					'loadTextDomain' 
			) );
			
			// load the options and the auth token
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanOAuthToken::getInstance ();
			
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
		public function loadTextDomain() {
			$langDir = basename ( dirname ( $this->postmanPhpFile ) ) . '/Postman/languages/';
			$success = load_plugin_textdomain ( 'postman-smtp', false, $langDir );
			if (! $success && get_locale () != 'en_US') {
				$this->logger->error ( 'Could not load text domain ' . $langDir . 'postman-smtp-' . get_locale () . '.po' );
			}
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
