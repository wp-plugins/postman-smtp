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
		public static function isPostmanConfiguredToSendEmail(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			$directory = PostmanTransportDirectory::getInstance ();
			$selectedTransport = $options->getTransportType ();
			foreach ( $directory->getTransports () as $transport ) {
				if ($transport->getSlug () == $selectedTransport && $transport->isConfigured ( $options, $token )) {
					return true;
				}
			}
			return false;
		}
		public static function isOAuthRequired(PostmanTransport $transport, $hostname) {
			return $transport->isGoogleOAuthRequired ( $hostname ) || $transport->isMicrosoftOAuthRequired ( $hostname ) || $transport->isYahooOAuthRequired ( $hostname );
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
		public function isSmtp() {
			return false;
		}
		public function isGoogleOAuthRequired($hostname) {
			return false;
		}
		public function isMicrosoftOAuthRequired($hostname) {
			return false;
		}
		public function isYahooOAuthRequired($hostname) {
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
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function getMisconfigurationMessage(PostmanOAuthHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
		}
	}
}

