<?php
if (! class_exists ( "PostmanHotmailAuthenticationManager" )) {
	
	require_once 'PostmanAbstractAuthenticationManager.php';
	
	/**
	 * https://msdn.microsoft.com/en-us/library/ff750690.aspx OAuth WRAP (Messenger Connect)
	 * https://msdn.microsoft.com/en-us/library/ff749624.aspx Working with OAuth WRAP (Messenger Connect)
	 */
	class PostmanHotmailAuthenticationManager extends PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// constants
		const SMTP_HOSTNAME = 'smtp.live.com';
		const WINDOWS_LIVE_ENDPOINT = 'https://login.live.com/oauth20_authorize.srf';
		
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
			$_SESSION [PostmanGmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] = 'hotmail';
			
			$endpoint = PostmanHotmailAuthenticationManager::WINDOWS_LIVE_ENDPOINT;
			$scope = PostmanHotmailAuthenticationManager::SCOPE;
			
			$callbackUrl = PostmanSmtpHostProperties::getRedirectUrl ( PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME );
			// $callbackUrl = 'http://computer.com/~jasonhendriks/wordpress/wp-admin/options-general.php';
			
			$authUrl = $endpoint . "?client_id=" . $this->getClientId () . "&client_secret=" . $this->getClientSecret () . "&response_type=code&scope=" . $scope . "&redirect_uri=" . urlencode ( $callbackUrl );
			
			$this->getLogger ()->debug ( "authenticating with windows live" );
			header ( 'Location: ' . filter_var ( $authUrl, FILTER_SANITIZE_URL ) );
			exit ();
		}
		
		/**
		 * **********************************************
		 * If we have a code back from the OAuth 2.0 flow,
		 * we need to exchange that for an access token.
		 * We store the resultant access token
		 * bundle in the session, and redirect to ourself.
		 * **********************************************
		 */
		public function tradeCodeForToken() {
			if (isset ( $_GET ['code'] )) {
				$code = $_GET ['code'];
				$this->getLogger ()->debug ( 'Found authorization code in request header' );
				$getAccessToken_value = $this->getAccessToken ( 'https://login.live.com/oauth20_token.srf', admin_url ( 'options-general.php' ), $code );
				$getatoken = json_decode ( stripslashes ( $getAccessToken_value ) );
				
				if ($getatoken === NULL) {
					$atoken = $getAccessToken_value;
				} else {
					$atoken = $this->decodeReceivedAuthorizationToken ( $getatoken );
				}
				$this->getLogger ()->debug ( "atoken " . $atoken );
				
				return true;
			} else {
				$this->getLogger ()->debug ( 'Expected code in the request header but found none - user probably denied request' );
				return false;
			}
		}
		
		/**
		 * (non-PHPdoc)
		 * https://msdn.microsoft.com/en-us/library/hh243649.aspx#refresh
		 *
		 * @see PostmanAuthenticationManager::refreshToken()
		 */
		public function refreshToken() {
			$this->getLogger ()->debug ( 'Refreshing Token' );
		}
	}
}
?>