<?php
if (! class_exists ( "PostmanOAuthSmtpEngine" )) {
	
	require_once 'PostmanSmtpEngine.php';
	
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
	// require_once 'Zend-1.12.10/Mail/Protocol/Smtp/Auth/Login.php';
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
		
		// define some constants
		const AUTH_VALUE = 'oauth2';
		const SSL_VALUE = 'ssl';
		
		// logger for all concrete classes - populate with setLogger($logger)
		private $logger;
		
		// are we in text/html mode?
		private $isTextHtml;
		
		// set by the caller
		private $senderEmail;
		private $recipients;
		private $subject;
		private $body;
		private $headers;
		private $attachments;
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanSmtpEngine::send()
		 */
		public function send() {
			assert ( ! empty ( $this->senderEmail ) );
			assert ( ! empty ( $this->port ) );
			assert ( ! empty ( $this->hostname ) );
			
			// create the Message
			$mail = new Zend_Mail ();
			// headers must be set before body
			$this->processHeaders ( $mail );
			$this->processBody ( $mail );
			$this->processRecipients ( $mail );
			$mail->setSubject ( $this->subject );
			$this->addHeader ( 'X-Mailer', 'Postman SMTP for WordPress', $mail );
			$this->processAttachments ( $mail );
			
			// get the transport configuration
			$config = $this->createConfig ( $this->hostname, $this->port );
			assert ( ! empty ( $config ) );
			
			// create the SMTP transport
			$transport = new Zend_Mail_Transport_Smtp ( $this->hostname, $config );
			$mail->setFrom ( $this->senderEmail );
			assert ( ! empty ( $transport ) );
			$this->logger->debug ( "Sending mail" );
			$mail->send ( $transport );
		}
		
		/**
		 * Let the subclass define the transport configuration
		 *
		 * @param unknown $senderEmail        	
		 * @param unknown $hostname        	
		 * @param unknown $port        	
		 */
		abstract protected function createConfig($hostname, $port);
		
		/**
		 * Validate an email address
		 *
		 * @param unknown $email        	
		 * @return number
		 */
		public function validateEmail($email) {
			$exp = "/^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$/i";
			return preg_match ( $exp, $email );
		}
		
		/**
		 * Adds recipients to the message.
		 *
		 * @param unknown $email|Array
		 *        	or comma-separated list of email addresses to send message.
		 * @param
		 *        	string
		 */
		private function processRecipients(Zend_Mail $mail) {
			$email = $this->recipients;
			if (! is_array ( $email )) {
				// http://tiku.io/questions/955963/splitting-comma-separated-email-addresses-in-a-string-with-commas-in-quotes-in-p
				$t = $this->stringGetCsvAlternate ( $email );
				foreach ( $t as $k => $v ) {
					if (strpos ( $v, ',' ) !== false) {
						$t [$k] = '"' . str_replace ( ' <', '" <', $v );
					}
					$tokenizedEmail = trim ( $t [$k] );
					if (! $this->validateEmail ( $tokenizedEmail )) {
						$message = 'Recipient e-mail "' . $tokenizedEmail . '" is invalid.';
						$this->logger->error ( $message );
						throw new Exception ( $message );
					}
					$this->logger->debug ( "To: " . $tokenizedEmail );
					$mail->addTo ( $tokenizedEmail );
				}
			} else {
				if (! $this->validateEmail ( $email )) {
					$message = 'Recipient e-mail "' . $email . '" is invalid.';
					$this->logger->error ( $message );
					throw new Exception ( $message );
				}
				$this->logger->debug ( "To: " . $email . '(' . $name . ')' );
				$mail->addTo ( $email, $name );
			}
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
		 * Add the message body, HTML or Text depending on the headers that were sent.
		 *
		 * @param Zend_Mail $mail        	
		 */
		private function processBody(Zend_Mail $mail) {
			if ($this->isTextHtml) {
				$this->logger->debug ( 'Adding body as html' );
				$mail->setBodyHtml ( $this->body );
			} else {
				$this->logger->debug ( 'Adding body as text' );
				$mail->setBodyText ( $this->body );
			}
		}
		/**
		 * unknown $header| Mail headers to send with the message.
		 * (string or array)
		 * For the string version, each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n")
		 *
		 * @todo http://framework.zend.com/manual/1.12/en/zend.mail.additional-headers.html
		 *      
		 */
		private function processHeaders(Zend_Mail $mail) {
			$headers = $this->headers;
			if (! is_array ( $headers )) {
				// WordPress may send a string where "each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n") (advanced)"
				$headers = explode ( PHP_EOL, $headers );
			}
			// otherwise WordPress sends an array
			foreach ( $headers as $header ) {
				if (! empty ( $header )) {
					$explodedHeader = explode ( ':', $header, 2 );
					$name = $explodedHeader [0];
					$value = trim ( $explodedHeader [1] );
					$this->addHeader ( $name, $value, $mail );
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
		private function addHeader($name, $value, Zend_Mail $mail) {
			if (strtolower ( $name ) == "to") {
				$this->logger->debug ( "to Header: " . $value );
				$mail->addTo ( $value );
			} else if (strtolower ( $name ) == "cc") {
				$this->logger->debug ( "cc Header: " . $value );
				$mail->addCc ( $value );
			} else if (strtolower ( $name ) == "bcc") {
				$this->logger->debug ( "bcc Header: " . $value );
				$mail->addBcc ( $value );
			} else if (strtolower ( $name ) == "from") {
				$this->overrideSender ( $value );
			} else if (strtolower ( $name ) == "subject") {
				$this->logger->debug ( "subject Header: " . $value );
				$mail->setSubject ( $value );
			} else if (strtolower ( $name ) == "reply-to") {
				$this->logger->debug ( "reply-to Header: " . $value );
				$mail->setReplyTo ( $value );
			} else if (strtolower ( $name ) == "return-path") {
				$this->logger->debug ( "return-path Header: " . $value );
				$mail->setReturnPath ( $value );
			} else if (strtolower ( $name ) == "date") {
				$this->logger->debug ( "date Header: " . $value );
				$mail->setDate ( $value );
			} else if (strtolower ( $name ) == "message-id") {
				$this->logger->debug ( "message-id Header: " . $value );
				$mail->setMessageId ( $value );
			} else if (strtolower ( $name ) == "message-id") {
				$this->logger->debug ( "message-id Header: " . $value );
				$mail->setMessageId ( $value );
			} else if (strtolower ( $name ) == 'content-type' && strtolower ( $value ) == 'text/html') {
				$this->isTextHtml = true;
				$this->logger->debug ( "content-type Header: " . $value );
				$mail->addHeader ( $name, $value, false );
			} else {
				$this->logger->debug ( "Allowed Header: " . $name . ': ' . $value );
				$mail->addHeader ( $name, $value, false );
			}
		}
		
		/**
		 * Add attachments to the message
		 *
		 * @param Zend_Mail $mail        	
		 */
		private function processAttachments(Zend_Mail $mail) {
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
		function setReceipients($recipients) {
			$this->recipients = $recipients;
		}
		function setAttachments($attachments) {
			$this->attachments = $attachments;
		}
		function setSender($sender) {
			$this->senderEmail = $sender;
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
