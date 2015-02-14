<?php
if (! class_exists ( "PostmanSmtpEngine" )) {
	
	require_once 'PostmanAbstractSmtpEngine.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanOAuthSmtpEngine extends PostmanAbstractSmtpEngine implements PostmanSmtpEngine {
		
		//
		const AUTH_VALUE = 'oauth2';
		const SSL_VALUE = 'ssl';
		const ZEND_TRANSPORT_CONFIG_AUTH = 'auth';
		const ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST = 'xoauth2_request';
		
		// set in the constructor
		private $accessToken;
		private $authenticationType;
		private $encryptionType;
		
		/**
		 *
		 * @param unknown $senderEmail        	
		 * @param unknown $accessToken        	
		 */
		function __construct($accessToken, $authType, $encType) {
			assert ( ! empty ( $accessToken ) );
			assert ( ! empty ( $authType ) );
			assert ( ! empty ( $encType ) );
			$this->setLogger ( new PostmanLogger ( get_class ( $this ) ) );
			$this->accessToken = $accessToken;
			$this->authenticationType = $authType;
			$this->encryptionType = $encType;
		}
		
		/**
		 * The $sender can only be the $sender that Google authorized
		 * If the user tries to ignore the $sender with a From: header, ignore it.
		 *
		 * @see PostmanAbstractSmtpEngine::overrideSender()
		 */
		function overrideSender(PostmanEmailAddress $sender) {
			$sender->setEmail ( $this->getSender ()->getEmail () );
			return $sender;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAbstractSmtpEngine::createConfig()
		 */
		public function createConfig(PostmanEmailAddress $sender, $hostname, $port) {
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
			assert ( isset ( $sender ) );
			
			if (isset ( $sender )) {
				$senderEmail = $sender->getEmail ();
			}
			assert ( ! empty ( $senderEmail ) );
			
			$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$this->accessToken}\1\1" );
			assert ( ! empty ( $initClientRequestEncoded ) );
			$config = array (
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => $this->encryptionType,
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_AUTH => $this->authenticationType,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST => $initClientRequestEncoded 
			);
			$this->getLogger ()->debug ( sprintf ( 'Routing mail via %1$s:%2$s using auth:%3$s over ssl:%4$s for user %5$s', $hostname, $port, $this->authenticationType, $this->encryptionType, $senderEmail ) );
			return $config;
		}
	}
}

