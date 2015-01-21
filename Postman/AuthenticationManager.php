<?php

namespace Postman {

	interface AuthenticationManager {
		public function __construct(AuthenticationToken $authenticationToken);
		public function isTokenExpired();
		public function refreshToken();
		public function authenticate();
		public function setAuthenticationToken(AuthenticationToken $authenticationToken);
		public function getAuthenticationToken();
	}
}