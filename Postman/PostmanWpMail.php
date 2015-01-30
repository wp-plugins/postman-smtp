<?php
if (! class_exists ( "PostmanWpMail" )) {
	
	require_once 'Postman-Core/PostmanSmtpEngineFactory.php';
	
	/**
	 * Moved this code into a class so it could be used by both wp_mail() and PostmanSendTestEmailController
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanWpMail {
		private $exception;
		public function send(PostmanOptions $wpMailOptions, PostmanAuthorizationToken $wpMailAuthorizationToken, $to, $subject, $message, $headers = '', $attachments = array()) {
			$logger = new PostmanLogger ( get_class ( $this ) );
			try {
				// ensure the token is up-to-date
				$logger->debug ( 'Ensuring Access Token is up-to-date' );
				
				// interact with the Authentication Manager
				$wpMailAuthManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $wpMailOptions->getClientId (), $wpMailOptions->getClientSecret (), $wpMailAuthorizationToken );
				if ($wpMailAuthManager->isTokenExpired ()) {
					$logger->debug ( 'Access Token has expired, attempting refresh' );
					$wpMailAuthManager->refreshToken ();
					$wpMailAuthorizationToken->save ();
				}
				// send the message
				$logger->debug ( 'Sending mail' );
				
				// interact with the SMTP Engine
				$engine = PostmanSmtpEngineFactory::getInstance ()->createSmtpEngine ( $wpMailOptions, $wpMailAuthorizationToken );
				$engine->setBody ( $message );
				$engine->setSubject ( $subject );
				$engine->setReceipients ( $to );
				$engine->setHeaders ( $headers );
				$engine->setAttachments ( $attachments );
				$engine->send ( $wpMailOptions->getHostname (), $wpMailOptions->getPort () );
				return true;
			} catch ( Exception $e ) {
				$this->exception = $e;
				$logger->debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
		}
		public function getException() {
			return $this->exception;
		}
	}
}
?>