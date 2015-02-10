<?php
if (! class_exists ( "PostmanAbstractAuthenticationManager" )) {
	
	require_once 'PostmanAuthenticationManager.php';
	
	/**
	 */
	abstract class PostmanAbstractAuthenticationManager implements PostmanAuthenticationManager {
		
		// constants
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
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken, PostmanLogger $logger) {
			assert ( ! empty ( $clientId ) );
			assert ( ! empty ( $clientSecret ) );
			assert ( ! empty ( $authorizationToken ) );
			$this->logger = $logger;
			$this->clientId = $clientId;
			$this->clientSecret = $clientSecret;
			$this->authorizationToken = $authorizationToken;
		}
		protected function getLogger() {
			return $this->logger;
		}
		protected function getClientId() {
			return $this->clientId;
		}
		protected function getClientSecret() {
			return $this->clientSecret;
		}
		protected function getAuthorizationToken() {
			return $this->authorizationToken;
		}
		
		/**
		 */
		public function isTokenExpired() {
			$expireTime = ($this->authorizationToken->getExpiryTime () - PostmanGmailAuthenticationManager::FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE);
			$tokenHasExpired = time () > $expireTime;
			$this->logger->debug ( 'Access Token Expiry Time is ' . $expireTime . ', expires_in=' . ($expireTime - time ()) . ', expired=' . ($tokenHasExpired ? 'yes' : 'no') );
			return true || $tokenHasExpired;
		}
		
		/**
		 * Parses the authorization token and extracts the expiry time, accessToken, and if this is a first-time authorization, a refresh token.
		 *
		 * @param unknown $client        	
		 */
		protected function decodeReceivedAuthorizationToken($newtoken) {
			
			// update expiry time
			$newExpiryTime = time () + $newtoken->{PostmanGmailAuthenticationManager::EXPIRES};
			$this->getAuthorizationToken ()->setExpiryTime ( $newExpiryTime );
			$this->getLogger ()->debug ( 'Updating Access Token Expiry Time ' );
			
			// update acccess token
			$newAccessToken = $newtoken->{PostmanGmailAuthenticationManager::ACCESS_TOKEN};
			$this->getAuthorizationToken ()->setAccessToken ( $newAccessToken );
			$this->getLogger ()->debug ( 'Updating Access Token' );
			
			// update refresh token, if there is one
			if (isset ( $newtoken->{PostmanGmailAuthenticationManager::REFRESH_TOKEN} )) {
				$newRefreshToken = $newtoken->{PostmanGmailAuthenticationManager::REFRESH_TOKEN};
				$this->getAuthorizationToken ()->setRefreshToken ( $newRefreshToken );
				$this->getLogger ()->debug ( 'Updating Refresh Token ' );
			}
		}
		
		/**
		 *
		 * @return mixed
		 */
		protected function getAccessToken($accessTokenUrl, $redirectUri, $code) {
			$postvals = "client_id=" . $this->getClientId () . "&client_secret=" . $this->getClientSecret () . "&grant_type=authorization_code" . "&redirect_uri=" . urlencode ( $redirectUri ) . "&code=" . $code;
			$fullUrl = $accessTokenUrl . '?' . $postvals;
			return postmanHttpTransport ( $fullUrl );
		}
		/**
		 *
		 * @return mixed
		 */
		protected function refreshAccessToken($accessTokenUrl, $redirectUri) {
			// the format of the URL is
			// client_id=CLIENT_ID&client_secret=CLIENT_SECRET&redirect_uri=REDIRECT_URI&grant_type=refresh_token&refresh_token=REFRESH_TOKEN
			$postvals = "client_id=" . $this->getClientId () . "&client_secret=" . $this->getClientSecret () . "&redirect_uri=" . urlencode ( $redirectUri ) . "&grant_type=refresh_token&refresh_token=" . $this->getAuthorizationToken ()->getRefreshToken ();
			// example request string
			// client_id=0000000603DB0F&redirect_uri=http%3A%2F%2Fwww.contoso.com%2Fcallback.php&client_secret=LWILlT555GicSrIATma5qgyBXebRI&refresh_token=*LA9...//refresh token string shortened for example//...xRoX&grant_type=refresh_token
			$fullUrl = $accessTokenUrl . '?' . $postvals;
			return postmanHttpTransport ( $fullUrl );
		}
		protected function processRefreshTokenResponse($response) {
			$this->processResponse ( $response, 'Could not refresh token' );
		}
		protected function processTradeCodeForTokenResponse($response) {
			$this->processResponse ( $response, 'Could not acquire authentication token' );
		}
		
		/**
		 * Decoded the received token
		 *
		 * @param unknown $response        	
		 * @throws Exception
		 */
		private function processResponse($response, $errorMessage) {
			$this->getLogger ()->debug ( 'Processing response ' . $response);
			$authToken = json_decode ( stripslashes ( $response ) );
			if ($authToken === NULL) {
				$message = $errorMessage . ': ' . $response;
				$this->getLogger ()->error ( $message );
				throw new Exception ( $message );
			} else {
				$this->decodeReceivedAuthorizationToken ( $authToken );
			}
		}
	}
}
?>