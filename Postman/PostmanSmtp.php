<?php

// setup the main entry point
if (! class_exists ( 'PostmanSmtp' )) {
	
	require_once 'Common.php';
	require_once 'Postman-Mail/PostmanSmtpTransport.php';
	
	require_once 'PostmanOAuthToken.php';
	require_once 'PostmanConfigTextHelper.php';
	require_once 'PostmanOptions.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'PostmanAdminController.php';
	require_once 'PostmanActivationHandler.php';
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanSmtp {
		const POSTMAN_TCP_READ_TIMEOUT = 60;
		const POSTMAN_TCP_CONNECTION_TIMEOUT = 10;
		const LONG_ENOUGH_SEC = 432000;
		private $postmanPhpFile;
		private $logger;
		private $messageHandler;
		private $options;
		private $authToken;
		private $wpMailBinder;
		
		/**
		 *
		 * @param unknown $postmanPhpFile        	
		 */
		public function __construct($postmanPhpFile) {
			
			// calculate the basename
			$this->postmanPhpFile = $postmanPhpFile;
			
			// start the logger
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			
			// store instances of the Options and OAuthToken
			$this->options = PostmanOptions::getInstance ();
			$this->authToken = PostmanOAuthToken::getInstance ();
			
			// create am instance of the MessageHandler
			$this->messageHandler = new PostmanMessageHandler ( $this->options, $this->authToken );
			
			// store an instance of the WpMailBinder
			$this->wpMailBinder = PostmanWpMailBinder::getInstance ();
			
			// These are operations that have to happen NOW, before the init() hook
			// and even before WordPress loads its interna pluggable functions
			$this->preInit ();
		}
		
		/**
		 * These functions have to be called before the WordPress pluggables are loaded
		 */
		private function preInit() {
			// register the SMTP transport
			$this->registerTransport ();
			
			// load the text domain
			$this->loadTextDomain ();
			
			// bind to wp_mail
			$this->wpMailBinder->bind ();
			
			if (is_admin ()) {
				// fire up the AdminController
				$basename = plugin_basename ( $this->postmanPhpFile );
				$adminController = new PostmanAdminController ( $basename, $this->options, $this->authToken, $this->messageHandler );
			}
			
			// handle plugin activation/deactivation
			$upgrader = new PostmanActivationHandler ();
			register_activation_hook ( $this->postmanPhpFile, array (
					$upgrader,
					'activatePostman' 
			) );
			
			// call the initialization on the standard WordPress plugins_loaded hook
			add_action ( 'plugins_loaded', array (
					$this,
					'init' 
			) );
		}
		
		/**
		 * Initializes the Plugin
		 *
		 * 1. Loads the text domain
		 * 2. Binds to wp_mail()
		 * 3. adds the [postman-version] shortcode
		 */
		public function init() {
			$this->logger->debug ( 'Postman Smtp v' . POSTMAN_PLUGIN_VERSION . ' starting' );
			
			// are we bound?
			if ($this->wpMailBinder->isUnboundDueToException ()) {
				$this->messageHandler->addError ( __ ( 'Postman is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', 'postman-smtp' ) );
			} else if (! PostmanWpMailBinder::getInstance ()->isBound ()) {
				$this->logger->debug ( ' Not binding, plugin is not configured.' );
			}
			
			// add the version shortcode
			// register WordPress hooks
			add_shortcode ( 'postman-version', array (
					$this,
					'version_shortcode' 
			) );
		}
		
		/**
		 * Adds the regular SMTP transport
		 */
		private function registerTransport() {
			PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanSmtpTransport () );
		}
		
		/**
		 * Loads the appropriate language file
		 */
		private function loadTextDomain() {
			$textDomain = 'postman-smtp';
			$langDir = basename ( dirname ( $this->postmanPhpFile ) ) . '/Postman/languages/';
			$success = load_plugin_textdomain ( $textDomain, false, $langDir );
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
