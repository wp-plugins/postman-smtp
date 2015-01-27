<?php

/*
 * Plugin Name: Postman OAuth SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Send your mail with your Gmail account by adding what Google calls "the latest security measures" (i.e. SMTPS with OAuth 2.0 authentication). As of July 2014, this is <a href="http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html">recommended</a> and in some cases, <a href="https://support.google.com/accounts/answer/6010255">required</a> for sending mail via Gmail. Hotmail support will be added in a future release.
 * Version: 0.2.4.1
 * Author: Jason Hendriks
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// these constants are used for OAuth HTTP requests
define ( 'POSTMAN_HOME_PAGE_URL', admin_url ( 'options-general.php' ) . '?page=postman' );

// load the guts of Postman
require_once 'Postman/core.php';
require_once 'Postman/PostmanWpMail.php';
require_once 'Postman/AdminController.php';
require_once 'Postman/WordPressUtils.php';

if (! isset ( $_SESSION )) {
	// needs predictable access to the session
	session_start ();
}

// start the Postman Admin page
$kevinCostener = new PostmanAdminController ( plugin_basename ( __FILE__ ) );
$logger = new PostmanLogger ();

// replace the wp_mail function with Postman's
if ($kevinCostener->isRequestOAuthPermissiongAllowed () && $kevinCostener->isSendingEmailAllowed ()) {
	if (! function_exists ( 'wp_mail' )) {
		function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
			// load settings from database
			$options = get_option ( PostmanWordpressUtil::POSTMAN_OPTIONS );
			$postmanWpMail = new PostmanWpMail ( $options );
			return $postmanWpMail->send ( $to, $subject, $message, $headers, $attachments );
		}
	} else {
		$logger->debug ( 'cant replace wp_mail' );
		$util = new PostmanWordpressUtil ();
		$util->addWarning ( PostmanAdminController::NAME . ' is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.' );
	}
}

if (! function_exists ( 'activatePostman' )) {
	register_activation_hook ( __FILE__, 'activatePostman' );
	function activatePostman() {
		$authOptions = get_option ( PostmanAuthorizationToken::OPTIONS_NAME );
		$options = get_option ( PostmanWordpressUtil::POSTMAN_OPTIONS );
		if (empty ( $authOptions ) && ! (empty ( $options ))) {
			// copy the variables from $options to $authToken
			$authToken = new PostmanAuthorizationToken ();
			$authToken->setAccessToken ( $options [PostmanAuthorizationToken::ACCESS_TOKEN] );
			$authToken->setRefreshToken ( $options [PostmanAuthorizationToken::REFRESH_TOKEN] );
			$authToken->setExpiryTime ( $options [PostmanAuthorizationToken::EXPIRY_TIME] );
 			$authToken->save ();
		}
	}
}

?>
