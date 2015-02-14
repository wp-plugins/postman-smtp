<?php
if (! class_exists ( 'PostmanSmtpHostProperties' )) {
	class PostmanSmtpHostProperties {
		const GMAIL_HOSTNAME = 'smtp.gmail.com';
		const WINDOWS_LIVE_HOSTNAME = 'smtp.live.com';
		const YAHOO_HOSTNAME = 'smtp.mail.yahoo.com';
		
		// get the callback URL
		static function getRedirectUrl($hostname) {
			if (self::isGoogle ( $hostname ) || self::isYahoo ( $hostname )) {
				return admin_url ( 'options-general.php' ) . '?page=postman';
			} else if (self::isMicrosoft ( $hostname )) {
				return admin_url ( 'options-general.php' );
			} else {
				return admin_url ( 'options-general.php' ) . '?page=postman';
			}
		}
		static function isOauthHost($hostname) {
			return self::isGoogle ( $hostname ) || self::isMicrosoft ( $hostname ) || self::isYahoo ( $hostname );
		}
		static function isGoogle($hostname) {
			return endsWith ( $hostname, 'gmail.com' );
		}
		static function isMicrosoft($hostname) {
			return endsWith ( $hostname, 'live.com' );
		}
		static function isYahoo($hostname) {
			return endsWith ( $hostname, 'yahoo.com' );
		}
		static function getServiceName($hostname) {
			if (self::isGoogle ( $hostname )) {
				return 'Google';
			} else if (self::isMicrosoft ( $hostname )) {
				return 'Microsoft';
			} else if (self::isYahoo ( $hostname )) {
				return 'Yahoo';
			} else {
				return '';
			}
		}
	}
}