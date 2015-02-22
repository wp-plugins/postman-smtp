<?php
if (! interface_exists ( "PostmanOptionsInterface" )) {
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 *
	 * Make sure these emails are permitted (see http://en.wikipedia.org/wiki/E-mail_address#Internationalization):
	 */
	interface PostmanOptionsInterface {
		public function save();
		public function isNew();
		public function isSendingEmailAllowed(PostmanOAuthToken $token);
		public function isPermissionNeeded(PostmanOAuthToken $token);
		public function isSmtpServerRequirementsNotMet();
		public function isOAuthRequirementsNotMet($isOauthHost);
		public function isPasswordCredentialsNeeded();
		public function isErrorPrintingEnabled();
		public function getLogLevel();
		public function getHostname();
		public function getPort();
		public function getSenderEmail();
		public function getSenderName();
		public function getClientId();
		public function getClientSecret();
		public function getTransportType();
		public function getAuthenticationType();
		public function getEncryptionType();
		public function getUsername();
		public function getPassword();
		public function getReplyTo();
		public function getConnectionTimeout();
		public function getReadTimeout();
		public function isSenderNameOverridePrevented();
		public function isAuthTypePassword();
		public function isAuthTypeOAuth2();
		public function isAuthTypeLogin();
		public function isAuthTypePlain();
		public function isAuthTypeCrammd5();
		public function isAuthTypeNone();
	}
}