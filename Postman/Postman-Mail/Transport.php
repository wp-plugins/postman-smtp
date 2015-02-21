<?php
if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isSmtp();
		public function isGoogleOAuthRequired();
		public function isTranscriptSupported();
		public function getSlug();
		public function getName();
		public function createZendMailTransport($hostname, $config);
		public function isConfigured(PostmanOptions $options, PostmanOAuthToken $token);
	}
}

if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
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
			return _x ( 'SMTP', 'Transport Name' );
		}
		public function createZendMailTransport($hostname, $config) {
			return new Zend_Mail_Transport_Smtp ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptions $options) {
			return $this->getName () . ' (' . $options->getHostname () . ':' . $options->getPort () . ')';
		}
		public function isConfigured(PostmanOptions $options, PostmanOAuthToken $token) {
			return true;
		}
	}
}

