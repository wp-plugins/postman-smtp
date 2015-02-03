<?php
require_once 'PostmanOAuthSmtpEngine.php';
require_once 'PostmanBasicSslSmtpEngine.php';
require_once 'PostmanBasicTlsSmtpEngine.php';
require_once 'PostmanNoAuthSmtpEngine.php';

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
	public function createSmtpEngine(PostmanOptions $options, PostmanAuthorizationToken $authorizationToken) {
		if ($options->getAuthorizationType () == PostmanOptions::AUTHORIZATION_TYPE_OAUTH2) {
			// ensure the token is up-to-date
			$this->logger->debug ( 'Ensuring Access Token is up-to-date' );
			// interact with the Authentication Manager
			$wpMailAuthManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options->getAuthorizationType (), $options->getClientId (), $options->getClientSecret (), $authorizationToken );
			if ($wpMailAuthManager->isTokenExpired ()) {
				$this->logger->debug ( 'Access Token has expired, attempting refresh' );
				$wpMailAuthManager->refreshToken ();
				$authorizationToken->save ();
			}
			$engine = new PostmanOAuthSmtpEngine ( $authorizationToken->getAccessToken () );
		} else if ($options->getAuthorizationType () == PostmanOptions::AUTHORIZATION_TYPE_BASIC_SSL) {
			$engine = new PostmanBasicSslSmtpEngine ( $options->getUsername (), $options->getPassword () );
		} else if ($options->getAuthorizationType () == PostmanOptions::AUTHORIZATION_TYPE_BASIC_TLS) {
			$engine = new PostmanBasicTlsSmtpEngine ( $options->getUsername (), $options->getPassword () );
		} else {
			$engine = new PostmanNoAuthSmtpEngine ();
		}
		$this->logger->debug ( 'Created ' . get_class ( $engine ) );
		return $engine;
	}
}