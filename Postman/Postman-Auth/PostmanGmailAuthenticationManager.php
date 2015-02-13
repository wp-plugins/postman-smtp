<?php
if (! class_exists ( "PostmanGmailAuthenticationManager" )) {
	
	require_once 'PostmanAbstractAuthenticationManager.php';
	require_once 'PostmanStateIdMissingException.php';
	
	/**
	 * https://developers.google.com/accounts/docs/OAuth2WebServer
	 * https://developers.google.com/gmail/xoauth2_protocol
	 * https://developers.google.com/gmail/api/auth/scopes
	 */
	class PostmanGmailAuthenticationManager extends PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// This endpoint is the target of the initial request. It handles active session lookup, authenticating the user, and user consent.
		const GOOGLE_ENDPOINT = 'https://accounts.google.com/o/oauth2/auth';
		const GOOGLE_REFRESH = 'https://www.googleapis.com/oauth2/v3/token';
		
		// this scope doesn't work
		// Create, read, update, and delete drafts. Send messages and drafts.
		const SCOPE_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';
		
		// this scope doesn't work
		// All read/write operations except immediate, permanent deletion of threads and messages, bypassing Trash.
		const SCOPE_MODIFY = 'https://www.googleapis.com/auth/gmail.modify';
		
		// Full access to the account, including permanent deletion of threads and messages. This scope should only be requested if your application needs to immediately and permanently delete threads and messages, bypassing Trash; all other actions can be performed with less permissive scopes.
		const SCOPE_FULL_ACCESS = 'https://mail.google.com/';
		const AUTH_TEMP_ID = 'GOOGLE_OAUTH_TEMP_ID';
		
		// the sender email address
		private $senderEmail;
		
		/**
		 * Constructor
		 *
		 * Get a Client ID from https://account.live.com/developers/applications/index
		 */
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken, $senderEmail) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $senderEmail ) );
			$logger = new PostmanLogger ( get_class ( $this ) );
			$this->senderEmail = $senderEmail;
			parent::__construct ( $clientId, $clientSecret, $authorizationToken, $logger );
		}
		
		/**
		 * The authorization sequence begins when your application redirects a browser to a Google URL;
		 * the URL includes query parameters that indicate the type of access being requested.
		 *
		 * As in other scenarios, Google handles user authentication, session selection, and user consent.
		 * The result is an authorization code, which Google returns to your application in a query string.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::requestVerificationCode()
		 */
		public function requestVerificationCode() {
			
			// Create a state token to prevent request forgery.
			// Store it in the session for later validation.
			$state = md5 ( rand () );
			$_SESSION [PostmanGmailAuthenticationManager::AUTH_TEMP_ID] = $state;
			
			$params = array (
					'response_type' => 'code',
					'redirect_uri' => urlencode ( PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::GMAIL_HOSTNAME ) ),
					'client_id' => $this->getClientId (),
					'scope' => urlencode ( PostmanGmailAuthenticationManager::SCOPE_FULL_ACCESS ),
					'access_type' => 'offline',
					'approval_prompt' => 'force',
					'state' => $state,
					'login_hint' => $this->senderEmail 
			);
			
			build_query ( $params );
			$authUrl = PostmanGmailAuthenticationManager::GOOGLE_ENDPOINT . '?' . build_query ( $params );
			
			$this->getLogger ()->debug ( 'Requesting verification code from Google' );
			$_SESSION [PostmanAdminController::POSTMAN_ACTION] = PostmanAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS;
			postmanRedirect ( $authUrl );
		}
		
		/**
		 * After receiving the authorization code, your application can exchange the code
		 * (along with a client ID and client secret) for an access token and, in some cases,
		 * a refresh token.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::handleAuthorizatinGrantCode()
		 */
		public function handleAuthorizatinGrantCode() {
			if (isset ( $_GET ['code'] )) {
				$this->getLogger ()->debug ( 'Found authorization code in request header' );
				$code = $_GET ['code'];
				if (isset ( $_GET ['state'] ) && $_GET ['state'] == $_SESSION [PostmanGmailAuthenticationManager::AUTH_TEMP_ID]) {
					unset ( $_SESSION [PostmanGmailAuthenticationManager::AUTH_TEMP_ID] );
					$this->getLogger ()->debug ( 'Found valid state in request header' );
				} else {
					$this->getLogger()->error('The grant code from Google had no accompanying state and may be a forgery');
					throw new PostmanStateIdMissingException();
				}
				$this->requestAuthorizationToken ( PostmanGmailAuthenticationManager::GOOGLE_REFRESH, PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::GMAIL_HOSTNAME ), $code );
				return true;
			} else {
				$this->getLogger ()->debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		
		/**
		 * If a refresh token is present in the authorization code exchange,
		 * then it can be used to obtain new access tokens at any time.
		 * This is called offline access, because the user does not have to be present
		 * at the browser when the application obtains a new access token.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::refreshToken()
		 */
		public function refreshToken() {
			$this->getLogger ()->debug ( 'Refreshing Token' );
			
			$callbackUrl = PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::GMAIL_HOSTNAME );
			assert ( ! empty ( $callbackUrl ) );
			
			$refreshUrl = PostmanGmailAuthenticationManager::GOOGLE_REFRESH;
			assert ( ! empty ( $refreshUrl ) );
			
			$this->refreshAccessToken ( $refreshUrl, $callbackUrl );
		}
	}
}
?>