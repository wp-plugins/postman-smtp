<?php
if (! class_exists ( 'PostmanInputSanitizer' )) {
	class PostmanInputSanitizer {
		private $logger;
		private $options;
		const VALIDATION_SUCCESS = 'validation_success';
		const VALIDATION_FAILED = 'validation_failed';
		public function __construct(PostmanOptions $options) {
			assert ( isset ( $options ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
		}
		
		/**
		 * Sanitize each setting field as needed
		 *
		 * @param array $input
		 *        	Contains all settings fields as array keys
		 */
		public function sanitize($input) {
			$this->logger->debug ( "Sanitizing data before storage" );
			
			$new_input = array ();
			$success = true;
			
			$this->sanitizeString ( 'Encryption Type', PostmanOptions::ENCRYPTION_TYPE, $input, $new_input );
			$this->sanitizeString ( 'Hostname', PostmanOptions::HOSTNAME, $input, $new_input );
			if (! empty ( $input [PostmanOptions::PORT] )) {
				$port = absint ( $input [PostmanOptions::PORT] );
				if ($port > 0) {
					$this->sanitizeInt ( 'Port', PostmanOptions::PORT, $input, $new_input );
				} else {
					$new_input [PostmanOptions::PORT] = $this->options->getPort ();
					add_settings_error ( PostmanOptions::PORT, PostmanOptions::PORT, 'Invalid TCP Port', 'error' );
					$success = false;
				}
			}
			// check the auth type AFTER the hostname because we reset the hostname if auth is bad
			$this->sanitizeString ( 'Transport Type', PostmanOptions::TRANSPORT_TYPE, $input, $new_input );
			$this->sanitizeString ( 'Authorization Type', PostmanOptions::AUTHENTICATION_TYPE, $input, $new_input );
			$this->sanitizeString ( 'Sender Name', PostmanOptions::SENDER_NAME, $input, $new_input );
			$this->sanitizeString ( 'Client ID', PostmanOptions::CLIENT_ID, $input, $new_input );
			$this->sanitizeString ( 'Client Secret', PostmanOptions::CLIENT_SECRET, $input, $new_input );
			$this->sanitizeString ( 'Username', PostmanOptions::BASIC_AUTH_USERNAME, $input, $new_input );
			$this->sanitizeString ( 'Password', PostmanOptions::BASIC_AUTH_PASSWORD, $input, $new_input );
			$this->sanitizeString ( 'Reply-To', PostmanOptions::REPLY_TO, $input, $new_input );
			$this->sanitizeString ( 'Sender Name Override', PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE, $input, $new_input );
			$this->sanitizeInt ( 'Read Timeout', PostmanOptions::READ_TIMEOUT, $input, $new_input );
			$this->sanitizeInt ( 'Conenction Timeout', PostmanOptions::CONNECTION_TIMEOUT, $input, $new_input );
			$this->sanitizeInt ( 'Log Level', PostmanOptions::LOG_LEVEL, $input, $new_input );
			
			if (! empty ( $input [PostmanOptions::SENDER_EMAIL] )) {
				$newEmail = $input [PostmanOptions::SENDER_EMAIL];
				$this->logger->debug ( 'Sanitize Sender Email ' . $newEmail );
				if (postmanValidateEmail ( $newEmail )) {
					$new_input [PostmanOptions::SENDER_EMAIL] = sanitize_text_field ( $newEmail );
				} else {
					$new_input [PostmanOptions::SENDER_EMAIL] = $this->options->getSenderEmail ();
					add_settings_error ( PostmanOptions::SENDER_EMAIL, PostmanOptions::SENDER_EMAIL, 'You have entered an invalid e-mail address', 'error' );
					$success = false;
				}
			}
			
			if ($new_input [PostmanOptions::CLIENT_ID] != $this->options->getClientId () || $new_input [PostmanOptions::CLIENT_SECRET] != $this->options->getClientSecret () || $new_input [PostmanOptions::HOSTNAME] != $this->options->getHostname ()) {
				$this->logger->debug ( "Recognized new Client ID" );
				// the user entered a new client id and we should destroy the stored auth token
				delete_option ( PostmanOAuthToken::OPTIONS_NAME );
			}
			
			// WordPress calling Sanitize twice is a known issue
			// https://core.trac.wordpress.org/ticket/21989
			$action = PostmanSession::getInstance ()->getAction ();
			if ($action != self::VALIDATION_SUCCESS && $action != self::VALIDATION_FAILED) {
				if (! empty ( $new_input [PostmanOptions::BASIC_AUTH_PASSWORD] )) {
					// base-64 scramble password
					$new_input [PostmanOptions::BASIC_AUTH_PASSWORD] = base64_encode ( $new_input [PostmanOptions::BASIC_AUTH_PASSWORD] );
					$this->logger->debug ( 'Encoding password as ' . $new_input [PostmanOptions::BASIC_AUTH_PASSWORD] );
				}
			} else {
				$this->logger->debug ( 'Wordpress called sanitize() twice, skipping the second password encode' );
			}
			
			// add Postman plugin version number to database
			$new_input [PostmanOptions::VERSION] = POSTMAN_PLUGIN_VERSION;
			
			if ($success) {
				PostmanSession::getInstance ()->setAction ( self::VALIDATION_SUCCESS );
			} else {
				PostmanSession::getInstance ()->setAction ( self::VALIDATION_FAILED );
			}
			
			return $new_input;
		}
		/**
		 * Sanitize each setting field as needed
		 *
		 * @param array $input
		 *        	Contains all settings fields as array keys
		 */
		public function testSanitize($input) {
			$new_input = array ();
			
			if (isset ( $input ['test_email'] ))
				$new_input ['test_email'] = sanitize_text_field ( $input ['test_email'] );
			
			return $new_input;
		}
		private function sanitizeString($desc, $key, $input, &$new_input) {
			if (isset ( $input [$key] )) {
				$this->logSanitize ( $desc, $input [$key] );
				$new_input [$key] = sanitize_text_field ( $input [$key] );
			}
		}
		private function sanitizeInt($desc, $key, $input, &$new_input) {
			if (isset ( $input [$key] )) {
				$this->logSanitize ( $desc, $input [$key] );
				$new_input [$key] = absint ( $input [$key] );
			}
		}
		private function logSanitize($desc, $value) {
			$this->logger->debug ( 'Sanitize ' . $desc . ' ' . $value );
		}
	}
}
