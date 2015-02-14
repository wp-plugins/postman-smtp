<?php
if (! class_exists ( "PostmanLogger" )) {
	
	//
	class PostmanLogger {
		private $name;
		function __construct($name) {
			$this->name = $name;
		}
		function debug($text) {
			error_log ( 'DEBUG ' . $this->name . ': ' . $text );
		}
		function error($text) {
			error_log ( 'ERROR ' . $this->name . ': ' . $text );
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
?>