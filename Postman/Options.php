<?php

namespace Postman {

	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class Options {

		const CLIENT_ID = 'oauth_client_id';
		const CLIENT_SECRET = 'oauth_client_secret';
		const REFRESH_TOKEN = 'refresh_token';
		const TOKEN_EXPIRES = 'auth_token_expires';
		const ACCESS_TOKEN = 'access_token';
		
		const SMTP_TYPE = 'smtp_type';
		const SENDER_EMAIL = 'sender_email';
		const PORT = "port";
		const HOSTNAME = "hostname";
		const TEST_EMAIL = 'test_email';
						
		private $hostname;
		private $port;
		private $senderEmail;
		private $testEmail;
		private $smtpType;
		
		/**
		 * Constructor
		 */
		public function __construct() {
			$options = get_option ( POSTMAN_OPTIONS );
			$this->loadFromOptions ( $options );
		}
		
		/**
		 * Set state based on $options
		 * 
		 * @param unknown $options        	
		 */
		public function loadFromOptions($options) {
			$this->hostname = $options [Options::HOSTNAME];
			$this->port = $options [Options::PORT];
			$this->senderEmail = $options [Options::SENDER_EMAIL];
			$this->testEmail = $options[Options::TEST_EMAIL];
			$this->smtpType = $options[Options::SMTP_TYPE];
		}
		
		public function getHostname() {
			return $this->hostname;
		}
		
		public function getPort() {
			return $this->port;
		}
		
		public function getSenderEmail() {
			return $this->senderEmail;
		}
		
		public function getTestEmail() {
			return $this->testEmail;
		}
		
		public function getSmtpType() {
			return $this->smtpType;
		}
	}
}