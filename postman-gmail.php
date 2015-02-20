<?php

/*
 * Plugin Name: Postman Gmail Extension
 * Plugin URI: https://wordpress.org/plugins/postman-gmail/
 * Description: Can't send Gmail because ports 465 and 587 are blocked on your host? No problem! The Postman Gmail Extension works with Postman SMTP to send your mail out on the HTTPS port, port 443.
 * Version: 0.1
 * Author: Jason Hendriks
 * Text Domain: postman-gmail
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// define constants
define ( 'POSTMAN_GMAIL_API_PLUGIN_VERSION', '0.1' );

require_once 'Postman/PostmanGmailMain.php';

// todo:
// when displaying drop-down, look up English name by plugin slug in Transport Directory
// when checking for configuration errors, check that the plugin slug is in Transport Directory
// before sending mail, use a factory to create the Transport based on the plugin slug
// during sending mail, use a visitor to encapsulate the appropriate Zend Transport
// move startup validation and WpMailBind to start at plugins_loaded event

// read for coding child plugins: http://wordpress.stackexchange.com/questions/127818/how-to-make-a-plugin-require-another-plugin

// read for coding WHEN to look for available extensions: http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded

// you will probably want to add set_include_path(get_include_path() . PATH_SEPARATOR . '/path/to/google-api-php-client/src');
// (from )

$waterworld = new PostmanGmail (__FILE__);
