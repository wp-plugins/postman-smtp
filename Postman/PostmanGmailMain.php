<?php

// setup the main entry point
if (! class_exists ( 'PostmanGmail' )) {
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanGmail {
		private $postmanPhpFile;
		private $logger;
		/**
		 *
		 * @param unknown $postmanPhpFile        	
		 */
		public function __construct($postmanPhpFile) {
			$this->postmanPhpFile = $postmanPhpFile;
			add_action ( 'plugins_loaded', array (
					$this,
					'init' 
			) );
			register_deactivation_hook ( $postmanPhpFile, array (
					$this,
					'handleDeactivationEvent' 
			) );
		}
		public function init() {
			if (class_exists ( 'PostmanLogger' )) {
				require_once 'PostmanTransportDirectory.php';
				$this->logger = new PostmanLogger ( get_class ( $this ) );
				$this->logger->debug ( 'Postman Gmail Extension v' . POSTMAN_GMAIL_API_PLUGIN_VERSION . ' starting' );
				$this->addTransport ();
				$this->loadTextDomain ();
			}
		}
		public function handleDeactivationEvent() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->logger->debug ( 'Deactivating' );
			$options = PostmanOptions::getInstance ();
			if ($options->getTransportType () == PostmanGmailApiTransport::SLUG) {
				$options->setTransportType ( PostmanSmtpTransport::SLUG );
				$options->save ();
				$mh = new PostmanMessageHandler ( $options, PostmanOAuthToken::getInstance () );
				$mh->addError ( __ ( 'Postman Transport reset to SMTP. Attention may be required.' ) );
			}
		}
		function addTransport() {
			if (class_exists ( 'PostmanOptions' )) {
				$options = PostmanOptions::getInstance ();
				$authToken = PostmanOAuthToken::getInstance ();
				require_once 'Postman-Mail/PostmanGmailTransport.php';
				PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanGmailApiTransport () );
			}
		}
		public function loadTextDomain() {
			$langDir = basename ( dirname ( $this->postmanPhpFile ) ) . '/Postman/languages/';
			$success = load_plugin_textdomain ( 'postman-smtp', false, $langDir );
			if (! $success && get_locale () != 'en_US') {
				$this->logger->error ( 'Could not load text domain ' . $langDir . 'postman-smtp-' . get_locale () . '.po' );
			}
		}
	}
}
