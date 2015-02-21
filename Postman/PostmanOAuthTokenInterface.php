<?php
if (! interface_exists ( 'PostmanOAuthTokenInterface.php' )) {
	//
	interface PostmanOAuthTokenInterface {
		public static function getInstance();
		public function save();
		public function getVendorName();
		public function getExpiryTime();
		public function getAccessToken();
		public function getRefreshToken();
	}
}