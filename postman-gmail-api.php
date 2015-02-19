<?php

/*
 * Plugin Name: Postman Gmail Extension
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Can't send Gmail because ports 465 and 587 are blocked on your host? No problem! The Postman Gmail Extension works with Postman SMTP to send your mail out on the HTTPS port, port 443.
 * Version: 0.1
 * Author: Jason Hendriks
 * Text Domain: postman-smtp
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// ideas for future versions of the plugin
// -- if a test message succeeds, capture the domain of the primary mx, smtp server hostname and port, auth type, encryption type into a database (with the user's permission)
// -- write a plugin to capture the above information
// -- send mail in the background using ajax - the single mail thread can block the PHP server for quite some time

// define constants
define ( 'POSTMAN_GMAIL_API_PLUGIN_VERSION', '0.1' );

// set-up the error handler
if (! function_exists ( 'postmanHandleErrors' )) {
	/**
	 * Handles unexpected errors
	 */
	function postmanHandleErrors() {
		// error_get_last is only in PHP 5.2 and newer
		if (function_exists ( 'error_get_last' )) {
			$last_error = error_get_last ();
			$t = $last_error ['type'];
			$logger = new PostmanLogger ( 'postman.php' );
			// E_ALL screws up my port test ajax!!
			if (! is_null ( $last_error ) && ($t & (E_ALL | E_COMPILE_ERROR | E_ERROR | E_PARSE | E_NOTICE)) && preg_match ( "/postman/i", $last_error ['file'] )) {
				// if there has been a fatal error
				$message = sprintf ( '%s in %s on line %d', $last_error ['message'], $last_error ['file'], $last_error ['line'] );
				if (PostmanOptions::getInstance ()->isErrorPrintingEnabled ()) {
					printf ( '<h2>Bad, Postman!</h2> <p><b><tt>X-(</b></tt></p> <p>Look at the mess you made:</p><code>%s</code>', $message );
				}
				$logger->error ( $message );
			} else {
				$logger->debug ( 'Normal exit' );
			}
		}
	}
}

// load the common functions
require_once 'Postman/postman-common-wp-functions.php';

// create a Logger
$logger = new PostmanLogger ( 'postman-gmail-api.php' );
$logger->debug ( 'Postman v' . POSTMAN_PLUGIN_VERSION . ' starting' );

// register error handler
register_shutdown_function ( 'postmanHandleErrors' );

//read for coding child plugins: http://wordpress.stackexchange.com/questions/127818/how-to-make-a-plugin-require-another-plugin

// read for coding WHEN to look for available extensions: http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
