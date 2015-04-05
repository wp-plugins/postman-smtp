<?php
if (! class_exists ( "PostmanLogger" )) {
	
	//
	class PostmanLogger {
		const ALL_INT = - 2147483648;
		const DEBUG_INT = 10000;
		const ERROR_INT = 40000;
		const FATAL_INT = 50000;
		const INFO_INT = 20000;
		const OFF_INT = 2147483647;
		const WARN_INT = 30000;
		private $name;
		private $logLevel;
		private $wpDebug;
		function __construct($name) {
			$this->name = $name;
			$this->wpDebug = defined ( 'WP_DEBUG' );
			if (class_exists ( 'PostmanOptions' )) {
				$this->logLevel = PostmanOptions::getInstance ()->getLogLevel ();
			} else {
				$this->logLevel = self::DEBUG_INT;
			}
		}
		// better logging thanks to http://www.smashingmagazine.com/2011/03/08/ten-things-every-wordpress-plugin-developer-should-know/
		function debug($text) {
			if ($this->wpDebug && self::DEBUG_INT >= $this->logLevel) {
				if (is_array ( $text ) || is_object ( $text )) {
					error_log ( 'DEBUG ' . $this->name . ': ' . print_r ( $text, true ) );
				} else {
					error_log ( 'DEBUG ' . $this->name . ': ' . $text );
				}
			}
		}
		function error($text) {
			if ($this->$wpDebug && self::ERROR_INT >= $this->logLevel) {
				if (is_array ( $text ) || is_object ( $text )) {
					error_log ( 'ERROR' . $this->name . ': ' . print_r ( $text, true ) );
				} else {
					error_log ( 'ERROR ' . $this->name . ': ' . $text );
				}
			}
		}
	}
}
if (! function_exists ( 'postmanValidateEmail' )) {
	/**
	 * Validate an e-mail address
	 *
	 * @param unknown $email        	
	 * @return number
	 */
	function postmanValidateEmail($email) {
		return true;
		$exp = "/^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$/i";
		return preg_match ( $exp, $email );
	}
}

if (! function_exists ( 'str_getcsv' )) {
	/**
	 * Using fgetscv (PHP 4) as a work-around for str_getcsv (PHP 5.3)
	 * From http://stackoverflow.com/questions/13430120/str-getcsv-alternative-for-older-php-version-gives-me-an-empty-array-at-the-e
	 *
	 * @param unknown $string        	
	 * @return multitype:
	 */
	function str_getcsv($string) {
		$fh = fopen ( 'php://temp', 'r+' );
		fwrite ( $fh, $string );
		rewind ( $fh );
		
		$row = fgetcsv ( $fh );
		
		fclose ( $fh );
		return $row;
	}
}

// from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
if (! function_exists ( 'startsWith' )) {
	function startsWith($haystack, $needle) {
		$length = strlen ( $needle );
		return (substr ( $haystack, 0, $length ) === $needle);
	}
}

// from http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
if (! function_exists ( 'endsWith' )) {
	function endsWith($haystack, $needle) {
		$length = strlen ( $needle );
		if ($length == 0) {
			return true;
		}
		
		return (substr ( $haystack, - $length ) === $needle);
	}
}

if (! function_exists ( 'stripUrlPath' )) {
	/**
	 * Strips the path form a URL
	 *
	 * Return just the scheme and the host (e.g. http://mysite.com/this-is/the/path => http://mysite.com/)
	 * http://stackoverflow.com/questions/19040904/php-strip-path-from-url-no-built-in-function
	 *
	 * @param unknown $url        	
	 */
	function stripUrlPath($url) {
		$urlParts = parse_url ( $url );
		if (isset ( $urlParts ['scheme'] ) && isset ( $urlParts ['host'] )) {
			return $urlParts ['scheme'] . "://" . $urlParts ['host'] . "/";
		} else {
			throw new ParseUrlException ();
		}
	}
}

if (! function_exists ( 'postmanObfuscateEmail' )) {
	function postmanObfuscateEmail($email) {
		$start = 2;
		$end = strpos ( $email, '@' );
		if ($end == false) {
			// if it's not an email..
			$start = 4;
			$end = strlen ( $email ) - 4;
		} else {
			$end -= 2;
		}
		$result = '';
		for($c = 0; $c < strlen ( $email ); $c ++) {
			if ($c >= $start && $c < $end) {
				$result .= '*';
			} else {
				$result .= $email [$c];
			}
		}
		return $result;
	}
}

if (! function_exists ( 'postmanObfuscatePassword' )) {
	function postmanObfuscatePassword($password) {
		return str_repeat ( '*', strlen ( $password ) );
	}
}
if (! class_exists ( 'ParseUrlException' )) {
	class ParseUrlException extends Exception {
	}
}

if (! function_exists ( 'isHostAddressNotADomainName' )) {
	/**
	 * Detect if the host is NOT a domain name
	 *
	 * @param unknown $ipAddress        	
	 * @return number
	 */
	function isHostAddressNotADomainName($host) {
		// IPv4 / IPv6 test from http://stackoverflow.com/a/17871737/4368109
		$ipv6Detected = preg_match ( '/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/', $host );
		$ipv4Detected = preg_match ( '/((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])/', $host );
		return $ipv4Detected || $ipv6Detected;
		// from http://stackoverflow.com/questions/106179/regular-expression-to-match-dns-hostname-or-ip-address
		// return preg_match ( '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9‌​]{2}|2[0-4][0-9]|25[0-5])$/', $ipAddress );
	}
}

if (! function_exists ( 'postmanGetServerName' )) {
	function postmanGetServerName() {
		$serverName = '127.0.0.1';
		if (isset ( $_SERVER ['SERVER_NAME'] )) {
			$serverName = $_SERVER ['SERVER_NAME'];
		}
		if (empty ( $serverName )) {
			if (isset ( $_SERVER ['HTTP_HOST'] )) {
				$serverName = $_SERVER ['HTTP_HOST'];
			}
		}
		return $serverName;
	}
}

