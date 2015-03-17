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
		public static function checkAllowUrlFopen() {
			return filter_var ( ini_get ( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN );
		}
		/**
		 * Return an array of state:
		 * [n][name=>x,ready=>true|false,required=true|false]
		 */
		public static function getState() {
			$state = array ();
			array_push ( $state, array (
					'name' => 'iconv',
					'ready' => self::checkIconv (),
					'required' => true 
			) );
			array_push ( $state, array (
					'name' => 'spl_autoload',
					'ready' => self::checkSpl (),
					'required' => true 
			) );
			array_push ( $state, array (
					'name' => 'openssl',
					'ready' => self::checkOpenSsl (),
					'required' => false 
			) );
			array_push ( $state, array (
					'name' => 'allow_url_fopen',
					'ready' => self::checkAllowUrlFopen (),
					'required' => false 
			) );
			return $state;
		}
		/**
		 *
		 * @return boolean
		 */
		public static function isReady() {
			$states = self::getState ();
			foreach ( $states as $state ) {
				if ($state ['ready'] == false && $state ['required'] == true) {
					return false;
				}
			}
			
			return true;
		}
	}
}