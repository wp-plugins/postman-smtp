<?php
if (! class_exists ( "PostmanLogger" )) {
	
	require_once 'PostmanOptions.php';
	
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
		function __construct($name) {
			$this->name = $name;
			$this->logLevel = PostmanOptions::getInstance ()->getLogLevel ();
		}
		// TODO better logging http://www.smashingmagazine.com/2011/03/08/ten-things-every-wordpress-plugin-developer-should-know/
		function debug($text) {
			if (self::DEBUG_INT >= $this->logLevel) {
				error_log ( 'DEBUG ' . $this->name . ': ' . $text );
			}
		}
		function error($text) {
			if (self::ERROR_INT >= $this->logLevel) {
				error_log ( 'ERROR ' . $this->name . ': ' . $text );
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

if (! function_exists ( 'endsWith' )) {
	function endsWith($string, $test) {
		$strlen = strlen ( $string );
		$testlen = strlen ( $test );
		if ($testlen > $strlen)
			return false;
		return substr_compare ( $string, $test, $strlen - $testlen, $testlen ) === 0;
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

if (! class_exists ( 'ParseUrlException' )) {
	class ParseUrlException extends Exception {
	}
}