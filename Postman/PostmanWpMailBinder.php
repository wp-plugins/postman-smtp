<?php
require_once 'PostmanWpMail.php';
require_once 'PostmanMessageHandler.php';
require_once 'PostmanOptions.php';
if (! class_exists ( "PostmanWpMailBinder" )) {
	class PostmanWpMailBinder {
		private $logger;
		private $basename;
		private $couldNotReplaceWpMail;
		private $messageHandler;
		function __construct($basename, PostmanOptions $binderOptions, PostmanOAuthToken $binderAuthorizationToken, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $binderOptions ) );
			assert ( ! empty ( $binderAuthorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->basename = $basename;
			$this->messageHandler = $messageHandler;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			
			// the bind should happen as soon as possible, but the error messages have to wait
			// until the admin_init event
			add_action ( 'admin_init', array (
					$this,
					'warnIfCanNotBindToWpMail' 
			) );
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
				} else {
					$this->logger->error ( 'Cannot bind to wp_mail' );
					$this->couldNotReplaceWpMail = true;
				}
			} else {
				$this->logger->debug ( 'Not binding, plugin is not configured.' );
			}
		}
		function warnIfCanNotBindToWpMail() {
			if ($this->couldNotReplaceWpMail) {
				$this->logger->error ( 'Alerting administrator about bind problem' );
				add_action ( 'admin_notices', Array (
						$this->messageHandler,
						'displayCouldNotReplaceWpMail' 
				) );
			}
		}
	}
}