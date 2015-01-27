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
		private $exception;
		
		//
		private $senderEmail;
		private $accessToken;
		
		// constructor
		function __construct($senderEmail, $accessToken) {
			$this->logger = new PostmanLogger ();
			$this->mail = new Zend_Mail ();
			$this->senderEmail= $senderEmail;
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
				$this->logger->debug ( 'Error: \'' . $senderEmail .'\' is not a valid email address' );
				return false;
			}
			$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$accessToken}\1\1" );
			$this->logger->debug ( 'initClientRequest=' . base64_decode ( $initClientRequestEncoded ) );
			assert ( ! empty ( $initClientRequestEncoded ) );
			$config = array (
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => PostmanOAuthSmtpEngine::SSL_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_AUTH => PostmanOAuthSmtpEngine::AUTH_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST => $initClientRequestEncoded 
			);
			
			// create the SMTP transport
			$transport = new Zend_Mail_Transport_Smtp ( $hostname, $config );
			try {
				$this->mail->setFrom ( $senderEmail );
				assert ( ! empty ( $transport ) );
				$this->mail->send ( $transport );
				return true;
			} catch ( \Zend_Mail_Protocol_Exception $e ) {
				$this->exception = $e;
				$this->logger->debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
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
				$t = str_getcsv ( $email );
				foreach ( $t as $k => $v ) {
					if (strpos ( $v, ',' ) !== false) {
						$t [$k] = '"' . str_replace ( ' <', '" <', $v );
					}
					$this->mail->addTo ( trim ( $t [$k] ) );
				}
			} else {
				$this->mail->addTo ( $email, $name );
			}
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
		public function getException() {
			return $this->exception;
		}
	}
}
?>
