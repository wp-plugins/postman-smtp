<?php
if (! class_exists ( 'PostmanOAuthToken.php' )) {
	//
	class PostmanOAuthToken {
		const OPTIONS_NAME = 'postman_auth_token';
		//
		const REFRESH_TOKEN = 'refresh_token';
		const EXPIRY_TIME = 'auth_token_expires';
		const ACCESS_TOKEN = 'access_token';
		//
		private $accessToken;
		private $refreshToken;
		private $expiryTime;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanOAuthToken ();
			}
			return $inst;
		}
		
		// private constructor
		private function __construct() {
			$this->load();
		}
		
		/**
		 * Load the Postman OAuth token properties to the database
		 */
		private function load() {
			$a = get_option ( PostmanOAuthToken::OPTIONS_NAME );
			$this->setAccessToken ( $a [PostmanOAuthToken::ACCESS_TOKEN] );
			$this->setRefreshToken ( $a [PostmanOAuthToken::REFRESH_TOKEN] );
			$this->setExpiryTime ( $a [PostmanOAuthToken::EXPIRY_TIME] );
		}
		
		/**
		 * Save the Postman OAuth token properties to the database
		 */
		public function save() {
			$a [PostmanOAuthToken::ACCESS_TOKEN] = $this->getAccessToken ();
			$a [PostmanOAuthToken::REFRESH_TOKEN] = $this->getRefreshToken ();
			$a [PostmanOAuthToken::EXPIRY_TIME] = $this->getExpiryTime ();
			update_option ( PostmanOAuthToken::OPTIONS_NAME, $a );
		}
		public function getExpiryTime() {
			return $this->expiryTime;
		}
		public function getAccessToken() {
			return $this->accessToken;
		}
		public function getRefreshToken() {
			return $this->refreshToken;
		}
		public function setExpiryTime($time) {
			$this->expiryTime = sanitize_text_field ( $time );
		}
		public function setAccessToken($token) {
			$this->accessToken = sanitize_text_field ( $token );
		}
		public function setRefreshToken($token) {
			$this->refreshToken = sanitize_text_field ( $token );
		}
	}
}