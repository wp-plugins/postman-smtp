<?php
if (! class_exists ( "PostmanSendTestEmailController" )) {
	class PostmanSendTestEmailController {
		const SUBJECT = 'WordPress Postman SMTP Test';
		const MESSAGE = 'Hello, World!';
		const EOL = "\r\n";
		
		//
		private $logger;
		private $message;
		private $transcript;
		
		//
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		/**
		 *
		 * @param unknown $options        	
		 * @param unknown $recipient        	
		 */
		public function send(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, $recipient, PostmanMessageHandler $messageHandler, $serviceName) {
			assert ( ! empty ( $messageHandler ) );
			$result = $this->simeplSend ( $options, $authorizationToken, $recipient );
			if ($result) {
				$messageHandler->addMessage ( $this->message );
			} else {
				$messageHandler->addError ( $this->message );
			}
		}
		public function simeplSend(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, $recipient, $serviceName) {
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $recipient ) );
			
			$headers = array ();
			$subject = PostmanSendTestEmailController::SUBJECT;
			// Lines in email are terminated by CRLF ("\r\n") according to RFC2821
			// Englsih - Mandarin - French - Hindi - Spanish - Arabic - Portuguese - Russian - Bengali - Japanese - Punjabi
			$message .= sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - السلام عليكم - Olá - Привет! - নমস্কার - 今日は - ਸਤਿ ਸ੍ਰੀ ਅਕਾਲ।%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanSendTestEmailController::EOL, PostmanSendTestEmailController::EOL, sprintf ( __ ( 'Sent by Postman v%s' ), POSTMAN_PLUGIN_VERSION ) );
			// $headers = array (
			// 'Content-Type: text/html;'
			// );
			
			// send through wp_mail
			$this->logger->debug ( 'Sending Test email' );
			PostmanStats::getInstance ()->disable ();
			$wp_mail_result = wp_mail ( $recipient, $subject, $message, $headers );
			
			if (! $wp_mail_result) {
				$this->logger->error ( 'wp_mail failed :( re-trying through the internal engine' );
				$postmanWpMail = new PostmanWpMail ();
				$postmanWpMailResult = $postmanWpMail->send ( $options, $authorizationToken, $recipient, $subject, $message, $headers );
				$this->transcript = $postmanWpMail->getTranscript ();
			}
			PostmanStats::getInstance ()->enable ();
			
			//
			if ($wp_mail_result) {
				$this->logger->debug ( 'Test Email delivered to SMTP server' );
				$this->message = 'Your message was delivered to the SMTP server! Congratulations :)';
				return true;
			} else if (! $postmanWpMailResult) {
				$this->logger->error ( 'Test Email NOT delivered to SMTP server - ' . $postmanWpMail->getException ()->getCode () );
				if ($postmanWpMail->getException ()->getCode () == 334) {
					$this->message = 'Communication Error [334] - check that your Sender Email is the same as your ' . $serviceName . ' account. You may need to re-create the Client ID.';
				} else {
					$this->message = $postmanWpMail->getException ()->getMessage ();
				}
				return false;
			} else {
				$message = 'Something is wrong, sending throgh wp_mail() failed, but sending through internal engine succeeded. Time to debug!';
				$this->logger->error ( $message );
				$this->message = $message;
				return false;
			}
		}
		public function getMessage() {
			return $this->message;
		}
		public function getTranscript() {
			return $this->transcript;
		}
	}
}
