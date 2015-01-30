<?php
if (! class_exists ( "PostmanWpMail" )) {
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
				$wpMailAuthManager = PostmanAuthenticationManagerFactory::createAuthenticationManager ( $wpMailOptions->getClientId (), $wpMailOptions->getClientSecret (), $wpMailAuthorizationToken );
				if ($wpMailAuthManager->isTokenExpired ()) {
					$logger->debug ( 'Access Token has expired, attempting refresh' );
					$wpMailAuthManager->refreshToken ();
					$wpMailAuthorizationToken->save ();
				}
				// send the message
				$this->engine = new PostmanOAuthSmtpEngine ( $wpMailOptions->getSenderEmail (), $wpMailAuthorizationToken->getAccessToken () );
				$this->engine->setBodyText ( $message );
				$this->engine->setSubject ( $subject );
				$this->engine->addTo ( $to );
				$this->engine->send ( $wpMailOptions->getHostname (), $wpMailOptions->getPort () );
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