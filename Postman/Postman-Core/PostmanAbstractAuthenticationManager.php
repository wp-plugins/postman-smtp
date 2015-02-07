<?php
if (! class_exists ( "PostmanAbstractAuthenticationManager" )) {
	
	require_once 'PostmanAuthenticationManager.php';
	
	/**
	 */
	abstract class PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// constants
		const APPROVAL_PROMPT = 'force';
		const ACCESS_TYPE = 'offline';
		const ACCESS_TOKEN = 'access_token';
		const REFRESH_TOKEN = 'refresh_token';
		const EXPIRES = 'expires_in';
		
		// the oauth authorization options
		private $clientId;
		private $clientSecret;
		private $authorizationToken;
		private $logger;
		
		/**
		 * Constructor
		 */
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken, PostmanLogger $logger) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			$this->logger = $logger;
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			$this->authorizationToken = $authorizationToken;
		}

		protected function getLogger() {
			return $this->logger;
		}
		
		protected function getClientId() {
			return $this->clientId;
		}
		
		protected function getClientSecret() {
			return $this->clientSecret;
		}

		protected function getAuthorizationToken() {
			return $this->authorizationToken;
		}
		
		/**
		 */
		public function isTokenExpired() {
			$expireTime = ($this->authorizationToken->getExpiryTime () - PostmanGmailAuthenticationManager::FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE);
			$tokenHasExpired = time () > $expireTime;
			$this->logger->debug ( 'Access Token Expiry Time is ' . $expireTime . ', expired=' . ($tokenHasExpired ? 'yes' : 'no') );
			return $tokenHasExpired;
		}
		
	}
}
?>