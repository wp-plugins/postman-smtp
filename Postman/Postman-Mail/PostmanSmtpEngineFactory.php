<?php
require_once 'PostmanMailAuthenticator.php';

/**
 *
 * @author jasonhendriks
 *        
 */
class PostmanSmtpEngineFactory {
	private $logger;
	
	// singleton instance
	public static function getInstance() {
		static $inst = null;
		if ($inst === null) {
			$inst = new PostmanSmtpEngineFactory ();
		}
		return $inst;
	}
	private function __construct() {
		$this->logger = new PostmanLogger ( get_class ( $this ) );
	}
	
	/**
	 *
	 * @param PostmanOptions $options        	
	 * @param PostmanOAuthToken $authorizationToken        	
	 * @return PostmanSmtpEngine
	 */
	public function createSmtpEngine(PostmanOptions $options, PostmanOAuthToken $authorizationToken) {
		$transport = PostmanTransportUtils::getCurrentTransport ();
		assert ( isset ( $transport ) );
		$authenticator = $transport->createPostmanMailAuthenticator ( $options, $authorizationToken );
		if ($options->isAuthTypeOAuth2 ()) {
			$this->ensureAuthtokenIsUpdated ( $transport, $options, $authorizationToken );
		}
		$engine = new PostmanSmtpEngine ( $authenticator, $transport );
		return $engine;
	}
	
	/**
	 */
	private function ensureAuthtokenIsUpdated(PostmanTransport $transport, PostmanOptions $options, PostmanOAuthToken $authorizationToken) {
		// ensure the token is up-to-date
		$this->logger->debug ( 'Ensuring Access Token is up-to-date' );
		// interact with the Authentication Manager
		$wpMailAuthManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $transport, $options, $authorizationToken );
		if ($wpMailAuthManager->isAccessTokenExpired ()) {
			$this->logger->debug ( 'Access Token has expired, attempting refresh' );
			$wpMailAuthManager->refreshToken ();
			$authorizationToken->save ();
		}
	}
}