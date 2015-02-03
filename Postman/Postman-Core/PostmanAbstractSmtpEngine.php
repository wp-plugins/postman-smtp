<?php
if (! class_exists ( "PostmanOAuthSmtpEngine" )) {
	
	require_once 'PostmanSmtpEngine.php';
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
	require_once 'Zend-1.12.10/Mail/Protocol/Abstract.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Exception.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Oauth2.php';
	require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Login.php';
	
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
	abstract class PostmanAbstractSmtpEngine implements PostmanSmtpEngine {
		
		// logger for all concrete classes - populate with setLogger($logger)
		protected $logger;
		
		// set by the caller
		private $hostname;
		private $port;
		private $senderEmail;
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
		
		// determined by the send() method
		private $isTextHtml;
		private $contentType;
		private $charset;
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanSmtpEngine::send()
		 */
		public function send() {
			assert ( ! empty ( $this->senderEmail ) );
			assert ( ! empty ( $this->port ) );
			assert ( ! empty ( $this->hostname ) );
			
			// pre-processing
			$this->processHeaders ();
			
			// create the Message
			$charset = $this->getCharset ();
			$this->logger->debug ( 'Building Zend_Mail with charset=' . $charset );
			$mail = new Zend_Mail ( $charset );
			
			// add the Postman signature
			$mail->addHeader ( 'X-Mailer', 'Postman SMTP v' . POSTMAN_PLUGIN_VERSION . ' for WordPress' );
			
			// add the content type
			$contentType = $this->getContentType ();
			if (false !== stripos ( $contentType, 'multipart' ) && ! empty ( $this->boundary )) {
				// Lines in email are terminated by CRLF ("\r\n") according to RFC2821
				$mail->addHeader ( sprintf ( "Content-Type: %s;\r\n\t boundary=\"%s\"", $contentType, $this->boundary ) );
			} else {
				$mail->addHeader ( 'Content-Type', $contentType );
			}
			$this->logger->debug ( 'Adding content-type ' . $contentType );
			
			// add the headers
			foreach ( ( array ) $this->headers as $name => $content ) {
				$this->logger->debug ( 'Adding header ' . $name . '=' . $content );
				$mail->addHeader ( $name, $content );
			}
			
			// add the to recipients
			foreach ( ( array ) $this->toRecipients as $recipient ) {
				$this->logger->debug ( 'Adding to ' . $recipient->getEmail () );
				$mail->addTo ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the cc recipients
			foreach ( ( array ) $this->ccRecipients as $recipient ) {
				$this->logger->debug ( 'Adding cc ' . $recipient->getEmail () );
				$mail->addCc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the to recipients
			foreach ( ( array ) $this->bccRecipients as $recipient ) {
				$this->logger->debug ( 'Adding bcc ' . $recipient->getEmail () );
				$mail->addBcc ( $recipient->getEmail (), $recipient->getName () );
			}
			
			// add the reply-to
			if (isset ( $this->replyTo )) {
				$mail->setReplyTo ( $this->replyTo );
			}
			
			// add the return-path
			if (isset ( $this->returnPath )) {
				$mail->setReturnPath ( $this->returnPath );
			}
			
			// add the date
			if (isset ( $this->date )) {
				$mail->setDate ( $this->date );
			}
			
			// add the date
			if (isset ( $this->messageId )) {
				$mail->setMessageId ( $this->messageId );
			}
			
			// add the subject
			if (isset ( $this->subject )) {
				$mail->setSubject ( $this->subject );
			}
			
			// add the message content as either text or html
			switch (strtolower ( $contentType )) {
				case 'text/plain' :
					$this->logger->debug ( 'Adding body as text' );
					$mail->setBodyText ( $this->body );
					break;
				case 'text/html' :
					$this->logger->debug ( 'Adding body as html' );
					$mail->setBodyHtml ( $this->body );
					break;
				default :
					$this->logger->error ( 'Unknown content-type: ' . $contentType );
					$mail->setBodyText ( $this->body );
					break;
			}
			
			// add attachments
			$this->addAttachmentsToMail ( $mail );
			
			// get the transport configuration
			$config = $this->createConfig ( $this->hostname, $this->port );
			assert ( ! empty ( $config ) );
			
			// create the SMTP transport
			$transport = new Zend_Mail_Transport_Smtp ( $this->hostname, $config );
			$mail->setFrom ( $this->senderEmail );
			assert ( ! empty ( $transport ) );
			
			// send the message
			$this->logger->debug ( "Sending mail" );
			$mail->send ( $transport );
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
			if (! is_array ( $recipients )) {
				// http://tiku.io/questions/955963/splitting-comma-separated-email-addresses-in-a-string-with-commas-in-quotes-in-p
				$t = $this->stringGetCsvAlternate ( $recipients );
				foreach ( $t as $k => $v ) {
					if (strpos ( $v, ',' ) !== false) {
						$t [$k] = '"' . str_replace ( ' <', '" <', $v );
					}
					$tokenizedEmail = trim ( $t [$k] );
					$this->logger->debug ( 'User Added recipient: ' . $tokenizedEmail );
					array_push ( $recipientList, $this->createPostmanEmailAddress ( $tokenizedEmail ) );
				}
			} else {
				foreach ( $recipients as $recipient ) {
					$this->logger->debug ( 'User Added recipient: ' . $recipient );
					array_push ( $recipientList, $this->createPostmanEmailAddress ( $recipient ) );
				}
			}
		}
		private function createPostmanEmailAddress($recipient) {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			$recipient_name = '';
			if (preg_match ( '/(.*)<(.+)>/', $recipient, $matches )) {
				if (count ( $matches ) == 3) {
					$recipient_name = $matches [1];
					$recipient = $matches [2];
				}
			}
			return new PostmanEmailAddress ( $recipient, $recipient_name );
		}
		
		/**
		 * Using fgetscv (PHP 4) as a work-around for str_getcsv (PHP 5.3)
		 * From http://stackoverflow.com/questions/13430120/str-getcsv-alternative-for-older-php-version-gives-me-an-empty-array-at-the-e
		 *
		 * @param unknown $string        	
		 * @return multitype:
		 */
		private function stringGetCsvAlternate($string) {
			$fh = fopen ( 'php://temp', 'r+' );
			fwrite ( $fh, $string );
			rewind ( $fh );
			
			$row = fgetcsv ( $fh );
			
			fclose ( $fh );
			return $row;
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
							$this->parts = preg_split ( '/boundary=/i', trim ( $header ) );
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
		 * @param Zend_Mail $mail        	
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
					$this->overrideSender ( $content );
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
			$this->logger->debug ( 'Processing ' . $desc . ' Header ' . $name . ': ' . $content );
		}
		
		/**
		 * Add attachments to the message
		 *
		 * @param Zend_Mail $mail        	
		 */
		private function addAttachmentsToMail(Zend_Mail $mail) {
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
					$at = new Zend_Mime_Part ( file_get_contents ( $file ) );
					// $at->type = 'image/gif';
					$at->disposition = Zend_Mime::DISPOSITION_INLINE;
					$at->encoding = Zend_Mime::ENCODING_BASE64;
					$at->filename = basename ( $file );
					$mail->addAttachment ( $at );
				}
			}
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
		protected function overrideSender($sender) {
			$this->setSender ( $sender );
		}
		function setAttachments($attachments) {
			$this->attachments = $attachments;
		}
		function setSender($sender) {
			$this->senderEmail = $sender;
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
	}
}
?>
