<?php
if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isSmtp();
		public function isGmailApi();
	}
}

if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
		public function isSmtp() {
			return true;
		}
		public function isGmailApi() {
			return false;
		}
	}
}

if (! class_exists ( 'PostmanGmailApiTransport' )) {
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
		private $logger;
		public function __construct($clientId, $clientSecret, $accessToken) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $accessToken ) );
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			$this->accessToken = $accessToken;
			$this->logger = new PostmanLogger ( 'PostmanGmailApiTransport' );
		}
		public function isSmtp() {
			return false;
		}
		public function isGmailApi() {
			return true;
		}
		public function mail() {
			require_once 'google-api-php-client/src/Google/Client.php';
			require_once 'google-api-php-client/src/Google/Service/Gmail.php';
			
			// Replace this with your Google Client ID
			$client_id = $this->clientId;
			$client_secret = $this->clientSecret;
			$redirect_uri = 'http://ctrlq.org/'; // Change this
			
			$client = new Google_Client ();
			$client->setClientId ( $client_id );
			$client->setClientSecret ( $client_secret );
			$client->setRedirectUri ( $redirect_uri );
			
			/*
			 * Google_Client::setAccessToken(string $accessToken)
			 * Set the OAuth 2.0 access token using the string that resulted from calling createAuthUrl() or Google_Client#getAccessToken().
			 *
			 * Parameters:
			 * string $accessToken JSON encoded string containing in the following format: {"access_token":"TOKEN", "refresh_token":"TOKEN", "token_type":"Bearer", "expires_in":3600, "id_token":"TOKEN", "created":1320790426}
			 */
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
			
			// Prepare the message in message/rfc822
			try {
				
				$message = 'From: jason <jasonhendriks3@hotmail.com>' . PostmanSmtpEngine::EOL . 'To: jason@hendriks.ca' . PostmanSmtpEngine::EOL . 'Subject: WordPress Postman SMTP Test' . PostmanSmtpEngine::EOL .  PostmanSmtpEngine::EOL . 'Hello!';
				
				
				
				// The message needs to be encoded in Base64URL
				$mime = rtrim ( strtr ( base64_encode ( $message ), '+/', '-_' ), '=' );
				$msg = new Google_Service_Gmail_Message ();
				$msg->setRaw ( $mime );
				$service->users_messages->send ( "me", $msg );
				return true;
			} catch ( Exception $e ) {
				$this->logger->error ( $e->getMessage () );
				return false;
			}
		}
	}
}