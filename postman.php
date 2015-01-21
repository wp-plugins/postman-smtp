<?php

/*
 * Plugin Name: Postman OAuth SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Send your mail with your Gmail account by adding what Google calls "the latest security measures" (i.e. SMTPS with OAuth 2.0 authentication). As of July 2014, this is <a href="http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html">recommended</a> and in some cases, <a href="https://support.google.com/accounts/answer/6010255">required</a> for sending mail via Gmail. Hotmail support will be added in a future release.
 * Version: 0.2
 * Author: Jason Hendriks
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Postman {

	define ( 'WP_PLUGIN_DIR', plugin_dir_path ( __FILE__ ) );
	
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
	
	define ( 'Postman\DEBUG', false );
	
	require_once 'Postman/PostmanOAuthSmtpEngine.php';
	require_once 'Postman/PostmanAdminController.php';
	require_once 'Postman/GmailAuthenticationManager.php';
	require_once 'Postman/AuthenticationToken.php';
	require_once 'Postman/Options.php';
	require_once 'Postman/WordPressUtils.php';
	
	require_once 'Zend/Mail/Transport/Smtp.php';
	require_once 'Zend/Mail.php';
	
	session_start ();
	
	if (isset ( $_SESSION [GmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] )) {
		$authenticationToken = new AuthenticationToken ( get_option ( POSTMAN_OPTIONS ) );
		$gmailAuthenticationManager = new GmailAuthenticationManager ( $authenticationToken );
		$gmailAuthenticationManager->tradeCodeForToken ( '\Postman\saveOptions' );
		die ();
	} else {
		$smtpOAuthMailerAdmin = new PostmanAdminController ();
	}
	function saveOptions(AuthenticationToken $authenticationToken) {
		$options = get_option ( POSTMAN_OPTIONS );
		$options [Options::ACCESS_TOKEN] = $authenticationToken->getAccessToken ();
		$options [Options::REFRESH_TOKEN] = $authenticationToken->getRefreshToken ();
		$options [Options::TOKEN_EXPIRES] = $authenticationToken->getExpiryTime ();
		update_option ( POSTMAN_OPTIONS, $options );
	}
	
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
		$engine = new PostmanOAuthSmtpEngine ();
		$engine->setBodyText ( $message );
		$engine->setSubject ( $subject );
		$engine->addTo ( $to );
		return $engine->send ();
	}
}

namespace {

	$options = get_option ( POSTMAN_OPTIONS );
	if ($smtpOAuthMailerAdmin->isRequestOAuthPermissiongAllowed () && $smtpOAuthMailerAdmin->isSendingEmailAllowed ()) {
		if (! function_exists ( 'wp_mail' )) {
			function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
				return call_user_func_array ( '\Postman\wp_mail', func_get_args () );
			}
		} else {
			$smtpOAuthMailerAdmin->addWarningUnableToImplementWpMail ();
		}
	}
	
	// Adds "Settings" link to the plugin action page
	add_filter ( 'plugin_action_links_' . plugin_basename ( __FILE__ ), 'add_action_links' );
	
	//
	function add_action_links($links) {
		$mylinks = array (
				'<a href="' . HOME_PAGE_URL . '">Settings</a>' 
		);
		return array_merge ( $links, $mylinks );
	}
}
?>
