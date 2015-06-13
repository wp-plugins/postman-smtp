<?php
if (! class_exists ( "PostmanMailEngine" )) {
	
	require_once 'Zend-1.12.10/Loader.php';
	require_once 'Zend-1.12.10/Registry.php';
	require_once 'Zend-1.12.10/Mime/Message.php';
	require_once 'Zend-1.12.10/Mime/Part.php';
	require_once 'Zend-1.12.10/Mime.php';
	require_once 'Zend-1.12.10/Validate/Interface.php';
	require_once 'Zend-1.12.10/Validate/Abstract.php';
	require_once 'Zend-1.12.10/Validate.php';
	require_once 'Zend-1.12.10/Validate/Ip.php';
	require_once 'Zend-1.12.10/Validate/Hostname.php';
	require_once 'Zend-1.12.10/Mail.php';
	require_once 'Zend-1.12.10/Exception.php';
	require_once 'Zend-1.12.10/Mail/Exception.php';
	require_once 'Zend-1.12.10/Mail/Transport/Exception.php';
	require_once 'Zend-1.12.10/Mail/Transport/Abstract.php';
	require_once 'Zend-1.12.10/Mail/Transport/Smtp.php';
	require_once 'Zend-1.12.10/Mail/Transport/Sendmail.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Abstract.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Exception.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Oauth2.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Login.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Crammd5.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Plain.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * The various Transports available:
	 * http://framework.zend.com/manual/current/en/modules/zend.mail.smtp.options.html
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanMailEngine {
		
		// logger for all concrete classes - populate with setLogger($logger)
		protected $logger;
		
		// the result
		private $transcript;
		
		//
		private $transport;
		private $authenticator;
		
		/**
		 *
		 * @param unknown $senderEmail        	
		 * @param unknown $accessToken        	
		 */
		function __construct(PostmanTransport $transport, PostmanZendMailTransportConfigurationFactory $authenticator) {
			assert ( isset ( $authenticator ) );
			assert ( isset ( $transport ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->authenticator = $authenticator;
			$this->transport = $transport;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanSmtpEngine::send()
		 */
		public function send(PostmanMessage $message, $hostname) {
			
			// create the Message
			$charset = $message->getCharset ();
			$this->logger->debug ( 'Building Postman_Zend_Mail with charset=' . $charset );
			$mail = new Postman_Zend_Mail ( $charset );
			
			// add the Postman signature - append it to whatever the user may have set
			if ($message->isPostmanSignatureEnabled ()) {
				$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
				$mail->addHeader ( 'X-Mailer', sprintf ( 'Postman SMTP %s for WordPress (%s)', $pluginData ['version'], 'https://wordpress.org/plugins/postman-smtp/' ) );
			}
			
			// add the headers - see http://framework.zend.com/manual/1.12/en/zend.mail.additional-headers.html
			foreach ( ( array ) $message->getHeaders () as $header ) {
				$this->logger->debug ( sprintf ( 'Adding user header %s=%s', $header ['name'], $header ['content'] ) );
				$mail->addHeader ( $header ['name'], $header ['content'], true );
			}
			
			// add the content type
			$contentType = $message->getContentType ();
			if (false !== stripos ( $contentType, 'multipart' ) && ! empty ( $this->boundary )) {
				// Lines in email are terminated by CRLF ("\r\n") according to RFC2821
				$contentType = sprintf ( "%s;\r\n\t boundary=\"%s\"", $contentType, $message->getBoundary () );
			}
			
			// add the Content-Type header, overriding what the user may have set
			$mail->addHeader ( 'Content-Type', $contentType, false );
			$this->logger->debug ( 'Adding content-type ' . $contentType );
			
			// add the From Header
			$fromHeader = $this->addFrom ( $message, $mail );
			$fromHeader->log ( $this->logger, 'From' );
			
			// add the Sender Header, overriding what the user may have set
			$mail->addHeader ( 'Sender', $message->getSenderAddress (), false );
			// from RFC 5321: http://tools.ietf.org/html/rfc5321#section-4.4
			// A message-originating SMTP system SHOULD NOT send a message that
			// already contains a Return-path header field.
			// I changed Zend/Mail/Mail.php to fix this
			$mail->setReturnPath ( $message->getSenderAddress () );
			
			// add the to recipients
			foreach ( ( array ) $message->getToRecipients () as $recipient ) {
				$recipient->log ( $this->logger, 'To' );
				$mail->addTo ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the cc recipients
			foreach ( ( array ) $message->getCcRecipients () as $recipient ) {
				$recipient->log ( $this->logger, 'Cc' );
				$mail->addCc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the to recipients
			foreach ( ( array ) $message->getBccRecipients () as $recipient ) {
				$recipient->log ( $this->logger, 'Bcc' );
				$mail->addBcc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the reply-to
			$replyTo = $message->getReplyTo ();
			if (! empty ( $replyTo )) {
				$mail->setReplyTo ( $replyTo->getEmail (), $replyTo->getName () );
			}
			
			// add the return-path
			$returnPath = $message->getReturnPath ();
			if (! empty ( $returnPath )) {
				$returnPath = new PostmanEmailAddress ( $returnPath );
				$mail->setReturnPath ( $returnPath->getEmail () );
			}
			
			// add the date
			$date = $message->getDate ();
			if (! empty ( $date )) {
				$mail->setDate ( $date );
			}
			
			// add the messageId
			$messageId = $message->getMessageId ();
			if (! empty ( $messageId )) {
				$mail->setMessageId ( $messageId );
			}
			
			// add the subject
			if (null !== $message->getSubject ()) {
				$mail->setSubject ( $message->getSubject () );
			}
			
			// add the message content as either text or html
			if (substr ( $contentType, 0, 10 ) === 'text/plain') {
				$this->logger->debug ( 'Adding body as text' );
				$mail->setBodyText ( $message->getBody () );
			} else if (substr ( $contentType, 0, 9 ) === 'text/html') {
				$this->logger->debug ( 'Adding body as html' );
				$mail->setBodyHtml ( $message->getBody () );
			} else if (substr ( $contentType, 0, 21 ) === 'multipart/alternative') {
				$this->logger->debug ( 'Adding body as multipart/alternative' );
				$arr = explode ( PHP_EOL, $message->getBody () );
				$textBody = '';
				$htmlBody = '';
				$mode = '';
				foreach ( $arr as $s ) {
					if (substr ( $s, 0, 25 ) === "Content-Type: text/plain;") {
						$mode = 'foundText';
					} else if (substr ( $s, 0, 24 ) === "Content-Type: text/html;") {
						$mode = 'foundHtml';
					} else if ($mode == 'textReading') {
						$textBody .= $s;
					} else if ($mode == 'htmlReading') {
						$htmlBody .= $s;
					} else if ($mode == 'foundText') {
						if ($s == '') {
							$mode = 'textReading';
						}
					} else if ($mode == 'foundHtml') {
						if ($s == '') {
							$mode = 'htmlReading';
						}
					}
				}
				$mail->setBodyHtml ( $htmlBody );
				$mail->setBodyText ( $textBody );
			} else {
				$this->logger->error ( 'Unknown content-type: ' . $contentType );
				$mail->setBodyText ( $message->getBody () );
				break;
			}
			
			// add attachments
			$message->addAttachmentsToMail ( $mail );
			
			// get the transport configuration
			$config = $this->authenticator->createConfig ( $this->transport );
			assert ( ! empty ( $config ) );
			
			// create the SMTP transport
			$zendTransport = $this->transport->createZendMailTransport ( $hostname, $config );
			assert ( ! empty ( $zendTransport ) );
			
			try {
				// send the message
				$this->logger->debug ( "Sending mail" );
				$mail->send ( $zendTransport );
				// finally not supported??
				if ($zendTransport->getConnection () && ! PostmanUtils::isEmpty ( $zendTransport->getConnection ()->getLog () )) {
					$this->transcript = $zendTransport->getConnection ()->getLog ();
					$this->logger->trace ( $this->transcript );
				} else {
					// TODO then use the Raw Message as the Transcript
				}
			} catch ( Exception $e ) {
				// finally not supported??
				if ($zendTransport->getConnection () && ! PostmanUtils::isEmpty ( $zendTransport->getConnection ()->getLog () )) {
					$this->transcript = $zendTransport->getConnection ()->getLog ();
					$this->logger->trace ( $this->transcript );
				} else {
					// TODO then use the Raw Message as the Transcript
				}
				
				// get the current exception message
				$message = $e->getMessage ();
				if ($e->getCode () == 334) {
					// replace the unusable Google message with a better one in the case of code 334
					$message = sprintf ( __ ( 'Communication Error [334] - make sure the Sender Email belongs to the account which provided the OAuth 2.0 consent.', 'postman-smtp' ) );
				}
				// create a new exception
				$newException = new Exception ( $message, $e->getCode (), $e->getPrevious () );
				// throw the new exception after handling
				throw $newException;
			}
		}
		
		/**
		 * Get the sender from PostmanMessage and add it to the Postman_Zend_Mail object
		 *
		 * @param PostmanMessage $message        	
		 * @param Postman_Zend_Mail $mail        	
		 * @return PostmanEmailAddress
		 */
		public function addFrom(PostmanMessage $message, Postman_Zend_Mail $mail) {
			$sender = $message->getFromAddress ();
			// now log it and push it into the message
			$senderEmail = $sender->getEmail ();
			$senderName = $sender->getName ();
			assert ( ! empty ( $senderEmail ) );
			if (! empty ( $senderName )) {
				$mail->setFrom ( $senderEmail, $senderName );
			} else {
				$mail->setFrom ( $senderEmail );
			}
			return $sender;
		}
		
		// return the SMTP session transcript
		public function getTranscript() {
			return $this->transcript;
		}
	}
}

