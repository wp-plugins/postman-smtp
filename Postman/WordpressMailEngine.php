<?php

namespace Postman {

	require_once WP_PLUGIN_DIR . '/postman/Zend/Mail/Transport/Smtp.php';
	require_once WP_PLUGIN_DIR . '/postman/Zend/Mail.php';
	
	/**
	 */
	class WordpressMailEngine {
		
		// this class wraps Zend_Mail
		private $mail;
		
		// property declaration
		private $ssl = 'ssl';
		private $auth = 'oauth2';
		private $port = '465';
		private $subject = '';
		
		// required input
		private $email;
		private $token;
		private $server;
		
		//
		function __construct() {
			$gmailAuthenticationManager = new GmailAuthenticationManager();
			$gmailAuthenticationManager->refreshTokenIfRequired();
			$this->mail = new \Zend_Mail ();
		}
		
		// method declaration
		public function send() {
			
			$initClientRequestEncoded = base64_encode ( "user={$this->email}\1auth=Bearer {$this->token}\1\1" );
			$config = array (
					'ssl' => $this->ssl,
					'port' => $this->port,
					'auth' => $this->auth,
					'xoauth2_request' => $initClientRequestEncoded 
			);
			$transport = new \Zend_Mail_Transport_Smtp ( $this->server, $config );
			try {
				$this->mail->send ( $transport );
			} catch ( \Zend_Mail_Protocol_Exception $e ) {
				// renew token and try again
				echo "Caught exception: " . get_class ( $e ) . "\n";
				echo "Message: " . $e->getMessage () . "\n";
				return false;
			}
			return true;
		}
		
		public function toString() {
			print "authEmail=".$this->email."\n";
			print "authToken=".$this->token."\n";
			print "server=".$this->server."\n";
			print "from=".$this->mail->getFrom()."\n";
			print "to=".implode("|",$this->mail->getRecipients())."\n";
			print "subject=".$this->mail->getSubject()."\n";
		}
		
		// setters
		function setAuthEmail($authEmail) {
			$this->email = $authEmail;
		}
		function setAuthToken($token) {
			$this->token = $token;
		}
		function setServer($server) {
			$this->server = $server;
		}
		public function setFrom($email, $name = null) {
			$this->mail->setFrom ( $email, $name );
		}
		public function addTo($email, $name = '') {
			$this->mail->addTo ( $email, $name );
		}
		function setBodyText($bodyText) {
			$this->mail->setBodyText ( $bodyText );
		}
		function setSubject($subject) {
			$this->mail->setSubject ( $subject );
		}
	}
}
?>
