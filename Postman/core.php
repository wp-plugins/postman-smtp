<?php
require_once 'OAuthSmtpEngine.php';
require_once 'GmailAuthenticationManager.php';
require_once 'OptionsUtil.php';
require_once 'PostmanAuthorizationToken.php';
require_once 'PostmanAuthenticationManagerFactory.php';

if (! class_exists ( "PostmanLogger" )) {
	
	//
	class PostmanLogger {
		function debug($text) {
			error_log ( "PostmanSmtp: " . $text );
		}
	}
}
?>