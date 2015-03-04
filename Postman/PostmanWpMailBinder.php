<?php
require_once 'PostmanWpMail.php';
require_once 'PostmanMessageHandler.php';
require_once 'PostmanOptions.php';
if (! class_exists ( "PostmanWpMailBinder" )) {
	class PostmanWpMailBinder {
		private $logger;
		private $bound;
		private $bindError;
		
		/**
		 * private singleton constructor
		 */
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
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
		 * Replaced wp_mail() after making sure
		 * 1) the plugin has not already bound to wp_mail and
		 * 2) the plugin is properly configured.
		 */
		function bind() {
			if (! $this->bound) {
				$binderOptions = PostmanOptions::getInstance ();
				$binderAuthorizationToken = PostmanOAuthToken::getInstance ();
				if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $binderOptions, $binderAuthorizationToken )) {
					$this->replacePluggableFunctionWpMail ();
				}
			}
		}
		
		/**
		 * The code to replace the pluggable wp_mail()
		 *
		 * If the function exists, it's probably because another plugin has
		 * replaced the pluggable function first, and we set an error flag.
		 *
		 * If the function does not exist, then the replacement was successful
		 * and we set a success flag.
		 *
		 * @return boolean
		 */
		private function replacePluggableFunctionWpMail() {
			if (! function_exists ( 'wp_mail' )) {
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
					// get the Options and AuthToken
					$wp_mail_options = PostmanOptions::getInstance ();
					$wp_mail_authToken = PostmanOAuthToken::getInstance ();
					// create an instance of PostmanWpMail to send the message
					$wp_mail_postmanWpMail = new PostmanWpMail ();
					// send the message
					return $wp_mail_postmanWpMail->send ( $wp_mail_options, $wp_mail_authToken, $to, $subject, $message, $headers, $attachments );
				}
				$this->logger->debug ( 'Bound to wp_mail()' );
				$this->bound = true;
			} else {
				$this->logger->error ( 'Fatal Error: Tried to bind, but someone else beat us there' );
				$this->bindError = true;
			}
		}
		public function isBound() {
			return $this->bound;
		}
		public function isUnboundDueToException() {
			return $this->bindError;
		}
	}
}