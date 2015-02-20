<?php
require_once 'PostmanOAuthSmtpEngine.php';
require_once 'PostmanPasswordAuthSmtpEngine.php';
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
	public function createSmtpEngine(PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanTransport $transport) {
		if ($options->isAuthTypeOAuth2 ()) {
			// ensure the token is up-to-date
			$this->logger->debug ( 'Ensuring Access Token is up-to-date' );
			// interact with the Authentication Manager
			$wpMailAuthManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options, $authorizationToken );
			if ($wpMailAuthManager->isAccessTokenExpired ()) {
				$this->logger->debug ( 'Access Token has expired, attempting refresh' );
				$wpMailAuthManager->refreshToken ();
				$authorizationToken->save ();
			}
			$engine = new PostmanOAuthSmtpEngine($authorizationToken->getAccessToken (), $options->getAuthorizationType(), $options->getEncryptionType());
		} else if ($options->isAuthTypeNone ()) {
			$engine = new PostmanNoAuthSmtpEngine ();
		} else {
			$engine = new PostmanPasswordAuthSmtpEngine ( $options->getUsername (), $options->getPassword (), $options->getAuthorizationType (), $options->getEncryptionType () );
		}
		$this->logger->debug ( 'Created ' . get_class ( $engine ) );
		$engine->setTransport($transport);
		return $engine;
	}
}