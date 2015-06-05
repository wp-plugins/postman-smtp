<?php
if (! interface_exists ( 'PostmanMailTransportConfiguration' )) {
	interface PostmanMailTransportConfiguration {
		function createConfig();
	}
}

if (! class_exists ( 'PostmanGeneralMailAuthenticator' )) {
	class PostmanGeneralMailAuthenticator implements PostmanMailTransportConfiguration {
		private $options;
		private $authToken;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken) {
			$this->options = $options;
			$this->authToken = $authToken;
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
	class PostmanOAuth2MailAuthenticator implements PostmanMailTransportConfiguration {
		private $options;
		private $authToken;
		private $logger;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken) {
			$this->options = $options;
			$this->authToken = $authToken;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		private function getEncryptionType() {
			return $this->options->getEncryptionType ();
		}
		private function getPort() {
			return $this->options->getPort ();
		}
		public function createConfig() {
			$senderEmail = $this->options->getSenderEmail ();
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			assert ( ! empty ( $senderEmail ) );
			$vendor = '';
			if (PostmanUtils::endsWith ( $this->options->getHostname (), 'yahoo.com' )) {
				// Yahoo Mail requires a Vendor - see http://imapclient.freshfoo.com/changeset/535%3A80ae438f4e4a/
				$vendor = sprintf ( "vendor=Postman SMTP %s\1", $pluginData ['version'] );
			}
			$initClientRequestEncoded = base64_encode ( sprintf ( "user=%s\1auth=Bearer %s\1%s\1", $senderEmail, $this->authToken->getAccessToken (), $vendor ) );
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
