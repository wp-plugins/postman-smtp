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
		
		// this class wraps Zend_Mail
		private $mail;
		
		//
		private $senderEmail;
		private $accessToken;
		
		// constructor
		function __construct($senderEmail, $accessToken) {
			assert ( ! empty ( $senderEmail ) );
			assert ( ! empty ( $accessToken ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->mail = new Zend_Mail ();
			$this->senderEmail = $senderEmail;
			$this->accessToken = $accessToken;
		}
		
		/**
		 * Verifies the Authentication Token and sends an email
		 *
		 * @return boolean
		 */
		public function send($hostname, $port) {
			$senderEmail = $this->senderEmail;
			$accessToken = $this->accessToken;
			// create the options for authentication
			assert ( ! empty ( $senderEmail ) );
			assert ( ! empty ( $accessToken ) );
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
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
			$this->mail->setFrom ( $senderEmail );
			assert ( ! empty ( $transport ) );
			$this->logger->debug ( "Sending mail" );
			$this->mail->send ( $transport );
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
		public function addTo($email, $name = '') {
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
					$this->mail->addTo ( $tokenizedEmail );
				}
			} else {
				if (! $this->validateEmail ( $email )) {
					$message = 'Recipient e-mail "' . $email . '" is invalid.';
					$this->logger->error ( $message );
					throw new Exception ( $message );
				}
				$this->logger->debug ( "To: " . $email . '(' . $name . ')' );
				$this->mail->addTo ( $email, $name );
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
		function setBodyText($bodyText) {
			$this->mail->setBodyText ( $bodyText );
		}
		function setSubject($subject) {
			$this->mail->setSubject ( $subject );
		}
		/**
		 * unknown $header| Mail headers to send with the message.
		 * (string or array)
		 * For the string version, each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n")
		 *
		 * @todo http://framework.zend.com/manual/1.12/en/zend.mail.additional-headers.html
		 *      
		 */
		function setHeaders($header) {
			// $this->mail->addHeader ( $header );
		}
	}
}
?>
