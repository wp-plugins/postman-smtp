<?php

/*
 * Plugin Name: Postman SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Email not working? Never lose another message again! Postman is the first and only WordPress SMTP plugin to implement OAuth 2.0. Setup is a breeze with the Configuration Wizard and integrated Port Tester. Enjoy worry-free, guaranteed delivery even if your password changes!
 * Version: 1.3.1
 * Author: Jason Hendriks
 * Text Domain: postman
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// ideas for future versions of the plugin
// -- send mail in the background using ajax - the single mail thread can block the PHP server for quite some time
// -- add a wizard style to the tester - then if the test fails, we can display proper debug information
// -- if a test message succeeds, capture the domain of the primary mx, smtp server hostname and port, auth type, encyrption type into a database (with the user's permission)
// -- write a plugin to capture the above information
// -- replace curl_init with WP_Http (higher priority)
// -- replace Google library with something custom (higher priority)

// define constants
define ( 'POSTMAN_HOME_PAGE_RELATIVE_URL', 'options-general.php?page=postman' );
define ( 'POSTMAN_HOME_PAGE_ABSOLUTE_URL', admin_url ( POSTMAN_HOME_PAGE_RELATIVE_URL ) );
define ( 'POSTMAN_PLUGIN_VERSION', '1.3.1' );

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
			if (! is_null ( $last_error ) && ($t & (E_ERROR | E_PARSE)) && preg_match ( "/postman/i", $last_error ['file'] )) {
				// if there has been a fatal error
				$message = sprintf ( '%s in %s on line %d', $last_error ['message'], $last_error ['file'], $last_error ['line'] );
				printf ( '<h2>Bad, Postman!</h2> <p><b><tt>X-(</b></tt></p> <p>Look at this mess:</p><code>%s</code>', $message );
			} else {
				$logger->debug ( 'Normal exit' );
			}
		}
	}
}

if (! function_exists ( 'postmanRedirect' )) {
	/**
	 * A façade function that handles redirects.
	 * Inside WordPress we can use wp_redirect(). Outside WordPress, not so much. **Load it before postman-core.php**
	 *
	 * @param unknown $url        	
	 */
	function postmanRedirect($url) {
		$logger = new PostmanLogger ( 'postman.php' );
		$logger->debug ( sprintf ( "Redirecting to '%s'", $url ) );
		wp_redirect ( $url );
		exit ();
	}
}

if (! function_exists ( 'postmanMain' )) {
	/**
	 * The main entry point for Postman
	 */
	function postmanMain() {
		
		// create a Logger
		$logger = new PostmanLogger ( 'postman.php' );
		
		// load the options and the auth token
		require_once 'Postman/PostmanOptions.php';
		$options = PostmanOptions::getInstance ();
		require_once 'Postman/PostmanAuthorizationToken.php';
		$authToken = PostmanAuthorizationToken::getInstance ();
		
		$basename = plugin_basename ( __FILE__ );
		
		// create a message handler
		require_once 'Postman/PostmanMessageHandler.php';
		$messageHandler = new PostmanMessageHandler ( $options );
		
		// bind to wp_mail()
		require_once 'Postman/PostmanWpMailBinder.php';
		new PostmanWpMailBinder ( plugin_basename ( __FILE__ ), $options, $authToken, $messageHandler );
		
		if (is_admin ()) {
			
			// Adds "Settings" link to the plugin action page
			add_filter ( 'plugin_action_links_' . $basename, 'postmanModifyLinksOnPluginsListPage' );
			
			// the options screen
			require_once 'Postman/AdminController.php';
			
			// start the Postman Admin page
			$kevinCostner = new PostmanAdminController ( $basename, $options, $authToken, $messageHandler );
		}
	}
}
if (! function_exists ( 'postmanModifyLinksOnPluginsListPage' )) {
	function postmanModifyLinksOnPluginsListPage($links) {
		$mylinks = array (
				'<a href="' . esc_url ( POSTMAN_HOME_PAGE_ABSOLUTE_URL ) . '">Settings</a>' 
		);
		return array_merge ( $links, $mylinks );
	}
}
// handle plugin activation
if (! function_exists ( 'activatePostman' )) {
	register_activation_hook ( __FILE__, 'activatePostman' );
	/**
	 * Handle activation of plugin
	 */
	function activatePostman() {
		$logger = new PostmanLogger ( 'postman.php' );
		$logger->debug ( "Activating plugin" );
		// prior to version 0.2.5, $authOptions did not exist
		$authOptions = get_option ( PostmanAuthorizationToken::OPTIONS_NAME );
		$options = get_option ( PostmanOptions::POSTMAN_OPTIONS );
		if (empty ( $authOptions ) && ! (empty ( $options ))) {
			// copy the variables from $options to $authToken
			$authToken = new PostmanAuthorizationToken ();
			$authToken->setAccessToken ( $options [PostmanAuthorizationToken::ACCESS_TOKEN] );
			$authToken->setRefreshToken ( $options [PostmanAuthorizationToken::REFRESH_TOKEN] );
			$authToken->setExpiryTime ( $options [PostmanAuthorizationToken::EXPIRY_TIME] );
			$authToken->save ();
		}
		if (! isset ( $options ['authorization_type'] )) {
			// prior to 1.0.0, access tokens were saved in authOptions without an auth type
			// prior to 0.2.5, access tokens were save in options without an auth type
			if (isset ( $authOptions [PostmanAuthorizationToken::ACCESS_TOKEN] ) || isset ( $options [PostmanAuthorizationToken::ACCESS_TOKEN] )) {
				$options ['authorization_type'] = 'oauth2';
				update_option ( PostmanOptions::POSTMAN_OPTIONS, $options );
			}
		}
		if (! isset ( $options [PostmanOptions::ENCRYPTION_TYPE] )) {
			// prior to 1.3, encryption type was combined with authentication type
			if (isset ( $options ['authorization_type'] )) {
				$authType = $options ['authorization_type'];
				switch ($authType) {
					case 'none' :
						$options [PostmanOptions::AUTHENTICATION_TYPE] = PostmanOptions::AUTHENTICATION_TYPE_NONE;
						$options [PostmanOptions::ENCRYPTION_TYPE] = PostmanOptions::ENCRYPTION_TYPE_NONE;
						break;
					case 'basic-ssl' :
						$options [PostmanOptions::AUTHENTICATION_TYPE] = PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
						$options [PostmanOptions::ENCRYPTION_TYPE] = PostmanOptions::ENCRYPTION_TYPE_SSL;
						break;
					case 'basic-tls' :
						$options [PostmanOptions::AUTHENTICATION_TYPE] = PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
						$options [PostmanOptions::ENCRYPTION_TYPE] = PostmanOptions::ENCRYPTION_TYPE_TLS;
						break;
					case 'oauth2' :
						$options [PostmanOptions::AUTHENTICATION_TYPE] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
						$options [PostmanOptions::ENCRYPTION_TYPE] = PostmanOptions::ENCRYPTION_TYPE_SSL;
						break;
					default :
				}
				update_option ( PostmanOptions::POSTMAN_OPTIONS, $options );
			}
		}
	}
}

// start the session
if (! isset ( $_SESSION )) {
	session_start ();
}

// load the core stuff
require_once 'postman-core.php';

// register error handler
register_shutdown_function ( 'postmanHandleErrors' );

// start all the fuss
postmanMain ();

?>
