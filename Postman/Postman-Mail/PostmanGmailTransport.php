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
		private $clientId;
		private $clientSecret;
		private $accessToken;
		private $senderEmail;
		private $logger;
		const SLUG = 'gmail_api';
		public function __construct($clientId, $clientSecret, $senderEmail, $accessToken) {
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			$this->accessToken = $accessToken;
			$this->logger = new PostmanLogger ( 'PostmanGmailApiTransport' );
			$this->senderEmail = $senderEmail;
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
			assert ( ! empty ( $this->clientId ) );
			assert ( ! empty ( $this->clientSecret ) );
			assert ( ! empty ( $this->accessToken ) );
			$client = new Google_Client ();
			$client->setClientId ( $this->clientId );
			$client->setClientSecret ( $this->clientSecret );
			$client->setRedirectUri ( '' );
			// rebuild the google access token
			$token = new stdClass();
			$token->access_token = $this->accessToken;
			$token->refresh_token = $this->accessToken;
			$token->token_type = 'Bearer';
			$token->expires_in = 3600;
			$token->id_token = null;
			$token->created = 0;
			$client->setAccessToken ( json_encode ( $token ) );
			// We only need permissions to compose and send emails
			$client->addScope ( "https://www.googleapis.com/auth/gmail.compose" );
			$service = new Google_Service_Gmail ( $client );
			$config [PostmanZendMailTransportGmailApi::SERVICE_OPTION] = $service;
			$config [PostmanZendMailTransportGmailApi::SENDER_EMAIL_OPTION] = $this->senderEmail;
			$this->logger->debug('Sender Email='.$this->senderEmail);
			return new PostmanZendMailTransportGmailApi ( $hostname, $config );
		}
		public function getDeliveryDetails() {
			return $this->getName ();
		}
	}
}
