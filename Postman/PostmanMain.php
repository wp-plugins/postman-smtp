<?php

// setup the main entry point
if (! class_exists ( 'Postman' )) {
	
	require_once 'Common.php';
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
	class PostmanSmtp {
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
			
			// initialzie the plugin
			add_action ( 'plugins_loaded', array (
					$this,
					'init' 
			) );
			
			// add the SMTP transport
			$this->addTransport ();
			
			// bind to wp_mail
			if (class_exists ( 'PostmanWpMailBinder' )) {
				// once the PostmanWpMailBinder has been loaded, ask it to bind
				PostmanWpMailBinder::getInstance ()->bind ();
			}
			
			// load the options and the auth token
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanOAuthToken::getInstance ();
			
			// create a message handler
			$messageHandler = new PostmanMessageHandler ( $options, $authToken );
			
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
		public function init() {
			$this->logger->debug ( 'Postman Smtp v' . POSTMAN_PLUGIN_VERSION . ' starting' );
			// load the text domain
			$this->loadTextDomain ();
		}
		private function addTransport() {
			PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanSmtpTransport () );
		}
		private function loadTextDomain() {
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
