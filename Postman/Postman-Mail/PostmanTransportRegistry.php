<?php
require_once 'PostmanZendMailTransportConfigurationFactory.php';

if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isServiceProviderGoogle($hostname);
		public function isServiceProviderMicrosoft($hostname);
		public function isServiceProviderYahoo($hostname);
		public function isTranscriptSupported();
		public function getSlug();
		public function getName();
		public function createZendMailTransport($hostname, $config);
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token);
		
		// @deprecated
		public function isOAuthUsed($authType);
		// @deprecated
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken);
		// @deprecated
		public function getConfigurationRecommendation($hostData);
		// @deprecated
		public function getHostsToTest($hostname);
	}
}

if (! class_exists ( 'PostmanTransportRegistry' )) {
	class PostmanTransportRegistry {
		private $transports;
		private $logger;
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
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
		 * A short-hand way of showing the complete delivery method
		 *
		 * @param PostmanTransport $transport        	
		 * @return string
		 */
		public function getPublicTransportUri(PostmanTransport $transport) {
			$options = PostmanOptions::getInstance ();
			$transportName = $transport->getSlug ();
			$auth = $transport->getAuthenticationType ( $options );
			$security = $transport->getSecurityType ( $options );
			$host = $transport->getHostname ( $options );
			$port = $transport->getHostPort ( $options );
			return sprintf ( '%s:%s:%s://%s:%s', $transportName, $security, $auth, $host, $port );
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
		 * @param PostmanOptions $options        	
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
		 * @param PostmanOptions $options        	
		 * @return boolean
		 */
		public function isRequestOAuthPermissionAllowed(PostmanOptions $options, PostmanOAuthToken $authToken) {
			// does the current transport use OAuth 2.0
			$oauthUsed = self::getCurrentTransport ()->isOAuthUsed ( $options->getAuthenticationType () );
			
			// is the transport configured
			$configured = self::getCurrentTransport ()->isConfigured ( $options, $authToken );
			
			return $oauthUsed && $configured;
		}
		
		/**
		 * Polls all the installed transports to get a complete list of sockets to probe for connectivity
		 *
		 * @param unknown $hostname        	
		 * @param unknown $isGmail        	
		 * @return multitype:
		 */
		public function getSocketsForSetupWizardToProbe($hostname, $isGmail) {
			$hosts = array ();
			foreach ( $this->getTransports () as $transport ) {
				$socketsToTest = $transport->getSocketsForSetupWizardToProbe ( $hostname, $isGmail );
				$this->logger->trace ( 'sockets to test:' );
				$this->logger->trace ( $socketsToTest );
				$hosts = array_merge ( $hosts, $socketsToTest );
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
		public function getRecommendation($hostData, $userAuthOverride, $originalSmtpServer) {
			
			//
			$priority = - 1;
			$winningRecommendation = null;
			$logger = new PostmanLogger ( get_class ( $this ) );
			$scrubbedUserAuthOverride = $this->scrubUserOverride ( $hostData, $userAuthOverride );
			foreach ( $this->getTransports () as $transport ) {
				$recommendation = $transport->getConfigurationBid ( $hostData, $scrubbedUserAuthOverride, $originalSmtpServer );
				$logger->debug ( sprintf ( 'Transport %s bid %s', $transport->getName (), $recommendation ['priority'] ) );
				if ($recommendation ['priority'] > $priority) {
					$priority = $recommendation ['priority'];
					$winningRecommendation = $recommendation;
				}
			}
			return $winningRecommendation;
		}
		private function scrubUserOverride($hostData, $userAuthOverride) {
			$this->logger->trace ( 'before scrubbing userAuthOverride: ' . $userAuthOverride );
			// validate the userAuthOverride
			$oauthIsAllowed = false;
			$passwordIsAllowed = false;
			$noneIsAllowed = false;
			if (! PostmanUtils::parseBoolean ( $hostData ['auth_xoauth'] )) {
				if ($userAuthOverride == 'oauth2') {
					$userAuthOverride = null;
				}
			}
			if (! PostmanUtils::parseBoolean ( $hostData ['auth_crammd5'] ) && ! PostmanUtils::parseBoolean ( $hostData ['auth_plain'] ) && ! PostmanUtils::parseBoolean ( $hostData ['auth_login'] )) {
				if ($userAuthOverride == 'password') {
					$userAuthOverride = null;
				}
			}
			if (! PostmanUtils::parseBoolean ( $hostData ['auth_none'] )) {
				if ($userAuthOverride == 'none') {
					$userAuthOverride = null;
				}
			}
			$this->logger->trace ( 'after scrubbing userAuthOverride: ' . $userAuthOverride );
			return $userAuthOverride;
		}
	}
}

if (! class_exists ( 'PostmanDummyTransport' )) {
	class PostmanDummyTransport implements PostmanTransport {
		const UNCONFIGURED = 'unconfigured';
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isServiceProviderGoogle($hostname) {
			return PostmanUtils::endsWith ( $hostname, 'gmail.com' );
		}
		public function isServiceProviderMicrosoft($hostname) {
			return PostmanUtils::endsWith ( $hostname, 'live.com' );
		}
		public function isServiceProviderYahoo($hostname) {
			return PostmanUtils::endsWith ( $hostname, 'yahoo.com' );
		}
		public function isOAuthUsed($authType) {
			return false;
		}
		public function isTranscriptSupported() {
			return false;
		}
		public function getSlug() {
		}
		public function getName() {
		}
		public function createZendMailTransport($hostname, $config) {
		}
		public function getDeliveryDetails(PostmanOptions $options) {
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		/**
		 *
		 * @deprecated
		 *
		 * @see PostmanTransport::getHostsToTest()
		 */
		public function getHostsToTest($hostname) {
		}
		public function getSocketsForSetupWizardToProbe($hostname, $isGmail) {
		}
		/**
		 *
		 * @deprecated
		 *
		 * @see PostmanTransport::getConfigurationRecommendation()
		 */
		public function getConfigurationRecommendation($hostData) {
		}
		public function getConfigurationBid($hostData, $originalSmtpServer) {
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			/* translators: where %s is the name of the transport (e.g. smtp) */
			return sprintf ( __ ( 'The selected transport \'%s\' is unavailable. The external plugin was probably deactivated.', 'postman-smtp' ), $options->getTransportType () );
		}
		/**
		 *
		 * @deprecated
		 *
		 * @see PostmanTransport::createPostmanMailAuthenticator()
		 */
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken) {
		}
	}
}
