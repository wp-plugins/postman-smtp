<?php
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
		public static function getCurrentTransport() {
			$transportType = PostmanOptions::getInstance ()->getTransportType ();
			$transports = PostmanTransportDirectory::getInstance ()->getTransports ();
			if (! isset ( $transports [$transportType] )) {
				return new PostmanSmtpTransport ();
			} else {
				return $transports [$transportType];
			}
		}
	}
}