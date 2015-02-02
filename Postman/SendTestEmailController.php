<?php
if (! class_exists ( "PostmanSendTestEmailController" )) {
	class PostmanSendTestEmailController {
		const SUBJECT = 'WordPress Postman SMTP Test';
		const MESSAGE = 'Hello, World!';
		
		//
		private $logger;
		
		//
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		/**
		 *
		 * @param unknown $options        	
		 * @param unknown $recipient        	
		 */
		public function send(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, $recipient, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $recipient ) );
			assert ( ! empty ( $messageHandler ) );
			$subject = PostmanSendTestEmailController::SUBJECT;
			$message = PostmanSendTestEmailController::MESSAGE;
			
			$this->logger->debug ( 'Sending Test email' );
			
			// send through wp_mail
			$wp_mail_result = wp_mail ( $recipient, $subject, $message . ' - sent by Postman via wp_mail()' );
			
			if (! $wp_mail_result) {
				$this->logger->error ( 'wp_mail failed :( re-trying through the internal engine' );
				$postmanWpMail = new PostmanWpMail ();
				$postmanWpMailResult = $postmanWpMail->send ( $options, $authorizationToken, $recipient, $subject, $message . ' - sent by Postman via internal engine' );
			}
			
			//
			if ($wp_mail_result) {
				$this->logger->debug ( 'Test Email delivered to SMTP server' );
				$messageHandler->addMessage ( 'Your message was delivered to the SMTP server! Congratulations :)' );
			} else if (! $postmanWpMailResult) {
				$this->logger->error ( 'Test Email NOT delivered to SMTP server - ' . $postmanWpMail->getException ()->getCode () );
				if ($postmanWpMail->getException ()->getCode () == 334) {
					$messageHandler->addError ( 'Oh, bother! ... Communication Error [334] - check that your Sender Email is the same as your Gmail account.' );
				} else {
					$messageHandler->addError ( 'Oh, bother! ... ' . $postmanWpMail->getException ()->getMessage () );
				}
			} else {
				$message = 'Something is wrong, sending throgh wp_mail() failed, but sending through internal engine succeeded. Time to debug!';
				$this->logger->error ( $message );
				$messageHandler->addError ( $message );
			}
			
		}
	}
}
?>