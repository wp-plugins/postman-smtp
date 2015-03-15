<?php
if (! class_exists ( "PostmanSendTestEmailController" )) {
	class PostmanSendTestEmailController {
		
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
		public function sendTestEmailWithMessageHandler(PostmanOptions $options, PostmanOAuthToken $authorizationToken, $recipient, PostmanMessageHandler $messageHandler, $serviceName, $subject, $message) {
			assert ( ! empty ( $messageHandler ) );
			$result = $this->sendTestEmail ( $options, $authorizationToken, $recipient, $message );
			if ($result) {
				$messageHandler->addMessage ( $this->message );
			} else {
				$messageHandler->addError ( $this->message );
			}
		}
		public function sendTestEmail(PostmanOptions $options, PostmanOAuthToken $authorizationToken, $recipient, $serviceName, $subject, $message, $headers = array ()) {
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $recipient ) );
			
			
			// $headers = array (
			// 'Content-Type: text/html;'
			// );
			
			// send through wp_mail
			$this->logger->debug ( 'Sending Test email' );
			PostmanStats::getInstance ()->disable ();
			// $wp_mail_result = wp_mail ( $recipient, $subject, $message, $headers );
			// $this->logger->error ( 'wp_mail failed :( re-trying through the internal engine' );
			$postmanWpMail = new PostmanWpMail ();
			$postmanWpMailResult = $postmanWpMail->send ( $options, $authorizationToken, $recipient, $subject, $message, $headers );
			$this->transcript = $postmanWpMail->getTranscript ();
			PostmanStats::getInstance ()->enable ();
			
			//
			if ($postmanWpMailResult) {
				$this->logger->debug ( 'Test Email delivered to server' );
				return true;
			} else if (! $postmanWpMailResult) {
				$this->logger->error ( 'Test Email NOT delivered to server - ' . $postmanWpMail->getException ()->getCode () );
				if ($postmanWpMail->getException ()->getCode () == 334) {
					$this->logger->error ( 'Communication Error [334]!' );
					throw new PostmanSendMailCommunicationError334 ();
				} else {
					$this->message = $postmanWpMail->getException ()->getMessage ();
				}
				return false;
			} else {
				$this->logger->error ( 'Something is wrong, sending through wp_mail() failed, but sending through internal engine succeeded.' );
				throw new PostmanSendMailInexplicableException ();
			}
		}
		public function getMessage() {
			return $this->message;
		}
		public function getTranscript() {
			return $this->transcript;
		}
	}
	
	if (! class_exists ( 'PostmanSendMailCommunicationError334' )) {
		class PostmanSendMailCommunicationError334 extends Exception {
		}
	}
	if (! class_exists ( 'PostmanSendMailInexplicableException' )) {
		class PostmanSendMailInexplicableException extends Exception {
		}
	}
}
