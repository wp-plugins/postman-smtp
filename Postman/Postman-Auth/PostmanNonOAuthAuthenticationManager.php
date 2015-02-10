<?php
if (! class_exists ( "PostmanNonOAuthAuthenticationManager" )) {
	
	require_once 'PostmanAuthenticationManager.php';
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanNonOAuthAuthenticationManager implements PostmanAuthenticationManager {
		
		/**
		 * Constructor
		 */
		public function __construct() {
		}
		
		/**
		 */
		public function isTokenExpired() {
			return false;
		}
		
		/**
		 */
		public function refreshToken() {
			// no-op
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAuthenticationManager::requestVerificationCode()
		 */
		public function requestVerificationCode() {
			postmanRedirect ( $authUrl );
		}
		
		/**
		 */
		public function tradeCodeForToken() {
		}
	}
}
?>