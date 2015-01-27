<?php
if (! class_exists ( "PostmanAuthenticationManagerFactory" )) {
	
	//
	class PostmanAuthenticationManagerFactory {
		public static function createAuthenticationManager($options, PostmanAuthorizationToken &$authorizationToken) {
			$clientId = PostmanOptionUtil::getClientId($options);
			$clientSecret = PostmanOptionUtil::getClientSecret($options);
			$authenticationManager = new GmailAuthenticationManager ( $clientId, $clientSecret, $authorizationToken );
			return $authenticationManager;
		}
	}
}