<?php

/*
 * Plugin Name: Postman SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Gmail not working? Never lose another email again! Postman is the first and only WordPress plugin to implement Google's OAuth 2.0 authentication. Setup is a breeze with the Configuration Wizard and built-in TCP Port Tester. Enjoy worry-free, guaranteed delivery even if your password changes!
 * Version: 1.2
 * Author: Jason Hendriks
 * Text Domain: postman
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// these constants are used for OAuth HTTP requests
define ( 'POSTMAN_HOME_PAGE_URL', admin_url ( 'options-general.php' ) . '?page=postman' );
define ( 'POSTMAN_PLUGIN_VERSION', '1.2' );
define ( 'POSTMAN_TCP_TIMEOUT', 30 );

// start all the fuss
postmanMain ();

// all the fuss
function postmanMain() {
	
	// create a Logger
	require_once 'Postman/Postman-Common/postman-common.php';
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
		// load the core of Postman
		require_once 'Postman/Postman-Mail/core.php';
		require_once 'Postman/Postman-Auth/core.php';
		
		// check if there is an auth token waiting for granting and possibly exit
		if (isset ( $_SESSION [PostmanGmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] )) {
			unset ( $_SESSION [PostmanAuthenticationManager::AUTHORIZATION_IN_PROGRESS] );
			if (isset ( $_GET ['code'] )) {
				postmanHandleAuthorizationGrant ( $logger, $options, $authToken );
				// redirect to plugin setting page and exit()
				header ( 'Location: ' . esc_url ( POSTMAN_HOME_PAGE_URL ) );
				exit ();
			}
		}
		
		// Adds "Settings" link to the plugin action page
		add_filter ( 'plugin_action_links_' . $basename, 'modifyLinksOnPluginsListPage' );
		
		if (true || isset ( $_GET ['page'] ) && $_GET ['page'] == 'postman') {
			
			// the options screen
			require_once 'Postman/AdminController.php';
			
			// start the Postman Admin page
			$kevinCostner = new PostmanAdminController ( $basename, $options, $authToken, $messageHandler );
		}
	}
}
function modifyLinksOnPluginsListPage($links) {
	$mylinks = array (
			'<a href="' . esc_url ( POSTMAN_HOME_PAGE_URL ) . '">Settings</a>' 
	);
	return array_merge ( $links, $mylinks );
}
/**
 * Handles the authorization grant
 */
function postmanHandleAuthorizationGrant(PostmanLogger $logger, PostmanOptions $options, PostmanAuthorizationToken $authorizationToken) {
	$logger->debug ( 'Authorization in progress' );
	unset ( $_SESSION [PostmanGmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] );
	
	$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options, $authorizationToken );
	try {
		if ($authenticationManager->tradeCodeForToken ()) {
			$logger->debug ( 'Authorization successful' );
			// save to database
			$authorizationToken->save ();
		} else {
			PostmanMessageHandler::addError ( 'Your email provider did not grant Postman permission. Try again.' );
		}
	} catch ( Google_Auth_Exception $e ) {
		$logger->error ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
		PostmanMessageHandler::addError ( 'Error authenticating with this Client ID - please create a new one. [<em>' . $e->getMessage () . ' code=' . $e->getCode () . '</em>]' );
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
		if (! isset ( $options [PostmanOptions::AUTHENTICATION_TYPE] )) {
			// prior to 1.0.0, access tokens were saved in authOptions without an auth type
			// prior to 0.2.5, access tokens were save in options without an auth type
			if (isset ( $authOptions [PostmanAuthorizationToken::ACCESS_TOKEN] ) || isset ( $options [PostmanAuthorizationToken::ACCESS_TOKEN] )) {
				$options [PostmanOptions::AUTHENTICATION_TYPE] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
				update_option ( PostmanOptions::POSTMAN_OPTIONS, $options );
			}
		}
	}
}

if (! function_exists ( 'str_getcsv' )) {
	/**
	 * Using fgetscv (PHP 4) as a work-around for str_getcsv (PHP 5.3)
	 * From http://stackoverflow.com/questions/13430120/str-getcsv-alternative-for-older-php-version-gives-me-an-empty-array-at-the-e
	 *
	 * @param unknown $string        	
	 * @return multitype:
	 */
	function str_getcsv($string) {
		$fh = fopen ( 'php://temp', 'r+' );
		fwrite ( $fh, $string );
		rewind ( $fh );
		
		$row = fgetcsv ( $fh );
		
		fclose ( $fh );
		return $row;
	}
}
?>
