<?php
if (! class_exists ( 'PostmanPreRequisitesCheck' )) {
	class PostmanPreRequisitesCheck {
		public static function checkIconv() {
			return function_exists ( 'iconv' );
		}
		public static function checkSpl() {
			return function_exists ( 'spl_autoload_register' );
		}
		public static function checkOpenSsl() {
			return extension_loaded ( 'openssl' );
		}
		public static function isReady() {
			return self::checkIconv () && self::checkSpl () && self::checkOpenSsl ();
		}
		public static function getPreRequisiteErrors() {
			$errors = array ();
			if (! self::checkIconv ()) {
				array_push ( $errors, self::printPreReqMessage ( 'iconv' ) );
			}
			if (! self::checkSpl ()) {
				array_push ( $errors, self::printPreReqMessage ( 'spl' ) );
			}
			if (! self::checkOpenSsl ()) {
				array_push ( $errors, self::printPreReqMessage ( 'openssl' ) );
			}
			return $errors;
		}
		private static function printPreReqMessage($thing) {
			return sprintf ( __ ( 'Your PHP installation is missing the <b>%1$s</b> library. Please install <b>%1$s</b> before continuing.' ), $thing );
		}
	}
}