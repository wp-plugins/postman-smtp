<?php
if (! class_exists ( "PostmanOptionUtil" )) {
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanOptionUtil {
		const CLIENT_ID = 'oauth_client_id';
		const CLIENT_SECRET = 'oauth_client_secret';
		const SMTP_TYPE = 'smtp_type';
		const SENDER_EMAIL = 'sender_email';
		const PORT = 'port';
		const HOSTNAME = 'hostname';
		const TEST_EMAIL = 'test_email';
		
		//
		public static function getHostname($options) {
			if (isset ( $options [PostmanOptionUtil::HOSTNAME] ))
				return $options [PostmanOptionUtil::HOSTNAME];
		}
		public static function getPort($options) {
			if (isset ( $options [PostmanOptionUtil::PORT] ))
				return $options [PostmanOptionUtil::PORT];
		}
		public static function getSenderEmail($options) {
			if (isset ( $options [PostmanOptionUtil::SENDER_EMAIL] ))
				return $options [PostmanOptionUtil::SENDER_EMAIL];
		}
		public static function getClientId($options) {
			if (isset ( $options [PostmanOptionUtil::CLIENT_ID] ))
				return $options [PostmanOptionUtil::CLIENT_ID];
		}
		public static function getClientSecret($options) {
			if (isset ( $options [PostmanOptionUtil::CLIENT_SECRET] ))
				return $options [PostmanOptionUtil::CLIENT_SECRET];
		}
		public static function getTestEmail($options) {
			if (isset ( $options [PostmanOptionUtil::TEST_EMAIL] ))
				return $options [PostmanOptionUtil::TEST_EMAIL];
		}
		public static function getSmtpType($options) {
			if (isset ( $options [PostmanOptionUtil::SMTP_TYPE] ))
				return $options [PostmanOptionUtil::SMTP_TYPE];
		}
		public static function updateAccessToken($authenticationToken, array &$options) {
			$options [PostmanOptionUtil::ACCESS_TOKEN] = $authenticationToken;
		}
		public static function updateRefreshToken($refreshToken, array &$options) {
			$options [PostmanOptionUtil::REFRESH_TOKEN] = $refreshToken;
		}
		public static function updateTokenExpiryTime($time, array &$options) {
			$options [PostmanOptionUtil::TOKEN_EXPIRES] = $time;
		}
		public static function debug(PostmanLogger $logger, array $options) {
			$logger->debug ( 'Sender Email=' . PostmanOptionUtil::getSenderEmail ( $options ) );
			$logger->debug ( 'Host=' . PostmanOptionUtil::getHostname ( $options ) );
			$logger->debug ( 'Port=' . PostmanOptionUtil::getPort ( $options ) );
			$logger->debug ( 'Client Id=' . PostmanOptionUtil::getClientId ( $options ) );
			$logger->debug ( 'Client Secret=' . PostmanOptionUtil::getClientSecret ( $options ) );
		}
	}
}