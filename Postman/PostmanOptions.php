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
		const AUTHENTICATION_TYPE = 'authorization_type';
		const SENDER_EMAIL = 'sender_email';
		const SENDER_NAME = 'sender_name';
		const PORT = 'port';
		const HOSTNAME = 'hostname';
		const REPLY_TO = 'reply_to';
		const TEST_EMAIL = 'test_email';
		const BASIC_AUTH_USERNAME = 'basic_auth_username';
		const BASIC_AUTH_PASSWORD = 'basic_auth_password';
		const AUTHENTICATION_TYPE_NONE = 'none';
		const AUTHENTICATION_TYPE_BASIC_SSL = 'basic-ssl';
		const AUTHENTICATION_TYPE_BASIC_TLS = 'basic-tls';
		const AUTHENTICATION_TYPE_OAUTH2 = 'oauth2';
		const ALLOW_SENDER_NAME_OVERRIDE = 'allow_sender_name_override';
		
		// options data
		private $options;
		private $logger;
		
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
			$this->logger = new PostmanLogger ( 'PostmanOptions' );
		}
		//
		public function save() {
			update_option ( PostmanOptions::POSTMAN_OPTIONS, $this->options );
		}
		
		//
		public function isRequestOAuthPermissionAllowed() {
			$clientId = $this->getClientId ();
			$clientSecret = $this->getClientSecret ();
			$authType = $this->getAuthorizationType ();
			return $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 && ! empty ( $clientId ) && ! empty ( $clientSecret );
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
			if ($authType == PostmanOptions::AUTHENTICATION_TYPE_NONE) {
				return true;
			} else if ($authType == PostmanOptions::AUTHENTICATION_TYPE_BASIC_SSL || $authType == PostmanOptions::AUTHENTICATION_TYPE_BASIC_TLS) {
				return ! empty ( $username ) && ! empty ( $password );
			} else if ($authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2) {
				$accessToken = $token->getAccessToken ();
				$refreshToken = $token->getRefreshToken ();
				$senderEmail = $this->getSenderEmail ();
				return ! empty ( $accessToken ) && ! empty ( $refreshToken ) && ! empty ( $senderEmail );
			} else {
				$authType = null;
			}
		}
		public function isPermissionNeeded(PostmanAuthorizationToken $token) {
			$authType = $this->getAuthorizationType ();
			$hostname = $this->getHostname ();
			$port = $this->getPort ();
			$senderEmail = $this->getSenderEmail ();
			$clientId = $this->getClientId ();
			$clientSecret = $this->getClientSecret ();
			if ($authType != PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 || empty ( $hostname ) || empty ( $port ) || empty ( $senderEmail ) || empty ( $clientId ) || empty ( $clientSecret )) {
				$this->logger->debug ( 'authtype: ' . $authType );
				$this->logger->debug ( 'doesnt look like this is oatuh2' );
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
		public function getHostname() {
			if (isset ( $this->options [PostmanOptions::HOSTNAME] ))
				return $this->options [PostmanOptions::HOSTNAME];
		}
		public function getPort() {
			if (isset ( $this->options [PostmanOptions::PORT] ))
				return $this->options [PostmanOptions::PORT];
		}
		public function getSenderEmail() {
			if (isset ( $this->options [PostmanOptions::SENDER_EMAIL] ))
				return $this->options [PostmanOptions::SENDER_EMAIL];
		}
		public function getSenderName() {
			if (isset ( $this->options [PostmanOptions::SENDER_NAME] ))
				return $this->options [PostmanOptions::SENDER_NAME];
		}
		public function getClientId() {
			if (isset ( $this->options [PostmanOptions::CLIENT_ID] ))
				return $this->options [PostmanOptions::CLIENT_ID];
		}
		public function getClientSecret() {
			if (isset ( $this->options [PostmanOptions::CLIENT_SECRET] ))
				return $this->options [PostmanOptions::CLIENT_SECRET];
		}
		public function getAuthorizationType() {
			if (isset ( $this->options [PostmanOptions::AUTHENTICATION_TYPE] ))
				return $this->options [PostmanOptions::AUTHENTICATION_TYPE];
		}
		public function getUsername() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_USERNAME] ))
				return $this->options [PostmanOptions::BASIC_AUTH_USERNAME];
		}
		public function getPassword() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_PASSWORD] ))
				return $this->options [PostmanOptions::BASIC_AUTH_PASSWORD];
		}
		public function getReplyTo() {
			if (isset ( $this->options [PostmanOptions::REPLY_TO] ))
				return $this->options [PostmanOptions::REPLY_TO];
		}
		public function isSenderNameOverridePrevented() {
			if (isset ( $this->options [PostmanOptions::ALLOW_SENDER_NAME_OVERRIDE] ))
				return $this->options [PostmanOptions::ALLOW_SENDER_NAME_OVERRIDE];
		}
		public function setSenderNameOverrideAllowed($allow) {
			$this->options [PostmanOptions::ALLOW_SENDER_NAME_OVERRIDE] = $allow;
		}
		public function setHostname($hostname) {
			$this->options [PostmanOptions::HOSTNAME] = $hostname;
		}
		public function setHostnameIfEmpty($hostname) {
			if (! isset ( $this->options [PostmanOptions::HOSTNAME] )) {
				$this->setHostname ( $hostname );
			}
		}
		public function setPort($port) {
			$this->options [PostmanOptions::PORT] = $port;
		}
		public function setPortIfEmpty($port) {
			if (! isset ( $this->options [PostmanOptions::PORT] )) {
				$this->setPort ( $port );
			}
		}
		public function setSenderEmail($senderEmail) {
			$this->options [PostmanOptions::SENDER_EMAIL] = $senderEmail;
		}
		public function setSenderEmailIfEmpty($senderEmail) {
			if (! isset ( $this->options [PostmanOptions::SENDER_EMAIL] )) {
				$this->setSenderEmail ( $senderEmail );
			}
		}
		public function setSenderName($senderName) {
			$this->options [PostmanOptions::SENDER_NAME] = $senderName;
		}
		public function setSenderNameIfEmpty($senderName) {
			if (! isset ( $this->options [PostmanOptions::SENDER_NAME] )) {
				$this->setSenderName ( $senderName );
			}
		}
		public function setClientId($clientId) {
			$this->options [PostmanOptions::CLIENT_ID] = $clientId;
		}
		public function setClientSecret($clientSecret) {
			$this->options [PostmanOptions::CLIENT_SECRET] = $clientSecret;
		}
		public function setAuthorizationType($authType) {
			$this->options [PostmanOptions::AUTHENTICATION_TYPE] = $authType;
		}
		public function setAuthorizationTypeIfEmpty($authType) {
			if (! isset ( $this->options [PostmanOptions::AUTHENTICATION_TYPE] )) {
				$this->setAuthorizationType ( $authType );
			}
		}
		public function setUsername($username) {
			$this->options [PostmanOptions::BASIC_AUTH_USERNAME];
		}
		public function setPassword($password) {
			$this->options [PostmanOptions::BASIC_AUTH_PASSWORD];
		}
		public function setReplyTo($replyTo) {
			$this->options [PostmanOptions::REPLY_TO];
		}
		public function debug(PostmanLogger $logger) {
			$logger->debug ( 'Sender Email=' . $this->getSenderEmail () );
			$logger->debug ( 'Host=' . $this->getHostname () );
			$logger->debug ( 'Port=' . $this->getPort () );
			$logger->debug ( 'Client Id=' . $this->getClientId () );
			$logger->debug ( 'Client Secret=' . $this->getClientSecret () );
		}
	}
}