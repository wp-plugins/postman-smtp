<?php
if (! class_exists ( 'PostmanUtils' )) {
	class PostmanUtils {
		private static $logger;
		
		//
		const POSTMAN_SETTINGS_PAGE_STUB = 'postman';
		const POSTMAN_EMAIL_LOG_PAGE_STUB = 'postman_email_log';
		// redirections back to THIS SITE should always be relative because of IIS bug
		const POSTMAN_EMAIL_LOG_PAGE_RELATIVE_URL = 'tools.php?page=postman_email_log';
		const POSTMAN_HOME_PAGE_RELATIVE_URL = 'options-general.php?page=postman';
		
		//
		const NO_ECHO = false;
		public static function staticInit() {
			PostmanUtils::$logger = new PostmanLogger ( 'PostmanUtils' );
			if (isset ( $_REQUEST ['page'] )) {
				PostmanUtils::$logger->trace ( 'Current page: ' . $_REQUEST ['page'] );
			}
		}
		
		/**
		 * Returns an escaped URL
		 */
		public static function getEmailLogPageUrl() {
			return menu_page_url ( self::POSTMAN_EMAIL_LOG_PAGE_STUB, self::NO_ECHO );
		}
		
		/**
		 * Returns an escaped URL
		 */
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
		public static function startsWith($haystack, $needle) {
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
		public static function obfuscatePassword($password) {
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
		/**
		 * Makes the outgoing HTTP requests
		 * Inside WordPress we can use wp_remote_post().
		 * Outside WordPress, not so much.
		 *
		 * @param unknown $url        	
		 * @param unknown $args        	
		 * @return the HTML body
		 */
		static function remotePostGetBodyOnly($url, $parameters, array $headers = array()) {
			$response = PostmanUtils::remotePost ( $url, $parameters, $headers );
			$theBody = wp_remote_retrieve_body ( $response );
			return $theBody;
		}
		
		/**
		 * Makes the outgoing HTTP requests
		 * Inside WordPress we can use wp_remote_post().
		 * Outside WordPress, not so much.
		 *
		 * @param unknown $url        	
		 * @param unknown $args        	
		 * @return the HTTP response
		 */
		static function remotePost($url, $parameters = array(), array $headers = array()) {
			$args = array (
					'timeout' => PostmanOptions::getInstance ()->getConnectionTimeout (),
					'headers' => $headers,
					'body' => $parameters 
			);
			PostmanUtils::$logger->trace ( sprintf ( 'Posting to %s', $url ) );
			PostmanUtils::$logger->trace ( 'Post header:' );
			PostmanUtils::$logger->trace ( $headers );
			PostmanUtils::$logger->trace ( 'Posting args:' );
			PostmanUtils::$logger->trace ( $parameters );
			$response = wp_remote_post ( $url, $args );
			
			// pre-process the response
			if (is_wp_error ( $response )) {
				PostmanUtils::$logger->error ( $response->get_error_message () );
				throw new Exception ( 'Error executing wp_remote_post: ' . $response->get_error_message () );
			} else {
				return $response;
			}
		}
		/**
		 * A facade function that handles redirects.
		 * Inside WordPress we can use wp_redirect(). Outside WordPress, not so much. **Load it before postman-core.php**
		 *
		 * @param unknown $url        	
		 */
		static function redirect($url) {
			// redirections back to THIS SITE should always be relative because of IIS bug
			PostmanUtils::$logger->trace ( sprintf ( "Redirecting to '%s'", $url ) );
			wp_redirect ( $url );
			exit ();
		}
		static function parseBoolean($var) {
			return filter_var ( $var, FILTER_VALIDATE_BOOLEAN );
		}
		static function logMemoryUse($startingMemory, $description) {
			PostmanUtils::$logger->trace ( sprintf ( $description . ' memory used: %s', PostmanUtils::roundBytes ( memory_get_usage () - $startingMemory ) ) );
		}
		
		/**
		 * Rounds the bytes returned from memory_get_usage to smaller amounts used IEC binary prefixes
		 * See http://en.wikipedia.org/wiki/Binary_prefix
		 * 
		 * @param unknown $size        	
		 * @return string
		 */
		static function roundBytes($size) {
			$unit = array (
					'B',
					'KiB',
					'MiB',
					'GiB',
					'TiB',
					'PiB' 
			);
			return @round ( $size / pow ( 1024, ($i = floor ( log ( $size, 1024 ) )) ), 2 ) . ' ' . $unit [$i];
		}
	}
	PostmanUtils::staticInit ();
}
