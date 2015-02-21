<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
	require_once 'PostmanGoogleAuthenticationManager.php';
	require_once 'PostmanMicrosoftAuthenticationManager.php';
	require_once 'PostmanNonOAuthAuthenticationManager.php';
	require_once 'PostmanYahooAuthenticationManager.php';
	
	//
	class PostmanAuthenticationManagerFactory {
		private $logger;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanAuthenticationManagerFactory ();
			}
			return $inst;
		}
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		public function createAuthenticationManager(PostmanTransport $transport, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanConfigTextHelper $scribe = null) {
			$authenticationType = $options->getAuthorizationType ();
			$hostname = $options->getHostname ();
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			$senderEmail = $options->getSenderEmail ();
			if (! isset ( $scribe )) {
				$transport = PostmanTransportUtils::getCurrentTransport ();
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $hostname );
			}
			$redirectUrl = $scribe->getCallbackUrl ();
			if ($transport->isOAuthUsed ( $authorizationToken ) && $scribe->isGoogle ()) {
				$authenticationManager = new PostmanGoogleAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl, $senderEmail );
			} else if ($transport->isOAuthUsed ( $authorizationToken ) && $scribe->isMicrosoft ()) {
				$authenticationManager = new PostmanMicrosoftAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl );
			} else if ($transport->isOAuthUsed ( $authorizationToken ) && $scribe->isYahoo ()) {
				$authenticationManager = new PostmanYahooAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl );
			} else {
				$authenticationManager = new PostmanNonOAuthAuthenticationManager ();
			}
			$this->logger->debug ( 'Created ' . get_class ( $authenticationManager ) );
			return $authenticationManager;
		}
	}
}