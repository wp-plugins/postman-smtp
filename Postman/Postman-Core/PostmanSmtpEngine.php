<?php
if (! interface_exists ( "PostmanSmtpEngine" )) {
	interface PostmanSmtpEngine {
		
		// constants
		const ZEND_TRANSPORT_CONFIG_SSL = 'ssl';
		const ZEND_TRANSPORT_CONFIG_TLS = 'tls';
		const ZEND_TRANSPORT_CONFIG_PORT = 'port';
		
		/**
		 * Send a message.
		 */
		public function send();
	}
}
