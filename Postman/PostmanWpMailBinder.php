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
		function __construct($basename, PostmanOptions $binderOptions, PostmanAuthorizationToken $binderAuthorizationToken, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $binderOptions ) );
			assert ( ! empty ( $binderAuthorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->basename = $basename;
			$this->messageHandler = $messageHandler;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'admin_init', array (
					$this,
					'warnIfCanNotBindToWpMail' 
			) );
			if ($binderOptions->isRequestOAuthPermissiongAllowed () && $binderOptions->isSendingEmailAllowed ( $binderAuthorizationToken )) {
				
				if (! function_exists ( 'wp_mail' )) {
					/**
					 * Define our own wp_mail
					 *
					 * @param unknown $to        	
					 * @param unknown $subject        	
					 * @param unknown $message        	
					 * @param string $headers        	
					 * @param unknown $attachments        	
					 * @return boolean
					 */
					function wp_mail($wp_mail_to, $wp_mail_subject, $wp_mail_message, $wp_mail_headers = '', $wp_mail_attachments = array()) {
						// get the Options and AuthToken
						$wp_mail_options = PostmanOptions::getInstance ();
						$wp_mail_authToken = PostmanAuthorizationToken::getInstance ();
						// create an instance of PostmanWpMail to send the message
						$wp_mail_postmanWpMail = new PostmanWpMail ();
						// send the message
						return $wp_mail_postmanWpMail->send ( $wp_mail_options, $wp_mail_authToken, $wp_mail_to, $wp_mail_subject, $wp_mail_message, $wp_mail_headers, $wp_mail_attachments );
					}
				} else {
					$this->logger->debug ( 'cant replace wp_mail' );
					$this->couldNotReplaceWpMail = true;
				}
			}
		}
		function warnIfCanNotBindToWpMail() {
			if (is_plugin_active ( $this->basename )) {
				if ($this->couldNotReplaceWpMail) {
					$this->logger->debug ( 'oops, can not bind to wp_mail()' );
					add_action ( 'admin_notices', Array (
							$this,
							'displayCouldNotReplaceWpMail' 
					) );
				}
			}
		}
		public function displayCouldNotReplaceWpMail() {
			$this->messageHandler->displayWarningMessage ( PostmanAdminController::NAME . ' is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.' );
		}
	}
}