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
		function __construct(PostmanMailAuthenticator $authenticator, PostmanTransport $transport) {
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
			$mail->addHeader ( 'X-Mailer', sprintf ( 'Postman SMTP %s for WordPress (%s)', POSTMAN_PLUGIN_VERSION, 'https://wordpress.org/plugins/postman-smtp/' ), true );
			
			// add the headers
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
			$mail->addHeader ( 'Content-Type', $contentType );
			$this->logger->debug ( 'Adding content-type ' . $contentType );
			
			// add the sender
			$sender = $message->addFrom ( $mail, $this->authenticator );
			$sender->log ( $this->logger, 'From' );
			
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
				$replyTo = new PostmanEmailAddress ( $replyTo );
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
			$config = $this->authenticator->createConfig ();
			assert ( ! empty ( $config ) );
			
			// create the SMTP transport
			$zendTransport = $this->transport->createZendMailTransport ( $hostname, $config );
			assert ( ! empty ( $zendTransport ) );
			
			try {
				// send the message
				$this->logger->debug ( "Sending mail" );
				$mail->send ( $zendTransport );
				// finally not supported??
				if ($zendTransport->getConnection ()) {
					$this->transcript = $zendTransport->getConnection ()->getLog ();
					$this->logger->debug ( $this->transcript );
				}
			} catch ( Exception $e ) {
				// finally not supported??
				if ($zendTransport->getConnection ()) {
					$this->transcript = $zendTransport->getConnection ()->getLog ();
					$this->logger->debug ( $this->transcript );
				}
				throw $e;
			}
		}
		
		// return the SMTP session transcript
		public function getTranscript() {
			return $this->transcript;
		}
	}
}

