<?php

namespace Postman {

	require_once WP_PLUGIN_DIR . '/postman-smtp/Postman/AuthenticationManager.php';
	
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Client.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Service/Oauth2.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Model.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Service.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Service/Resource.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Config.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Google/Auth/OAuth2.php';
	
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
		private $authenticationToken;
		
		/**
		 * Constructor
		 */
		public function __construct(AuthenticationToken $authenticationToken, $gmailAddress) {
			$this->authenticationToken = $authenticationToken;
			// needs predictable access to the session
			session_start ();
		}
		
		/**
		 */
		private function createGoogleClient() {
			// print "<br/>client id=" . $this->google_client_id;
			// print "<br/>client secret=" . $this->google_client_secret;
			// print "<br/>redirect=" . $this->google_redirect_url;
			
			// Create the Client
			$client = new \Google_Client ();
			// Set Basic Client info as established at the beginning of the file
			$client->setClientId ( $this->authenticationToken->getClientId () );
			$client->setClientSecret ( $this->authenticationToken->getClientSecret () );
			$client->setRedirectUri ( OAUTH_REDIRECT_URL );
			$client->setLoginHint($gmailAddress);
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
		function isTokenExpired() {
			$expireTime = ($this->authenticationToken->getExpiryTime () - FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE);
			return time () > $expireTime;
		}
		
		/**
		 */
		function refreshToken() {
			$client = $this->createGoogleClient ();
			$client->refreshToken ( $this->authenticationToken->getRefreshToken () );
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
		function authenticate() {
			$client = $this->createGoogleClient ();
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
		function tradeCodeForToken() {
			if (isset ( $_GET ['code'] )) {
				$client = $this->createGoogleClient ();
				$client->authenticate ( $_GET ['code'] );
				$this->decodeReceivedAuthorizationToken ( $client );
				return true;
			}
		}
		
		/**
		 * Parses the authorization token and extracts the expiry time, accessToken, and if this is a first-time authorization, a refresh token.
		 *
		 * @param unknown $client        	
		 */
		private function decodeReceivedAuthorizationToken(\Google_Client $client) {
			$newtoken = json_decode ( $client->getAccessToken () );
			$this->authenticationToken->setExpiryTime ( (time () + $newtoken->{GmailAuthenticationManager::EXPIRES}) );
			$this->authenticationToken->setAccessToken ( $newtoken->{GmailAuthenticationManager::ACCESS_TOKEN} );
			$refreshToken = $newtoken->{GmailAuthenticationManager::REFRESH_TOKEN};
			if (isset ( $refreshToken )) {
				$this->authenticationToken->setRefreshToken ( $refreshToken );
			}
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