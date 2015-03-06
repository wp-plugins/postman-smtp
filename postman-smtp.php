<?php

/*
 * Plugin Name: Postman SMTP
 * Plugin URI: https://wordpress.org/plugins/postman-smtp/
 * Description: Email not working? Postman is the first and only WordPress SMTP plugin to implement OAuth 2.0 for Gmail, Hotmail and Yahoo Mail. Setup is a breeze with the Configuration Wizard and integrated Port Tester. Enjoy worry-free delivery even if your password changes!
 * Version: 1.5.4
 * Author: Jason Hendriks
 * Text Domain: postman-smtp
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// ideas for future versions of the plugin
// -- move plugin import stuffs to wizard so we can validate these settings
// -- add WPMU functionality
// -- add Widget to WordPress dashboard
// -- add timeout setting for connectivity test
// -- if a test message succeeds, capture the domain of the primary mx, smtp server hostname and port, auth type, encryption type into a database (with the user's permission)
// -- write a plugin to capture the above information
// -- send mail in the background using ajax - the single mail thread can block the PHP server for quite some time

// define constants
define ( 'POSTMAN_PLUGIN_VERSION', '1.5.4' );

// TODO mailpoet
/*
add_action ( 'init', 'mailpoet_hidden_options' );
function mailpoet_hidden_options() {
	if (class_exists ( 'WYSIJA' )) {
		$model_config = WYSIJA::get ( 'config', 'model' );
		$model_config->save ( array (
				'allow_wpmail' => true 
		) );
	}
}
*/

// load the common functions
require_once 'Postman/postman-common-wp-functions.php';

// create a Logger
require_once 'Postman/Common.php';
$logger = new PostmanLogger ( 'postman-smtp.php' );

// start Postman
require_once 'Postman/PostmanSmtp.php';
$kevinCostner = new PostmanSmtp ( __FILE__ );

