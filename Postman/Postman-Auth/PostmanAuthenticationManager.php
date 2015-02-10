<?php
if (! interface_exists ( "PostmanAuthenticationManager" )) {
	interface PostmanAuthenticationManager {
		
		const POSTMAN_AUTHORIZATION_IN_PROGRESS = 'request_oauth_permission';
		const FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE = 60;
		
		public function isTokenExpired();
		public function refreshToken();
		public function requestVerificationCode();
		public function tradeCodeForToken();
	}
}
