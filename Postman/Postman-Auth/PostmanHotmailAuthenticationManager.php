<?php
if (! class_exists ( "PostmanHotmailAuthenticationManager" )) {
	
	require_once 'PostmanAbstractAuthenticationManager.php';
	
	/**
	 * https://msdn.microsoft.com/en-us/library/hh243647.aspx (Seems to be the most up-to-date doc on OAuth 2.0
	 * https://msdn.microsoft.com/en-us/library/hh243649.aspx (Seems to be the most up-to-date examples on using the API)
	 * https://msdn.microsoft.com/en-us/library/ff750690.aspx OAuth WRAP (Messenger Connect)
	 * https://msdn.microsoft.com/en-us/library/ff749624.aspx Working with OAuth WRAP (Messenger Connect)
	 * https://gist.github.com/kayalshri/5262641 Working example from Giriraj Namachivayam (kayalshri)
	 */
	class PostmanHotmailAuthenticationManager extends PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// constants
		const SMTP_HOSTNAME = 'smtp.live.com';
		const WINDOWS_LIVE_ENDPOINT = 'https://login.live.com/oauth20_authorize.srf';
		const WINDOWS_LIVE_REFRESH = 'https://login.live.com/oauth20_token.srf';
		
		// http://stackoverflow.com/questions/7163786/messenger-connect-oauth-wrap-api-to-get-user-emails
		// http://quabr.com/26329398/outlook-oauth-send-emails-with-wl-imap-scope-in-php
		const SCOPE = 'wl.imap,wl.offline_access';
		
		/**
		 * Constructor
		 *
		 * Get a Client ID from https://account.live.com/developers/applications/index
		 */
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			$logger = new PostmanLogger ( get_class ( $this ) );
			parent::__construct ( $clientId, $clientSecret, $authorizationToken, $logger );
		}
		
		/**
		 * **********************************************
		 * Request Verification Code
		 * https://msdn.microsoft.com/en-us/library/ff749592.aspx
		 *
		 * The following example shows a URL that enables
		 * a user to provide consent to an application by
		 * using a Windows Live ID.
		 *
		 * When successful, this URL returns the user to
		 * your application, along with a verification
		 * code.
		 * **********************************************
		 */
		public function requestVerificationCode() {
			$_SESSION [PostmanAdminController::POSTMAN_ACTION] = PostmanAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS;
						
			$endpoint = PostmanHotmailAuthenticationManager::WINDOWS_LIVE_ENDPOINT;
			$scope = PostmanHotmailAuthenticationManager::SCOPE;
			
			$callbackUrl = PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME );
			// $callbackUrl = 'http://computer.com/~jasonhendriks/wordpress/wp-admin/options-general.php';
			
			$authUrl = $endpoint . "?client_id=" . $this->getClientId () . "&client_secret=" . $this->getClientSecret () . "&response_type=code&scope=" . $scope . "&redirect_uri=" . urlencode ( $callbackUrl );
			
			$this->getLogger ()->debug ( 'Requesting verification code from Microsoft' );
			postmanRedirect ( $authUrl );
		}
		
		/**
		 * **********************************************
		 * If we have a code back from the OAuth 2.0 flow,
		 * we need to exchange that for an access token.
		 * We store the resultant access token
		 * bundle in the session, and redirect to ourself.
		 * **********************************************
		 */
		public function handleAuthorizatinGrantCode() {
			if (isset ( $_GET ['code'] )) {
				$code = $_GET ['code'];
				$this->getLogger ()->debug ( 'Found authorization code in request header' );
				$this->requestAuthorizationToken ( 'https://login.live.com/oauth20_token.srf', PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME ), $code );
				return true;
			} else {
				$this->getLogger ()->debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		
		/**
		 * The Content-Type header should have the value "application/x-www-form-urlencoded".
		 *
		 * Construct the request body using the following template and replace these elements:
		 *
		 * 1. Replace CLIENT_ID with your app's client ID.
		 *
		 * 2. Replace REDIRECT_URI with the URI to your callback webpage. This URI must be the
		 * same as the URI that you specified when you requested an authorization code. The
		 * URI must use URL escape codes, such as %20 for spaces, %3A for colons, and %2F
		 * for forward slashes.
		 *
		 * 3. Replace CLIENT_SECRET with your app's client secret. The client secret must use
		 * URL escape codes, such as %2B for the plus sign.
		 *
		 * 4. Replace REFRESH_TOKEN with the refresh token that you obtained earlier.
		 *
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::refreshToken()
		 */
		public function refreshToken() {
			$this->getLogger ()->debug ( 'Refreshing Token' );
			
			$callbackUrl = PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME );
			assert ( ! empty ( $callbackUrl ) );
			
			$windowsLiveUrl = PostmanHotmailAuthenticationManager::WINDOWS_LIVE_REFRESH;
			assert ( ! empty ( $windowsLiveUrl ) );
			
			$this->refreshAccessToken ( $windowsLiveUrl, $callbackUrl );
		}
	}
}
?>