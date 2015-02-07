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
			$this->logger->debug ( 'Access Token Expiry Time is ' . $expireTime . ', expired=' . ($tokenHasExpired ? 'yes' : 'no') );
			return $tokenHasExpired;
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
			return $this->curl_request ( $accessTokenUrl, 'POST', $postvals );
		}
		/**
		 *
		 * @param unknown $url        	
		 * @param unknown $method        	
		 * @param unknown $postvals        	
		 * @return mixed
		 */
		private function curl_request($url, $method, $postvals) {
			$ch = curl_init ( $url );
			if ($method == "POST") {
				$options = array (
						CURLOPT_POST => 1,
						CURLOPT_POSTFIELDS => $postvals,
						CURLOPT_RETURNTRANSFER => 1 
				);
			} else {
				
				$options = array (
						CURLOPT_RETURNTRANSFER => 1 
				);
			}
			curl_setopt_array ( $ch, $options );
			// if ($this->header) {
			
			// curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
			// $this->header . $postvals
			// ) );
			// }
			
			$response = curl_exec ( $ch );
			curl_close ( $ch );
			// print_r($response);
			return $response;
		}
	}
}
?>