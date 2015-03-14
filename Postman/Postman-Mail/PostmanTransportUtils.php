<?php
if (! class_exists ( 'PostmanTransportUtils' )) {
	class PostmanTransportUtils {
		/**
		 * Retrieve a Transport by slug
		 * Look up a specific Transport use:
		 * A) when retrieving the transport saved in the database
		 * B) when querying what a theoretical scenario involving this transport is like
		 * (ie.for ajax in config screen)
		 *
		 * @param unknown $slug        	
		 */
		public static function getTransport($slug) {
			$directory = PostmanTransportDirectory::getInstance ();
			$transports = $directory->getTransports ();
			if (isset ( $transports [$slug] )) {
				return $transports [$slug];
			}
		}
		public static function getDeliveryUri(PostmanTransport $transport) {
			if (! method_exists ( $transport, 'getVersion' )) {
				return 'undefined';
			} else {
				$options = PostmanOptions::getInstance ();
				$transportName = $transport->getSlug ();
				$auth = $transport->getAuthenticationType ( $options );
				$security = $transport->getSecurityType ( $options );
				$user = postmanObfuscateEmail ( $transport->getCredentialsId ( $options ) );
				$pass = postmanObfuscatePassword ( $transport->getCredentialsSecret ( $options ) );
				$host = $transport->getHostname ( $options );
				$port = $transport->getHostPort ( $options );
				return sprintf ( '%s:%s:%s://%s:%s@%s:%s', $transportName, $security, $auth, $user, $pass, $host, $port );
			}
		}
		/**
		 * Determine if a specific transport is registered in the directory.
		 *
		 * @param unknown $slug        	
		 */
		public static function isRegistered($slug) {
			$directory = PostmanTransportDirectory::getInstance ();
			$transports = $directory->getTransports ();
			return isset ( $transports [$slug] );
		}
		
		/**
		 * Retrieve the transport Postman is currently configured with.
		 *
		 * @return PostmanDummyTransport|PostmanTransport
		 */
		public static function getCurrentTransport() {
			$transportType = PostmanOptions::getInstance ()->getTransportType ();
			$transports = PostmanTransportDirectory::getInstance ()->getTransports ();
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
		public static function isPostmanReadyToSendEmail(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			$directory = PostmanTransportDirectory::getInstance ();
			$selectedTransport = $options->getTransportType ();
			foreach ( $directory->getTransports () as $transport ) {
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
		public static function isRequestOAuthPermissionAllowed(PostmanOptionsInterface $options, PostmanOAuthTokenInterface $authToken) {
			// does the current transport use OAuth 2.0
			$oauthUsed = self::getCurrentTransport ()->isOAuthUsed ( $options->getAuthenticationType () );
			
			// is the transport configured
			$configured = self::getCurrentTransport ()->isConfigured ( $options, $authToken );
			
			return $oauthUsed && $configured;
		}
		public static function getHostsToTest($hostname) {
			$directory = PostmanTransportDirectory::getInstance ();
			$hosts = array ();
			foreach ( $directory->getTransports () as $transport ) {
				$hosts = array_merge ( $hosts, $transport->getHostsToTest ( $hostname ) );
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
		public static function getConfigurationRecommendation($hostData) {
			$directory = PostmanTransportDirectory::getInstance ();
			$priority = - 1;
			$winningRecommendation = null;
			$logger = new PostmanLogger ( 'PostmanTransportUtils' );
			foreach ( $directory->getTransports () as $transport ) {
				$recommendation = $transport->getConfigurationRecommendation ( $hostData );
				if ($recommendation) {
					$logger->debug ( sprintf ( 'Got a recommendation: [%d] %s', $recommendation ['priority'], $recommendation ['message'] ) );
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

