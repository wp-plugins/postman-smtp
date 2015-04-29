<?php
if (! class_exists ( "PostmanOptions" )) {
	require_once ('Postman-Common.php');
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 *
	 * Make sure these emails are permitted (see http://en.wikipedia.org/wiki/E-mail_address#Internationalization):
	 */
	class PostmanOptions implements PostmanOptionsInterface {
		
		// the option database name
		const POSTMAN_OPTIONS = 'postman_options';
		
		// the options fields
		const VERSION = 'version';
		const SENDER_EMAIL = 'sender_email';
		const SENDER_NAME = 'sender_name';
		const REPLY_TO = 'reply_to';
		const FORCED_TO_RECIPIENTS = 'forced_to';
		const FORCED_CC_RECIPIENTS = 'forced_cc';
		const FORCED_BCC_RECIPIENTS = 'forced_bcc';
		const ADDITIONAL_HEADERS = 'headers';
		const TEST_EMAIL = 'test_email';
		const HOSTNAME = 'hostname';
		const PORT = 'port';
		const TRANSPORT_TYPE = 'transport_type';
		const AUTHENTICATION_TYPE = 'auth_type';
		const AUTHENTICATION_TYPE_NONE = 'none';
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
		const PREVENT_SENDER_EMAIL_OVERRIDE = 'prevent_sender_email_override';
		const CONNECTION_TIMEOUT = 'connection_timeout';
		const READ_TIMEOUT = 'read_timeout';
		const LOG_LEVEL = 'log_level';
		const RUN_MODE = 'run_mode';
		const RUN_MODE_PRODUCTION = 'production';
		const RUN_MODE_LOG_ONLY = 'log_only';
		const RUN_MODE_IGNORE = 'ignore';
		const MAIL_LOG_ENABLED = 'mail_log_enabled';
		const MAIL_LOG_MAX_ENTRIES = 'mail_log_max_entries';
		
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
		public function isNew() {
			return ! isset ( $this->options [PostmanOptions::VERSION] );
		}
		public function isMailLoggingEnabled() {
			if ($this->isNew ())
				return true;
			if (isset ( $this->options [PostmanOptions::MAIL_LOG_ENABLED] ))
				return $this->options [PostmanOptions::MAIL_LOG_ENABLED];
			else
				return false;
		}
		public function getMailLoggingMaxEntries() {
			if (isset ( $this->options [PostmanOptions::MAIL_LOG_MAX_ENTRIES] ))
				return $this->options [PostmanOptions::MAIL_LOG_MAX_ENTRIES];
			else
				return 10;
		}
		public function getLogLevel() {
			if (isset ( $this->options [PostmanOptions::LOG_LEVEL] ))
				return $this->options [PostmanOptions::LOG_LEVEL];
			else
				return PostmanLogger::ERROR_INT;
		}
		public function getForcedToRecipients() {
			if (isset ( $this->options [self::FORCED_TO_RECIPIENTS] ))
				return $this->options [self::FORCED_TO_RECIPIENTS];
		}
		public function getForcedCcRecipients() {
			if (isset ( $this->options [self::FORCED_CC_RECIPIENTS] ))
				return $this->options [self::FORCED_CC_RECIPIENTS];
		}
		public function getForcedBccRecipients() {
			if (isset ( $this->options [self::FORCED_BCC_RECIPIENTS] ))
				return $this->options [self::FORCED_BCC_RECIPIENTS];
		}
		public function getAdditionalHeaders() {
			if (isset ( $this->options [self::ADDITIONAL_HEADERS] ))
				return $this->options [self::ADDITIONAL_HEADERS];
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
		public function getTransportType() {
			if (isset ( $this->options [PostmanOptions::TRANSPORT_TYPE] ))
				return $this->options [PostmanOptions::TRANSPORT_TYPE];
			else
				return PostmanSmtpTransport::SLUG;
		}
		public function getAuthenticationType() {
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
				return base64_decode ( $this->options [PostmanOptions::BASIC_AUTH_PASSWORD] );
		}
		public function getObfuscatedPassword() {
			return postmanObfuscatePassword ( $this->getPassword () );
		}
		public function getReplyTo() {
			if (isset ( $this->options [PostmanOptions::REPLY_TO] ))
				return $this->options [PostmanOptions::REPLY_TO];
		}
		public function getConnectionTimeout() {
			if (! empty ( $this->options [self::CONNECTION_TIMEOUT] ))
				return $this->options [self::CONNECTION_TIMEOUT];
			else
				return Postman::POSTMAN_TCP_CONNECTION_TIMEOUT;
		}
		public function getReadTimeout() {
			if (! empty ( $this->options [self::READ_TIMEOUT] ))
				return $this->options [self::READ_TIMEOUT];
			else
				return Postman::POSTMAN_TCP_READ_TIMEOUT;
		}
		public function isPluginSenderNameEnforced() {
			if ($this->isNew ())
				return true;
			if (isset ( $this->options [PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE] ))
				return $this->options [PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE];
		}
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanOptionsInterface::isSenderNameOverridePrevented()
		 * @deprecated by isPluginSenderNameEnforced
		 */
		public function isSenderNameOverridePrevented() {
			return $this->isPluginSenderEmailEnforced ();
		}
		public function isPluginSenderEmailEnforced() {
			if ($this->isNew ())
				return true;
			if (isset ( $this->options [PostmanOptions::PREVENT_SENDER_EMAIL_OVERRIDE] ))
				return $this->options [PostmanOptions::PREVENT_SENDER_EMAIL_OVERRIDE];
		}
		/**
		 *
		 * @deprecated by isPluginSenderEmailEnforced
		 */
		public function isSenderEmailOverridePrevented() {
			return $this->isPluginSenderEmailEnforced ();
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
		public function setTransportType($transportType) {
			$this->options [PostmanOptions::TRANSPORT_TYPE] = $transportType;
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
			$this->options [PostmanOptions::BASIC_AUTH_PASSWORD] = base64_encode ( $password );
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
		public function isAuthTypePassword() {
			return $this->isAuthTypeLogin () || $this->isAuthTypeCrammd5 () || $this->isAuthTypePlain ();
		}
		public function isAuthTypeOAuth2() {
			return PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 == $this->getAuthenticationType ();
		}
		public function isAuthTypeLogin() {
			return PostmanOptions::AUTHENTICATION_TYPE_LOGIN == $this->getAuthenticationType ();
		}
		public function isAuthTypePlain() {
			return PostmanOptions::AUTHENTICATION_TYPE_PLAIN == $this->getAuthenticationType ();
		}
		public function isAuthTypeCrammd5() {
			return PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 == $this->getAuthenticationType ();
		}
		public function isAuthTypeNone() {
			return PostmanOptions::AUTHENTICATION_TYPE_NONE == $this->getAuthenticationType ();
		}
	}
}