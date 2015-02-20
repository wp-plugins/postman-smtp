<?php
if (! class_exists ( 'PostmanTransportDirectory' )) {
	
	class PostmanTransportDirectory {
		private $transports;
		private $logger;
		/**
		 * private constructor
		 */
		private function __construct() {
			// add the default Transport
			$this->logger = new PostmanLogger ( 'PostmanTransportDirectory' );
			$this->registerTransport ( new PostmanSmtpTransport () );
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
		public function getCurrentTransport() {
			$transportType = PostmanOptions::getInstance ()->getTransportType ();
			$transport = $this->getTransport ( $transportType );
			if (! $transport) {
				$this->logger->error ( 'Could not load transport \'' . $transportType . '\'' );
				return new PostmanSmtpTransport ();
			}
			return $transport;
		}
		/**
		 * Retrieve a Transport by slug
		 * Look up a specific Transport, normally used only when retrieving the transport saved in the database
		 *
		 * @param unknown $slug        	
		 */
		private function getTransport($slug) {
			$this->logger->debug ( 'Looking for transport ' . $slug );
			if (isset ( $this->transports [$slug] )) {
				return $this->transports [$slug];
			}
		}
	}
}