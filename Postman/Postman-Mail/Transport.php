<?php
if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isSmtp() {
			return true;
		}
		public function isGoogleOAuthRequired() {
			return false;
		}
		public function isTranscriptSupported() {
			return true;
		}
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'SMTP', 'Transport Name', 'postman-smtp' );
		}
		public function createZendMailTransport($hostname, $config) {
			return new Zend_Mail_Transport_Smtp ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
			return $this->getName () . ' (' . $options->getHostname () . ':' . $options->getPort () . ')';
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			$configured = true;
			$configured &= false;
			$this->logger->debug ( 'isConfigured ' . $configured );
			return $configured;
		}
		public function getMisconfigurationMessage(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return 'oops';
		}
	}
}

if (! class_exists ( 'PostmanTransportUtils' )) {
	class PostmanTransportUtils {
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
	}
}