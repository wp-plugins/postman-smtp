<?php
if (! class_exists ( "PostmanOptions" )) {
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanOptions {
		// the option database name
		const POSTMAN_OPTIONS = 'postman_options';
		
		// the options fields
		const CLIENT_ID = 'oauth_client_id';
		const CLIENT_SECRET = 'oauth_client_secret';
		const AUTHORIZATION_TYPE = 'authorization_type';
		const SENDER_EMAIL = 'sender_email';
		const PORT = 'port';
		const HOSTNAME = 'hostname';
		const TEST_EMAIL = 'test_email';
		const BASIC_AUTH_USERNAME = 'basic_auth_username';
		const BASIC_AUTH_PASSWORD = 'basic_auth_password';
		const AUTHORIZATION_TYPE_NONE = 'none';
		const AUTHORIZATION_TYPE_BASIC_SSL = 'basic-ssl';
		const AUTHORIZATION_TYPE_BASIC_TLS = 'basic-tls';
		const AUTHORIZATION_TYPE_OAUTH2 = 'oauth2';
		
		// options data
		private $options;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanOptions ();
			}
			return $inst;
		}
		
		/**
		 * private constructor
		 */
		private function __construct() {
			$this->options = get_option ( PostmanOptions::POSTMAN_OPTIONS );
		}
		//
		public function save() {
			update_option ( PostmanOptions::POSTMAN_OPTIONS, $this->options );
		}
		
		//
		public function isRequestOAuthPermissionAllowed() {
			$clientId = $this->getClientId ();
			$clientSecret = $this->getClientSecret ();
			return ! empty ( $clientId ) && ! empty ( $clientSecret );
		}
		public function isSendingEmailAllowed(PostmanAuthorizationToken $token) {
			$authType = $this->getAuthorizationType ();
			$hostname = $this->getHostname ();
			$port = $this->getPort ();
			$username = $this->getUsername ();
			$password = $this->getPassword ();
			if (empty ( $hostname ) || empty ( $port )) {
				return false;
			}
			if ($authType == PostmanOptions::AUTHORIZATION_TYPE_NONE) {
				return true;
			} else if ($authType == PostmanOptions::AUTHORIZATION_TYPE_BASIC_SSL || $authType == PostmanOptions::AUTHORIZATION_TYPE_BASIC_TLS) {
				return ! empty ( $username ) && ! empty ( $password );
			} else if ($authType == PostmanOptions::AUTHORIZATION_TYPE_OAUTH2) {
				$accessToken = $token->getAccessToken ();
				$refreshToken = $token->getRefreshToken ();
				$senderEmail = $this->getSenderEmail ();
				return ! empty ( $accessToken ) && ! empty ( $refreshToken ) && ! empty ( $senderEmail );
			} else {
				throw new Exception ( "oops", 0 );
			}
		}
		public function isPermissionNeeded(PostmanAuthorizationToken $token) {
			$authType = $this->getAuthorizationType ();
			$hostname = $this->getHostname ();
			$port = $this->getPort ();
			$senderEmail = $this->getSenderEmail ();
			$clientId = $this->getClientId ();
			$clientSecret = $this->getClientSecret ();
			if ($authType != PostmanOptions::AUTHORIZATION_TYPE_OAUTH2 || empty ( $hostname ) || empty ( $port ) || empty ( $senderEmail ) || empty ( $clientId ) || empty ( $clientSecret )) {
				return false;
			} else {
				$accessToken = $token->getAccessToken ();
				$refreshToken = $token->getRefreshToken ();
				if (empty ( $accessToken ) || empty ( $refreshToken )) {
					return true;
				} else {
					return false;
				}
			}
		}
		
		//
		public 

		function getHostname() {
			if (isset ( $this->options [PostmanOptions::HOSTNAME] ))
				return $this->options [PostmanOptions::HOSTNAME];
		}
		public 

		function getPort() {
			if (isset ( $this->options [PostmanOptions::PORT] ))
				return $this->options [PostmanOptions::PORT];
		}
		public 

		function getSenderEmail() {
			if (isset ( $this->options [PostmanOptions::SENDER_EMAIL] ))
				return $this->options [PostmanOptions::SENDER_EMAIL];
		}
		public 

		function getClientId() {
			if (isset ( $this->options [PostmanOptions::CLIENT_ID] ))
				return $this->options [PostmanOptions::CLIENT_ID];
		}
		public 

		function getClientSecret() {
			if (isset ( $this->options [PostmanOptions::CLIENT_SECRET] ))
				return $this->options [PostmanOptions::CLIENT_SECRET];
		}
		public 

		function getAuthorizationType() {
			if (isset ( $this->options [PostmanOptions::AUTHORIZATION_TYPE] ))
				return $this->options [PostmanOptions::AUTHORIZATION_TYPE];
		}
		public 

		function getUsername() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_USERNAME] ))
				return $this->options [PostmanOptions::BASIC_AUTH_USERNAME];
		}
		public 

		function getPassword() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_PASSWORD] ))
				return $this->options [PostmanOptions::BASIC_AUTH_PASSWORD];
		}
		public 

		function setHostname($hostname) {
			$this->options [PostmanOptions::HOSTNAME] = $hostname;
		}
		public 

		function setHostnameIfEmpty($hostname) {
			if (! isset ( $this->options [PostmanOptions::HOSTNAME] )) {
				$this->setHostname ( $hostname );
			}
		}
		public 

		function setPort($port) {
			$this->options [PostmanOptions::PORT] = $port;
		}
		public 

		function setPortIfEmpty($port) {
			if (! isset ( $this->options [PostmanOptions::PORT] )) {
				$this->setPort ( $port );
			}
		}
		public 

		function setSenderEmail($senderEmail) {
			$this->options [PostmanOptions::SENDER_EMAIL] = $senderEmail;
		}
		public 

		function setSenderEmailIfEmpty($senderEmail) {
			if (! isset ( $this->options [PostmanOptions::SENDER_EMAIL] )) {
				$this->setSenderEmail ( $senderEmail );
			}
		}
		public 

		function setClientId($clientId) {
			$this->options [PostmanOptions::CLIENT_ID] = $clientId;
		}
		public 

		function setClientSecret($clientSecret) {
			$this->options [PostmanOptions::CLIENT_SECRET] = $clientSecret;
		}
		public 

		function setAuthorizationType($authType) {
			$this->options [PostmanOptions::AUTHORIZATION_TYPE] = $authType;
		}
		public 

		function setAuthorizationTypeIfEmpty($authType) {
			if (! isset ( $this->options [PostmanOptions::AUTHORIZATION_TYPE] )) {
				$this->setAuthorizationType ( $authType );
			}
		}
		public 

		function setUsername($username) {
			$this->options [PostmanOptions::BASIC_AUTH_USERNAME];
		}
		public 

		function setPassword($password) {
			$this->options [PostmanOptions::BASIC_AUTH_PASSWORD];
		}
		public 

		function debug(PostmanLogger $logger) {
			$logger->debug ( 'Sender Email=' . $this->getSenderEmail () );
			$logger->debug ( 'Host=' . $this->getHostname () );
			$logger->debug ( 'Port=' . $this->getPort () );
			$logger->debug ( 'Client Id=' . $this->getClientId () );
			$logger->debug ( 'Client Secret=' . $this->getClientSecret () );
		}
	}
}