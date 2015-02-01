<?php
if (! interface_exists ( "PostmanAuthenticationManager" )) {
	interface PostmanAuthenticationManager {
		const AUTHORIZATION_IN_PROGRESS = 'AUTHORIZATION_IN_PROGRESS';
		public function isTokenExpired();
		public function refreshToken();
		public function authenticate($gmailAddress);
	}
}
