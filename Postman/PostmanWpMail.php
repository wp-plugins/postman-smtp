<?php
if (! class_exists ( "PostmanWpMail" )) {
	
	require_once 'Postman-Mail/PostmanSmtpEngineFactory.php';
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
		 * @param unknown $message        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 * @return boolean
		 */
		public function send(PostmanOptions $wpMailOptions, PostmanOAuthToken $wpMailAuthorizationToken, $to, $subject, $message, $headers = '', $attachments = array()) {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			// send the message
			$this->logger->debug ( 'Sending mail' );
			// interact with the SMTP Engine
			try {
				$engine = PostmanSmtpEngineFactory::getInstance ()->createSmtpEngine ( $wpMailOptions, $wpMailAuthorizationToken );
				try {
					$engine->allowSenderOverride ( ! $wpMailOptions->isSenderNameOverridePrevented () );
					$engine->setBody ( $message );
					$engine->setSubject ( $subject );
					$engine->addTo ( $to );
					$engine->setHeaders ( $headers );
					$engine->setAttachments ( $attachments );
					$engine->setSender ( $wpMailOptions->getSenderEmail (), $wpMailOptions->getSenderName () );
					$engine->setHostname ( $wpMailOptions->getHostname () );
					$engine->setPort ( $wpMailOptions->getPort () );
					$engine->setReplyTo ( $wpMailOptions->getReplyTo () );
					$engine->send ();
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