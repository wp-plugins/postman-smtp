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
		 * The constructor contains the procedures that HAVE to run
		 * right away.
		 *
		 * Delaying them until the WordPress init() hook won't do.
		 *
		 * @param unknown $postmanPhpFile        	
		 */
		public function __construct($postmanPhpFile) {
			
			// calculate the basename
			$basename = plugin_basename ( $postmanPhpFile );
			$this->postmanPhpFile = $postmanPhpFile;
			
			// start the logger
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			
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
			
			// handle plugin activation/deactivation
			require_once 'PostmanActivationHandler.php';
			$upgrader = new PostmanActivationHandler ();
			register_activation_hook ( $postmanPhpFile, array (
					$upgrader,
					'activatePostman' 
			) );
			
			// initialize the plugin
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
			
			// load the text domain
			$this->loadTextDomain ();
			
			// are we bound?
			if (PostmanWpMailBinder::getInstance ()->getBindError ()) {
				// add an error message for the user
				$options = PostmanOptions::getInstance ();
				$authToken = PostmanOAuthToken::getInstance ();
				$messageHandler = new PostmanMessageHandler ( $options, $authToken );
				add_action ( 'admin_notices', Array (
						$messageHandler,
						'displayCouldNotReplaceWpMail' 
				) );
			} else if (! PostmanWpMailBinder::getInstance ()->getBound ()) {
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
		private function addTransport() {
			PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanSmtpTransport () );
		}
		
		/**
		 * Loads the appropriate language file
		 */
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
