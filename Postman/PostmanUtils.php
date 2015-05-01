<?php
if (! class_exists ( 'PostmanUtils' )) {
	class PostmanUtils {
		private static $logger;
		
		//
		const POSTMAN_SETTINGS_PAGE_STUB = 'postman';
		const POSTMAN_EMAIL_LOG_PAGE_STUB = 'postman_email_log';
		
		//
		const NO_ECHO = false;
		public static function staticInit() {
			PostmanUtils::$logger = new PostmanLogger ( 'PostmanUtils' );
			if (isset ( $_REQUEST ['page'] )) {
				PostmanUtils::$logger->trace ( 'Current page: ' . $_REQUEST ['page'] );
			}
		}
		
		//
		public static function getEmailLogPageUrl() {
			return menu_page_url ( self::POSTMAN_EMAIL_LOG_PAGE_STUB, self::NO_ECHO );
		}
		
		//
		public static function getSettingsPageUrl() {
			return menu_page_url ( self::POSTMAN_SETTINGS_PAGE_STUB, self::NO_ECHO );
		}
		
		//
		public static function isCurrentPagePostmanAdmin($page = 'postman') {
			$result = (isset ( $_REQUEST ['page'] ) && substr ( $_REQUEST ['page'], 0, strlen ( $page ) ) == $page);
			return $result;
		}
		/**
		 * from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
		 *
		 * @param unknown $haystack        	
		 * @param unknown $needle        	
		 * @return boolean
		 */
		function startsWith($haystack, $needle) {
			$length = strlen ( $needle );
			return (substr ( $haystack, 0, $length ) === $needle);
		}
		/**
		 * from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
		 *
		 * @param unknown $haystack        	
		 * @param unknown $needle        	
		 * @return boolean
		 */
		public static function endsWith($haystack, $needle) {
			$length = strlen ( $needle );
			if ($length == 0) {
				return true;
			}
			return (substr ( $haystack, - $length ) === $needle);
		}
		public static function postmanObfuscatePassword($password) {
			return str_repeat ( '*', strlen ( $password ) );
		}
		/**
		 * Detect if the host is NOT a domain name
		 *
		 * @param unknown $ipAddress        	
		 * @return number
		 */
		public static function isHostAddressNotADomainName($host) {
			// IPv4 / IPv6 test from http://stackoverflow.com/a/17871737/4368109
			$ipv6Detected = preg_match ( '/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/', $host );
			$ipv4Detected = preg_match ( '/((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])/', $host );
			return $ipv4Detected || $ipv6Detected;
			// from http://stackoverflow.com/questions/106179/regular-expression-to-match-dns-hostname-or-ip-address
			// return preg_match ( '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9‌​]{2}|2[0-4][0-9]|25[0-5])$/', $ipAddress );
		}
	}
	PostmanUtils::staticInit ();
}
