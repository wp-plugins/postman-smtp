<?php

/*
 * Plugin Name: Postman SMTP
 * Plugin URI: https://wordpress.org/plugins/postman-smtp/
 * Description: Email not reliable? Postman is the first and only WordPress SMTP plugin to implement OAuth 2.0 for Gmail, Hotmail and Yahoo Mail. Setup is a breeze with the Configuration Wizard and integrated Port Tester. Enjoy worry-free delivery even if your password changes!
 * Version: 1.6.12a
 * Author: Jason Hendriks
 * Text Domain: postman-smtp
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// The Postman Mail API
//
// filter postman_test_email: before calling wp_mail, implement this filter and return true to disable the success/fail counters
// filter postman_wp_mail_result: apply this filter after calling wp_mail for an array containg the SMTP error, transcript and time

// ideas for future versions of the plugin
// -- SendGrid API https://github.com/sendgrid/sendgrid-php-example
// -- Postmark API http://plugins.svn.wordpress.org/postmark-approved-wordpress-plugin/trunk/postmark.php
// -- Amazon SES API http://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-email-api.html
// -- Postman API with WordPress filters - test mode, smtp result
// -- add WPMU functionality. ideas: allow network setup for network emails. allow network admin to choose whether subdomains may override with their own settings. subdomains may override with their own settings.
// -- send mail in the background using ajax - the single mail thread can block the PHP server for quite some time

/**
 * Create the main Postman class to start Postman
 */
function postman_start($startingMemory) {
	postman_setupPostman ();
	PostmanUtils::logMemoryUse ( $startingMemory, 'Postman' );
}
function postman_setupPostman() {
	require_once 'Postman/Postman.php';
	$kevinCostner = new Postman ( __FILE__, '1.6.12a' );
}
/**
 * Start Postman
 */
postman_start ( memory_get_usage () );

