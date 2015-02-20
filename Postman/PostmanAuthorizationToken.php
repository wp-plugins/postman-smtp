<?php
if (! class_exists ( "PostmanAuthorizationToken" )) {
	
	//
	class PostmanAuthorizationToken {
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
				$inst = new PostmanAuthorizationToken ();
			}
			return $inst;
		}
		
		// private constructor
		private function __construct() {
			// load settings from database
			$a = get_option ( PostmanAuthorizationToken::OPTIONS_NAME );
			$this->setAccessToken ( $a [PostmanAuthorizationToken::ACCESS_TOKEN] );
			$this->setRefreshToken ( $a [PostmanAuthorizationToken::REFRESH_TOKEN] );
			$this->setExpiryTime ( $a [PostmanAuthorizationToken::EXPIRY_TIME] );
		}
		
		/**
		 */
		public function save() {
			$a = array ();
			$a [PostmanAuthorizationToken::ACCESS_TOKEN] = $this->getAccessToken ();
			$a [PostmanAuthorizationToken::REFRESH_TOKEN] = $this->getRefreshToken ();
			$a [PostmanAuthorizationToken::EXPIRY_TIME] = $this->getExpiryTime ();
			
			update_option ( PostmanAuthorizationToken::OPTIONS_NAME, $a );
		}
		public function getTokenExpiryTime() {
			return $this->getExpiryTime ();
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