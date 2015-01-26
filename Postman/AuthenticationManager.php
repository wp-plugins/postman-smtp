<?php

namespace Postman {

	interface AuthenticationManager {
		public function __construct(&$options);
		public function isTokenExpired();
		public function refreshToken();
		public function authenticate($gmailAddress);
		public function setAuthenticationToken(AuthenticationToken $authenticationToken);
		public function getAuthenticationToken();
	}
}