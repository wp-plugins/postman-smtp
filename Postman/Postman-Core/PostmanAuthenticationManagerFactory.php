<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
	require_once 'PostmanGmailAuthenticationManager.php';
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
		public function createAuthenticationManager($authenticationType, $clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken) {
			if ($authenticationType == PostmanOptions::AUTHORIZATION_TYPE_OAUTH2) {
				$authenticationManager = new PostmanGmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken );
			} else {
				$authenticationManager = new PostmanNonOAuthAuthenticationManager ();
			}
			$this->logger->debug ( 'Created ' . get_class ( $authenticationManager ) );
			return $authenticationManager;
		}
	}
}