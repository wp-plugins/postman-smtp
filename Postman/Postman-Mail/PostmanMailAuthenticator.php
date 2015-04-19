<?php
if (! interface_exists ( 'PostmanMailAuthenticator' )) {
	interface PostmanMailAuthenticator {
		function createConfig();
		function isSenderNameOverridePrevented();
		function isSenderEmailOverridePrevented();
	}
}

if (! class_exists ( 'PostmanGeneralMailAuthenticator' )) {
	class PostmanGeneralMailAuthenticator implements PostmanMailAuthenticator {
		private $options;
		private $authToken;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken) {
			$this->options = $options;
			$this->authToken = $authToken;
		}
		public function isSenderNameOverridePrevented() {
			return false;
		}
		public function isSenderEmailOverridePrevented() {
			return false;
		}
		public function createConfig() {
			$logger = new PostmanLogger ( get_class ( $this ) );
			$config = array (
					'port' => $this->options->getPort () 
			);
			$logger->debug ( sprintf ( 'Using %s:%s ', $this->options->getHostname (), $config ['port'] ) );
			if ($this->options->getEncryptionType () != PostmanOptions::ENCRYPTION_TYPE_NONE) {
				$config ['ssl'] = $this->options->getEncryptionType ();
				$logger->debug ( 'Using encryption ' . $config ['ssl'] );
			} else {
				$logger->debug ( 'Using no encryption' );
			}
			if ($this->options->getAuthenticationType () != PostmanOptions::AUTHENTICATION_TYPE_NONE) {
				$config ['auth'] = $this->options->getAuthenticationType ();
				$config ['username'] = $this->options->getUsername ();
				$config ['password'] = $this->options->getPassword ();
				$logger->debug ( sprintf ( 'Using auth %s with username %s and password %s ', $config ['auth'], $config ['username'], $this->options->getObfuscatedPassword () ) );
			} else {
				$logger->debug ( 'Using no authentication' );
			}
			return $config;
		}
	}
}

if (! class_exists ( 'PostmanOAuth2MailAuthenticator' )) {
	class PostmanOAuth2MailAuthenticator implements PostmanMailAuthenticator {
		private $options;
		private $authToken;
		private $logger;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken) {
			$this->options = $options;
			$this->authToken = $authToken;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		public function isSenderNameOverridePrevented() {
			return false;
		}
		public function isSenderEmailOverridePrevented() {
			return true;
		}
		private function getEncryptionType() {
			return $this->options->getEncryptionType ();
		}
		private function getPort() {
			return $this->options->getPort ();
		}
		public function createConfig() {
			$version = POSTMAN_PLUGIN_VERSION;
			$initClientRequestEncoded = '';
			$senderEmail = $this->options->getSenderEmail ();
			assert ( ! empty ( $senderEmail ) );
			if (endsWith ( $this->options->getHostname (), 'yahoo.com' )) {
				// Yahoo Mail requires a Vendor - see http://imapclient.freshfoo.com/changeset/535%3A80ae438f4e4a/
				$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$this->authToken->getAccessToken()}\1vendor=Postman SMTP {$version}\1\1" );
			} else {
				$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$this->authToken->getAccessToken()}\1\1" );
			}
			assert ( ! empty ( $initClientRequestEncoded ) );
			$config = array (
					'ssl' => $this->getEncryptionType (),
					'port' => $this->getPort (),
					'auth' => $this->options->getAuthenticationType (),
					'xoauth2_request' => $initClientRequestEncoded 
			);
			$this->logger->debug ( sprintf ( 'Using auth %s with encryption %s to %s:%s ', $config ['auth'], $config ['ssl'], $this->options->getHostname (), $this->getPort () ) );
			return $config;
		}
	}
}
