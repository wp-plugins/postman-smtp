<?php
if (! class_exists ( 'PostmanGmailApiTransport' )) {
	/**
	 * This class integrates Postman with the Gmail API
	 * http://ctrlq.org/code/19860-gmail-api-send-emails
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanGmailApiTransport implements PostmanTransport {
		const SLUG = 'gmail_api';
		const PORT = 443;
		const HOST = 'www.googleapis.com';
		const ENCRYPTION_TYPE = 'ssl';
		private $logger;
		private $pluginData;
		public function __construct($pluginData) {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->pluginData = $pluginData;
		}
		
		// this should be standard across all transports
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'Gmail API', 'Transport Name', 'postman-smtp' );
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getVersion() {
			return $this->pluginData ['Version'];
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getHostname(PostmanOptionsInterface $options) {
			return 'www.googleapis.com';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getHostPort(PostmanOptionsInterface $options) {
			return self::PORT;
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getAuthenticationType(PostmanOptionsInterface $options) {
			return 'oauth2';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getSecurityType(PostmanOptionsInterface $options) {
			return 'https';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getCredentialsId(PostmanOptionsInterface $options) {
			return $options->getClientId ();
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getCredentialsSecret(PostmanOptionsInterface $options) {
			return $options->getClientSecret ();
		}
		public function isServiceProviderGoogle($hostname) {
			return true;
		}
		public function isServiceProviderMicrosoft($hostname) {
			return false;
		}
		public function isServiceProviderYahoo($hostname) {
			return false;
		}
		public function isOAuthUsed($authType) {
			return true;
		}
		public function isTranscriptSupported() {
			return false;
		}
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken) {
			require_once 'PostmanGmailApiMailAuthenticator.php';
			return new PostmanGmailApiMailAuthenticator ( $options, $authToken, $this->pluginData['Version'] );
		}
		public function createZendMailTransport($hostname, $config) {
			require_once 'PostmanZendMailTransportGmailApi.php';
			require_once 'google-api-php-client-1.1.2/src/Google/Client.php';
			require_once 'google-api-php-client-1.1.2/src/Google/Service/Gmail.php';
			$options = PostmanOptions::getInstance ();
			$authToken = PostmanOAuthToken::getInstance ();
			$client = new Postman_Google_Client ();
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
			$service = new Postman_Google_Service_Gmail ( $client );
			$config [PostmanZendMailTransportGmailApi::SERVICE_OPTION] = $service;
			return new PostmanZendMailTransportGmailApi ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
			$deliveryDetails ['auth_desc'] = _x ( 'OAuth 2.0', 'Authentication Type is OAuth 2.0', 'postman-smtp' );
			/* translators: %s is the Authentication Type (e.g. OAuth 2.0) */
			return sprintf ( __ ( 'Postman will send mail via the <b>Gmail API</b> using %s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails ['auth_desc'] . '</b>' );
		}
		/**
		 * If the Transport is not properly configured, the MessageHandler warns the user,
		 * and WpMailBind refuses to bind to wp_mail()
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// This transport is configured if:
			$configured = true;
			
			// 1. there is a sender email address
			$senderEmailAddress = $options->getSenderEmail ();
			$configured &= ! empty ( $senderEmailAddress );
			
			// 2. for some reason the Gmail API wants a Client ID and Client Secret; Auth Token itself is not good enough.
			$clientId = $options->getClientId ();
			$configured &= ! empty ( $clientId );
			$clientSecret = $options->getClientSecret ();
			$configured &= ! empty ( $clientSecret );
			
			return $configured;
		}
		/**
		 * The transport can have all the configuration it needs, but still not be ready for use
		 * Check to see if permission is required from the OAuth 2.0 provider
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// 1. is the transport configured
			$configured = $this->isConfigured ( $options, $token );
			
			// 2. do we have permission from the OAuth 2.0 provider
			$configured &= ! $this->isPermissionNeeded ( $token );
			
			return $configured;
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			if ($this->isConfigurationNeeded ( $options )) {
				return sprintf ( __ ( 'The Gmail API transport requires a Sender Email Address, Client ID and Client Secret.', 'postman-smtp' ) );
			} else if ($this->isPermissionNeeded ( $token )) {
				$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
				$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanAdminController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $scribe->getRequestPermissionLinkText () );
				return $message;
			}
		}
		private function isConfigurationNeeded(PostmanOptionsInterface $options) {
			$senderEmail = $options->getSenderEmail ();
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			return empty ( $senderEmail ) || empty ( $clientId ) || empty ( $clientSecret );
		}
		private function isPermissionNeeded(PostmanOAuthTokenInterface $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			$oauthVendor = $token->getVendorName ();
			return $oauthVendor != PostmanGoogleAuthenticationManager::VENDOR_NAME || empty ( $accessToken ) || empty ( $refreshToken );
		}
		
		/**
		 * Given a hostname, what ports should we test?
		 *
		 * May return an array of several combinations.
		 */
		public function getHostsToTest($hostname, $isGmail) {
			$hosts = array ();
			if ($isGmail) {
				$hosts = array (
						array (
								'host' => self::HOST,
								'port' => self::PORT 
						) 
				);
			}
			$this->logger->debug ( 'hosts to test:' );
			$this->logger->debug ( $hosts );
			return $hosts;
		}
		
		/**
		 * Postman Gmail API supports delivering mail with these parameters:
		 *
		 * 70 gmail api on port 465 to www.googleapis.com
		 *
		 * @param unknown $hostData        	
		 */
		public function getConfigurationRecommendation($hostData) {
			$port = $hostData ['port'];
			$hostname = $hostData ['host'];
			$oauthPotential = ($hostname == self::HOST);
			if ($oauthPotential) {
				if ($port == self::PORT) {
					$recommendation ['success'] = true;
					/* translators: where %d is the port number */
					$recommendation ['message'] = sprintf ( __ ( 'Postman recommends Gmail API configuration on port %d' ), self::PORT );
					$recommendation ['transport'] = self::SLUG;
					$recommendation ['priority'] = 19500;
					$recommendation ['enc'] = null;
					$recommendation ['secure'] = true;
					$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
					$recommendation ['port'] = null;
					$recommendation ['hostname'] = null;
					$recommendation ['display_auth'] = 'oauth2';
					return $recommendation;
				}
			}
		}
	}
}
