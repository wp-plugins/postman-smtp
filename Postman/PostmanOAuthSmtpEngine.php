<?php

namespace Postman {

	require_once 'PostmanSmtpEngine.php';
	
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
		
		// this class wraps Zend_Mail
		private $mail;
		private $postmanOptions;
		private $authenticationToken;
		private $exception;
		
		// constructor
		function __construct() {
			$this->mail = new \Zend_Mail ();
			$options = get_option ( POSTMAN_OPTIONS );
			$this->postmanOptions = new Options ( $options );
			$this->authenticationToken = new AuthenticationToken ( $options );
		}
		
		/**
		 * Verifies the Authentication Token and sends an email
		 *
		 * @return boolean
		 */
		public function send() {
			
			// refresh the authentication token, if necessary
			$this->verifyAndRefreshAuthToken ();
			
			// send mail
			return $this->sendMail ();
		}
		
		/**
		 * Uses the Authentication Manager to refresh the token if necessary and saves the updated access token back to the database
		 */
		private function verifyAndRefreshAuthToken() {
			// create an auth manager
			$authenticationManager = new GmailAuthenticationManager ( $this->authenticationToken );
			
			// ensure the token is up-to-date
			if ($authenticationManager->isTokenExpired ()) {
				$authenticationManager->refreshToken ();
				$options = get_option ( POSTMAN_OPTIONS );
				$options [Options::ACCESS_TOKEN] = $this->authenticationToken->getAccessToken ();
				$options [Options::TOKEN_EXPIRES] = $this->authenticationToken->getExpiryTime ();
				update_option ( POSTMAN_OPTIONS, $options );
			}
		}
		
		/**
		 * Uses ZendMail to send the message
		 *
		 * @return boolean
		 */
		private function sendMail() {
			// create the options for authentication
			$initClientRequestEncoded = base64_encode ( "user={$this->postmanOptions->getSenderEmail()}\1auth=Bearer {$this->authenticationToken->getAccessToken()}\1\1" );
			$config = array (
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => PostmanOAuthSmtpEngine::SSL_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $this->postmanOptions->getPort (),
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_AUTH => PostmanOAuthSmtpEngine::AUTH_VALUE,
					PostmanOAuthSmtpEngine::ZEND_TRANSPORT_CONFIG_XOAUTH2_REQUEST => $initClientRequestEncoded 
			);
			
			// create the SMTP transport
			$transport = new \Zend_Mail_Transport_Smtp ( $this->postmanOptions->getHostname (), $config );
			try {
				$this->mail->setFrom ( $this->postmanOptions->getSenderEmail () );
				
				if (\Postman\DEBUG) {
					echo "<h3>clientRequest</h3>";
					var_dump ( $initClientRequestEncoded );
					echo "<h3>config</h3>";
					var_dump ( $config );
					echo "<h3>transport</h3>";
					var_dump ( $transport );
					echo "<h3>mail</h3>";
					var_dump ( $this->mail );
					echo "<br/>";
				}
				$this->mail->send ( $transport );
			} catch ( \Zend_Mail_Protocol_Exception $e ) {
				$this->exception = $e;
				if (\Postman\DEBUG) {
					echo "Caught exception: " . get_class ( $e ) . "\n";
					echo "Message: " . $e->getMessage () . "\n";
					echo "Code: " . $e->getCode () . "\n";
				}
				return false;
			}
			return true;
		}
		
		/**
		 * Print the internal state
		 */
		public function toString() {
			if (\Postman\DEBUG) {
				print "authEmail=" . $this->postmanOptions->email . "\n";
				print "authToken=" . $this->authenticationToken->accessToken . "\n";
				print "server=" . $this->postmanOptions->hostname . "\n";
				print "from=" . $this->mail->getFrom () . "\n";
				print "to=" . implode ( "|", $this->mail->getRecipients () ) . "\n";
				print "subject=" . $this->mail->getSubject () . "\n";
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
		 * @todo
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
