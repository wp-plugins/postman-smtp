<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
	require_once 'PostmanGmailAuthenticationManager.php';
	require_once 'PostmanHotmailAuthenticationManager.php';
	require_once 'PostmanNonOAuthAuthenticationManager.php';
	
	//
	class PostmanAuthenticationManagerFactory {
		private $logger;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanAuthenticationManagerFactory ();
			}
			return $inst;
		}
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		public function createAuthenticationManager(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken) {
			$authenticationType = $options->getAuthorizationType ();
			$hostname = $options->getHostname ();
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			$senderEmail = $options->getSenderEmail();
			if ($authenticationType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 && $hostname == PostmanGmailAuthenticationManager::SMTP_HOSTNAME) {
				$authenticationManager = new PostmanGmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $senderEmail );
			} else if ($authenticationType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 && $hostname == PostmanHotmailAuthenticationManager::SMTP_HOSTNAME) {
				$authenticationManager = new PostmanHotmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken );
			} else {
				$authenticationManager = new PostmanNonOAuthAuthenticationManager ();
			}
			$this->logger->debug ( 'Created ' . get_class ( $authenticationManager ) );
			return $authenticationManager;
		}
	}
}