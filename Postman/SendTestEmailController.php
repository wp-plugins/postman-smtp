<?php
if (! class_exists ( "PostmanSendTestEmailController" )) {
	class PostmanSendTestEmailController {
		const SUBJECT = 'WordPress Postman SMTP Test';
		const MESSAGE = 'Hello, World!';
		
		//
		private $logger;
		private $util;
		
		//
		function __construct() {
			$this->logger = new PostmanLogger ();
			$this->util = new PostmanWordpressUtil ();
		}
		
		/**
		 *
		 * @param unknown $options        	
		 * @param unknown $recipient        	
		 */
		public function send($options, &$authorizationToken, $recipient) {
			$hostname = PostmanOptionUtil::getHostname ( $options );
			$port = PostmanOptionUtil::getPort ( $options );
			$from = PostmanOptionUtil::getSenderEmail ( $options );
			$subject = PostmanSendTestEmailController::SUBJECT;
			$message = PostmanSendTestEmailController::MESSAGE;
			
			$this->logger->debug ( 'Sending Test email: server=' . $hostname . ':' . $port . ' from=' . $from . ' to=' . $recipient . ' subject=' . $subject );
			
			// send through wp_mail
			$result = wp_mail ( $recipient, $subject, $message . ' - sent by Postman via wp_mail()' );
			
			if (! $result) {
				$this->logger->debug ( 'wp_mail failed :( re-trying through the internal engine' );
				$postmanWpMail = new PostmanWpMail ( $options, $authorizationToken );
				$result = $postmanWpMail->send ( $recipient, $subject, $message . ' - sent by Postman via internal engine' );
			}
			
			//
			if ($result) {
				$this->logger->debug ( 'Test Email delivered to SMTP server' );
				$this->util->addMessage ( 'Your message was delivered to the SMTP server! Congratulations :)' );
			} else {
				$this->logger->debug ( 'Test Email NOT delivered to SMTP server - ' . $postmanWpMail->getException ()->getCode () );
				if ($postmanWpMail->getException ()->getCode () == 334) {
					$this->util->addError ( 'Oh, bother! ... Communication Error [334].' );
				} else {
					$this->util->addError ( 'Oh, bother! ... ' . $postmanWpMail->getException ()->getMessage () );
				}
			}
			
			$this->logger->debug ( 'Redirecting to home page' );
			wp_redirect ( POSTMAN_HOME_PAGE_URL );
			exit ();
		}
	}
}
?>