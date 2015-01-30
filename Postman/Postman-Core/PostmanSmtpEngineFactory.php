<?php
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
		$engine = new PostmanOAuthSmtpEngine ( $options->getSenderEmail (), $authorizationToken->getAccessToken () );
		$this->logger->debug ( 'Created ' . get_class ( $engine ) );
		return $engine;
	}
}