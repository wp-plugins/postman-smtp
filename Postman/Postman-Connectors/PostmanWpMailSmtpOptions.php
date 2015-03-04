<?php

// "WP Mail SMTP" (aka "Email")
// each field is a new row in options : mail_from, mail_from_name, smtp_host, smtp_port, smtp_ssl, smtp_auth, smtp_user, smtp_pass
// "Easy SMTP Mail" aka. "Webriti SMTP Mail" appears to share the data format of "WP Mail SMTP" so no need to create an Options class for it.
//
if (! class_exists ( 'PostmanWpMailSmtpOptions' )) {
	class PostmanWpMailSmtpOptions extends PostmanAbstractPluginOptions implements PostmanPluginOptions {
		const SLUG = 'wp_mail_smtp';
		const PLUGIN_NAME = 'WP Mail SMTP';
		const SENDER_EMAIL = 'mail_from';
		const SENDER_NAME = 'mail_from_name';
		const HOSTNAME = 'smtp_host';
		const PORT = 'smtp_port';
		const ENCRYPTION_TYPE = 'smtp_ssl';
		const AUTHENTICATION_TYPE = 'smtp_auth';
		const USERNAME = 'smtp_user';
		const PASSWORD = 'smtp_pass';
		public function __construct() {
			$this->options [self::SENDER_EMAIL] = get_option ( self::SENDER_EMAIL );
			$this->options [self::SENDER_NAME] = get_option ( self::SENDER_NAME );
			$this->options [self::HOSTNAME] = get_option ( self::HOSTNAME );
			$this->options [self::PORT] = get_option ( self::PORT );
			$this->options [self::ENCRYPTION_TYPE] = get_option ( self::ENCRYPTION_TYPE );
			$this->options [self::AUTHENTICATION_TYPE] = get_option ( self::AUTHENTICATION_TYPE );
			$this->options [self::USERNAME] = get_option ( self::USERNAME );
			$this->options [self::PASSWORD] = get_option ( self::PASSWORD );
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
			if (isset ( $this->options [self::HOSTNAME] ))
				return $this->options [self::HOSTNAME];
		}
		public function getPort() {
			if (isset ( $this->options [self::PORT] ))
				return $this->options [self::PORT];
		}
		public function getUsername() {
			if (isset ( $this->options [self::USERNAME] ))
				return $this->options [self::USERNAME];
		}
		public function getPassword() {
			if (isset ( $this->options [self::PASSWORD] ))
				return $this->options [self::PASSWORD];
		}
		public function getAuthenticationType() {
			if (isset ( $this->options [self::AUTHENTICATION_TYPE] )) {
				switch ($this->options [self::AUTHENTICATION_TYPE]) {
					case 'true' :
						return PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
					case 'false' :
						return PostmanOptions::AUTHENTICATION_TYPE_NONE;
				}
			}
		}
		public function getEncryptionType() {
			if (isset ( $this->options [self::ENCRYPTION_TYPE] )) {
				switch ($this->options [self::ENCRYPTION_TYPE]) {
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