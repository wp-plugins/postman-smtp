<?php
if (! class_exists ( "PostmanOptions" )) {
	
	require_once 'Postman-Auth/PostmanSmtpHostProperties.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanOptions {
		// the option database name
		const POSTMAN_OPTIONS = 'postman_options';
		
		// the options fields
		const SENDER_EMAIL = 'sender_email';
		const SENDER_NAME = 'sender_name';
		const REPLY_TO = 'reply_to';
		const TEST_EMAIL = 'test_email';
		const HOSTNAME = 'hostname';
		const PORT = 'port';
		const AUTHENTICATION_TYPE = 'auth_type';
		const AUTHENTICATION_TYPE_NONE = 'none';
		const AUTHENTICATION_TYPE_PASSWORD = 'password';
		const AUTHENTICATION_TYPE_PLAIN = 'plain';
		const AUTHENTICATION_TYPE_LOGIN = 'login';
		const AUTHENTICATION_TYPE_CRAMMD5 = 'crammd5';
		const AUTHENTICATION_TYPE_OAUTH2 = 'oauth2';
		const ENCRYPTION_TYPE = 'enc_type';
		const ENCRYPTION_TYPE_NONE = 'none';
		const ENCRYPTION_TYPE_SSL = 'ssl';
		const ENCRYPTION_TYPE_TLS = 'tls';
		const CLIENT_ID = 'oauth_client_id';
		const CLIENT_SECRET = 'oauth_client_secret';
		const BASIC_AUTH_USERNAME = 'basic_auth_username';
		const BASIC_AUTH_PASSWORD = 'basic_auth_password';
		const PREVENT_SENDER_NAME_OVERRIDE = 'prevent_sender_name_override';
		const CONNECTION_TIMEOUT = 'connection_timeout';
		const READ_TIMEOUT = 'read_timeout';
		
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
			$this->logger = new PostmanLogger ( get_class ( $this ) );
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
			} else if ($authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN || $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN) {
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
			if (! $this->isAuthTypeOAuth2 () || empty ( $hostname ) || empty ( $port ) || empty ( $senderEmail ) || empty ( $clientId ) || empty ( $clientSecret )) {
				$this->logger->debug ( 'authtype: ' . $authType );
				$this->logger->debug ( 'doesnt look like this is OAuth 2.0' );
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
		public function getEncryptionType() {
			if (isset ( $this->options [PostmanOptions::ENCRYPTION_TYPE] ))
				return $this->options [PostmanOptions::ENCRYPTION_TYPE];
		}
		public function getUsername() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_USERNAME] ))
				return $this->options [PostmanOptions::BASIC_AUTH_USERNAME];
		}
		public function getPassword() {
			if (isset ( $this->options [PostmanOptions::BASIC_AUTH_PASSWORD] ))
				return base64_decode($this->options [PostmanOptions::BASIC_AUTH_PASSWORD]);
		}
		public function getReplyTo() {
			if (isset ( $this->options [PostmanOptions::REPLY_TO] ))
				return $this->options [PostmanOptions::REPLY_TO];
		}
		public function getConnectionTimeout() {
			if (! empty ( $this->options [self::CONNECTION_TIMEOUT] ))
				return $this->options [self::CONNECTION_TIMEOUT];
			else
				return PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT;
		}
		public function getReadTimeout() {
			if (! empty ( $this->options [self::READ_TIMEOUT] ))
				return $this->options [self::READ_TIMEOUT];
			else
				return PostmanMain::POSTMAN_TCP_READ_TIMEOUT;
		}
		public function isSenderNameOverridePrevented() {
			if (isset ( $this->options [PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE] ))
				return $this->options [PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE];
		}
		public function setSenderNameOverridePrevented($prevent) {
			$this->options [PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE] = $prevent;
		}
		public function setHostname($hostname) {
			$this->options [PostmanOptions::HOSTNAME] = $hostname;
		}
		public function setHostnameIfEmpty($hostname) {
			if (empty ( $this->options [PostmanOptions::HOSTNAME] )) {
				$this->setHostname ( $hostname );
			}
		}
		public function setPort($port) {
			$this->options [PostmanOptions::PORT] = $port;
		}
		public function setPortIfEmpty($port) {
			if (empty ( $this->options [PostmanOptions::PORT] )) {
				$this->setPort ( $port );
			}
		}
		public function setSenderEmail($senderEmail) {
			$this->options [PostmanOptions::SENDER_EMAIL] = $senderEmail;
		}
		public function setSenderEmailIfEmpty($senderEmail) {
			if (empty ( $this->options [PostmanOptions::SENDER_EMAIL] )) {
				$this->setSenderEmail ( $senderEmail );
			}
		}
		public function setSenderName($senderName) {
			$this->options [PostmanOptions::SENDER_NAME] = $senderName;
		}
		public function setSenderNameIfEmpty($senderName) {
			if (empty ( $this->options [PostmanOptions::SENDER_NAME] )) {
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
		public function setEncryptionType($encType) {
			$this->options [PostmanOptions::ENCRYPTION_TYPE] = $encType;
		}
		public function setUsername($username) {
			$this->options [PostmanOptions::BASIC_AUTH_USERNAME] = $username;
		}
		public function setPassword($password) {
			$this->options [PostmanOptions::BASIC_AUTH_PASSWORD] = base64_encode($password);
		}
		public function setReplyTo($replyTo) {
			$this->options [PostmanOptions::REPLY_TO] = $replyTo;
		}
		public function setConnectionTimeout($seconds) {
			$this->options [self::CONNECTION_TIMEOUT] = $seconds;
		}
		public function setReadTimeout($seconds) {
			$this->options [self::READ_TIMEOUT] = $seconds;
		}
		public function setConnectionTimeoutIfEmpty($seconds) {
			if (! isset ( $this->options [self::CONNECTION_TIMEOUT] )) {
				$this->setConnectionTimeout ( $seconds );
			}
		}
		public function setReadTimeoutIfEmpty($seconds) {
			if (! isset ( $this->options [self::READ_TIMEOUT] )) {
				$this->setReadTimeout ( $seconds );
			}
		}
		public function debug(PostmanLogger $logger) {
			$logger->debug ( 'Sender Email=' . $this->getSenderEmail () );
			$logger->debug ( 'Host=' . $this->getHostname () );
			$logger->debug ( 'Port=' . $this->getPort () );
			$logger->debug ( 'Client Id=' . $this->getClientId () );
			$logger->debug ( 'Client Secret=' . $this->getClientSecret () );
		}
		public function isAuthTypeOAuth2() {
			return PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 == $this->getAuthorizationType ();
		}
		public function isAuthTypeLogin() {
			return PostmanOptions::AUTHENTICATION_TYPE_LOGIN == $this->getAuthorizationType ();
		}
		public function isAuthTypeNone() {
			return PostmanOptions::AUTHENTICATION_TYPE_NONE == $this->getAuthorizationType ();
		}
		public function isSmtpHostGmail() {
			return PostmanSmtpHostProperties::isGmail ( $this->getHostname () );
		}
		public function isSmtpHostHotmail() {
			return PostmanSmtpHostProperties::isHotmail ( $this->getHostname () );
		}
	}
}