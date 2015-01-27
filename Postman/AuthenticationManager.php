<?php
if (! interface_exists ( "PostmanAuthenticationManager" )) {
	interface PostmanAuthenticationManager {
		const AUTHORIZATION_IN_PROGRESS = 'AUTHORIZATION_IN_PROGRESS';
		public function __construct($clientId, $clientSecret, PostmanAuthorizationToken &$authorizationToken);
		public function isTokenExpired();
		public function refreshToken();
		public function authenticate($gmailAddress);
	}
}
