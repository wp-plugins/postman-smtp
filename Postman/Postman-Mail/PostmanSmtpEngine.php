<?php
if (! class_exists ( "PostmanSmtpEngine" )) {
	
	require_once 'PostmanEmailAddress.php';
	
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
	class PostmanSmtpEngine {
		const EOL = "\r\n";
		
		// logger for all concrete classes - populate with setLogger($logger)
		protected $logger;
		
		// set by the caller
		private $hostname;
		private $port;
		private $sender;
		private $replyTo;
		private $toRecipients;
		private $ccRecipients;
		private $bccRecipients;
		private $subject;
		private $body;
		private $headers;
		private $attachments;
		private $returnPath;
		private $date;
		private $messageId;
		private $overrideSenderAllowed;
		
		// determined by the send() method
		private $isTextHtml;
		private $contentType;
		private $charset;
		private $overrideSender;
		
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
			$this->setLogger ( new PostmanLogger ( get_class ( $this ) ) );
			$this->authenticator = $authenticator;
			$this->transport = $transport;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanSmtpEngine::send()
		 */
		public function send() {
			
			// pre-processing
			$this->processHeaders ();
			
			// create the Message
			$charset = $this->getCharset ();
			$this->logger->debug ( 'Building Postman_Zend_Mail with charset=' . $charset );
			$mail = new Postman_Zend_Mail ( $charset );
			
			// add the Postman signature
			$mail->addHeader ( 'X-Mailer', 'Postman SMTP v' . POSTMAN_PLUGIN_VERSION . ' for WordPress' );
			
			// add the content type
			$contentType = $this->getContentType ();
			if (false !== stripos ( $contentType, 'multipart' ) && ! empty ( $this->boundary )) {
				// Lines in email are terminated by CRLF ("\r\n") according to RFC2821
				$contentType = sprintf ( "%s;\r\n\t boundary=\"%s\"", $contentType, $this->boundary );
			}
			$mail->addHeader ( 'Content-Type', $contentType );
			$this->logger->debug ( 'Adding content-type ' . $contentType );
			
			// add the sender
			$this->addFrom ( $mail );
			
			// add the headers
			foreach ( ( array ) $this->headers as $name => $content ) {
				$this->logger->debug ( 'Adding header ' . $name . '=' . $content );
				$mail->addHeader ( $name, $content );
			}
			
			// add the to recipients
			foreach ( ( array ) $this->toRecipients as $recipient ) {
				$recipient->log ( $this->logger, 'To' );
				$mail->addTo ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the cc recipients
			foreach ( ( array ) $this->ccRecipients as $recipient ) {
				$recipient->log ( $this->logger, 'Cc' );
				$mail->addCc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the to recipients
			foreach ( ( array ) $this->bccRecipients as $recipient ) {
				$recipient->log ( $this->logger, 'Bcc' );
				$mail->addBcc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the reply-to
			if (! empty ( $this->replyTo )) {
				$replyTo = new PostmanEmailAddress ( $this->replyTo );
				$mail->setReplyTo ( $replyTo->getEmail (), $replyTo->getName () );
			}
			
			// add the return-path
			if (! empty ( $this->returnPath )) {
				$returnPath = new PostmanEmailAddress ( $this->returnPath );
				$mail->setReturnPath ( $returnPath->getEmail () );
			}
			
			// add the date
			if (! empty ( $this->date )) {
				$mail->setDate ( $this->date );
			}
			
			// add the messageId
			if (! empty ( $this->messageId )) {
				$mail->setMessageId ( $this->messageId );
			}
			
			// add the subject
			if (isset ( $this->subject )) {
				$mail->setSubject ( $this->subject );
			}
			
			// add the message content as either text or html
			if (substr ( $contentType, 0, 10 ) === 'text/plain') {
				$this->logger->debug ( 'Adding body as text' );
				$mail->setBodyText ( $this->body );
			} else if (substr ( $contentType, 0, 9 ) === 'text/html') {
				$this->logger->debug ( 'Adding body as html' );
				$mail->setBodyHtml ( $this->body );
			} else if (substr ( $contentType, 0, 21 ) === 'multipart/alternative') {
				$this->logger->debug ( 'Adding body as multipart/alternative' );
				$arr = explode ( PHP_EOL, $this->body );
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
				$mail->setBodyText ( $this->body );
				break;
			}
			
			// add attachments
			$this->addAttachmentsToMail ( $mail );
			
			// get the transport configuration
			$config = $this->authenticator->createConfig ();
			assert ( ! empty ( $config ) );
			
			// create the SMTP transport
			$zendTransport = $this->transport->createZendMailTransport ( $this->hostname, $config );
			assert ( ! empty ( $zendTransport ) );
			
			// send the message
			$this->logger->debug ( "Sending mail" );
			try {
				$mail->send ( $zendTransport );
				if ($zendTransport->getConnection ())
					$this->transcript = $zendTransport->getConnection ()->getLog ();
			} catch ( Exception $e ) {
				$c = $zendTransport->getConnection ();
				if (isset ( $c )) {
					$this->transcript = $zendTransport->getConnection ()->getLog ();
				}
				throw $e;
			}
		}
		
		/**
		 *
		 * @param Postman_Zend_Mail $mail        	
		 */
		private function addFrom(Postman_Zend_Mail $mail) {
			
			// by default, sender is what Postman set
			$sender = PostmanEmailAddress::copy ( $this->sender );
			
			// but we will let other plugins override the sender via the headers
			if (isset ( $this->overrideSender )) {
				$s1 = new PostmanEmailAddress ( $this->overrideSender );
				$s1name = $s1->getName ();
				$s1email = $s1->getEmail ();
				if (! empty ( $s1name )) {
					$sender->setName ( $s1name );
				}
				if (! empty ( $s1email )) {
					$sender->setEmail ( $s1email );
				}
			}
			/**
			 * Filter the email address to send from.
			 *
			 * @since 2.2.0
			 *       
			 * @param string $from_email
			 *        	Email address to send from.
			 */
			// and other plugins can override the email via a filter
			$sender->setEmail ( apply_filters ( 'wp_mail_from', $sender->getEmail () ) );
			
			/**
			 * Filter the name to associate with the "from" email address.
			 *
			 * @since 2.3.0
			 *       
			 * @param string $from_name
			 *        	Name associated with the "from" email address.
			 */
			// and other plugins can override the name via a filter
			$sender->setName ( apply_filters ( 'wp_mail_from_name', $sender->getName () ) );
			
			// but the MailAuthenticator has the final say
			$this->authenticator->filterSender ( $sender );
			
			// now log it and push it into the message
			assert ( isset ( $sender ) );
			if (isset ( $sender )) {
				$senderEmail = $sender->getEmail ();
				$senderName = $sender->getName ();
				assert ( ! empty ( $senderEmail ) );
				$sender->log ( $this->logger, 'From' );
				if (! empty ( $senderName )) {
					$mail->setFrom ( $senderEmail, $senderName );
				} else {
					$mail->setFrom ( $senderEmail );
				}
			}
		}
		
		/**
		 * Get the charset, checking first the WordPress bloginfo, then the header, then the wp_mail_charset filter.
		 *
		 * @return string
		 */
		protected function getCharset() {
			// If we don't have a charset from the input headers
			if (empty ( $this->charset ))
				$this->charset = get_bloginfo ( 'charset' );
			
			/**
			 * Filter the default wp_mail() charset.
			 *
			 * @since 2.3.0
			 *       
			 * @param string $charset
			 *        	Default email charset.
			 */
			$this->charset = apply_filters ( 'wp_mail_charset', $this->charset );
			
			return $this->charset;
		}
		
		/**
		 * Get the content type, checking first the header, then the wp_mail_content_type filter
		 *
		 * @return string
		 */
		protected function getContentType() {
			// Set Content-Type and charset
			// If we don't have a content-type from the input headers
			if (! isset ( $this->contentType ))
				$this->contentType = 'text/plain';
			/**
			 * Filter the wp_mail() content type.
			 *
			 * @since 2.3.0
			 *       
			 * @param string $content_type
			 *        	Default wp_mail() content type.
			 */
			$this->contentType = apply_filters ( 'wp_mail_content_type', $this->contentType );
			return $this->contentType;
		}
		public function addTo($to) {
			if (! isset ( $this->toRecipients )) {
				$this->toRecipients = array ();
			}
			$this->addRecipients ( $this->toRecipients, $to );
		}
		public function addCc($cc) {
			if (! isset ( $this->ccRecipients )) {
				$this->ccRecipients = array ();
			}
			$this->addRecipients ( $this->ccRecipients, $cc );
		}
		public function addBcc($bcc) {
			if (! isset ( $this->bccRecipients )) {
				$this->bccRecipients = array ();
			}
			$this->addRecipients ( $this->bccRecipients, $bcc );
		}
		/**
		 *
		 * @param unknown $recipients
		 *        	Array or comma-separated list of email addresses to send message.
		 * @throws Exception
		 */
		private function addRecipients(&$recipientList, $recipients) {
			$recipients = PostmanEmailAddress::convertToArray ( $recipients );
			foreach ( $recipients as $recipient ) {
				$this->logger->debug ( 'User Added recipient: ' . $recipient );
				array_push ( $recipientList, new PostmanEmailAddress ( $recipient ) );
			}
		}
		
		/**
		 * For the string version, each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n")
		 *
		 * @todo http://framework.zend.com/manual/1.12/en/zend.mail.additional-headers.html
		 *      
		 */
		private function processHeaders() {
			$headers = $this->headers;
			$this->headers = array ();
			if (! is_array ( $headers )) {
				// WordPress may send a string where "each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n") (advanced)"
				// this converts that string to an array
				$headers = explode ( "\n", str_replace ( "\r\n", "\n", $headers ) );
				// $headers = explode ( PHP_EOL, $headers );
			}
			// otherwise WordPress sends an array
			foreach ( $headers as $header ) {
				if (! empty ( $header )) {
					// boundary may be in a header line, but it's not a header
					// eg. boundary="----=_NextPart_DC7E1BB5...
					if (strpos ( $header, ':' ) === false) {
						if (false !== stripos ( $header, 'boundary=' )) {
							$parts = preg_split ( '/boundary=/i', trim ( $header ) );
							$this->boundary = trim ( str_replace ( array (
									"'",
									'"' 
							), '', $parts [1] ) );
						}
						continue;
					}
					list ( $name, $content ) = explode ( ':', trim ( $header ), 2 );
					$this->processHeader ( $name, $content );
				}
			}
		}
		
		/**
		 * Add the headers that were processed in processHeaders()
		 * Zend requires that several headers are specially handled.
		 *
		 * @param unknown $name        	
		 * @param unknown $value        	
		 * @param Postman_Zend_Mail $mail        	
		 */
		private function processHeader($name, $content) {
			$name = trim ( $name );
			$content = trim ( $content );
			switch (strtolower ( $name )) {
				case 'content-type' :
					$this->logProcessHeader ( 'Content-Type', $name, $content );
					if (strpos ( $content, ';' ) !== false) {
						list ( $type, $this->charset ) = explode ( ';', $content );
						$this->contentType = trim ( $type );
						if (false !== stripos ( $this->charset, 'charset=' )) {
							$this->charset = trim ( str_replace ( array (
									'charset=',
									'"' 
							), '', $this->charset ) );
						} elseif (false !== stripos ( $this->charset, 'boundary=' )) {
							$this->boundary = trim ( str_replace ( array (
									'BOUNDARY=',
									'boundary=',
									'"' 
							), '', $this->charset ) );
							$this->charset = '';
						}
					} else {
						$this->contentType = trim ( $content );
					}
					break;
				case 'to' :
					$this->logProcessHeader ( 'To', $name, $content );
					$this->addTo ( $content );
					break;
				case 'cc' :
					$this->logProcessHeader ( 'Cc', $name, $content );
					$this->addCc ( $content );
					break;
				case 'bcc' :
					$this->logProcessHeader ( 'Bcc', $name, $content );
					$this->addBcc ( $content );
					break;
				case 'from' :
					$this->logProcessHeader ( 'From', $name, $content );
					$this->overrideSender = $content;
					break;
				case 'subject' :
					$this->logProcessHeader ( 'Subject', $name, $content );
					$this->setSubject ( $content );
					break;
				case 'reply-to' :
					$this->logProcessHeader ( 'Reply-To', $name, $content );
					$this->setReplyTo ( $content );
					break;
				case 'return-path' :
					$this->logProcessHeader ( 'Return-Path', $name, $content );
					$this->setReturnPath ( $content );
					break;
				case 'date' :
					$this->logProcessHeader ( 'Date', $name, $content );
					$this->setDate ( $content );
					break;
				case 'message-id' :
					$this->logProcessHeader ( 'Message-Id', $name, $content );
					$this->setMessageId ( $content );
					break;
				default :
					// Add it to our grand headers array
					$this->logProcessHeader ( 'other', $name, $content );
					$this->headers [$name] = $content;
					break;
			}
		}
		
		/**
		 *
		 * @param unknown $desc        	
		 * @param unknown $name        	
		 * @param unknown $content        	
		 */
		private function logProcessHeader($desc, $name, $content) {
			$this->logger->debug ( 'Processing ' . $desc . ' Header - ' . $name . ': ' . $content );
		}
		
		/**
		 * Add attachments to the message
		 *
		 * @param Postman_Zend_Mail $mail        	
		 */
		private function addAttachmentsToMail(Postman_Zend_Mail $mail) {
			$attachments = $this->attachments;
			if (! is_array ( $attachments )) {
				// WordPress may a single filename or a newline-delimited string list of multiple filenames
				$attArray = explode ( PHP_EOL, $attachments );
			} else {
				$attArray = $attachments;
			}
			// otherwise WordPress sends an array
			foreach ( $attArray as $file ) {
				if (! empty ( $file )) {
					$this->logger->debug ( "Adding attachment: " . $file );
					$at = new Postman_Zend_Mime_Part ( file_get_contents ( $file ) );
					// $at->type = 'image/gif';
					$at->disposition = Postman_Zend_Mime::DISPOSITION_INLINE;
					$at->encoding = Postman_Zend_Mime::ENCODING_BASE64;
					$at->filename = basename ( $file );
					$mail->addAttachment ( $at );
				}
			}
		}
		
		/**
		 * If this is not set, the FROM header and WordPress FROM hooks are ignored
		 */
		public function allowSenderOverride($allow) {
			$this->overrideSenderAllowed = $allow;
		}
		
		// public accessors
		public function setHeaders($headers) {
			$this->headers = $headers;
		}
		function setBody($body) {
			$this->body = $body;
		}
		function setSubject($subject) {
			$this->subject = $subject;
		}
		function setAttachments($attachments) {
			$this->attachments = $attachments;
		}
		function setSender($sender, $name = null) {
			$this->sender = new PostmanEmailAddress ( $sender, $name );
		}
		function setReplyTo($replyTo) {
			$this->replyTo = $replyTo;
		}
		function setReturnPath($returnPath) {
			$this->returnPath = $returnPath;
		}
		function setMessageId($messageId) {
			$this->messageId = $messageId;
		}
		function setDate($date) {
			$this->date = $date;
		}
		function setHostname($hostname) {
			$this->hostname = $hostname;
		}
		function setPort($port) {
			$this->port = $port;
		}
		
		// set the internal logger (defined in the abstract class)
		protected function setLogger($logger) {
			$this->logger = $logger;
		}
		// set the internal logger (defined in the abstract class)
		protected function getLogger() {
			return $this->logger;
		}
		// return the SMTP session transcript
		public function getTranscript() {
			return $this->transcript;
		}
	}
}

/**
 * I renamed the Zend classes, but unfortunately these five class names remain or I break
 * compatibility with the Postman Gmail API extension :-(
 */
if (! class_exists ( 'Zend_Mail_Transport_Smtp' )) {
	abstract class Zend_Mail_Transport_Abstract extends Postman_Zend_Mail_Transport_Abstract {
	}
	class Zend_Mail_Protocol_Smtp extends Postman_Zend_Mail_Protocol_Smtp {
	}
	abstract class Zend_Mail_Protocol_Abstract extends Postman_Zend_Mail_Protocol_Abstract {
	}
	class Zend_Mime extends Postman_Zend_Mime {
	}
	class Zend_Mail_Transport_Exception extends Postman_Zend_Mail_Transport_Exception {
	}
}
