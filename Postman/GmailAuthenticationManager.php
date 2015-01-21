<?php

namespace Postman {

	require_once WP_PLUGIN_DIR . '/postman/Google/Client.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Service/Oauth2.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Model.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Service.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Service/Resource.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Config.php';
	require_once WP_PLUGIN_DIR . '/postman/Google/Auth/OAuth2.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class GmailAuthenticationManager {
		private $options;
		
		/**
		 * Start up
		 */
		public function __construct() {
			$this->options = get_option ( POSTMAN_OPTIONS );
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
			$client->setClientId ( $this->options ['oauth_client_id'] );
			$client->setClientSecret ( $this->options ['oauth_client_secret'] );
			$client->setRedirectUri ( OAUTH_REDIRECT_URL );
			$client->setScopes ( 'https://mail.google.com/' );
			// Set this to 'force' in order to get a new refresh_token.
			// Useful if you had already granted access to this application.
			$client->setApprovalPrompt ( 'force' );
			// Critical in order to get a refresh_token, otherwise it's not provided in the response.
			$client->setAccessType ( 'offline' );
			
			$google_oauthV2 = new \Google_Service_Oauth2 ( $client );
			return $client;
		}
		
		/**
		 */
		function refreshTokenIfRequired() {
			if (time () > ($this->options ['auth_token_expires'] - 60)) {
				$client = $this->createGoogleClient ();
				$client->refreshToken ( $this->options ['refresh_token'] );
				$this->saveTokens ( $client );
			}
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
			$_SESSION ['SMTP_OAUTH_GMAIL_AUTH_IN_PROGRESS'] = 'true';
			$authUrl = $client->createAuthUrl ();
			header ( 'Location: ' . filter_var ( $authUrl, FILTER_SANITIZE_URL ) );
			die ();
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
			$client = $this->createGoogleClient ();
			unset ( $_SESSION ['SMTP_OAUTH_GMAIL_AUTH_IN_PROGRESS'] );
			if (isset ( $_GET ['code'] )) {
				$client->authenticate ( $_GET ['code'] );
				$this->saveTokens ( $client );
				header ( 'Location: ' . filter_var ( HOME_PAGE_URL, FILTER_SANITIZE_URL ) );
				die ();
			} else {
				// failure - the user probably clicked cancel
				header ( 'Location: ' . filter_var ( HOME_PAGE_URL, FILTER_SANITIZE_URL ) );
				die ();
			}
		}
		
		/**
		 *
		 * @param unknown $client        	
		 */
		private function saveTokens($client) {
			$tokens = json_decode ( $client->getAccessToken () );
			$this->options ['auth_token_expires'] = (time () + $tokens->{'expires_in'});
			print "expires: ".$this->options['auth_token_expires'];
			$this->options ['access_token'] = $tokens->{'access_token'};
			$refreshToken = $tokens->{'refresh_token'};
			if (! empty ( $refreshToken )) {
				$this->options ['refresh_token'] = $refreshToken;
			}
			update_option ( POSTMAN_OPTIONS, $this->options );
		}
	}
}
?>