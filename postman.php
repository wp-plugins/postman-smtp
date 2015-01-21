<?php

/*
 * Plugin Name: Postman OAuth SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Send your mail with your Gmail account by adding what Google calls "the latest security measures" (i.e. SMTPS with OAuth 2.0 authentication). As of July 2014, this is <a href="http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html">recommended</a> and in some cases, <a href="https://support.google.com/accounts/answer/6010255">required</a> for sending mail via Gmail. Hotmail support will be added in a future release.
 * Version: 0.1
 * Author: Jason Hendriks
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Postman {

	define ( 'WP_PLUGIN_DIR', '/Users/jasonhendriks/Sites/amsoil/wp-content/plugins/' );
	
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
	
	require_once WP_PLUGIN_DIR . '/postman-smtp/Postman/WordpressMailEngine.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Postman/SmtpOAuthMailerAdmin.php';
	require_once WP_PLUGIN_DIR . '/postman-smtp/Postman/GmailAuthenticationManager.php';
	
	$fromName;
	$fromEmail;
	
	/**
	 *
	 * @param unknown $to        	
	 * @param unknown $subject        	
	 * @param unknown $message        	
	 * @param unknown $headers        	
	 * @param unknown $attachments        	
	 */
	function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
		$engine = new WordpressMailEngine ();
		$options = get_option ( POSTMAN_OPTIONS );
		$engine->setAuthEmail ( $options ['oauth_email'] );
		$engine->setAuthToken ( $options ['access_token'] );
		$engine->setServer ( $options ['hostname'] );
		$engine->setBodyText ( $message );
		$engine->setSubject ( $subject );
		if (empty ( $fromEmail ) && empty ( $fromName )) {
			$engine->setFrom ( $options ['oauth_email'] );
		} elseif (empty ( $fromName )) {
			$engine->setFrom ( $this->fromName );
		} else {
			$engine->setFrom ( $this->fromEmail, $this->fromName );
		}
		$engine->addTo ( $to );
		return $engine->send ();
	}
	function wp_mail_smtp_mail_from($email) {
		$this->fromEmail = $email;
	}
	function wp_mail_smtp_mail_from_name($name) {
		$this->fromName = $name;
	}
}

namespace {

	require_once 'Zend/Mail/Transport/Smtp.php';
	require_once 'Zend/Mail.php';
	
	/**
	 *
	 * @return mixed
	 */
	$options = get_option ( POSTMAN_OPTIONS );
	if (! empty ( $options ['access_token'] )) {
		if (! function_exists ( 'wp_mail' )) {
			function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
				return call_user_func_array ( '\Postman\wp_mail', func_get_args () );
			}
		}
		if (! function_exists ( 'wp_mail_smtp_mail_from' )) {
			function wp_mail_smtp_mail_from($email) {
				return call_user_func_array ( '\Postman\wp_mail_smtp_mail_from', func_get_args () );
			}
		}
		if (! function_exists ( 'wp_mail_smtp_mail_from_name' )) {
			function wp_mail_smtp_mail_from_name($name) {
				return call_user_func_array ( '\Postman\wp_mail_smtp_mail_from_name', func_get_args () );
			}
		}
		// Add filters to replace the mail from name and emailaddress
		add_filter ( 'wp_mail_from', '\Postman\wp_mail_smtp_mail_from' );
		add_filter ( 'wp_mail_from_name', '\Postman\wp_mail_smtp_mail_from_name' );
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
	// print "<br/>GET: ";
	// print_r ( $_GET );
	// print "<br/>POST: ";
	// print_r ( $_POST );
	// print "<br/>HEADERS: ";
	// print_r ( getallheaders () );
	
	// wp_mail ( 'jason@hendriks.ca', 'a subject', 'a message' );
	
	session_start ();
	if (isset ( $_SESSION ['SMTP_OAUTH_GMAIL_AUTH_IN_PROGRESS'] )) {
		$gmailAuthenticationManager = new \Postman\GmailAuthenticationManager ();
		$gmailAuthenticationManager->tradeCodeForToken ();
	}
}
?>
