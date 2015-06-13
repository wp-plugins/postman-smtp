<?php
if (! class_exists ( "PostmanMessage" )) {
	
	require_once 'PostmanEmailAddress.php';
	
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
	class PostmanMessage {
		const EOL = "\r\n";
		
		// logger for all concrete classes - populate with setLogger($logger)
		protected $logger;
		
		// set by the caller
		private $sender;
		private $from;
		private $fromHeader;
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
		private $preventSenderNameOverride;
		private $preventSenderEmailOverride;
		
		// determined by the send() method
		private $isTextHtml;
		private $contentType;
		private $charset;
		
		//
		private $boundary;
		private $enablePostmanSignature;
		
		/**
		 * No-argument constructor
		 */
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->headers = array ();
			$this->toRecipients = array ();
			$this->ccRecipients = array ();
			$this->bccRecipients = array ();
		}
		public function setPostmanSignatureEnabled($enableSignature) {
			$this->enablePostmanSignature = $enableSignature;
		}
		public function isPostmanSignatureEnabled() {
			return $this->enablePostmanSignature;
		}
		
		/**
		 *
		 * @return PostmanEmailAddress
		 */
		public function getSenderAddress() {
			return $this->sender;
		}
		
		/**
		 *
		 * @return PostmanEmailAddress
		 */
		public function getFromAddress() {
			
			// by default, sender is what Postman set
			$from = PostmanEmailAddress::copy ( $this->from );
			$this->logger->trace ( $from );
			
			// but we will let other plugins override the from via the 'From' header
			if (isset ( $this->fromHeader )) {
				$s1 = new PostmanEmailAddress ( $this->fromHeader );
				$s1name = $s1->getName ();
				$s1email = $s1->getEmail ();
				if (! empty ( $s1name )) {
					$from->setName ( $s1name );
				}
				if (! empty ( $s1email )) {
					$from->setEmail ( $s1email );
				}
			}
			$this->logger->trace ( $from );
			/**
			 * Filter the email address to send from.
			 */
			// other plugins can override the email via a filter
			$from->setEmail ( apply_filters ( 'wp_mail_from', $from->getEmail () ) );
			
			/**
			 * Filter the name to associate with the "from" email address.
			 */
			// other plugins can override the name via a filter
			$from->setName ( apply_filters ( 'wp_mail_from_name', $from->getName () ) );
			$this->logger->trace ( $from );
			
			// but the user has the final say
			if ($this->isPluginSenderEmailEnforced ()) {
				$from->setEmail ( $this->from->getEmail () );
			}
			if ($this->isPluginSenderNameEnforced ()) {
				$from->setName ( $this->from->getName () );
			}
			$this->logger->trace ( $from );
			
			return $from;
		}
		
		/**
		 * Get the charset, checking first the WordPress bloginfo, then the header, then the wp_mail_charset filter.
		 *
		 * @return string
		 */
		public function getCharset() {
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
		public function getContentType() {
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
		/**
		 *
		 * @param unknown $recipients
		 *        	Array or comma-separated list of email addresses to send message.
		 * @throws Exception
		 */
		public function addTo($to) {
			$this->addRecipients ( $this->toRecipients, $to );
		}
		/**
		 *
		 * @param unknown $recipients
		 *        	Array or comma-separated list of email addresses to send message.
		 * @throws Exception
		 */
		public function addCc($cc) {
			$this->addRecipients ( $this->ccRecipients, $cc );
		}
		/**
		 *
		 * @param unknown $recipients
		 *        	Array or comma-separated list of email addresses to send message.
		 * @throws Exception
		 */
		public function addBcc($bcc) {
			$this->addRecipients ( $this->bccRecipients, $bcc );
		}
		/**
		 *
		 * @param unknown $recipients
		 *        	Array or comma-separated list of email addresses to send message.
		 * @throws Exception
		 */
		private function addRecipients(&$recipientList, $recipients) {
			if (! empty ( $recipients )) {
				$recipients = PostmanEmailAddress::convertToArray ( $recipients );
				foreach ( $recipients as $recipient ) {
					if (! empty ( $recipient )) {
						$this->logger->debug ( sprintf ( 'User added recipient: "%s"', $recipient ) );
						array_push ( $recipientList, new PostmanEmailAddress ( $recipient ) );
					}
				}
			}
		}
		
		/**
		 * For the string version, each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n")
		 */
		public function addHeaders($headers) {
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
							$this->logger->debug ( sprintf ( 'Processing special boundary header \'%s\'', $this->getBoundary () ) );
						} else {
							$this->logger->debug ( sprintf ( 'Ignoring broken header \'%s\'', $header ) );
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
					$this->fromHeader = $content;
					break;
				case 'subject' :
					$this->logProcessHeader ( 'Subject', $name, $content );
					$this->setSubject ( $content );
					break;
				case 'reply-to' :
					$this->logProcessHeader ( 'Reply-To', $name, $content );
					$this->setReplyTo ( $content );
					break;
				case 'sender' :
					$this->logProcessHeader ( 'Sender', $name, $content );
					$this->logger->warn ( sprintf ( 'Ignoring Sender header \'%s\'', $content ) );
					break;
				case 'return-path' :
					$this->logProcessHeader ( 'Return-Path', $name, $content );
					$this->logger->warn ( sprintf ( 'Ignoring Return-Path header \'%s\'', $content ) );
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
					array_push ( $this->headers, array (
							'name' => $name,
							'content' => $content 
					) );
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
		public function addAttachmentsToMail(Postman_Zend_Mail $mail) {
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
					$at->disposition = Postman_Zend_Mime::DISPOSITION_ATTACHMENT;
					$at->encoding = Postman_Zend_Mime::ENCODING_BASE64;
					$at->filename = basename ( $file );
					$mail->addAttachment ( $at );
				}
			}
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
		function setSender($email) {
			$this->sender = $email;
		}
		function setFrom($email, $name = null) {
			$this->from = new PostmanEmailAddress ( $email, $name );
		}
		function setReplyTo($replyTo) {
			$this->replyTo = new PostmanEmailAddress ( $replyTo );
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
		
		// sender override
		public function isPluginSenderNameEnforced() {
			return $this->preventSenderNameOverride;
		}
		public function setPreventSenderNameOverride($preventSenderNameOverride) {
			$this->preventSenderNameOverride = $preventSenderNameOverride;
		}
		public function isPluginSenderEmailEnforced() {
			return $this->preventSenderEmailOverride;
		}
		public function setPreventSenderEmailOverride($preventSenderEmailOverride) {
			$this->preventSenderEmailOverride = $preventSenderEmailOverride;
		}
		
		// return the headers
		public function getHeaders() {
			return $this->headers;
		}
		public function getBoundary() {
			return $this->boundary;
		}
		public function getToRecipients() {
			return $this->toRecipients;
		}
		public function getCcRecipients() {
			return $this->ccRecipients;
		}
		public function getBccRecipients() {
			return $this->bccRecipients;
		}
		public function getReplyTo() {
			return $this->replyTo;
		}
		public function getReturnPath() {
			return $this->returnPath;
		}
		public function getDate() {
			return $this->date;
		}
		public function getMessageId() {
			return $this->messageId;
		}
		public function getSubject() {
			return $this->subject;
		}
		public function getBody() {
			return $this->body;
		}
	}
}
