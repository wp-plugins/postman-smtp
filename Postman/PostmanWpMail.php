<?php
if (! class_exists ( "PostmanWpMail" )) {
	class PostmanWpMail {
		private $logger;
		private $options;
		private $authorizationToken;
		private $exception;
		/**
		 * 
		 * @param unknown $options
		 * @param unknown $authorizationToken - passed by reference as it is potentially altered by the AuthenticationManager
		 */
		function __construct($options, &$authorizationToken) {
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			$this->options = $options;
			$this->authorizationToken = &$authorizationToken;
			$this->logger = new PostmanLogger ();
		}
		public function send($to, $subject, $message, $headers = '', $attachments = array()) {
			try {
				// ensure the token is up-to-date
				$authenticationManager = PostmanAuthenticationManagerFactory::createAuthenticationManager($this->options, $this->authorizationToken );
				if ($authenticationManager->isTokenExpired ()) {
					$this->logger->debug ( 'Access Token has expired, attempting refresh' );
					$authenticationManager->refreshToken ();
					$this->authorizationToken->save ();
				}
				// send the message
				$this->engine = new PostmanOAuthSmtpEngine ( PostmanOptionUtil::getSenderEmail ( $this->options ), $this->authorizationToken->getAccessToken () );
				$this->engine->setBodyText ( $message );
				$this->engine->setSubject ( $subject );
				$this->engine->addTo ( $to );
				$this->engine->send ( PostmanOptionUtil::getHostname ( $this->options ), PostmanOptionUtil::getPort ( $this->options ) );
				return true;
			} catch ( Exception $e ) {
				$this->exception = $e;
				$this->logger->debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
		}
		public function getException() {
			return $this->exception;
		}
	}
}
?>