<?php
if (! class_exists ( 'PostmanSmtpHostProperties' )) {
	class PostmanSmtpHostProperties {
		const GMAIL_HOSTNAME = 'smtp.gmail.com';
		const WINDOWS_LIVE_HOSTNAME = 'smtp.live.com';
		const YAHOO_HOSTNAME = 'smtp.mail.yahoo.com';
		
		// get the callback URL
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
	}
}