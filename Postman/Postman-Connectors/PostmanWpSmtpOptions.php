<?php

// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:3:"ssl";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:15:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:3:"tls";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:15:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:0:"";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:9:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:0:"";s:4:"port";s:3:"465";s:8:"smtpauth";s:2:"no";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:9:"cleartext";s:10:"deactivate";s:0:"";}
if (! class_exists ( 'PostmanWpSmtpOptions' )) {
	class PostmanWpSmtpOptions extends PostmanAbstractPluginOptions implements PostmanPluginOptions {
		private $options;
		const SLUG = 'wp_smtp'; // god these names are terrible
		const PLUGIN_NAME = 'WP SMTP';
		const SENDER_EMAIL = 'from';
		const SENDER_NAME = 'fromname';
		const HOSTNAME = 'host';
		const PORT = 'port';
		const ENCRYPTION_TYPE = 'smtpsecure';
		const AUTHENTICATION_TYPE = 'smtpauth';
		const USERNAME = 'username';
		const PASSWORD = 'password';
		public function __construct() {
			$this->options = get_option ( 'wp_smtp_options' );
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
					case 'yes' :
						return PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
					case 'no' :
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
					case '' :
						return PostmanOptions::ENCRYPTION_TYPE_NONE;
				}
			}
		}
	}
}