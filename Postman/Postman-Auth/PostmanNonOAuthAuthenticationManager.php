<?php
if (! class_exists ( "PostmanNonOAuthAuthenticationManager" )) {
	
	require_once 'PostmanAuthenticationManager.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanNonOAuthAuthenticationManager implements PostmanAuthenticationManager {
		
		/**
		 */
		public function isAccessTokenExpired() {
			return false;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::requestVerificationCode()
		 */
		public function requestVerificationCode($transactionId) {
			// no-op
		}
		public function processAuthorizationGrantCode($transactionId) {
			// no-op
		}
		public function refreshToken() {
			// no-op
		}
		public function getAuthorizationUrl() {
			return null;
		}
		public function getTokenUrl() {
			return null;
		}
		public function getCallbackUri() {
			return null;
		}
		public function generateRequestTransactionId() {
			return 0;
		}
	}
}
