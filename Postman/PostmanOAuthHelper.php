<?php
if (! class_exists ( 'PostmanOAuthScribeFactory' )) {
	class PostmanOAuthScribeFactory {
		private function __construct() {
		}
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanOAuthScribeFactory ();
			}
			return $inst;
		}
		public function createPostmanOAuthScribe($authType, $hostname) {
			if ($authType != PostmanOptions::AUTHENTICATION_TYPE_OAUTH2) {
				return new PostmanNonOAuthScribe ( $hostname );
			} else {
				if (endsWith ( $hostname, 'gmail.com' )) {
					return new PostmanGoogleOAuthScribe ( $hostname );
				} else if (endsWith ( $hostname, 'live.com' )) {
					return new PostmanMicrosoftOAuthScribe ( $hostname );
				} else if (endsWith ( $hostname, 'yahoo.com' )) {
					return new PostmanYahooOAuthScribe ( $hostname );
				} else {
					// bad hostname, but OAuth selected
					return new PostmanNonOAuthScribe ( $hostname );
				}
			}
		}
	}
}
if (! interface_exists ( 'PostmanOAuthHelper' )) {
	interface PostmanOAuthHelper {
		public function isOauthHost();
		public function isGoogle();
		public function isMicrosoft();
		public function isYahoo();
		public function getCallbackUrl();
		public function getCallbackDomain();
		public function getClientIdLabel();
		public function getClientSecretLabel();
		public function getCallbackUrlLabel();
		public function getCallbackDomainLabel();
		public function getOwnerName();
		public function getServiceName();
		public function getApplicationDescription();
		public function getApplicationPortalName();
		public function getApplicationPortalUrl();
		public function getOAuthPort();
		public function getEncryptionType();
	}
}
if (! class_exists ( 'PostmanAbstractOAuthHelper' )) {
	
	/**
	 *
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractOAuthHelper implements PostmanOAuthHelper {
		protected $hostname;
		public function __construct($hostname) {
			$this->hostname = $hostname;
		}
		public function getOAuthHelp() {
			/* translators: parameters available are 1=portal-url, 2=portal-name, 3=clientId-name, 4=clientSecret-name, 5=callbackUrl, 6=service-name, 7=portal-application */
			$text = sprintf ( '<p id="wizard_oauth2_help">%s', sprintf ( __ ( 'Open the <a href="%1$s" target="_new">%2$s</a>, create %7$s using the URL\'s displayed below, and copy the %3$s and %4$s here.' ), $this->getApplicationPortalUrl (), $this->getApplicationPortalName (), $this->getClientIdLabel (), $this->getClientSecretLabel (), $this->getCallbackUrlLabel (), $this->getOwnerName (), $this->getApplicationDescription () ) );
			/* translators: parameters available are 1=portal-url, 2=portal-name, 3=clientId-name, 4=clientSecret-name, 5=callbackUrl, 6=service-name, 7=portal-application */
			$text .= sprintf ( ' %s</p>', sprintf ( __ ( 'See <a href="https://wordpress.org/plugins/postman-smtp/faq/" target="_new">How do I get a %6$s %3$s?</a> in the F.A.Q. for help.' ), $this->getApplicationPortalUrl (), $this->getApplicationPortalName (), $this->getClientIdLabel (), $this->getClientSecretLabel (), $this->getCallbackUrlLabel (), $this->getOwnerName (), $this->getApplicationDescription () ) );
			return $text;
		}
		function isOauthHost() {
			return false;
		}
		function isGoogle() {
			return false;
		}
		function isMicrosoft() {
			return false;
		}
		function isYahoo() {
			return false;
		}
		function getCallbackDomain() {
			$callbackUrl = $this->getCallbackUrl ();
			if (! empty ( $callbackUrl ))
				return stripUrlPath ( $this->getCallbackUrl () );
		}
		public function getRequestPermissionLinkText() {
			return sprintf ( __ ( 'Request permission from %s', 'Command to initiate OAuth authentication' ), $this->getOwnerName () );
		}
	}
}
if (! class_exists ( 'PostmanGoogleOAuthScribe' )) {
	class PostmanGoogleOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isGoogle() {
			return true;
		}
		function isOauthHost() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' ) . '?page=postman';
		}
		public function getClientIdLabel() {
			return __ ( 'Client ID', 'Name of the OAuth 2.0 Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Client Secret', 'Name of the OAuth 2.0 Client Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Redirect URI', 'Name of the Application Callback URI' );
		}
		public function getCallbackDomainLabel() {
			return __ ( 'Javascript Origins', 'Name of the Application Callback Domain' );
		}
		public function getOwnerName() {
			return __ ( "Google", 'Name of the email service owner' );
		}
		public function getServiceName() {
			return __ ( "Gmail", 'Name of the email service' );
		}
		public function getApplicationDescription() {
			return __ ( 'a Client ID for web application', 'Description of the email service OAuth 2.0 Application' );
		}
		public function getApplicationPortalName() {
			return __ ( 'Google Developer Console', 'Name of the email service portal' );
		}
		public function getApplicationPortalUrl() {
			return 'https://console.developers.google.com/';
		}
		public function getOAuthPort() {
			return 465;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_SSL;
		}
	}
}
if (! class_exists ( 'PostmanMicrosoftOAuthScribe' )) {
	class PostmanMicrosoftOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isMicrosoft() {
			return true;
		}
		function isOauthHost() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' );
		}
		public function getClientIdLabel() {
			return __ ( 'Client ID', 'Name of the OAuth 2.0 Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Client secret', 'Name of the OAuth 2.0 Client Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Redirect URL', 'Name of the Application Callback URI' );
		}
		public function getCallbackDomainLabel() {
			return __ ( 'Root Domain', 'Name of the Application Callback Domain' );
		}
		public function getOwnerName() {
			return __ ( 'Microsoft', 'Name of the email service owner' );
		}
		public function getServiceName() {
			return __ ( 'Outlook.com', 'Name of the email service' );
		}
		public function getApplicationDescription() {
			return __ ( 'an Application', 'Description of the email service OAuth 2.0 Application' );
		}
		public function getApplicationPortalName() {
			return __ ( 'Microsoft Developer Center', 'Name of the email service portal' );
		}
		public function getApplicationPortalUrl() {
			return 'https://account.live.com/developers/applications/index';
		}
		public function getOAuthPort() {
			return 587;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_TLS;
		}
	}
}
if (! class_exists ( 'PostmanYahooOAuthScribe' )) {
	class PostmanYahooOAuthScribe extends PostmanAbstractOAuthHelper {
		public function isYahoo() {
			return true;
		}
		function isOauthHost() {
			return true;
		}
		public function getCallbackUrl() {
			return admin_url ( 'options-general.php' ) . '?page=postman';
		}
		public function getClientIdLabel() {
			return __ ( 'Consumer Key', 'Name of the OAuth 2.0 Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Consumer Secret', 'Name of the OAuth 2.0 Client Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Home Page URL', 'Name of the Application Callback URI' );
		}
		public function getCallbackDomainLabel() {
			return __ ( 'Callback Domain', 'Name of the Application Callback Domain' );
		}
		public function getOwnerName() {
			return __ ( 'Yahoo', 'Name of the email service owner' );
		}
		public function getServiceName() {
			return __ ( 'Yahoo Mail', 'Name of the email service' );
		}
		public function getApplicationDescription() {
			return __ ( 'an Application', 'Description of the email service OAuth 2.0 Application' );
		}
		public function getApplicationPortalName() {
			return __ ( 'Yahoo Developer Network', 'Name of the email service portal' );
		}
		public function getApplicationPortalUrl() {
			return 'https://developer.apps.yahoo.com/projects';
		}
		public function getOAuthPort() {
			return 465;
		}
		public function getEncryptionType() {
			return PostmanOptions::ENCRYPTION_TYPE_SSL;
		}
	}
}
if (! class_exists ( 'PostmanNonOAuthScribe' )) {
	class PostmanNonOAuthScribe extends PostmanAbstractOAuthHelper {
		public function __construct($hostname) {
			parent::__construct ( $hostname );
		}
		public function isGoogle() {
			return endsWith ( $this->hostname, 'gmail.com' );
		}
		public function isMicrosoft() {
			return endsWith ( $this->hostname, 'live.com' );
		}
		public function isYahoo() {
			return endsWith ( $this->hostname, 'yahoo.com' );
		}
		public function getOAuthHelp() {
			return '<p id="wizard_oauth2_help"><span style="color:red" class="normal">Enter an Outgoing Mail Server with OAuth 2.0 capabilities.</span></p>';
		}
		public function getCallbackUrl() {
			return '';
		}
		public function getClientIdLabel() {
			return __ ( 'Client ID', 'Name of the OAuth 2.0 Client ID' );
		}
		public function getClientSecretLabel() {
			return __ ( 'Client Secret', 'Name of the OAuth 2.0 Client Secret' );
		}
		public function getCallbackUrlLabel() {
			return __ ( 'Redirect URI', 'Name of the Application Callback URI' );
		}
		public function getCallbackDomainLabel() {
			return __ ( 'Website Domain', 'Name of the Application Callback Domain' );
		}
		public function getOwnerName() {
			return '';
		}
		public function getServiceName() {
			return '';
		}
		public function getApplicationDescription() {
			return '';
		}
		public function getApplicationPortalName() {
			return '';
		}
		public function getApplicationPortalUrl() {
			return '';
		}
		public function getOAuthPort() {
			return '';
		}
		public function getEncryptionType() {
			return '';
		}
		public function getRequestPermissionLinkText() {
			return __ ( 'Request OAuth Permission', 'Command to initiate OAuth authentication' );
		}
	}
}