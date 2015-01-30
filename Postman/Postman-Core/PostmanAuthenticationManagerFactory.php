<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
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
		public function createAuthenticationManager($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken) {
			$authenticationManager = new GmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken );
			$this->logger->debug ( 'Created ' . get_class ( $authenticationManager ) );
			return $authenticationManager;
		}
	}
}