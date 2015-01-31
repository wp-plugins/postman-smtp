<?php
if (! class_exists ( "PostmanOAuthSmtpEngine" )) {
	
	require_once 'SmtpEngine.php';
	
	require_once 'Zend/Registry.php';
	require_once 'Zend/Mime.php';
	require_once 'Zend/Validate.php';
	require_once 'Zend/Validate/Hostname.php';
	require_once 'Zend/Mail.php';
	require_once 'Zend/Loader.php';
	require_once 'Zend/Loader/Autoloader.php';
	require_once 'Zend/Mail/Transport/Smtp.php';
	require_once 'Zend/Exception.php';
	require_once 'Zend/Mail/Exception.php';
	require_once 'Zend/Mail/Protocol/Exception.php';
	require_once 'Zend/Mail/Protocol/Smtp.php';
	require_once 'Zend/Mail/Protocol/Smtp/Auth/Oauth2.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanOAuthSmtpEngine implements PostmanSmtpEngine {
		
		// define some constants
		const ZEND_TRANSPORT_CONFIG_SSL = 'ssl';
		const ZEND_TRANSPORT_CONFIG_PORT = 'port';
		const ZEND_TRANSPORT_CONFIG_AUTH = 'auth';
		const ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST = 'xoauth2_request';
		const AUTH_VALUE = 'oauth2';
		const SSL_VALUE = 'ssl';
		
		//
		private $logger;
		
		// are we in text/html mode?
		private $textHtml;
		
		// set in the constructor
		private $senderEmail;
		private $accessToken;
		
		// set by the caller
		private $recipients;
		private $subject;
		private $body;
		private $headers;
		private $attachments;
		
		// constructor
		function __construct($senderEmail, $accessToken) {
			assert ( ! empty ( $senderEmail ) );
			assert ( ! empty ( $accessToken ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->senderEmail = $senderEmail;
			$this->accessToken = $accessToken;
		}
		public function setHeaders($headers) {
			$this->headers = $headers;
		}
		function setBody($body) {
			$this->body = $body;
		}
		function setSubject($subject) {
			$this->subject = $subject;
		}
		function setReceipients($recipients) {
			$this->recipients = $recipients;
		}
		function setAttachments($attachments) {
			$this->attachments = $attachments;
		}
		/**
		 * Verifies the Authentication Token and sends an email
		 *
		 * @return boolean
		 */
		public function send($hostname, $port) {
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
			
			// create the Message
			$mail = new Zend_Mail ();
			// headers must be set before body
			$this->processHeaders ( $mail );
			$this->processBody ( $mail );
			$this->processRecipiens ( $mail );
			$mail->setSubject ( $this->subject );
			$this->addHeader ( 'X-Mailer', 'Postman SMTP for WordPress', $mail );
			$this->processAttachments ( $mail );
			
			// prepare authentication
			$senderEmail = $this->senderEmail;
			$accessToken = $this->accessToken;
			assert ( ! empty ( $senderEmail ) );
			assert ( ! empty ( $accessToken ) );
			if (! $this->validateEmail ( $senderEmail )) {
				$message = 'Sender e-mail "' . $senderEmail . '" is invalid.';
				$this->logger->error ( $message );
				throw new Exception ( $message );
			}
			$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$accessToken}\1\1" );
			assert ( ! empty ( $initClientRequestEncoded ) );
			$config = array (
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => PostmanOAuthSmtpEngine::SSL_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_AUTH => PostmanOAuthSmtpEngine::AUTH_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST => $initClientRequestEncoded 
			);
			
			// create the SMTP transport
			$transport = new Zend_Mail_Transport_Smtp ( $hostname, $config );
			$mail->setFrom ( $senderEmail );
			assert ( ! empty ( $transport ) );
			$this->logger->debug ( "Sending mail" );
			$mail->send ( $transport );
		}
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
		private function processRecipiens(Zend_Mail $mail) {
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
		private function processBody(Zend_Mail $mail) {
			if ($this->textHtml) {
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
				$this->logger->debug ( "from Header ignored" );
				// not allowed in OAuth
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
				$this->textHtml = true;
				$this->logger->debug ( "content-type Header: " . $value );
				$mail->addHeader ( $name, $value, false );
			} else {
				$this->logger->debug ( "Allowed Header: " . $name . ': ' . $value );
				$mail->addHeader ( $name, $value, false );
			}
		}
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
	}
}
?>
