<?php
// handle plugin activation
if (! class_exists ( 'PostmanActivationHandler' )) {
	
	require_once ('PostmanOAuthToken.php');
	require_once ('PostmanOptions.php');
	
	/**
	 * If required, database upgrades are made during activation
	 * ALL NAMES should be HARDCODED..
	 * NO external constants. They might change over time!
	 *
	 * @author jasonhendriks
	 */
	class PostmanActivationHandler {
		private $logger;
		
		/**
		 * Handle activation of plugin
		 */
		public function activate_postman() {
			// Activation is not used often, lazy initialize the logger
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			
			// handle network activation
			// from https://wordpress.org/support/topic/new-function-wp_get_sites?replies=11
			if (function_exists ( 'is_multisite' ) && is_multisite ()) {
				// check if it is a network activation - if so, run the activation function for each blog id
				$old_blog = get_current_blog_id ();
				// Get all blog ids
				$subsites = wp_get_sites ();
				foreach ( $subsites as $subsite ) {
					$this->logger->trace ( 'multisite: switching to blog ' . $subsite ['blog_id'] );
					switch_to_blog ( $subsite ['blog_id'] );
					$this->handleOptionUpdates ();
				}
				switch_to_blog ( $old_blog );
			} else {
				$this->handleOptionUpdates ();
			}
		}
		
		/**
		 * Handle activation of plugin
		 */
		private function handleOptionUpdates() {
			$this->logger->debug ( "Activating plugin" );
			// prior to version 0.2.5, $authOptions did not exist
			$authOptions = get_option ( 'postman_auth_token' );
			$options = get_option ( 'postman_options' );
			$postmanState = get_option ( 'postman_state' );
			if (empty ( $authOptions ) && ! (empty ( $options )) && ! empty ( $options ['access_token'] )) {
				$this->logger->debug ( "Upgrading database: copying Authorization token from postman_options to postman_auth_token" );
				// copy the variables from $options to $authToken
				$authOptions ['access_token'] = $options ['access_token'];
				$authOptions ['refresh_token'] = $options ['refresh_token'];
				// there was a bug where we weren't setting the expiry time
				if (! empty ( $options ['auth_token_expires'] )) {
					$authOptions ['auth_token_expires'] = $options ['auth_token_expires'];
				}
				update_option ( 'postman_auth_token', $authOptions );
			}
			if (! isset ( $options ['authorization_type'] ) && ! isset ( $options ['auth_type'] )) {
				// prior to 1.0.0, access tokens were saved in authOptions without an auth type
				// prior to 0.2.5, access tokens were save in options without an auth type
				// either way, only oauth2 was supported
				if (isset ( $authOptions ['access_token'] ) || isset ( $options ['access_token'] )) {
					$this->logger->debug ( "Upgrading database: setting authorization_type to 'oauth2'" );
					$options ['authorization_type'] = 'oauth2';
					update_option ( 'postman_options', $options );
				}
			}
			if (! isset ( $options ['enc_type'] )) {
				// prior to 1.3, encryption type was combined with authentication type
				if (isset ( $options ['authorization_type'] )) {
					$this->logger->debug ( "Upgrading database: creating auth_type and enc_type from authorization_type" );
					$authType = $options ['authorization_type'];
					switch ($authType) {
						case 'none' :
							$options ['auth_type'] = 'none';
							$options ['enc_type'] = 'none';
							break;
						case 'basic-ssl' :
							$options ['auth_type'] = 'login';
							$options ['enc_type'] = 'ssl';
							break;
						case 'basic-tls' :
							$options ['auth_type'] = 'login';
							$options ['enc_type'] = 'tls';
							break;
						case 'oauth2' :
							$options ['auth_type'] = 'oauth2';
							$options ['enc_type'] = 'ssl';
							break;
						default :
					}
					update_option ( 'postman_options', $options );
				}
			}
			// prior to 1.3.3, the version identifier was not stored and the passwords were plaintext
			if (isset ( $options ['enc_type'] ) && ! (isset ( $options ['version'] ) || isset ( $postmanState ['version'] ))) {
				$this->logger->debug ( "Upgrading database: added plugin version and encoding password" );
				$options ['version'] = '1.3.3';
				if (isset ( $options ['basic_auth_password'] )) {
					$options ['basic_auth_password'] = base64_encode ( $options ['basic_auth_password'] );
				}
				update_option ( 'postman_options', $options );
			}
			// prior to 1.4.2, the transport was not identified and the auth token had no vendor
			if (isset ( $options ['auth_type'] ) && ! isset ( $options ['transport_type'] )) {
				$this->logger->debug ( "Upgrading database: added transport_type and vendor_name" );
				$options ['transport_type'] = 'smtp';
				update_option ( 'postman_options', $options );
				if (isset ( $authOptions ['access_token'] ) && isset ( $options ['oauth_client_id'] )) {
					// if there is a stored token..
					if (PostmanUtils::endsWith ( $options ['oauth_client_id'], 'googleusercontent.com' ))
						$authOptions ['vendor_name'] = 'google';
					else if (strlen ( $options ['oauth_client_id'] < strlen ( $options ['oauth_client_secret'] ) ))
						$authOptions ['vendor_name'] = 'microsoft';
					else
						$authOptions ['vendor_name'] = 'yahoo';
					update_option ( 'postman_auth_token', $authOptions );
				}
			}
			
			// for version 1.6.18, the envelope from was introduced
			if (! empty ( $options ['sender_email'] ) && empty ( $options ['envelope_sender'] )) {
				$this->logger->debug ( "Upgrading database: adding envelope_sender" );
				$options ['envelope_sender'] = $options ['sender_email'];
				update_option ( 'postman_options', $options );
			}
			
			// can we create a tmp file? - this code is duplicated in InputSanitizer
			PostmanUtils::deleteLockFile ();
			$lockSuccess = PostmanUtils::createLockFile ();
			// &= does not work as expected in my PHP
			$lockSuccess = $lockSuccess && PostmanUtils::deleteLockFile ();
			$postmanState ['locking_enabled'] = $lockSuccess;
			
			// always update the version number
			if (! isset ( $postmanState ['install_date'] )) {
				$this->logger->debug ( "Upgrading database: adding install_date" );
				$postmanState ['install_date'] = time ();
			}
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			$postmanState ['version'] = $pluginData ['version'];
			update_option ( 'postman_state', $postmanState );
			//
			delete_option ( 'postman_session' );
		}
	}
}
