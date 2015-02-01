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
		public function authenticate($gmailAddress) {
			header ( 'Location: ' . filter_var ( $authUrl, FILTER_SANITIZE_URL ) );
			exit ();
		}
	}
}
?>