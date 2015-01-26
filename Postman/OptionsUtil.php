<?php

namespace Postman {

	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class OptionsUtil {
		const CLIENT_ID = 'oauth_client_id';
		const CLIENT_SECRET = 'oauth_client_secret';
		const REFRESH_TOKEN = 'refresh_token';
		const TOKEN_EXPIRES = 'auth_token_expires';
		const ACCESS_TOKEN = 'access_token';
		const SMTP_TYPE = 'smtp_type';
		const SENDER_EMAIL = 'sender_email';
		const PORT = 'port';
		const HOSTNAME = 'hostname';
		const TEST_EMAIL = 'test_email';
		public static function getHostname($options) {
			return $options [OptionsUtil::HOSTNAME];
		}
		public static function getPort($options) {
			return $options [OptionsUtil::PORT];
		}
		public static function getSenderEmail($options) {
			return $options [OptionsUtil::SENDER_EMAIL];
		}
		public static function getClientId($options) {
			if (isset ( $options [OptionsUtil::CLIENT_ID] ))
				return $options [OptionsUtil::CLIENT_ID];
		}
		public static function getClientSecret($options) {
			return $options [OptionsUtil::CLIENT_SECRET];
		}
		public static function getTestEmail($options) {
			return $options [OptionsUtil::TEST_EMAIL];
		}
		public static function getSmtpType($options) {
			return $options [OptionsUtil::SMTP_TYPE];
		}
		public static function getTokenExpiryTime($options) {
			if (isset ( $options [OptionsUtil::TOKEN_EXPIRES] ))
				return $options [OptionsUtil::TOKEN_EXPIRES];
		}
		public static function getAccessToken($options) {
			return $options [OptionsUtil::ACCESS_TOKEN];
		}
		public static function getRefreshToken($options) {
			return $options [OptionsUtil::REFRESH_TOKEN];
		}
		public static function updateAccessToken($authenticationToken, array &$options) {
			$options [OptionsUtil::ACCESS_TOKEN] = $authenticationToken;
		}
		public static function updateRefreshToken($refreshToken, array &$options) {
			$options [OptionsUtil::REFRESH_TOKEN] = $refreshToken;
		}
		public static function updateTokenExpiryTime($time, array &$options) {
			$options [OptionsUtil::TOKEN_EXPIRES] = $time;
		}
	}
}