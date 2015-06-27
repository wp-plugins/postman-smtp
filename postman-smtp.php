<?php

/*
 * Plugin Name: Postman SMTP
 * Plugin URI: https://wordpress.org/plugins/postman-smtp/
 * Description: Email not reliable? Postman is the first and only WordPress SMTP plugin to implement OAuth 2.0 for Gmail, Hotmail and Yahoo Mail. Setup is a breeze with the Configuration Wizard and integrated Port Tester. Enjoy worry-free delivery even if your password changes!
 * Version: 1.6.23
 * Author: Jason Hendriks
 * Text Domain: postman-smtp
 * Author URI: http://www.codingmonkey.ca
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// The Postman Mail API
//
// filter postman_test_email: before calling wp_mail, implement this filter and return true to disable the success/fail counters
// filter postman_wp_mail_result: apply this filter after calling wp_mail for an array containg the SMTP error, transcript and time
// filter postman_get_plugin_metadata: get plugin metadata
// filter postman_wp_mail_bind_status: get wp_mail bind status
// filter print_postman_status: print the human-readable plugin state

// TODO
// -- Add dismiss option for "unconfigured message" .. for multisites
// -- Add resend option for failed messages
// -- customize sent-mail icon WordPress dashboard
// -- SendGrid API https://github.com/sendgrid/sendgrid-php-example
// -- Postmark API http://plugins.svn.wordpress.org/postmark-approved-wordpress-plugin/trunk/postmark.php
// -- Amazon SES API http://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-email-api.html
// -- Mandrill API
// -- multisite support for site-wide email configuration. allow network admin to choose whether subdomains may override with their own settings. subdomains may override with their own settings.
// -- mail queue : send mail in the background
// -- multiple mailbox support

/**
 * Create the main Postman class to start Postman
 */
function postman_start($startingMemory) {
	postman_setupPostman ();
	PostmanUtils::logMemoryUse ( $startingMemory, 'Postman' );
}
function postman_setupPostman() {
	require_once 'Postman/Postman.php';
	$kevinCostner = new Postman ( __FILE__, '1.6.23' );
}
/**
 * Start Postman
 */
postman_start ( memory_get_usage () );

