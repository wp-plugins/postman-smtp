<?php
if (! class_exists ( "PostmanWpMail" )) {
	class PostmanWpMail {
		private $logger;
		private $options;
		private $exception;
		function __construct(&$options) {
			assert ( ! empty ( $options ) );
			$this->options = $options;
			$this->logger = new PostmanLogger ();
		}
		public function send($to, $subject, $message, $headers = '', $attachments = array()) {
			
			// ensure the token is up-to-date
			try {
				$clientId = PostmanOptionUtil::getClientId($this->options);
				$clientSecret = PostmanOptionUtil::getClientSecret($this->options);
				$authorizationToken = new PostmanAuthorizationToken();
				$authorizationToken->load();
				$authenticationManager = new GmailAuthenticationManager($clientId, $clientSecret, $authorizationToken);
				if ($authenticationManager->isTokenExpired () || true) {
					$this->logger->debug ( 'Access Token has expired, attempting refresh' );
					$authenticationManager->refreshToken ();
					$authorizationToken->save();
				}
			} catch ( Exception $e ) {
				$this->exception = $e;
				$this->logger->debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				return false;
			}
			
			// send the message
			$this->engine = new PostmanOAuthSmtpEngine ( PostmanOptionUtil::getSenderEmail($this->options), $authorizationToken->getAccessToken() );
			$this->engine->setBodyText ( $message );
			$this->engine->setSubject ( $subject );
			$this->engine->addTo ( $to );
			
			$result = $this->engine->send(PostmanOptionUtil::getHostname($this->options), PostmanOptionUtil::getPort($this->options));
			if (! $result) {
				$this->exception = $this->engine->getException ();
			}
			
			return $result;
		}
		public function getException() {
			return $this->exception;
		}
	}
}
?>