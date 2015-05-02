<?php
if (! interface_exists ( 'PostmanMailTransportConfiguration' )) {
	interface PostmanMailTransportConfiguration {
		function createConfig();
		function getPluginVersion();
		function isPluginSenderNameEnforced();
		function isPluginSenderEmailEnforced();
	}
}

if (! class_exists ( 'PostmanMailTransportConfigurationFactory' )) {
	class PostmanMailTransportConfigurationFactory {
		private $logger;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanMailTransportConfigurationFactory ();
			}
			return $inst;
		}
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		public function createMailTransportConfiguration(PostmanTransport $transport, PostmanOptions $options, PostmanOAuthToken $authorizationToken) {
			return $transport->createPostmanMailAuthenticator ( $options, $authorizationToken );
		}
	}
}

if (! class_exists ( 'PostmanGeneralMailAuthenticator' )) {
	class PostmanGeneralMailAuthenticator implements PostmanMailTransportConfiguration {
		private $options;
		private $authToken;
		private $pluginVersion;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken, $pluginVersion) {
			assert ( isset ( $pluginVersion ) );
			$this->options = $options;
			$this->authToken = $authToken;
			$this->pluginVersion = $pluginVersion;
		}
		public function isPluginSenderNameEnforced() {
			return false;
		}
		public function isPluginSenderEmailEnforced() {
			return false;
		}
		public function getPluginVersion() {
			return $this->pluginVersion;
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
		private $pluginVersion;
		public function __construct(PostmanOptions $options, PostmanOAuthToken $authToken, $pluginVersion) {
			assert ( isset ( $pluginVersion ) );
			$this->options = $options;
			$this->authToken = $authToken;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->pluginVersion = $pluginVersion;
		}
		public function isPluginSenderNameEnforced() {
			return false;
		}
		public function isPluginSenderEmailEnforced() {
			return true;
		}
		private function getEncryptionType() {
			return $this->options->getEncryptionType ();
		}
		private function getPort() {
			return $this->options->getPort ();
		}
		public function getPluginVersion() {
			return $this->pluginVersion;
		}
		public function createConfig() {
			$initClientRequestEncoded = '';
			$senderEmail = $this->options->getSenderEmail ();
			assert ( ! empty ( $senderEmail ) );
			if (PostmanUtils::endsWith ( $this->options->getHostname (), 'yahoo.com' )) {
				// Yahoo Mail requires a Vendor - see http://imapclient.freshfoo.com/changeset/535%3A80ae438f4e4a/
				$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$this->authToken->getAccessToken()}\1vendor=Postman SMTP {$this->pluginVersion}\1\1" );
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
