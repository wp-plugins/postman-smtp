<?php
require_once 'PostmanWpMail.php';
require_once 'PostmanMessageHandler.php';
require_once 'PostmanOptions.php';
if (! class_exists ( "PostmanWpMailBinder" )) {
	class PostmanWpMailBinder {
		private $logger;
		private $bound;
		private $bindError;
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanWpMailBinder ();
			}
			return $inst;
		}
// 		public function init() {
// 			add_action ( 'admin_init', array (
// 					$this,
// 					'warnIfCanNotBindToWpMail' 
// 			) );
// 		}
		function bind() {
			if (! $this->bound) {
				
				$binderOptions = PostmanOptions::getInstance ();
				$binderAuthorizationToken = PostmanOAuthToken::getInstance ();
				if (PostmanTransportUtils::isPostmanConfiguredToSendEmail ( $binderOptions, $binderAuthorizationToken )) {
					
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
						$this->$bindError = true;
						// TODO then throw an Exception, don't go to the MessageHandler yourself :p
					}
				} else {
					$this->logger->debug ( 'Not binding, plugin is not configured.' );
				}
			} else {
				$this->logger->debug ( 'Alerady bound.' );
			}
		}
// 		function warnIfCanNotBindToWpMail() {
// 			if ($this->bindError) {
// 				$this->logger->error ( 'Alerting administrator about bind problem' );
// 				add_action ( 'admin_notices', Array (
// 						$this->messageHandler,
// 						'displayCouldNotReplaceWpMail' 
// 				) );
// 			}
// 		}
	}
}