<?php

// it's own table : wp_mail_bank
// id, from_name, from_email, mailer_type, return_path, return_email, smtp_host, smtp_port, word_wrap, encryption, smtp_keep_alive, authentication, smtp_username, smtp_password
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '1', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '2', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '0', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '0', '1', '0', 'postman@hendriks.ca', 'cleartext'
if (! class_exists ( 'PostmanWpMailBankOptions' )) {
	
	/**
	 * Import configuration from WP Mail Bank
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanWpMailBankOptions extends PostmanAbstractPluginOptions implements PostmanPluginOptions {
		const SLUG = 'wp_mail_bank';
		const PLUGIN_NAME = 'WP Mail Bank';
		const SENDER_EMAIL = 'from_email';
		const SENDER_NAME = 'from_name';
		const HOSTNAME = 'smtp_host';
		const PORT = 'smtp_port';
		const ENCRYPTION_TYPE = 'encryption';
		const AUTHENTICATION_TYPE = 'authentication';
		const USERNAME = 'smtp_username';
		const PASSWORD = 'smtp_password';
		public function __construct() {
			// data is stored in table wp_mail_bank
			// fields are id, from_name, from_email, mailer_type, return_path, return_email, smtp_host, smtp_port, word_wrap, encryption, smtp_keep_alive, authentication, smtp_username, smtp_password
		}
		public function getPluginSlug() {
			return self::SLUG;
		}
		public function getPluginName() {
			return self::PLUGIN_NAME;
		}
		public function getHostname() {
			if (isset ( $this->options [self::HOSTNAME] ))
				return $this->options [self::HOSTNAME];
		}
		public function getPort() {
			if (isset ( $this->options [self::PORT] ))
				return $this->options [self::PORT];
		}
		public function getSenderEmail() {
			if (isset ( $this->options [self::SENDER_EMAIL] ))
				return $this->options [self::SENDER_EMAIL];
		}
		public function getSenderName() {
			if (isset ( $this->options [self::SENDER_EMAIL] ))
				return $this->options [self::SENDER_EMAIL];
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
		public function getUsername() {
			if (isset ( $this->options [self::USERNAME] ))
				return $this->options [self::USERNAME];
		}
		public function getPassword() {
			if (isset ( $this->options [self::PASSWORD] ))
				return $this->options [self::PASSWORD];
		}
	}
}