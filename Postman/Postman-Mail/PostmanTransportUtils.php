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
		public static function getConfigurationBid($connectivityTestResults, $userAuthPreference = '') {
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
			$directory = PostmanTransportDirectory::getInstance ();
			$priority = - 1;
			$winningRecommendation = null;
			$logger = new PostmanLogger ( 'PostmanTransportUtils' );
			foreach ( $directory->getTransports () as $transport ) {
				$logger->debug ( sprintf ( 'Asking transport %s to bid on: %s:%s', $transport->getName (), $hostData ['host'], $hostData ['port'] ) );
				$recommendation = $transport->getConfigurationRecommendation ( $hostData );
				if ($recommendation) {
					if ($recommendation ['priority'] > $priority) {
						$priority = $recommendation ['priority'];
						$winningRecommendation = $recommendation;
					}
				}
			}
			// TODO remove this sometime
			// for some reason i coded Gmail API Transport <= 1.0.0 that 'auth' is null??? wtf
			if ($winningRecommendation ['transport'] == 'gmail_api') {
				$winningRecommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
			}
			return $winningRecommendation;
		}
	}
}

