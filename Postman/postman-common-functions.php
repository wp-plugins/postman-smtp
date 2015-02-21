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