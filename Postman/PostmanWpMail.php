<?php
if (! class_exists ( "PostmanWpMail" )) {
	
	require_once 'Postman-Mail/PostmanMailEngineFactory.php';
	require_once 'Postman-Auth/PostmanAuthenticationManagerFactory.php';
	require_once 'PostmanStats.php';
	
	/**
	 * Moved this code into a class so it could be used by both wp_mail() and PostmanSendTestEmailController
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanWpMail {
		private $exception;
		private $transcript;
		private $logger;
		
		/**
		 * This methods creates an instance of PostmanSmtpEngine and sends an email.
		 * Exceptions are held for later inspection. An instance of PostmanStats updates the success/fail tally.
		 *
		 * @param PostmanOptions $wpMailOptions        	
		 * @param PostmanOAuthToken $wpMailAuthorizationToken        	
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $body        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 * @return boolean
		 */
		public function send(PostmanOptions $wpMailOptions, PostmanOAuthToken $wpMailAuthorizationToken, $to, $subject, $body, $headers = '', $attachments = array()) {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			// send the message
			$this->logger->debug ( 'Sending mail' );
			// interact with the SMTP Engine
			try {
				$engine = PostmanMailEngineFactory::getInstance ()->createMailEngine ( $wpMailOptions, $wpMailAuthorizationToken );
				try {
					$message = new PostmanMessage ();
					$message->addHeaders ( $headers );
					$message->addHeaders ( $wpMailOptions->getAdditionalHeaders () );
					$message->setBody ( $body );
					$message->setSubject ( $subject );
					$message->addTo ( $to );
					$message->addTo ( $wpMailOptions->getForcedToRecipients () );
					$message->addCc ( $wpMailOptions->getForcedCcRecipients () );
					$message->addBcc ( $wpMailOptions->getForcedBccRecipients () );
					$message->setAttachments ( $attachments );
					$message->setSender ( $wpMailOptions->getSenderEmail (), $wpMailOptions->getSenderName () );
					
					// set the reply-to address if it hasn't been set already in the user's headers
					$optionsReplyTo = $wpMailOptions->getReplyTo ();
					$messageReplyTo = $message->getReplyTo ();
					if (! empty ( $optionsReplyTo ) && empty ( $messageReplyTo )) {
						$message->setReplyTo ( $optionsReplyTo );
					}
					
					// send the message
					$engine->send ( $message, $wpMailOptions->getHostname () );
					PostmanStats::getInstance ()->incrementSuccessfulDelivery ();
					$this->transcript = $engine->getTranscript ();
					return true;
				} catch ( Exception $e ) {
					$this->exception = $e;
					$this->logger->error ( get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . trim ( $e->getMessage () ) );
					PostmanStats::getInstance ()->incrementFailedDelivery ();
					$this->transcript = $engine->getTranscript ();
					return false;
				}
			} catch ( Exception $e ) {
				$this->exception = $e;
				$this->logger->error ( get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . trim ( $e->getMessage () ) );
				PostmanStats::getInstance ()->incrementFailedDelivery ();
				return false;
			}
			return false;
		}
		public function getException() {
			return $this->exception;
		}
		public function getTranscript() {
			return $this->transcript;
		}
	}
}