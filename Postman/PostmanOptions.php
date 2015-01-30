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
		const SMTP_TYPE = 'smtp_type';
		const SENDER_EMAIL = 'sender_email';
		const PORT = 'port';
		const HOSTNAME = 'hostname';
		const TEST_EMAIL = 'test_email';
		
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
		private function __construct() {
			$this->options = get_option ( PostmanOptions::POSTMAN_OPTIONS );
		}
		//
		public function isRequestOAuthPermissiongAllowed() {
			$clientId = $this->getClientId ();
			$clientSecret = $this->getClientSecret ();
			return ! empty ( $clientId ) && ! empty ( $haveClientSecret );
		}
		public function isSendingEmailAllowed(PostmanAuthorizationToken $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			$senderEmail = $this->getSenderEmail ();
			return ! empty ( $accessToken ) && ! empty ( $refreshToken ) && ! empty ( $senderEmail );
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
		public function getClientId() {
			if (isset ( $this->options [PostmanOptions::CLIENT_ID] ))
				return $this->options [PostmanOptions::CLIENT_ID];
		}
		public function getClientSecret() {
			if (isset ( $this->options [PostmanOptions::CLIENT_SECRET] ))
				return $this->options [PostmanOptions::CLIENT_SECRET];
		}
		public function getSmtpType() {
			if (isset ( $this->options [PostmanOptions::SMTP_TYPE] ))
				return $this->options [PostmanOptions::SMTP_TYPE];
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
		public function setClientId($clientId) {
			$this->options [PostmanOptions::CLIENT_ID] = $clientId;
		}
		public function setClientSecret($clientSecret) {
			$this->options [PostmanOptions::CLIENT_SECRET] = $clientSecret;
		}
		public function setSmtpType($smtpType) {
			$this->options [PostmanOptions::SMTP_TYPE] = $smtpType;
		}
		public function setSmtpTypeIfEmpty($smtpType) {
			if (! isset ( $this->options [PostmanOptions::SMTP_TYPE] )) {
				$this->setSmtpType ( $smtpType );
			}
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