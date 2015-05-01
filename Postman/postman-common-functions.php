<?php
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
	 * PHP version less than 5.3 don't have str_getcsv natively.
	 *
	 * @param unknown $string        	
	 * @return multitype:
	 */
	function str_getcsv($string) {
		$logger = new PostmanLogger ( 'postman-common-functions' );
		$logger->debug ( 'Using custom str_getcsv' );
		return postman_strgetcsv_impl ( $string );
	}
}

if (! function_exists ( 'postman_strgetcsv_impl' )) {
	/**
	 * From http://stackoverflow.com/questions/13430120/str-getcsv-alternative-for-older-php-version-gives-me-an-empty-array-at-the-e
	 *
	 * @param unknown $string        	
	 * @return multitype:
	 */
	function postman_strgetcsv_impl($string) {
		$fh = fopen ( 'php://temp', 'r+' );
		fwrite ( $fh, $string );
		rewind ( $fh );
		
		$row = fgetcsv ( $fh );
		
		fclose ( $fh );
		return $row;
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
			return $urlParts ['scheme'] . "://" . $urlParts ['host'];
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
		return PostmanUtils::obfuscatePassword ( $password );
	}
}
if (! class_exists ( 'ParseUrlException' )) {
	class ParseUrlException extends Exception {
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

