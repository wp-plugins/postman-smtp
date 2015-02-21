<?php
if (! class_exists ( "PostmanLogger" )) {
	
	require_once 'PostmanOptions.php';
	
	//
	class PostmanLogger {
		const ALL_INT = - 2147483648;
		const DEBUG_INT = 10000;
		const ERROR_INT = 40000;
		const FATAL_INT = 50000;
		const INFO_INT = 20000;
		const OFF_INT = 2147483647;
		const WARN_INT = 30000;
		private $name;
		private $logLevel;
		function __construct($name) {
			$this->name = $name;
			$this->logLevel = PostmanOptions::getInstance ()->getLogLevel ();
		}
		function debug($text) {
			if (self::DEBUG_INT >= $this->logLevel) {
				error_log ( 'DEBUG ' . $this->name . ': ' . $text );
			}
		}
		function error($text) {
			if (self::ERROR_INT >= $this->logLevel) {
				error_log ( 'ERROR ' . $this->name . ': ' . $text );
			}
		}
	}
}

if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isSmtp();
		public function isGoogleOAuthRequired();
		public function isTranscriptSupported();
		public function getSlug();
		public function getName();
		public function createZendMailTransport($hostname, $config);
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getMisconfigurationMessage(PostmanOptionsInterface $options, PostmanOAuthToken $token);
	}
}

if (! class_exists ( 'PostmanTransportDirectory' )) {
	class PostmanTransportDirectory {
		private $transports;
		private $logger;
		/**
		 * private constructor
		 */
		private function __construct() {
			// add the default Transport
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanTransportDirectory ();
			}
			return $inst;
		}
		public function registerTransport(PostmanTransport $instance) {
			$this->logger->debug ( 'Registering ' . $instance->getName () . ' transport as ' . $instance->getSlug () );
			$this->transports [$instance->getSlug ()] = $instance;
		}
		public function isRegistered($slug) {
			return isset ( $this->transports [$slug] );
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
			$this->logger->debug ( 'Looking for transport ' . $slug );
			if (isset ( $this->transports [$slug] )) {
				return $this->transports [$slug];
			}
		}
	}
}

