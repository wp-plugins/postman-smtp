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
		 * constructor
		 */
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
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
		public function send($to, $subject, $message, $headers = '', $attachments = array()) {
			$this->logger->trace ( 'wp_mail parameters before applying WordPress wp_mail filter:' );
			$this->traceParameters ( $to, $subject, $message, $headers, $attachments );
			
			/**
			 * Filter the wp_mail() arguments.
			 *
			 * @since 1.5.4
			 *       
			 * @param array $args
			 *        	A compacted array of wp_mail() arguments, including the "to" email,
			 *        	subject, message, headers, and attachments values.
			 */
			$atts = apply_filters ( 'wp_mail', compact ( 'to', 'subject', 'message', 'headers', 'attachments' ) );
			if (isset ( $atts ['to'] )) {
				$to = $atts ['to'];
			}
			
			if (isset ( $atts ['subject'] )) {
				$subject = $atts ['subject'];
			}
			
			if (isset ( $atts ['message'] )) {
				$message = $atts ['message'];
			}
			
			if (isset ( $atts ['headers'] )) {
				$headers = $atts ['headers'];
			}
			
			if (isset ( $atts ['attachments'] )) {
				$attachments = $atts ['attachments'];
			}
			
			if (! is_array ( $attachments )) {
				$attachments = explode ( "\n", str_replace ( "\r\n", "\n", $attachments ) );
			}
			
			$this->logger->trace ( 'wp_mail parameters after applying WordPress wp_mail filter:' );
			$this->traceParameters ( $to, $subject, $message, $headers, $attachments );
			
			// get the Options and AuthToken
			$wpMailOptions = PostmanOptions::getInstance ();
			$wpMailAuthorizationToken = PostmanOAuthToken::getInstance ();
			try {
				// create the message
				$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
				$transportConfiguration = PostmanMailTransportConfigurationFactory::getInstance ()->createMailTransportConfiguration ( $transport, $wpMailOptions, $wpMailAuthorizationToken );
				$messageBuilder = $this->createMessage ( $wpMailOptions, $to, $subject, $message, $headers, $attachments, $transportConfiguration );
				
				// send the message
				if ($wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION) {
					$this->logger->debug ( 'Sending mail' );
					$engine = PostmanMailEngineFactory::getInstance ()->createMailEngine ( $wpMailOptions, $wpMailAuthorizationToken, $transport, $transportConfiguration );
					$engine->send ( $messageBuilder, $wpMailOptions->getHostname () );
					$this->transcript = $engine->getTranscript ();
					
					// log the successful delivery
					PostmanStats::getInstance ()->incrementSuccessfulDelivery ();
				}
				if ($wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION || $wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_LOG_ONLY) {
					PostmanEmailLogService::getInstance ()->createSuccessLog ( $messageBuilder, $this->transcript, $transport );
				}
				return true;
			} catch ( Exception $e ) {
				// save the error for later
				$this->exception = $e;
				
				// write the error to the PHP log
				$this->logger->error ( get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . trim ( $e->getMessage () ) );
				
				// log the failed delivery
				if ($wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION) {
					PostmanStats::getInstance ()->incrementFailedDelivery ();
				}
				if ($wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION || $wpMailOptions->getRunMode () == PostmanOptions::RUN_MODE_LOG_ONLY) {
					PostmanEmailLogService::getInstance ()->createFailureLog ( $messageBuilder, $this->transcript, $transport, $e->getMessage () );
				}
				return false;
			}
		}
		
		/**
		 * Aggregates all the content into a Message to be sent to the MailEngine
		 *
		 * @param unknown $wpMailOptions        	
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $body        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 */
		private function createMessage(PostmanOptionsInterface $wpMailOptions, $to, $subject, $body, $headers, $attachments, PostmanMailTransportConfiguration $transportation) {
			$message = PostmanMessageFactory::createEmptyMessage ();
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
			$message->setPreventSenderEmailOverride ( $wpMailOptions->isSenderEmailOverridePrevented () || $transportation->isPluginSenderEmailEnforced () );
			$message->setPreventSenderNameOverride ( $wpMailOptions->isSenderNameOverridePrevented () || $transportation->isPluginSenderNameEnforced () );
			
			// set the reply-to address if it hasn't been set already in the user's headers
			$optionsReplyTo = $wpMailOptions->getReplyTo ();
			$messageReplyTo = $message->getReplyTo ();
			if (! empty ( $optionsReplyTo ) && empty ( $messageReplyTo )) {
				$message->setReplyTo ( $optionsReplyTo );
			}
			return $message;
		}
		
		/**
		 * Trace the parameters to aid in debugging
		 *
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $body        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 */
		private function traceParameters($to, $subject, $message, $headers, $attachments) {
			$this->logger->trace ( 'to:' );
			$this->logger->trace ( $to );
			$this->logger->trace ( 'subject:' );
			$this->logger->trace ( $subject );
			$this->logger->trace ( 'headers:' );
			$this->logger->trace ( $headers );
			$this->logger->trace ( 'attachments:' );
			$this->logger->trace ( $attachments );
			$this->logger->trace ( 'message:' );
			$this->logger->trace ( $message );
		}
		
		/**
		 *
		 * @return Exception
		 */
		public function getException() {
			return $this->exception;
		}
		public function getTranscript() {
			return $this->transcript;
		}
	}
}