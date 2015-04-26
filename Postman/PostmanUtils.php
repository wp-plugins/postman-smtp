<?php
if (! class_exists ( 'PostmanUtils' )) {
	class PostmanUtils {
		
		//
		const POSTMAN_SETTINGS_PAGE_STUB = 'postman_email_log';
		const POSTMAN_EMAIL_LOG_PAGE_STUB = 'postman_email_log';
		
		//
		const NO_ECHO = false;
		
		//
		public static function getEmailLogPageUrl() {
			return menu_page_url ( SELF::POSTMAN_EMAIL_LOG_PAGE_STUB, self::NO_ECHO );
		}
		
		//
		public static function getSettingsPageUrl() {
			return menu_page_url ( SELF::POSTMAN_SETTINGS_PAGE_STUB, self::NO_ECHO );
		}
	}
}
