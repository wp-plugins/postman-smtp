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
		/**
		 * Handle activation of plugin
		 */
		public function activatePostman() {
			$logger = new PostmanLogger ( get_class ( $this ) );
			$logger->debug ( "Activating plugin" );
			// prior to version 0.2.5, $authOptions did not exist
			$authOptions = get_option ( 'postman_auth_token' );
			$options = get_option ( 'postman_options' );
			$postmanState = get_option ( 'postman_state' );
			if (empty ( $authOptions ) && ! (empty ( $options )) && ! empty ( $options ['access_token'] )) {
				$logger->debug ( "Upgrading database: copying Authorization token from postman_options to postman_auth_token" );
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
					$logger->debug ( "Upgrading database: setting authorization_type to 'oauth2'" );
					$options ['authorization_type'] = 'oauth2';
					update_option ( 'postman_options', $options );
				}
			}
			if (! isset ( $options ['enc_type'] )) {
				// prior to 1.3, encryption type was combined with authentication type
				if (isset ( $options ['authorization_type'] )) {
					$logger->debug ( "Upgrading database: creating auth_type and enc_type from authorization_type" );
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
			if (isset ( $options ['enc_type'] ) && ! isset ( $options ['version'] )) {
				$logger->debug ( "Upgrading database: added plugin version and encoding password" );
				$options ['version'] = '1.3.3';
				if (isset ( $options ['basic_auth_password'] )) {
					$options ['basic_auth_password'] = base64_encode ( $options ['basic_auth_password'] );
				}
				update_option ( 'postman_options', $options );
			}
			// prior to 1.4.2, the transport was not identified and the auth token had no vendor
			if (isset ( $options ['auth_type'] ) && ! isset ( $options ['transport_type'] )) {
				$logger->debug ( "Upgrading database: added transport_type and vendor_name" );
				$options ['transport_type'] = 'smtp';
				update_option ( 'postman_options', $options );
				if (isset ( $authOptions ['access_token'] ) && isset ( $options ['oauth_client_id'] )) {
					// if there is a stored token..
					if (endsWith ( $options ['oauth_client_id'], 'googleusercontent.com' ))
						$authOptions ['vendor_name'] = 'google';
					else if (strlen ( $options ['oauth_client_id'] < strlen ( $options ['oauth_client_secret'] ) ))
						$authOptions ['vendor_name'] = 'microsoft';
					else
						$authOptions ['vendor_name'] = 'yahoo';
					update_option ( 'postman_auth_token', $authOptions );
				}
			}
			// always update the version number
			if (!isset ( $postmanState ['install_date'] )) {
				$postmanState ['install_date'] = time ();
			}
			$postmanState ['version'] = POSTMAN_PLUGIN_VERSION;
			update_option ( 'postman_state', $postmanState );
		}
	}
}
