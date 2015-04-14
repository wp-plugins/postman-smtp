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
				$message = PostmanMessageFactory::createEmptyMessage();
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
				$message->setPreventSenderEmailOverride ( $wpMailOptions->isSenderEmailOverridePrevented () );
				$message->setPreventSenderNameOverride ( $wpMailOptions->isSenderNameOverridePrevented () );
				
				// set the reply-to address if it hasn't been set already in the user's headers
				$optionsReplyTo = $wpMailOptions->getReplyTo ();
				$messageReplyTo = $message->getReplyTo ();
				if (! empty ( $optionsReplyTo ) && empty ( $messageReplyTo )) {
					$message->setReplyTo ( $optionsReplyTo );
				}
				
				// send the message
				$engine = PostmanMailEngineFactory::getInstance ()->createMailEngine ( $wpMailOptions, $wpMailAuthorizationToken );
				$engine->send ( $message, $wpMailOptions->getHostname () );
				$this->transcript = $engine->getTranscript ();
				
				// log the successful delivery
				PostmanStats::getInstance ()->incrementSuccessfulDelivery ();
				$log = PostmanEmailLogFactory::createSuccessLog($message, $this->transcript);
				PostmanEmailLogService::getInstance ()->writeToEmailLog ( $log );
				return true;
			} catch ( Exception $e ) {
				// save the error for later
				$this->exception = $e;
				
				// write the error to the PHP log
				$this->logger->error ( get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . trim ( $e->getMessage () ) );

				// log the failed delivery
				PostmanStats::getInstance ()->incrementFailedDelivery ();
				$log = PostmanEmailLogFactory::createFailureLog($message, $this->transcript, $e->getMessage());
				PostmanEmailLogService::getInstance ()->writeToEmailLog ( $log );
				return false;
			}
		}
		public function getException() {
			return $this->exception;
		}
		public function getTranscript() {
			return $this->transcript;
		}
	}
}