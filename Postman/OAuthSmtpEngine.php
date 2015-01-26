<?php

namespace Postman {

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
	class OAuthSmtpEngine implements SmtpEngine {
		
		// define some constants
		const ZEND_TRANSPORT_CONFIG_SSL = 'ssl';
		const ZEND_TRANSPORT_CONFIG_PORT = 'port';
		const ZEND_TRANSPORT_CONFIG_AUTH = 'auth';
		const ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST = 'xoauth2_request';
		const AUTH_VALUE = 'oauth2';
		const SSL_VALUE = 'ssl';
		
		//
		private $options;
		
		// this class wraps Zend_Mail
		private $mail;
		private $exception;
		
		// constructor
		function __construct(&$options) {
			assert ( ! empty ( $options ) );
			$this->options = &$options;
			$this->mail = new \Zend_Mail ();
		}
		
		/**
		 * Verifies the Authentication Token and sends an email
		 *
		 * @return boolean
		 */
		public function send() {
			
			// refresh the authentication token, if necessary
			if ($this->verifyAndRefreshAuthToken ()) {
				// send mail
				return $this->sendMail ();
			} else {
				return false;
			}
		}
		
		/**
		 * Uses the Authentication Manager to refresh the token if necessary and saves the updated access token back to the database
		 */
		private function verifyAndRefreshAuthToken() {
			// create an auth manager
			$authenticationManager = new GmailAuthenticationManager ( $this->options );
			
			// ensure the token is up-to-date
			try {
				if ($authenticationManager->isTokenExpired ()) {
					debug ( 'Access Token has expired, attempting refresh' );
					$authenticationManager->refreshToken ();
				}
				return true;
			} catch ( \Exception $e ) {
				$this->exception = $e;
				debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
		}
		public function validateEmail($email) {
			$exp = "/^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$/i";
			return preg_match ( $exp, $email );
		}
		/**
		 * Uses ZendMail to send the message
		 *
		 * @return boolean
		 */
		private function sendMail() {
			// create the options for authentication
			$senderEmail = OptionsUtil::getSenderEmail ( $this->options );
			assert ( ! empty ( $senderEmail ) );
			assert ( $this->validateEmail ( $senderEmail ) );
			$accessToken = OptionsUtil::getAccessToken ( $this->options );
			assert ( ! empty ( $accessToken ) );
			$port = OptionsUtil::getPort ( $this->options );
			assert ( ! empty ( $port ) );
			$hostname = OptionsUtil::getHostname ( $this->options );
			assert ( ! empty ( $hostname ) );
			$initClientRequestEncoded = base64_encode ( "user={$senderEmail}\1auth=Bearer {$accessToken}\1\1" );
			debug ( 'initClientRequest=' . base64_decode ( $initClientRequestEncoded ) );
			assert ( ! empty ( $initClientRequestEncoded ) );
			$config = array (
					OAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => OAuthSmtpEngine::SSL_VALUE,
					OAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					OAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_AUTH => OAuthSmtpEngine::AUTH_VALUE,
					OAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST => $initClientRequestEncoded 
			);
			
			// create the SMTP transport
			$transport = new \Zend_Mail_Transport_Smtp ( $hostname, $config );
			try {
				$this->mail->setFrom ( $senderEmail );
				assert ( ! empty ( $transport ) );
				$this->mail->send ( $transport );
				return true;
			} catch ( \Zend_Mail_Protocol_Exception $e ) {
				$this->exception = $e;
				debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
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
