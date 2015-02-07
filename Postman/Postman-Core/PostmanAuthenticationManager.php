<?php
if (! interface_exists ( "PostmanAuthenticationManager" )) {
	interface PostmanAuthenticationManager {
		const AUTHORIZATION_IN_PROGRESS = 'AUTHORIZATION_IN_PROGRESS';
		const FORCE_REFRESH_X_SECONDS_BEFORE_EXPIRE = 60;
		public function isTokenExpired();
		public function refreshToken();
		public function requestVerificationCode();
		public function tradeCodeForToken();
	}
}
