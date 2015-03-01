<?php
if (! class_exists ( 'PostmanConfigTextHelperFactory' )) {
	class PostmanConfigTextHelperFactory {
		/**
		 * This must support the concept of a hypothetical hostname, not the actual hostname
		 *
		 * @param PostmanTransport $transport        	
		 * @param unknown $hostname        	
		 * @return PostmanGoogleOAuthScribe|PostmanMicrosoftOAuthScribe|PostmanYahooOAuthScribe|PostmanNonOAuthScribe
		 */
		static function createScribe(PostmanTransport $transport, $hostname) {
			if ($transport->isServiceProviderGoogle ( $hostname )) {
				return new PostmanGoogleOAuthScribe ();
			} else if ($transport->isServiceProviderMicrosoft ( $hostname )) {
				return new PostmanMicrosoftOAuthScribe ();
			} else if ($transport->isServiceProviderYahoo ( $hostname )) {
				return new PostmanYahooOAuthScribe ();
			} else {
				return new PostmanNonOAuthScribe ( $hostname );
			}
		}
	}
}
if (! interface_exists ( 'PostmanConfigTextHelper' )) {
	interface PostmanConfigTextHelper {
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
if (! class_exists ( 'PostmanAbstractConfigTextHelper' )) {
	
	/**
	 *
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractConfigTextHelper implements PostmanConfigTextHelper {
		public function getOAuthHelp() {
			/* translators: parameters available are 1=portal-url, 2=portal-name, 3=clientId-name, 4=clientSecret-name, 5=callbackUrl, 6=service-name, 7=portal-application (e.g. Open the Google Developer Console, create a Client ID for web application using the URL's displayed below, and copy the Client ID and Client Secret here.) */
			$text = sprintf ( '%s', sprintf ( __ ( '<b style="color:red">Attention!</b> Open the <a href="%1$s" target="_new">%2$s</a>, create %7$s using the URL\'s displayed below, and copy the %3$s and %4$s here.', 'postman-smtp' ), $this->getApplicationPortalUrl (), $this->getApplicationPortalName (), $this->getClientIdLabel (), $this->getClientSecretLabel (), $this->getCallbackUrlLabel (), $this->getOwnerName (), $this->getApplicationDescription () ) );
			/* translators: parameters available are 1=portal-url, 2=portal-name, 3=clientId-name, 4=clientSecret-name, 5=callbackUrl, 6=service-name, 7=portal-application (e.g. See How do I get a Google Client ID? in the F.A.Q. for help.) */
			$text .= sprintf ( ' %s', sprintf ( __ ( 'See <a href="https://wordpress.org/plugins/postman-smtp/faq/" target="_new">How do I get a %6$s %3$s?</a> in the F.A.Q. for help.', 'postman-smtp' ), $this->getApplicationPortalUrl (), $this->getApplicationPortalName (), $this->getClientIdLabel (), $this->getClientSecretLabel (), $this->getCallbackUrlLabel (), $this->getOwnerName (), $this->getApplicationDescription () ) );
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
			/* translators: where %s is the Email Service Owner (e.g. Google, Microsoft or Yahoo) */
			return sprintf ( _x ( 'Request permission from %s', 'Command to initiate OAuth authentication', 'postman-smtp' ), $this->getOwnerName () );
		}
	}
}
if (! class_exists ( 'PostmanGoogleOAuthScribe' )) {
	class PostmanGoogleOAuthScribe extends PostmanAbstractConfigTextHelper {
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
			return _x ( 'Client ID', 'Name of the OAuth 2.0 Client ID', 'postman-smtp' );
		}
		public function getClientSecretLabel() {
			return _x ( 'Client Secret', 'Name of the OAuth 2.0 Client Secret', 'postman-smtp' );
		}
		public function getCallbackUrlLabel() {
			return _x ( 'Redirect URI', 'Name of the Application Callback URI', 'postman-smtp' );
		}
		public function getCallbackDomainLabel() {
			return _x ( 'Javascript Origins', 'Name of the Application Callback Domain', 'postman-smtp' );
		}
		public function getOwnerName() {
			return _x ( 'Google', 'Name of the email service owner', 'postman-smtp' );
		}
		public function getServiceName() {
			return _x ( 'Gmail', 'Name of the email service', 'postman-smtp' );
		}
		public function getApplicationDescription() {
			return _x ( 'a Client ID for web application', 'Description of the email service OAuth 2.0 Application', 'postman-smtp' );
		}
		public function getApplicationPortalName() {
			return _x ( 'Google Developer Console', 'Name of the email service portal', 'postman-smtp' );
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
	class PostmanMicrosoftOAuthScribe extends PostmanAbstractConfigTextHelper {
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
			return _x ( 'Client ID', 'Name of the OAuth 2.0 Client ID', 'postman-smtp' );
		}
		public function getClientSecretLabel() {
			return _x ( 'Client secret', 'Name of the OAuth 2.0 Client Secret', 'postman-smtp' );
		}
		public function getCallbackUrlLabel() {
			return _x ( 'Redirect URL', 'Name of the Application Callback URI', 'postman-smtp' );
		}
		public function getCallbackDomainLabel() {
			return _x ( 'Root Domain', 'Name of the Application Callback Domain', 'postman-smtp' );
		}
		public function getOwnerName() {
			return _x ( 'Microsoft', 'Name of the email service owner', 'postman-smtp' );
		}
		public function getServiceName() {
			return _x ( 'Outlook.com', 'Name of the email service', 'postman-smtp' );
		}
		public function getApplicationDescription() {
			return _x ( 'an Application', 'Description of the email service OAuth 2.0 Application', 'postman-smtp' );
		}
		public function getApplicationPortalName() {
			return _x ( 'Microsoft Developer Center', 'Name of the email service portal', 'postman-smtp' );
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
	class PostmanYahooOAuthScribe extends PostmanAbstractConfigTextHelper {
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
			return _x ( 'Consumer Key', 'Name of the OAuth 2.0 Client ID', 'postman-smtp' );
		}
		public function getClientSecretLabel() {
			return _x ( 'Consumer Secret', 'Name of the OAuth 2.0 Client Secret', 'postman-smtp' );
		}
		public function getCallbackUrlLabel() {
			return _x ( 'Home Page URL', 'Name of the Application Callback URI', 'postman-smtp' );
		}
		public function getCallbackDomainLabel() {
			return _x ( 'Callback Domain', 'Name of the Application Callback Domain', 'postman-smtp' );
		}
		public function getOwnerName() {
			return _x ( 'Yahoo', 'Name of the email service owner', 'postman-smtp' );
		}
		public function getServiceName() {
			return _x ( 'Yahoo Mail', 'Name of the email service', 'postman-smtp' );
		}
		public function getApplicationDescription() {
			return _x ( 'an Application', 'Description of the email service OAuth 2.0 Application', 'postman-smtp' );
		}
		public function getApplicationPortalName() {
			return _x ( 'Yahoo Developer Network', 'Name of the email service portal', 'postman-smtp' );
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
	class PostmanNonOAuthScribe extends PostmanAbstractConfigTextHelper {
		protected $hostname;
		public function __construct($hostname) {
			$this->hostname = $hostname;
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
			$text = __ ( 'Enter an Outgoing Mail Server with OAuth2 capabilities.', 'postman-smtp' );
			return sprintf ( '<span style="color:red" class="normal">%s</span>', $text );
		}
		public function getCallbackUrl() {
			return '';
		}
		public function getClientIdLabel() {
			return _x ( 'Client ID', 'Name of the OAuth 2.0 Client ID', 'postman-smtp' );
		}
		public function getClientSecretLabel() {
			return _x ( 'Client Secret', 'Name of the OAuth 2.0 Client Secret', 'postman-smtp' );
		}
		public function getCallbackUrlLabel() {
			return _x ( 'Redirect URI', 'Name of the Application Callback URI', 'postman-smtp' );
		}
		public function getCallbackDomainLabel() {
			return _x ( 'Website Domain', 'Name of the Application Callback Domain', 'postman-smtp' );
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
			return _x ( 'Request OAuth Permission', 'Command to initiate OAuth authentication', 'postman-smtp' );
		}
	}
}