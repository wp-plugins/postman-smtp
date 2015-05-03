<?php
if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isServiceProviderGoogle($hostname);
		public function isServiceProviderMicrosoft($hostname);
		public function isServiceProviderYahoo($hostname);
		public function isOAuthUsed($authType);
		public function isTranscriptSupported();
		public function getSlug();
		public function getName();
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken);
		public function createZendMailTransport($hostname, $config);
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getConfigurationRecommendation($hostData);
		public function getHostsToTest($hostname, $isGmail);
	}
}

if (! class_exists ( 'PostmanTransportRegistry' )) {
	class PostmanTransportRegistry {
		private $transports;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanTransportRegistry ();
			}
			return $inst;
		}
		public function registerTransport(PostmanTransport $instance) {
			$this->transports [$instance->getSlug ()] = $instance;
		}
		public function getTransports() {
			return $this->transports;
		}
		
		/**
		 * Retrieve a Transport by slug
		 * Look up a specific Transport use:
		 * A) when retrieving the transport saved in the database
		 * B) when querying what a theoretical scenario involving this transport is like
		 * (ie.for ajax in config screen)
		 *
		 * @param unknown $slug        	
		 */
		public function getTransport($slug) {
			$transports = $this->getTransports ();
			if (isset ( $transports [$slug] )) {
				return $transports [$slug];
			}
		}
		/**
		 *
		 * @deprecated by getTransportUri()
		 * @param PostmanTransport $transport        	
		 * @return string
		 */
		public function getDeliveryUri(PostmanTransport $transport) {
			return $this->getSecretTransportUri ( $transport, true, true );
		}
		public function getSecretTransportUri(PostmanTransport $transport, $obscureUsername = false, $obscurePassword = true) {
			if (! method_exists ( $transport, 'getVersion' )) {
				return 'undefined';
			} else {
				$options = PostmanOptions::getInstance ();
				$transportName = $transport->getSlug ();
				$auth = $transport->getAuthenticationType ( $options );
				$security = $transport->getSecurityType ( $options );
				if ($obscureUsername) {
					$user = postmanObfuscateEmail ( $transport->getCredentialsId ( $options ) );
				} else {
					$user = $transport->getCredentialsId ( $options );
				}
				if ($obscurePassword) {
					$pass = PostmanUtils::obfuscatePassword ( $transport->getCredentialsSecret ( $options ) );
				} else {
					$pass = $transport->getCredentialsSecret ( $options );
				}
				$host = $transport->getHostname ( $options );
				$port = $transport->getHostPort ( $options );
				return sprintf ( '%s:%s:%s://%s:%s@%s:%s', $transportName, $security, $auth, $user, $pass, $host, $port );
			}
		}
		public function getPublicTransportUri(PostmanTransport $transport) {
			if (! method_exists ( $transport, 'getVersion' )) {
				return 'undefined';
			} else {
				$options = PostmanOptions::getInstance ();
				$transportName = $transport->getSlug ();
				$auth = $transport->getAuthenticationType ( $options );
				$security = $transport->getSecurityType ( $options );
				$host = $transport->getHostname ( $options );
				$port = $transport->getHostPort ( $options );
				return sprintf ( '%s:%s:%s://%s:%s', $transportName, $security, $auth, $host, $port );
			}
		}
		/**
		 * Determine if a specific transport is registered in the directory.
		 *
		 * @param unknown $slug        	
		 */
		public function isRegistered($slug) {
			$transports = $this->getTransports ();
			return isset ( $transports [$slug] );
		}
		
		/**
		 * Retrieve the transport Postman is currently configured with.
		 *
		 * @return PostmanDummyTransport|PostmanTransport
		 */
		public function getCurrentTransport() {
			$transportType = PostmanOptions::getInstance ()->getTransportType ();
			$transports = $this->getTransports ();
			if (! isset ( $transports [$transportType] )) {
				// the dummy transport is usefor for specific error messages when no transport is loaded
				return new PostmanDummyTransport ();
			} else {
				return $transports [$transportType];
			}
		}
		/**
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isPostmanReadyToSendEmail(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			$selectedTransport = $options->getTransportType ();
			foreach ( $this->getTransports () as $transport ) {
				if ($transport->getSlug () == $selectedTransport && $transport->isReady ( $options, $token )) {
					return true;
				}
			}
			return false;
		}
		
		/**
		 * Determine whether to show the Request Permission link on the main menu
		 *
		 * This link is displayed if
		 * 1. the current transport requires OAuth 2.0
		 * 2. the transport is properly configured
		 * 3. we have a valid Client ID and Client Secret without an Auth Token
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @return boolean
		 */
		public function isRequestOAuthPermissionAllowed(PostmanOptionsInterface $options, PostmanOAuthTokenInterface $authToken) {
			// does the current transport use OAuth 2.0
			$oauthUsed = self::getCurrentTransport ()->isOAuthUsed ( $options->getAuthenticationType () );
			
			// is the transport configured
			$configured = self::getCurrentTransport ()->isConfigured ( $options, $authToken );
			
			return $oauthUsed && $configured;
		}
		public function getHostsToTest($hostname, $isGmail) {
			$hosts = array ();
			foreach ( $this->getTransports () as $transport ) {
				$hosts = array_merge ( $hosts, $transport->getHostsToTest ( $hostname, $isGmail ) );
			}
			return $hosts;
		}
		
		/**
		 * If the host port is a possible configuration option, recommend it
		 *
		 * $hostData includes ['host'] and ['port']
		 *
		 * response should include ['success'], ['message'], ['priority']
		 *
		 * @param unknown $hostData        	
		 */
		public function getConfigurationBid($connectivityTestResults, $userAuthPreference = '') {
			$hostData ['host'] = $connectivityTestResults ['hostname'];
			$hostData ['port'] = $connectivityTestResults ['port'];
			$hostData ['protocol'] = $connectivityTestResults ['protocol'];
			$hostData ['start_tls'] = $connectivityTestResults ['start_tls'];
			
			// write all the auth data
			$hostData ['auth_xoauth'] = $connectivityTestResults ['auth_xoauth'];
			$hostData ['auth_plain'] = $connectivityTestResults ['auth_plain'];
			$hostData ['auth_login'] = $connectivityTestResults ['auth_login'];
			$hostData ['auth_crammd5'] = $connectivityTestResults ['auth_crammd5'];
			$hostData ['auth_none'] = $connectivityTestResults ['auth_none'];
			$hostData ['id'] = $connectivityTestResults ['reported_hostname_domain_only'];
			// filter for user preference (remove select auth data)
			if ($userAuthPreference == 'oauth2') {
				$hostData ['auth_plain'] = null;
				$hostData ['auth_login'] = null;
				$hostData ['auth_crammd5'] = null;
			}
			if ($userAuthPreference == 'password') {
				$hostData ['auth_xoauth'] = null;
			}
			
			//
			$priority = - 1;
			$winningRecommendation = null;
			$logger = new PostmanLogger ( get_class ( $this ) );
			foreach ( $this->getTransports () as $transport ) {
				$logger->debug ( sprintf ( 'Asking transport %s to bid on: %s:%s', $transport->getName (), $hostData ['host'], $hostData ['port'] ) );
				$recommendation = $transport->getConfigurationRecommendation ( $hostData );
				if ($recommendation) {
					if ($recommendation ['priority'] > $priority) {
						$priority = $recommendation ['priority'];
						$winningRecommendation = $recommendation;
					}
				}
			}
			return $winningRecommendation;
		}
	}
}

