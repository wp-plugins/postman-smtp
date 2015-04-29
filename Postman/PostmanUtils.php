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
	}
	PostmanUtils::staticInit ();
}
