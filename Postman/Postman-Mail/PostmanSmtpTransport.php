<?php
if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isSmtp() {
			return true;
		}
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
			return $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
		}
		public function isTranscriptSupported() {
			return true;
		}
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'SMTP', 'Transport Name', 'postman-smtp' );
		}
		public function createZendMailTransport($hostname, $config) {
			return new Zend_Mail_Transport_Smtp ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
			$deliveryDetails ['transport_name'] = $this->getName ();
			if ($options->getEncryptionType () != PostmanOptions::ENCRYPTION_TYPE_NONE) {
				/* translators: where %1$s is the Transport type (e.g. SMTP or SMTPS) and %2$s is the encryption type (e.g. SSL or TLS) */
				$deliveryDetails ['transport_name'] = sprintf ( '%1$s-%2$s', _x ( 'SMTPS', 'Transport Name', 'postman-smtp' ), strtoupper ( $options->getEncryptionType () ) );
			}
			$deliveryDetails ['host'] = $options->getHostname () . ':' . $options->getPort ();
			if (PostmanTransportUtils::isOAuthRequired ( $this, $options->getAuthorizationType (), $options->getHostname () )) {
				$deliveryDetails ['auth_desc'] = _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' );
			} else if ($options->isAuthTypeNone ()) {
				$deliveryDetails ['auth_desc'] = _x ( 'no', 'Authentication Type', 'postman-smtp' );
			} else {
				/* translators: where %s is the Authentication Type (e.g. plain, login or crammd5) */
				$deliveryDetails ['auth_desc'] = sprintf ( _x ( 'Password (%s)', 'Authentication Type', 'postman-smtp' ), $options->getAuthorizationType () );
			}
			/* translators: where %1$s is the transport type, %2$s is the host, and %3$s is the Authentication Type (e.g. Postman will send mail via smtp.gmail.com:465 using OAuth 2.0 authentication.) */
			return sprintf ( __ ( 'Postman will send mail via %1$s to %2$s using %3$s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails ['transport_name'] . '</b>', '<b>' . $deliveryDetails ['host'] . '</b>', '<b>' . $deliveryDetails ['auth_desc'] . '</b>' );
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// This is configured if:
			$configured = true;
			
			// 1. the transport is configured
			$configured &= $this->isTransportConfigured ( $options );
			
			// 2. if authentication is enabled, that it is configured
			if ($options->isAuthTypePassword ()) {
				$configured &= $this->isPasswordAuthenticationConfigured ( $options );
			} else if ($options->isAuthTypeOAuth2 ()) {
				$configured &= $this->isOAuthAuthenticationConfigured ( $options );
			}
			
			// return the status
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
			return $options->isAuthTypeOAuth2 () && ! (empty ( $clientId ) || empty ( $clientSecret ) || empty ( $senderEmail ) || ! $isOauthHost);
		}
		private function isPermissionNeeded(PostmanOAuthToken $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			return ! (empty ( $accessToken ) || empty ( $refreshToken ));
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			if (! $this->isTransportConfigured ( $options )) {
				return __ ( 'Warning: Outgoing Mail Server (SMTP) and Port can not be empty.', 'postman-smtp' );
			} else if (! $this->isPasswordAuthenticationConfigured ( $options )) {
				return __ ( 'Warning: Password authentication (Plain/Login/CRAMMD5) requires a username and password.', 'postman-smtp' );
			} else if (! $this->isOAuthAuthenticationConfigured ( $options )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label (e.g. Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, Client ID, and Client Secret.) */
				$this->displayWarningMessage ( sprintf ( __ ( 'Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, %1$s, and %2$s.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () ) );
			} else if ($this->isPermissionNeeded ( $token )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label */
				$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
				$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanAdminController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $scribe->getRequestPermissionLinkText () );
				return $message;
			}
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
		public function isSmtp() {
			return false;
		}
		public function isServiceProviderGoogle($hostname) {
			return false;
		}
		public function isServiceProviderMicrosoft($hostname) {
			return false;
		}
		public function isServiceProviderYahoo($hostname) {
			return false;
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
		public function createZendMailTransport($hostname, $config) {
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
		}
	}
}


