<?php
if (! class_exists ( 'PostmanSmtpHostProperties' )) {
	class PostmanSmtpHostProperties {
		const GMAIL_HOSTNAME = 'smtp.gmail.com';
		const WINDOWS_LIVE_HOSTNAME = 'smtp.live.com';
		
		// get the callback URL
		static function getRedirectUrl($hostname) {
			switch ($hostname) {
				case PostmanSmtpHostProperties::GMAIL_HOSTNAME :
					return admin_url ( 'options-general.php' ) . '?page=postman';
				case PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME :
					return admin_url ( 'options-general.php' );
				default :
					return '';
			}
		}
		static function isOauthHost($hostname) {
			if ($hostname == PostmanSmtpHostProperties::GMAIL_HOSTNAME || $hostname == PostmanSmtpHostProperties::WINDOWS_LIVE_HOSTNAME) {
				return true;
			} else {
				return false;
			}
		}
	}
}