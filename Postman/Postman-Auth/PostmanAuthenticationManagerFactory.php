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
			$authenticationType = $options->getAuthenticationType ();
			$hostname = $options->getHostname ();
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			$senderEmail = $options->getMessageSenderEmail ();
			if (! isset ( $scribe )) {
				$transport = PostmanTransportRegistry::getInstance()->getCurrentTransport ();
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $hostname, $transport );
			}
			$redirectUrl = $scribe->getCallbackUrl ();
			if ($transport->isOAuthUsed ( $options->getAuthenticationType () ) && $transport->isServiceProviderGoogle ( $hostname )) {
				$authenticationManager = new PostmanGoogleAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl, $senderEmail );
			} else if ($transport->isOAuthUsed ( $options->getAuthenticationType () ) && $transport->isServiceProviderMicrosoft ( $hostname )) {
				$authenticationManager = new PostmanMicrosoftAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl );
			} else if ($transport->isOAuthUsed ( $options->getAuthenticationType () ) && $transport->isServiceProviderYahoo ( $hostname )) {
				$authenticationManager = new PostmanYahooAuthenticationManager ( $clientId, $clientSecret, $authorizationToken, $redirectUrl );
			} else {
				$authenticationManager = new PostmanNonOAuthAuthenticationManager ();
			}
			$this->logger->debug ( 'Created ' . get_class ( $authenticationManager ) );
			return $authenticationManager;
		}
	}
}