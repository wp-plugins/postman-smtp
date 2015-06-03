<?php
require_once 'PostmanWpMail.php';
require_once 'PostmanOptions.php';
require_once 'PostmanPreRequisitesCheck.php';

if (! class_exists ( 'PostmanWpMailBinder' )) {
	class PostmanWpMailBinder {
		private $logger;
		private $bound;
		private $bindError;
		
		/**
		 * private singleton constructor
		 */
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );

			// register the bind status hook
			add_filter ( 'postman_wp_mail_bind_status', array (
					$this,
					'postman_wp_mail_bind_status' 
			) );
		}
		
		/**
		 * Return the Singleton instance
		 *
		 * @return Ambigous <NULL, PostmanWpMailBinder>
		 */
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanWpMailBinder ();
			}
			return $inst;
		}
		
		/**
		 * Returns the bind result
		 */
		public function postman_wp_mail_bind_status() {
			$result = array (
					'bound' => $this->bound,
					'bind_error' => $this->bindError 
			);
			return $result;
		}
		
		/**
		 * Replace wp_mail() after making sure:
		 * 1) the plugin has not already bound to wp_mail and
		 * 2) wp_mail is available for use
		 * 3) the plugin is properly configured.
		 * 4) the plugin's prerequisites are met.
		 */
		function bind() {
			if (! $this->bound) {
				$binderOptions = PostmanOptions::getInstance ();
				$binderAuthorizationToken = PostmanOAuthToken::getInstance ();
				$ready = true;
				if (function_exists ( 'wp_mail' )) {
					// If the function exists, it's probably because another plugin has
					// replaced the pluggable function first, and we set an error flag.
					$this->logger->error ( 'wp_mail is already bound, Postman can not use it' );
					$this->bindError = true;
				}
				if (! PostmanTransportRegistry::getInstance ()->isPostmanReadyToSendEmail ( $binderOptions, $binderAuthorizationToken )) {
					// this is too common for new installs to be reported as an error
					$this->logger->warn ( 'Transport is not configured and ready' );
					$ready = false;
				}
				if (! PostmanPreRequisitesCheck::isReady ()) {
					$this->logger->error ( 'Prerequisite check failed' );
					$ready = false;
				}
				if ($ready && ! $this->bindError) {
					$this->logger->debug ( 'Binding to wp_mail()' );
					$this->replacePluggableFunctionWpMail ();
				}
			}
		}
		
		/**
		 * The code to replace the pluggable wp_mail()
		 *
		 * If the function does not exist, then the replacement was successful
		 * and we set a success flag.
		 *
		 * @return boolean
		 */
		private function replacePluggableFunctionWpMail() {
			/**
			 * The Postman drop-in replacement for the WordPress wp_mail() function
			 *
			 * @param string|array $to
			 *        	Array or comma-separated list of email addresses to send message.
			 * @param string $subject
			 *        	Email subject
			 * @param string $message
			 *        	Message contents
			 * @param string|array $headers
			 *        	Optional. Additional headers.
			 * @param string|array $attachments
			 *        	Optional. Files to attach.
			 * @return bool Whether the email contents were sent successfully.
			 */
			function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
				
				// create an instance of PostmanWpMail to send the message
				$postmanWpMail = new PostmanWpMail ();
				return $postmanWpMail->send ( $to, $subject, $message, $headers, $attachments );
			}
			$this->logger->debug ( 'Bound to wp_mail()' );
			$this->bound = true;
		}
		public function isBound() {
			return $this->bound;
		}
		public function isUnboundDueToException() {
			return $this->bindError;
		}
	}
}