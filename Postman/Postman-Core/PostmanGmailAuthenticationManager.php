<?php
if (! class_exists ( "PostmanGmailAuthenticationManager" )) {
	
	require_once 'PostmanAuthenticationManager.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanGmailAuthenticationManager implements PostmanAuthenticationManager {
		
		// constants
		const FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE = 60;
		const SCOPE = 'https://mail.google.com/';
		const APPROVAL_PROMPT = 'force';
		const ACCESS_TYPE = 'offline';
		const ACCESS_TOKEN = 'access_token';
		const REFRESH_TOKEN = 'refresh_token';
		const EXPIRES = 'expires_in';
		
		// the oauth authorization options
		private $clientId;
		private $clientSecret;
		private $authorizationToken;
		private $logger;
		
		/**
		 * Constructor
		 */
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			$this->authorizationToken = $authorizationToken;
			require_once 'google-api-php-client-1.1.2/autoload.php';
		}
		
		/**
		 */
		private function createGoogleClient() {
			assert ( ! empty ( $this->clientId ) );
			assert ( ! empty ( $this->clientSecret ) );
			
			// Create the Client
			$client = new Google_Client ();
			// Set Basic Client info as established at the beginning of the file
			$client->setClientId ( $this->clientId );
			$client->setClientSecret ( $this->clientSecret );
			$client->setRedirectUri ( POSTMAN_HOME_PAGE_URL );
			$client->setScopes ( PostmanGmailAuthenticationManager::SCOPE );
			// Set this to 'force' in order to get a new refresh_token.
			// Useful if you had already granted access to this application.
			$client->setApprovalPrompt ( PostmanGmailAuthenticationManager::APPROVAL_PROMPT );
			// Critical in order to get a refresh_token, otherwise it's not provided in the response.
			$client->setAccessType ( PostmanGmailAuthenticationManager::ACCESS_TYPE );
			
			$google_oauthV2 = new Google_Service_Oauth2 ( $client );
			return $client;
		}
		
		/**
		 */
		public function isTokenExpired() {
			$expireTime = ($this->authorizationToken->getExpiryTime () - PostmanGmailAuthenticationManager::FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE);
			$tokenHasExpired = time () > $expireTime;
			$this->logger->debug ( 'Access Token Expiry Time is ' . $expireTime . ', expired=' . ($tokenHasExpired ? 'yes' : 'no') );
			return $tokenHasExpired;
		}
		
		/**
		 */
		public function refreshToken() {
			$this->logger->debug ( 'Refreshing Token' );
			$client = $this->createGoogleClient ();
			$refreshToken = $this->authorizationToken->getRefreshToken ();
			assert ( ! empty ( $refreshToken ) );
			$client->refreshToken ( $refreshToken );
			$this->decodeReceivedAuthorizationToken ( $client );
		}
		
		/**
		 * **********************************************
		 * Make an API request on behalf of a user.
		 * In this case we need to have a valid OAuth 2.0
		 * token for the user, so we need to send them
		 * through a login flow. To do this we need some
		 * information from our API console project.
		 * **********************************************
		 */
		public function authenticate($gmailAddress) {
			assert ( ! empty ( $gmailAddress ) );
			$client = $this->createGoogleClient ();
			$client->setLoginHint ( $gmailAddress );
			$this->logger->debug ( "authenticating with google: loginHint=" . $gmailAddress );
			$_SESSION [PostmanGmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] = 'true';
			$authUrl = $client->createAuthUrl ();
			header ( 'Location: ' . filter_var ( $authUrl, FILTER_SANITIZE_URL ) );
			exit ();
		}
		
		/**
		 * **********************************************
		 * If we have a code back from the OAuth 2.0 flow,
		 * we need to exchange that with the authenticate()
		 * function.
		 * We store the resultant access token
		 * bundle in the session, and redirect to ourself.
		 * **********************************************
		 */
		public function tradeCodeForToken() {
			if (isset ( $_GET ['code'] )) {
				$code = $_GET ['code'];
				$this->logger->debug ( 'Found authorization code in request header' );
				$client = $this->createGoogleClient ();
				$client->authenticate ( $code );
				$this->decodeReceivedAuthorizationToken ( $client );
				return true;
			} else {
				$this->logger->debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		
		/**
		 * Parses the authorization token and extracts the expiry time, accessToken, and if this is a first-time authorization, a refresh token.
		 *
		 * @param unknown $client        	
		 */
		private function decodeReceivedAuthorizationToken(Google_Client $client) {
			$newtoken = json_decode ( $client->getAccessToken () );
			
			// update expiry time
			$newExpiryTime = time () + $newtoken->{PostmanGmailAuthenticationManager::EXPIRES};
			$this->authorizationToken->setExpiryTime ( $newExpiryTime );
			$this->logger->debug ( 'Updating Access Token Expiry Time' );
			
			// update acccess token
			$newAccessToken = $newtoken->{PostmanGmailAuthenticationManager::ACCESS_TOKEN};
			$this->authorizationToken->setAccessToken ( $newAccessToken );
			$this->logger->debug ( 'Updating Access Token' );
			
			// update refresh token, if there is one
			if (isset ( $newtoken->{PostmanGmailAuthenticationManager::REFRESH_TOKEN} )) {
				$newRefreshToken = $newtoken->{PostmanGmailAuthenticationManager::REFRESH_TOKEN};
				$this->authorizationToken->setRefreshToken ( $newRefreshToken );
				$this->logger->debug ( 'Updating Refresh Token' );
			}
		}
		
		public function getAuthorizationToken() {
			return $this->authorizationToken;
		}
	}
}
?>