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
			if(isset($options [OptionsUtil::HOSTNAME]))
			return $options [OptionsUtil::HOSTNAME];
		}
		public static function getPort($options) {
			if(isset($options [OptionsUtil::PORT]))
			return $options [OptionsUtil::PORT];
		}
		public static function getSenderEmail($options) {
			if(isset($options [OptionsUtil::SENDER_EMAIL]))
			return $options [OptionsUtil::SENDER_EMAIL];
		}
		public static function getClientId($options) {
			if (isset ( $options [OptionsUtil::CLIENT_ID] ))
				return $options [OptionsUtil::CLIENT_ID];
		}
		public static function getClientSecret($options) {
			if (isset ( $options [OptionsUtil::CLIENT_SECRET] ))
				return $options [OptionsUtil::CLIENT_SECRET];
		}
		public static function getTestEmail($options) {
			if(isset($options [OptionsUtil::TEST_EMAIL]))
			return $options [OptionsUtil::TEST_EMAIL];
		}
		public static function getSmtpType($options) {
			if(isset($options [OptionsUtil::SMTP_TYPE]))
			return $options [OptionsUtil::SMTP_TYPE];
		}
		public static function getTokenExpiryTime($options) {
			if (isset ( $options [OptionsUtil::TOKEN_EXPIRES] ))
				return $options [OptionsUtil::TOKEN_EXPIRES];
		}
		public static function getAccessToken($options) {
			if (isset ( $options [OptionsUtil::ACCESS_TOKEN] ))
				return $options [OptionsUtil::ACCESS_TOKEN];
		}
		public static function getRefreshToken($options) {
			if (isset ( $options [OptionsUtil::REFRESH_TOKEN] ))
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