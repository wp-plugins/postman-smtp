<?php
// handle plugin activation
if (! class_exists ( 'PostmanActivationHandler' )) {
	
	require_once ('PostmanAuthorizationToken.php');
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
			$logger = new PostmanLogger ( 'postman.php' );
			$logger->debug ( "Activating plugin" );
			// prior to version 0.2.5, $authOptions did not exist
			$authOptions = get_option ( 'postman_auth_token' );
			$options = get_option ( 'postman_options' );
			if (empty ( $authOptions ) && ! (empty ( $options ))) {
				// copy the variables from $options to $authToken
				$authOptions ['access_token'] = $options ['access_token'];
				$authOptions ['refresh_token'] = $options ['refresh_token'];
				$authOptions ['auth_token_expires'] = $options ['auth_token_expires'];
				update_option ( 'postman_auth_token', $authOptions );
			}
			if (! isset ( $options ['authorization_type'] )) {
				// prior to 1.0.0, access tokens were saved in authOptions without an auth type
				// prior to 0.2.5, access tokens were save in options without an auth type
				if (isset ( $authOptions ['access_token'] ) || isset ( $options ['access_token'] )) {
					$options ['authorization_type'] = 'oauth2';
					update_option ( 'postman_options', $options );
				}
			}
			if (! isset ( $options ['enc_type'] )) {
				// prior to 1.3, encryption type was combined with authentication type
				if (isset ( $options ['authorization_type'] )) {
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
		}
	}
}

register_activation_hook ( __FILE__, array (
		new PostmanActivationHandler (),
		'activatePostman' 
) );

?>