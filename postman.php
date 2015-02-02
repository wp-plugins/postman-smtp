<?php

/*
 * Plugin Name: Postman OAuth SMTP
 * Plugin URI: https://wordpress.org/plugins/postman/
 * Description: Send your mail with your Gmail account by adding what Google calls "the latest security measures" (i.e. SMTPS with OAuth 2.0 authentication). As of July 2014, this is <a href="http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html">recommended</a> and in some cases, <a href="https://support.google.com/accounts/answer/6010255">required</a> for sending mail via Gmail. Hotmail support will be added in a future release.
 * Version: 0.2.7
 * Author: Jason Hendriks
 * Author URI: https://profiles.wordpress.org/jasonhendriks/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// these constants are used for OAuth HTTP requests
define ( 'POSTMAN_HOME_PAGE_URL', admin_url ( 'options-general.php' ) . '?page=postman' );
define ( 'POSTMAN_PLUGIN_VERSION', '1.2.7' );

// load the core of Postman
require_once 'Postman/Postman-Core/core.php';

// bind Postman to wp_mail()
require_once 'Postman/PostmanWpMailBinder.php';

// display messages to the user
require_once 'Postman/PostmanMessageHandler.php';

// the options screen
require_once 'Postman/AdminController.php';

// start all the fuss
postmanSmtpMain ();

// all the fuss
function postmanSmtpMain() {
	if (! isset ( $_SESSION )) {
		// needs predictable access to the session
		session_start ();
	}
	
	// create a Logger
	$logger = new PostmanLogger ( 'postman.php' );
	
	// load the options and the auth token
	$options = PostmanOptions::getInstance ();
	$authToken = PostmanAuthorizationToken::getInstance ();
	
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
	
	// create a message handler
	$messageHandler = new PostmanMessageHandler ( $options );
	
	// start the Postman Admin page
	$kevinCostner = new PostmanAdminController ( plugin_basename ( __FILE__ ), $options, $authToken, $messageHandler );
	
	// bind to wp_mail()
	new PostmanWpMailBinder ( plugin_basename ( __FILE__ ), $options, $authToken, $messageHandler );
}

/**
 * Handles the authorization grant
 */
function postmanHandleAuthorizationGrant(PostmanLogger $logger, PostmanOptions $options, PostmanAuthorizationToken $authorizationToken) {
	$authType = $options->getAuthorizationType ();
	$clientId = $options->getClientId ();
	$clientSecret = $options->getClientSecret ();
	$logger->debug ( 'Authorization in progress' );
	unset ( $_SESSION [PostmanGmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] );
	
	$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $authType, $clientId, $clientSecret, $authorizationToken );
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
		// prior to 1.0.0, the auth type was set to 'gmail' instead of oauth2
		if ($options [PostmanOptions::AUTHORIZATION_TYPE] == 'gmail') {
			$options [PostmanOptions::AUTHORIZATION_TYPE] = PostmanOptions::AUTHORIZATION_TYPE_OAUTH2;
			update_option ( PostmanOptions::POSTMAN_OPTIONS, $options );
		}
	}
}

?>
