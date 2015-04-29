<?php
if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'SMTP', 'Transport Name', 'postman-smtp' );
		}
		public function getVersion() {
			return POSTMAN_PLUGIN_VERSION;
		}
		public function getHostname(PostmanOptionsInterface $options) {
			return $options->getHostname ();
		}
		public function getHostPort(PostmanOptionsInterface $options) {
			return $options->getPort ();
		}
		public function getAuthenticationType(PostmanOptionsInterface $options) {
			return $options->getAuthenticationType ();
		}
		public function getSecurityType(PostmanOptionsInterface $options) {
			return $options->getEncryptionType ();
		}
		public function getCredentialsId(PostmanOptionsInterface $options) {
			if ($options->isAuthTypeOAuth2 ()) {
				return $options->getClientId ();
			} else {
				return $options->getUsername ();
			}
		}
		public function getCredentialsSecret(PostmanOptionsInterface $options) {
			if ($options->isAuthTypeOAuth2 ()) {
				return $options->getClientSecret ();
			} else {
				return $options->getPassword ();
			}
		}
		public function isServiceProviderGoogle($hostname) {
			return endsWith ( $hostname, 'gmail.com' );
		}
		public function isServiceProviderMicrosoft($hostname) {
			return endsWith ( $hostname, 'live.com' );
		}
		public function isServiceProviderYahoo($hostname) {
			return strpos ( $hostname, 'yahoo' );
		}
		public function isOAuthUsed($authType) {
			return $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
		}
		public function isTranscriptSupported() {
			return true;
		}
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken) {
			if ($options->getAuthenticationType () == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2) {
				return new PostmanOAuth2MailAuthenticator ( $options, $authToken );
			} else {
				return new PostmanGeneralMailAuthenticator ( $options, $authToken );
			}
		}
		public function createZendMailTransport($hostname, $config) {
			return new Postman_Zend_Mail_Transport_Smtp ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
			$deliveryDetails ['transport_name'] = $this->getTransportDescription ( $options->getEncryptionType () );
			$deliveryDetails ['host'] = $options->getHostname () . ':' . $options->getPort ();
			$deliveryDetails ['auth_desc'] = $this->getAuthenticationDescription ( $options->getAuthenticationType () );
			/* translators: where %1$s is the transport type, %2$s is the host, and %3$s is the Authentication Type (e.g. Postman will send mail via smtp.gmail.com:465 using OAuth 2.0 authentication.) */
			return sprintf ( __ ( 'Postman will send mail via %1$s to %2$s using %3$s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails ['transport_name'] . '</b>', '<b>' . $deliveryDetails ['host'] . '</b>', '<b>' . $deliveryDetails ['auth_desc'] . '</b>' );
		}
		private function getTransportDescription($encType) {
			$deliveryDetails = $this->getName ();
			if ($encType == PostmanOptions::ENCRYPTION_TYPE_SSL) {
				/* translators: where %1$s is the Transport type (e.g. SMTP or SMTPS) and %2$s is the encryption type (e.g. SSL or TLS) */
				$deliveryDetails = sprintf ( '%1$s', _x ( 'SMTPS', 'Transport Name', 'postman-smtp' ) );
			} else if ($encType == PostmanOptions::ENCRYPTION_TYPE_TLS) {
				/* translators: where %1$s is the Transport type (e.g. SMTP or SMTPS) and %2$s is the encryption type (e.g. SSL or TLS) */
				$deliveryDetails = sprintf ( '%1$s', _x ( 'SMTP-STARTTLS', 'Transport Name', 'postman-smtp' ) );
			}
			return $deliveryDetails;
		}
		private function getAuthenticationDescription($authType) {
			if (PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 == $authType) {
				return _x ( 'OAuth 2.0', 'Authentication Type is OAuth 2.0', 'postman-smtp' );
			} else if (PostmanOptions::AUTHENTICATION_TYPE_NONE == $authType) {
				return _x ( 'no', 'as in "There is no Authentication"', 'postman-smtp' );
			} else {
				switch ($authType) {
					case PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 :
						$authDescription = _x ( 'CRAM-MD5', 'As in type used: CRAM-MD5', 'postman-smtp' );
						break;
					
					case PostmanOptions::AUTHENTICATION_TYPE_LOGIN :
						$authDescription = _x ( 'Login', 'As in type used: Login', 'postman-smtp' );
						break;
					
					case PostmanOptions::AUTHENTICATION_TYPE_PLAIN :
						$authDescription = _x ( 'Plain', 'As in type used: Plain', 'postman-smtp' );
						break;
					
					default :
						$authDescription = $authType;
						break;
				}
				/* translators: where %s is the Authentication Type (e.g. plain, login or crammd5) */
				return sprintf ( _x ( 'Password (%s)', 'This authentication type is password-based', 'postman-smtp' ), $authDescription );
			}
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// This is configured if:
			$configured = true;
			
			// 1. the transport is configured
			$configured &= $this->isTransportConfigured ( $options );
			
			// 2. if authentication is enabled, check further rules to confirm configured
			if ($options->isAuthTypePassword ()) {
				$configured &= $this->isPasswordAuthenticationConfigured ( $options );
			} else if ($options->isAuthTypeOAuth2 ()) {
				$configured &= $this->isOAuthAuthenticationConfigured ( $options );
			}
			
			// return the status
			return $configured;
		}
		/**
		 * The transport can have all the configuration it needs, but still not be ready for use
		 * Check to see if permission is required from the OAuth 2.0 provider
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// 1. is the transport configured
			$configured = $this->isConfigured ( $options, $token );
			
			// 2. do we have permission from the OAuth 2.0 provider
			$configured &= ! $this->isPermissionNeeded ( $options, $token );
			
			return $configured;
		}
		private function isTransportConfigured(PostmanOptionsInterface $options) {
			$hostname = $options->getHostname ();
			$port = $options->getPort ();
			return ! (empty ( $hostname ) || empty ( $port ));
		}
		private function isPasswordAuthenticationConfigured(PostmanOptionsInterface $options) {
			$username = $options->getUsername ();
			$password = $options->getPassword ();
			return $options->isAuthTypePassword () && ! (empty ( $username ) || empty ( $password ));
		}
		private function isOAuthAuthenticationConfigured(PostmanOptionsInterface $options) {
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			$senderEmail = $options->getSenderEmail ();
			$hostname = $options->getHostname ();
			$supportedOAuthProvider = $this->isServiceProviderGoogle ( $hostname ) || $this->isServiceProviderMicrosoft ( $hostname ) || $this->isServiceProviderYahoo ( $hostname );
			return $options->isAuthTypeOAuth2 () && ! (empty ( $clientId ) || empty ( $clientSecret ) || empty ( $senderEmail )) && $supportedOAuthProvider;
		}
		private function isPermissionNeeded(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			return $options->isAuthTypeOAuth2 () && (empty ( $accessToken ) || empty ( $refreshToken ));
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			if (! $this->isTransportConfigured ( $options )) {
				return __ ( 'Outgoing Mail Server (SMTP) and Port can not be empty.', 'postman-smtp' );
			} else if ($options->isAuthTypePassword () && ! $this->isPasswordAuthenticationConfigured ( $options )) {
				return __ ( 'Password authentication (Plain/Login/CRAM-MD5) requires a username and password.', 'postman-smtp' );
			} else if ($options->isAuthTypeOAuth2 () && ! $this->isOAuthAuthenticationConfigured ( $options )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label (e.g. Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, Client ID, and Client Secret.) */
				return sprintf ( __ ( 'OAuth 2.0 authentication requires a supported OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, %1$s, and %2$s.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
			} else if ($this->isPermissionNeeded ( $options, $token )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label */
				$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
				$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanViewController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $scribe->getRequestPermissionLinkText () );
				return $message;
			}
		}
		
		/**
		 * Given a hostname, what ports should we test?
		 *
		 * May return an array of several combinations.
		 */
		public function getHostsToTest($hostname) {
			$hosts = array (
					array (
							'host' => $hostname,
							'port' => '25' 
					),
					array (
							'host' => $hostname,
							'port' => '465' 
					),
					array (
							'host' => $hostname,
							'port' => '587' 
					) 
			);
			return $hosts;
		}
		
		/**
		 * First choose the auth method, in this order: XOAUTH (4000), CRAM-MD5 (3000), PLAIN (2000), LOGIN (1000)
		 * Second, choose the port, in this order: 587/STARTLS (300), 465/SMTPS (200), 25/SMTP (100), 443/GMAIL (150)
		 *
		 * SMTP supports sending with these combinations in this order of preferences:
		 *
		 * @param unknown $hostData        	
		 */
		public function getConfigurationRecommendation($hostData) {
			$port = $hostData ['port'];
			$hostname = $hostData ['host'];
			// because some servers, like smtp.broadband.rogers.com, report XOAUTH2 but have no OAuth2 front-end
			$supportedOAuth2Provider = $this->isServiceProviderGoogle ( $hostname ) || $this->isServiceProviderMicrosoft ( $hostname ) || $this->isServiceProviderYahoo ( $hostname );
			$score = 0;
			$recommendation = array ();
			// increment score for auth type
			if ($hostData ['start_tls']) {
				// STARTTLS was formalized in 2002
				// http://www.rfc-editor.org/rfc/rfc3207.txt
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_TLS;
				$score += 30000;
			} elseif ($hostData ['protocol'] == 'SMTPS') {
				// "The hopelessly confusing and imprecise term, SSL,
				// has often been used to indicate the SMTPS wrapper and
				// TLS to indicate the STARTTLS protocol extension."
				// http://stackoverflow.com/a/19942206/4368109
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_SSL;
				$score += 20000;
			} elseif ($hostData ['protocol'] == 'SMTP') {
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_NONE;
				$score += 10000;
			}
			if ($hostData ['auth_xoauth'] && $supportedOAuth2Provider) {
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
				$recommendation ['display_auth'] = 'oauth2';
				$score += 500;
			} elseif ($hostData ['auth_crammd5']) {
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5;
				$recommendation ['display_auth'] = 'password';
				$score += 400;
			} elseif ($hostData ['auth_plain']) {
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
				$recommendation ['display_auth'] = 'password';
				$score += 300;
			} elseif ($hostData ['auth_login']) {
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
				$recommendation ['display_auth'] = 'password';
				$score += 200;
			} elseif ($hostData ['auth_none']) {
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_NONE;
				$recommendation ['display_auth'] = 'none';
				$score += 100;
			}
			
			// if there was a way to send mail!
			if ($score > 0) {
				
				// tiny weighting to prejudice the port selection
				if ($port == 587) {
					$score += 4;
				} elseif ($port == 25) {
					// "due to the prevalence of machines that have worms,
					// viruses, or other malicious software that generate large amounts of
					// spam, many sites now prohibit outbound traffic on the standard SMTP
					// port (port 25), funneling all mail submissions through submission
					// servers."
					// http://www.rfc-editor.org/rfc/rfc6409.txt
					$score += 3;
				} elseif ($port == 465) {
					// use of port 465 for SMTP was deprecated in 1998
					// http://www.imc.org/ietf-apps-tls/mail-archive/msg00204.html
					$score += 2;
				} else {
					$score += 1;
				}
				
				// fill-in the rest of the recommendation
				$recommendation ['transport'] = PostmanSmtpTransport::SLUG;
				$recommendation ['priority'] = $score;
				$recommendation ['port'] = $port;
				$recommendation ['hostname'] = $hostname;
				$recommendation ['transport'] = self::SLUG;
				
				// create the recommendation message for the user
				$transportDescription = $this->getTransportDescription ( $recommendation ['enc'] );
				$authDesc = $this->getAuthenticationDescription ( $recommendation ['auth'] );
				/* translators: where %1$s is a description of the transport (eg. SMTPS-SSL), %2$s is a description of the authentication (eg. Password-CRAMMD5) and %3$d is the TCP port (eg. 465) */
				$recommendation ['message'] = sprintf ( __ ( 'Your recommended settings are %1$s with %2$s authentication on port %3$d.', 'postman-smtp' ), $transportDescription, $authDesc, $port );
			}
			return $recommendation;
		}
	}
}

if (! class_exists ( 'PostmanDummyTransport' )) {
	class PostmanDummyTransport implements PostmanTransport {
		const UNCONFIGURED = 'unconfigured';
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isServiceProviderGoogle($hostname) {
			return endsWith ( $hostname, 'gmail.com' );
		}
		public function isServiceProviderMicrosoft($hostname) {
			return endsWith ( $hostname, 'live.com' );
		}
		public function isServiceProviderYahoo($hostname) {
			return endsWith ( $hostname, 'yahoo.com' );
		}
		public function isOAuthUsed($authType) {
			return false;
		}
		public function isTranscriptSupported() {
			return false;
		}
		public function getSlug() {
		}
		public function getName() {
		}
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken) {
		}
		public function createZendMailTransport($hostname, $config) {
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function getHostsToTest($hostname) {
		}
		public function getConfigurationRecommendation($hostData) {
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			/* translators: where %s is the name of the transport (e.g. smtp) */
			return sprintf ( __ ( 'The selected transport \'%s\' is unavailable. The external plugin was probably deactivated.', 'postman-smtp' ), $options->getTransportType () );
		}
	}
}
