<?php
if (! class_exists ( 'PostmanEasyWpSmtpOptions' )) {
	
	require_once 'PostmanAbstractPluginOptions.php';
	
	/**
	 * Imports Easy WP SMTP options into Postman
	 *
	 * @author jasonhendriks
	 */
	class PostmanEasyWpSmtpOptions extends PostmanAbstractPluginOptions implements PostmanPluginOptions {
		private $options;
		const SLUG = 'easy_wp_smtp';
		const PLUGIN_NAME = 'Easy WP SMTP';
		const SMTP_SETTINGS = 'smtp_settings';
		const SENDER_EMAIL = 'from_email_field';
		const SENDER_NAME = 'from_name_field';
		const HOSTNAME = 'host';
		const PORT = 'port';
		const ENCRYPTION_TYPE = 'type_encryption';
		const AUTHENTICATION_TYPE = 'autentication';
		const USERNAME = 'username';
		const PASSWORD = 'password';
		public function __construct() {
			$this->options = get_option ( 'swpsmtp_options' );
		}
		public function getPluginSlug() {
			return self::SLUG;
		}
		public function getPluginName() {
			return self::PLUGIN_NAME;
		}
		public function getSenderEmail() {
			if (isset ( $this->options [self::SENDER_EMAIL] ))
				return $this->options [self::SENDER_EMAIL];
		}
		public function getSenderName() {
			if (isset ( $this->options [self::SENDER_NAME] ))
				return $this->options [self::SENDER_NAME];
		}
		public function getHostname() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::HOSTNAME] ))
				return $this->options [self::SMTP_SETTINGS] [self::HOSTNAME];
		}
		public function getPort() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::PORT] ))
				return $this->options [self::SMTP_SETTINGS] [self::PORT];
		}
		public function getUsername() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::USERNAME] ))
				return $this->options [self::SMTP_SETTINGS] [self::USERNAME];
		}
		public function getPassword() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::PASSWORD] ))
				return $this->options [self::SMTP_SETTINGS] [self::PASSWORD];
		}
		public function getAuthenticationType() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::AUTHENTICATION_TYPE] )) {
				switch ($this->options [self::SMTP_SETTINGS] [self::AUTHENTICATION_TYPE]) {
					case 'yes' :
						return PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
					case 'no' :
						return PostmanOptions::AUTHENTICATION_TYPE_NONE;
				}
			}
		}
		public function getEncryptionType() {
			if (isset ( $this->options [self::SMTP_SETTINGS] [self::ENCRYPTION_TYPE] )) {
				switch ($this->options [self::SMTP_SETTINGS] [self::ENCRYPTION_TYPE]) {
					case 'ssl' :
						return PostmanOptions::ENCRYPTION_TYPE_SSL;
					case 'tls' :
						return PostmanOptions::ENCRYPTION_TYPE_TLS;
					case 'none' :
						return PostmanOptions::ENCRYPTION_TYPE_NONE;
				}
			}
		}
	}
}