<?php
if (! class_exists ( 'PostmanGmailApiTransport' )) {
	require_once 'PostmanZendMailTransportGmailApi.php';
	require_once 'google-api-php-client-1.1.2/src/Google/Client.php';
	require_once 'google-api-php-client-1.1.2/src/Google/Service/Gmail.php';
	/**
	 * This class integrates Postman with the Gmail API
	 * http://ctrlq.org/code/19860-gmail-api-send-emails
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanGmailApiTransport implements PostmanTransport {
		const SLUG = 'gmail_api';
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		public function isSmtp() {
			return false;
		}
		public function isGoogleOAuthRequired() {
			return true;
		}
		public function isTranscriptSupported() {
			return false;
		}
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'Gmail API', 'Transport Name' );
		}
		public function createZendMailTransport($hostname, $config) {
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanOAuthToken::getInstance ();
			$client = new Google_Client ();
			$client->setClientId ( $options->getClientId () );
			$client->setClientSecret ( $options->getClientSecret () );
			$client->setRedirectUri ( '' );
			// rebuild the google access token
			$token = new stdClass ();
			$token->access_token = $authToken->getAccessToken ();
			$token->refresh_token = $authToken->getRefreshToken ();
			$token->token_type = 'Bearer';
			$token->expires_in = 3600;
			$token->id_token = null;
			$token->created = 0;
			$client->setAccessToken ( json_encode ( $token ) );
			// We only need permissions to compose and send emails
			$client->addScope ( "https://www.googleapis.com/auth/gmail.compose" );
			$service = new Google_Service_Gmail ( $client );
			$config [PostmanZendMailTransportGmailApi::SERVICE_OPTION] = $service;
			return new PostmanZendMailTransportGmailApi ( $hostname, $config );
		}
		public function getDeliveryDetails() {
			return $this->getName ();
		}
	}
}
