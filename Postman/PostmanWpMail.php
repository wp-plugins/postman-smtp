<?php
if (! class_exists ( "PostmanWpMail" )) {
	
	/**
	 * Moved this code into a class so it could be used by both wp_mail() and PostmanSendTestEmailController
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanWpMail {
		private $exception;
		private $transcript;
		private $totalTime;
		private $logger;
		
		/**
		 * Load the dependencies
		 */
		public function init() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			require_once 'Postman-Mail/PostmanMessage.php';
			require_once 'Postman-Email-Log/PostmanEmailLogService.php';
			require_once 'Postman-Mail/PostmanMailEngine.php';
			require_once 'Postman-Auth/PostmanAuthenticationManagerFactory.php';
			require_once 'PostmanStats.php';
		}
		
		/**
		 * This methods creates an instance of PostmanSmtpEngine and sends an email.
		 * Exceptions are held for later inspection. An instance of PostmanStats updates the success/fail tally.
		 *
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $body        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 * @return boolean
		 */
		public function send($to, $subject, $message, $headers = '', $attachments = array()) {
			
			// initialize for sending
			$this->init ();
			
			// build the message
			$postmanMessage = $this->processWpMailCall ( $to, $subject, $message, $headers, $attachments );
			
			// build the log
			$log = new PostmanEmailLog ();
			$log->originalTo = $to;
			$log->originalSubject = $subject;
			$log->originalMessage = $message;
			$log->originalHeaders = $headers;
			
			// send the message and return the result
			return $this->sendMessage ( $postmanMessage, $log );
		}
		
		/**
		 * Creates a new instance of PostmanMessage with a pre-set From and Reply-To
		 *
		 * @return PostmanMessage
		 */
		public function createNewMessage() {
			$message = new PostmanMessage ();
			$options = PostmanOptions::getInstance ();
			// the From is set now so that it can be overridden
			$message->setFrom ( $options->getMessageSenderEmail (), $options->getMessageSenderName () );
			// the Reply-To is set now so that it can be overridden
			$message->setReplyTo ( $options->getReplyTo () );
			$message->setCharset ( get_bloginfo ( 'charset' ) );
			return $message;
		}
		
		/**
		 * A convenient place for other code to send a PostmanMessage
		 *
		 * @param PostmanMessage $message        	
		 * @return boolean
		 */
		public function sendMessage(PostmanMessage $message, PostmanEmailLog $log) {
			
			// start the clock
			$startTime = microtime ( true ) * 1000;
			
			// get the Options and AuthToken
			$options = PostmanOptions::getInstance ();
			$authorizationToken = PostmanOAuthToken::getInstance ();
			
			// add plugin-specific attributes to PostmanMessage
			$message->addHeaders ( $options->getAdditionalHeaders () );
			$message->addTo ( $options->getForcedToRecipients () );
			$message->addCc ( $options->getForcedCcRecipients () );
			$message->addBcc ( $options->getForcedBccRecipients () );
			
			// get the transport and create the transportConfig and engine
			$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
			
			// create the Zend Mail Transport Configuration Factory
			if (PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 == $transport->getAuthenticationType ()) {
				$transportConfiguration = new PostmanOAuth2ConfigurationFactory ();
			} else {
				$transportConfiguration = new PostmanBasicAuthConfigurationFactory ();
			}
			
			// create the Mail Engine
			$engine = new PostmanMailEngine ( $transport, $transportConfiguration );
			
			// is this a test run?
			$testMode = apply_filters ( 'postman_test_email', false );
			$this->logger->debug ( 'testMode=' . $testMode );
			
			try {
				
				// validate the message
				$message->applyFilters ();
				$message->validate ();
				
				// send the message
				if ($options->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION) {
					if ($options->isAuthTypeOAuth2 ()) {
						PostmanUtils::lock ();
						// may throw an exception attempting to contact the OAuth2 provider
						$this->ensureAuthtokenIsUpdated ( $transport, $options, $authorizationToken );
					}
					
					$this->logger->debug ( 'Sending mail' );
					// may throw an exception attempting to contact the SMTP server
					$engine->send ( $message, $options->getHostname () );
					
					// increment the success counter, unless we are just tesitng
					if (! $testMode) {
						PostmanStats::getInstance ()->incrementSuccessfulDelivery ();
					}
				}
				if ($options->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION || $options->getRunMode () == PostmanOptions::RUN_MODE_LOG_ONLY) {
					// log the successful delivery
					PostmanEmailLogService::getInstance ()->writeSuccessLog ( $log, $message, $engine->getTranscript (), $transport );
				}
				
				// clean up
				$this->postSend ( $engine, $startTime, $options );
				
				// return successful
				return true;
			} catch ( Exception $e ) {
				// save the error for later
				$this->exception = $e;
				
				// write the error to the PHP log
				$this->logger->error ( get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . trim ( $e->getMessage () ) );
				
				// increment the failure counter, unless we are just tesitng
				if (! $testMode && $options->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION) {
					PostmanStats::getInstance ()->incrementFailedDelivery ();
				}
				if ($options->getRunMode () == PostmanOptions::RUN_MODE_PRODUCTION || $options->getRunMode () == PostmanOptions::RUN_MODE_LOG_ONLY) {
					// log the failed delivery
					PostmanEmailLogService::getInstance ()->writeFailureLog ( $log, $message, $engine->getTranscript (), $transport, $e->getMessage () );
				}
				
				// clean up
				$this->postSend ( $engine, $startTime, $options );
				
				// return failure
				return false;
			}
		}
		
		/**
		 * Builds a PostmanMessage based on the WordPress wp_mail parameters
		 *
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $message        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 */
		private function processWpMailCall($to, $subject, $message, $headers, $attachments) {
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
			
			// Postman API: register the response hook
			add_filter ( 'postman_wp_mail_result', array (
					$this,
					'postman_wp_mail_result' 
			) );
			
			// create the message
			$postmanMessage = $this->createNewMessage ();
			$this->populateMessageFromWpMailParams ( $postmanMessage, $to, $subject, $message, $headers, $attachments );
			
			// return the message
			return $postmanMessage;
		}
		
		/**
		 * Clean up after sending the mail
		 *
		 * @param PostmanMailEngine $engine        	
		 * @param unknown $startTime        	
		 */
		private function postSend(PostmanMailEngine $engine, $startTime, PostmanOptions $options) {
			// save the transcript
			$this->transcript = $engine->getTranscript ();
			
			// delete the semaphore
			if ($options->isAuthTypeOAuth2 ()) {
				PostmanUtils::unlock ();
			}
			
			// stop the clock
			$endTime = microtime ( true ) * 1000;
			$this->totalTime = $endTime - $startTime;
		}
		
		/**
		 * Returns the result of the last call to send()
		 *
		 * @return multitype:Exception NULL
		 */
		function postman_wp_mail_result() {
			$result = array (
					'time' => $this->totalTime,
					'exception' => $this->exception,
					'transcript' => $this->transcript 
			);
			return $result;
		}
		
		/**
		 */
		private function ensureAuthtokenIsUpdated(PostmanTransport $transport, PostmanOptions $options, PostmanOAuthToken $authorizationToken) {
			assert ( ! empty ( $transport ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			// ensure the token is up-to-date
			$this->logger->debug ( 'Ensuring Access Token is up-to-date' );
			// interact with the Authentication Manager
			$wpMailAuthManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $transport, $options, $authorizationToken );
			if ($wpMailAuthManager->isAccessTokenExpired ()) {
				$this->logger->debug ( 'Access Token has expired, attempting refresh' );
				$wpMailAuthManager->refreshToken ();
				$authorizationToken->save ();
			}
		}
		
		/**
		 * Aggregates all the content into a Message to be sent to the MailEngine
		 *
		 * @param unknown $to        	
		 * @param unknown $subject        	
		 * @param unknown $body        	
		 * @param unknown $headers        	
		 * @param unknown $attachments        	
		 */
		private function populateMessageFromWpMailParams(PostmanMessage $message, $to, $subject, $body, $headers, $attachments) {
			$message->addHeaders ( $headers );
			$message->setBody ( $body );
			$message->setSubject ( $subject );
			$message->addTo ( $to );
			$message->setAttachments ( $attachments );
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
	}
}