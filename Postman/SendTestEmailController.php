<?php
if (! class_exists ( "PostmanSendTestEmailController" )) {
	class PostmanSendTestEmailController {
		const SUBJECT = 'WordPress Postman SMTP Test';
		const MESSAGE = 'Hello, World!';
		const EOL = "\r\n";
		
		//
		private $logger;
		
		//
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		/**
		 *
		 * @param unknown $options        	
		 * @param unknown $recipient        	
		 */
		public function send(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, $recipient, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $recipient ) );
			assert ( ! empty ( $messageHandler ) );
			
			$headers = array();
			// $recipient = 'Kevin.Brine@pppg.com, Robert.Thomas@pppg.com, "Guice, Doug" <Doug.Guice@pppg.com>';
			$subject = PostmanSendTestEmailController::SUBJECT;
			// Lines in email are terminated by CRLF ("\r\n") according to RFC2821
			// Englsih - Mandarin - French - Hindi - Spanish - Arabic - Portuguese - Russian - Bengali - Japanese - Punjabi
			$message = 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - السلام عليكم - Olá - Привет! - নমস্কার - 今日は - ਸਤਿ ਸ੍ਰੀ ਅਕਾਲ।';
			$message .= PostmanSendTestEmailController::EOL . PostmanSendTestEmailController::EOL . 'Sent by Postman v' . POSTMAN_PLUGIN_VERSION . ' - https://wordpress.org/plugins/postman-smtp/';
// 			$headers = array (
// 					'Content-Type: text/html;',
// 					'From: Brian <brian@postman.com>' 
// 			);
			
			// send through wp_mail
			$this->logger->debug ( 'Sending Test email' );
			PostmanStats::getInstance ()->disable ();
			$wp_mail_result = wp_mail ( $recipient, $subject, $message, $headers );
			
			if (! $wp_mail_result) {
				$this->logger->error ( 'wp_mail failed :( re-trying through the internal engine' );
				$postmanWpMail = new PostmanWpMail ();
				$postmanWpMailResult = $postmanWpMail->send ( $options, $authorizationToken, $recipient, $subject, $message, $headers );
			}
			PostmanStats::getInstance ()->enable ();
			
			//
			if ($wp_mail_result) {
				$this->logger->debug ( 'Test Email delivered to SMTP server' );
				$messageHandler->addMessage ( 'Your message was delivered to the SMTP server! Congratulations :)' );
			} else if (! $postmanWpMailResult) {
				$this->logger->error ( 'Test Email NOT delivered to SMTP server - ' . $postmanWpMail->getException ()->getCode () );
				if ($postmanWpMail->getException ()->getCode () == 334) {
					$messageHandler->addError ( 'Oh, bother! ... Communication Error [334] - check that your Sender Email is the same as your Gmail account.' );
				} else {
					$messageHandler->addError ( 'Oh, bother! ... ' . $postmanWpMail->getException ()->getMessage () );
				}
			} else {
				$message = 'Something is wrong, sending throgh wp_mail() failed, but sending through internal engine succeeded. Time to debug!';
				$this->logger->error ( $message );
				$messageHandler->addError ( $message );
			}
		}
	}
}
?>