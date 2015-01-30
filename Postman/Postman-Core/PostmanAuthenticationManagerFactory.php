<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
	//
	class PostmanAuthenticationManagerFactory {
		public static function createAuthenticationManager($clientId, $clientSecret, PostmanAuthorizationToken $authorizationToken) {
			$authenticationManager = new GmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken );
			return $authenticationManager;
		}
	}
}