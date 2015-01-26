<?php

/*
 * Plugin Name: Postman OAuth SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Send your mail with your Gmail account by adding what Google calls "the latest security measures" (i.e. SMTPS with OAuth 2.0 authentication). As of July 2014, this is <a href="http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html">recommended</a> and in some cases, <a href="https://support.google.com/accounts/answer/6010255">required</a> for sending mail via Gmail. Hotmail support will be added in a future release.
 * Version: 0.2.3
 * Author: Jason Hendriks
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Postman {

	define ( 'POSTMAN_NAME', 'Postman SMTP' );
	define ( 'POSTMAN_SLUG', 'postman' );
	define ( 'POSTMAN_TEST_SLUG', 'postman-test' );
	
	define ( 'POSTMAN_OPTIONS', 'postman_options' );
	define ( 'POSTMAN_TEST_OPTIONS', 'postman_test_options' );
	define ( 'POSTMAN_PAGE_TITLE', POSTMAN_NAME . ' Settings' );
	define ( 'POSTMAN_MENU_TITLE', POSTMAN_NAME );
	define ( 'POSTMAN_PLUGIN_DIRECTORY', POSTMAN_SLUG );
	
	define ( 'OAUTH_REDIRECT_URL', admin_url ( 'options-general.php' ) );
	define ( 'HOME_PAGE_URL', OAUTH_REDIRECT_URL . '?page=postman' );
	
	require_once 'Postman/OAuthSmtpEngine.php';
	require_once 'Postman/AdminController.php';
	require_once 'Postman/GmailAuthenticationManager.php';
	require_once 'Postman/OptionsUtil.php';
	require_once 'Postman/WordPressUtils.php';
	
	if (! isset ( $_SESSION )) {
		// needs predictable access to the session
		session_start ();
	}
	
	$kevinCostener = new AdminController ( plugin_basename ( __FILE__ ) );
	
	/**
	 *
	 * @param unknown $to
	 *        	(string or array) (required) The intended recipient(s). Multiple recipients may be specified using an array or a comma-separated
	 * @param unknown $subject
	 *        	(string) (required) The subject of the message.
	 * @param unknown $message
	 *        	(string) (required) Message content.
	 * @param unknown $headers
	 *        	(string or array) (optional) Mail headers to send with the message. For the string version, each header line (beginning with From:, Cc:, etc.) is delimited with a newline ("\r\n") (advanced) Default: Empty
	 * @param unknown $attachments
	 *        	(string or array) (optional) Files to attach: a single filename, an array of filenames, or a newline-delimited string list of multiple filenames. (advanced) Default: Empty
	 */
	function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
		$options = get_option ( POSTMAN_OPTIONS );
		$engine = new OAuthSmtpEngine ( $options );
		$engine->setBodyText ( $message );
		$engine->setSubject ( $subject );
		$engine->addTo ( $to );
		return $engine->send ();
	}
	function debug($text) {
		error_log ( "PostmanSmtp: " . $text );
	}
	function addError($message) {
		$_SESSION [AdminController::ERROR_MESSAGE] = $message;
	}
	function addWarning($message) {
		$_SESSION [AdminController::WARNING_MESSAGE] = $message;
	}
	function addMessage($message) {
		$_SESSION [AdminController::SUCCESS_MESSAGE] = $message;
	}
}

namespace {

	if ($kevinCostener->isRequestOAuthPermissiongAllowed () && $kevinCostener->isSendingEmailAllowed ()) {
		if (! function_exists ( 'wp_mail' )) {
			function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
				return call_user_func_array ( '\Postman\wp_mail', func_get_args () );
			}
		} else {
			$kevinCostener->addWarningUnableToImplementWpMail ();
		}
	}
}
?>
