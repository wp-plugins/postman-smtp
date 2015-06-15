<?php
require_once 'PostmanTransportPrivate.php';
if (! class_exists ( 'PostmanGmailApiModuleTransport' )) {
	/**
	 * This class integrates Postman with the Gmail API
	 * http://ctrlq.org/code/19860-gmail-api-send-emails
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanGmailApiModuleTransport implements PostmanTransportPrivate {
		const SLUG = 'gmail_api';
		const PORT = 443;
		const HOST = 'www.googleapis.com';
		const ENCRYPTION_TYPE = 'ssl';
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
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
		public function getHostname() {
			return 'www.googleapis.com';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getHostPort() {
			return self::PORT;
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getAuthenticationType() {
			return 'oauth2';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getSecurityType() {
			return 'https';
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getCredentialsId() {
			return $options->getClientId ();
		}
		/**
		 * v0.2.1
		 *
		 * @return string
		 */
		public function getCredentialsSecret() {
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
		
		/**
		 * This is the only place where the Google library is loaded
		 *
		 * @see PostmanTransport::createZendMailTransport()
		 */
		public function createZendMailTransport($hostname, $config) {
			// This is where the ZendMail special transport is loaded
			require_once 'PostmanGmailApiModuleZendMailTransport.php';
			
			// This is the only place where the Google library is loaded
			require_once 'google-api-php-client-1.1.2/src/Google/Client.php';
			require_once 'google-api-php-client-1.1.2/src/Google/Service/Gmail.php';
			
			// build the Gmail Client
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
			$config [PostmanGmailApiModuleZendMailTransport::SERVICE_OPTION] = $service;
			return new PostmanGmailApiModuleZendMailTransport ( $hostname, $config );
		}
		public function getDeliveryDetails() {
			$deliveryDetails ['auth_desc'] = _x ( 'OAuth 2.0', 'Authentication Type is OAuth 2.0', 'postman-smtp' );
			/* translators: %s is the Authentication Type (e.g. OAuth 2.0) */
			return sprintf ( __ ( 'Postman will send mail via the <b>🔐Gmail API</b> using %s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails ['auth_desc'] . '</b>' );
		}
		/**
		 * If the Transport is not properly configured, the MessageHandler warns the user,
		 * and WpMailBind refuses to bind to wp_mail()
		 *
		 * @param PostmanOptions $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// This transport is configured if:
			$configured = true;
			
			// 1. the sender is configured
			$configured &= $this->isSenderConfigured($options);
			
			// 2. for some reason the Gmail API wants a Client ID and Client Secret; Auth Token itself is not good enough.
			$configured &= !$this->isOAuthCredentialsNeeded($options);
			
			return $configured;
		}
		/**
		 * The transport can have all the configuration it needs, but still not be ready for use
		 * Check to see if permission is required from the OAuth 2.0 provider
		 *
		 * @param PostmanOptions $options        	
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
			if ($this->isOAuthCredentialsNeeded ( $options )) {
				return sprintf ( __ ( 'OAuth 2.0 authentication requires a Client ID and Client Secret.', 'postman-smtp' ) );
			} else if (! $this->isSenderConfigured ( $options )) {
				return __ ( 'Envelope From Address and Message From Address can not be empty.', 'postman-smtp' );
			} else if ($this->isPermissionNeeded ( $token )) {
				$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
				$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanUtils::getGrantOAuthPermissionUrl (), $scribe->getRequestPermissionLinkText () );
				return $message;
			}
		}
		private function isOAuthCredentialsNeeded(PostmanOptions $options) {
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			return empty ( $clientId ) || empty ( $clientSecret );
		}
		private function isSenderConfigured(PostmanOptions $options) {
			$envelopeFrom = $options->getEnvelopeSender ();
			$messageFrom = $options->getMessageSenderEmail ();
			return ! (empty ( $envelopeFrom ) || empty ( $messageFrom ));
		}
		private function isPermissionNeeded(PostmanOAuthToken $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			$oauthVendor = $token->getVendorName ();
			return $oauthVendor != 'google' || empty ( $accessToken ) || empty ( $refreshToken );
		}
		
		/**
		 *
		 * @deprecated
		 *
		 * @see PostmanTransport::getHostsToTest()
		 */
		public function getHostsToTest($hostname) {
			return $this->getSocketsForSetupWizardToProbe ( $hostname, $hostname == 'smtp.gmail.com' );
		}
		/**
		 * Given a hostname, what ports should we test?
		 *
		 * May return an array of several combinations.
		 */
		public function getSocketsForSetupWizardToProbe($hostname, $isGmail) {
			$hosts = array ();
			if ($isGmail) {
				$hosts = array (
						array (
								'host' => self::HOST,
								'port' => self::PORT 
						) 
				);
			}
			return $hosts;
		}
		
		/**
		 *
		 * @deprecated (non-PHPdoc)
		 * @see PostmanTransport::getConfigurationRecommendation()
		 */
		public function getConfigurationRecommendation($hostData) {
			return $this->getConfigurationBid ( $hostData, '' );
		}
		/**
		 * Postman Gmail API supports delivering mail with these parameters:
		 *
		 * 70 gmail api on port 465 to www.googleapis.com
		 *
		 * @param unknown $hostData        	
		 */
		public function getConfigurationBid($hostData, $userAuthOverride, $originalSmtpServer) {
			$port = $hostData ['port'];
			$hostname = $hostData ['hostname'];
			$oauthPotential = ($hostname == self::HOST);
			$recommendation = array ();
			$recommendation ['priority'] = 0;
			$recommendation ['transport'] = self::SLUG;
			$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_NONE;
			$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
			$recommendation ['port'] = null;
			$recommendation ['hostname'] = null;
			$recommendation ['display_auth'] = 'oauth2';
			if ($oauthPotential) {
				if ($port == self::PORT) {
					/* translators: where %d is the port number */
					$recommendation ['message'] = sprintf ( __ ( 'Postman recommends Gmail API configuration on port %d' ), self::PORT );
					$recommendation ['priority'] = 27000;
				}
			}
			return $recommendation;
		}
		/**
		 *
		 * @deprecated
		 *
		 * @see PostmanTransport::createPostmanMailAuthenticator()
		 */
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken) {
		}
	}
}
