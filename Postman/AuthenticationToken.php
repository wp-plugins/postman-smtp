<?php

namespace Postman {

	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class AuthenticationToken {
		private $clientId;
		private $clientSecret;
		private $accessToken;
		private $refreshToken;
		private $expiryTime;
		
		/**
		 * Constructor
		 */
		public function __construct($options) {
			$this->loadFromOptions ( $options );
		}
		
		/**
		 * Set state based on $options
		 *
		 * @param unknown $options        	
		 */
		public function loadFromOptions($options) {
			$this->clientId = $options [Options::CLIENT_ID];
			$this->clientSecret = $options [Options::CLIENT_SECRET];
			$this->accessToken = $options [Options::ACCESS_TOKEN];
			$this->refreshToken = $options [Options::REFRESH_TOKEN];
			$this->expiryTime = $options [Options::TOKEN_EXPIRES];
		}
		
		public function getClientId() {
			return $this->clientId;
		}
		
		public function getClientSecret() {
			return $this->clientSecret;
		}
		
		public function getAccessToken() {
			return $this->accessToken;
		}
		
		public function setAccessToken($accessToken) {
			$this->accessToken = $accessToken;
		}
		
		public function getRefreshToken() {
			return $this->refreshToken;
		}
		
		public function setRefreshToken($refreshToken) {
			$this->refreshToken = $refreshToken;
		}
		
		public function getExpiryTime() {
			return $this->expiryTime;
		}
		
		public function setExpiryTime($expiryTimeInSeconds) {
			$this->expiryTime = $expiryTimeInSeconds;
		}
	}
}