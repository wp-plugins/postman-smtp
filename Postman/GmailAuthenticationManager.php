<?php

namespace Postman {

	require_once 'AuthenticationManager.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class GmailAuthenticationManager implements AuthenticationManager {
		
		// constants
		const FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE = 60;
		const AUTHORIZATION_IN_PROGRESS = 'AUTHORIZATION_IN_PROGRESS';
		const SCOPE = 'https://mail.google.com/';
		const APPROVAL_PROMPT = 'force';
		const ACCESS_TYPE = 'offline';
		const ACCESS_TOKEN = 'access_token';
		const REFRESH_TOKEN = 'refresh_token';
		const EXPIRES = 'expires_in';
		
		// the oauth authorization options
		private $options;
		
		/**
		 * Constructor
		 */
		public function __construct(&$options) {
			$this->options = &$options;
			require_once 'google-api-php-client-1.1.2/autoload.php';
		}
		
		/**
		 */
		private function createGoogleClient() {
			$clientId = OptionsUtil::getClientId ( $this->options );
			assert ( ! empty ( $clientId ) );
			$clientSecret = OptionsUtil::getClientSecret ( $this->options );
			assert ( ! empty ( $clientSecret ) );
			
			// Create the Client
			$client = new \Google_Client ();
			// Set Basic Client info as established at the beginning of the file
			$client->setClientId ( $clientId );
			$client->setClientSecret ( $clientSecret );
			$client->setRedirectUri ( OAUTH_REDIRECT_URL );
			$client->setScopes ( GmailAuthenticationManager::SCOPE );
			// Set this to 'force' in order to get a new refresh_token.
			// Useful if you had already granted access to this application.
			$client->setApprovalPrompt ( GmailAuthenticationManager::APPROVAL_PROMPT );
			// Critical in order to get a refresh_token, otherwise it's not provided in the response.
			$client->setAccessType ( GmailAuthenticationManager::ACCESS_TYPE );
			
			$google_oauthV2 = new \Google_Service_Oauth2 ( $client );
			return $client;
		}
		
		/**
		 */
		public function isTokenExpired() {
			$expireTime = (OptionsUtil::getTokenExpiryTime ( $this->options ) - GmailAuthenticationManager::FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE);
			debug ( "Expiry time is " . $expireTime );
			return time () > $expireTime;
		}
		
		/**
		 */
		public function refreshToken() {
			debug ( 'Refreshing Token' );
			$client = $this->createGoogleClient ();
			$refreshToken = OptionsUtil::getRefreshToken ( $this->options );
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
			$client = $this->createGoogleClient ();
			$client->setLoginHint ( $gmailAddress );
			debug ( "authenticating with google: loginHint=" . $gmailAddress );
			$_SESSION [GmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] = 'true';
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
				debug ( 'Found authorization code in request header' );
				$client = $this->createGoogleClient ();
				$client->authenticate ( $code );
				$this->decodeReceivedAuthorizationToken ( $client );
				return true;
			} else {
				debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		
		/**
		 * Parses the authorization token and extracts the expiry time, accessToken, and if this is a first-time authorization, a refresh token.
		 *
		 * @param unknown $client        	
		 */
		private function decodeReceivedAuthorizationToken(\Google_Client $client) {
			$newtoken = json_decode ( $client->getAccessToken () );
			
			// update expiry time
			$newExpiryTime = time () + $newtoken->{GmailAuthenticationManager::EXPIRES};
			debug ( 'Updating Access Token Expiry Time=' . $newExpiryTime );
			OptionsUtil::updateTokenExpiryTime ( $newExpiryTime, $this->options );
			
			// update acccess token
			$newAccessToken = $newtoken->{GmailAuthenticationManager::ACCESS_TOKEN};
			debug ( 'Updating Access Token=' . $newAccessToken );
			OptionsUtil::updateAccessToken ( $newAccessToken, $this->options );
			
			// update refresh token, if there is one
			if (isset ( $newtoken->{GmailAuthenticationManager::REFRESH_TOKEN} )) {
				$newRefreshToken = $newtoken->{GmailAuthenticationManager::REFRESH_TOKEN};
				debug ( 'Updating Refresh Token=' . $newRefreshToken );
				OptionsUtil::updateRefreshToken ( $newRefreshToken, $this->options );
			}
			
			// save to database
			update_option ( POSTMAN_OPTIONS, $this->options );
		}
		
		/*
		 * Accessors
		 */
		public function setAuthenticationToken(AuthenticationToken $authenticationToken) {
			$this->authenticationToken = $authenticationToken;
		}
		public function getAuthenticationToken() {
			return $this->authenticationToken;
		}
	}
}
?>