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
		public function __construct() {
			// data is stored in table wp_mail_bank
			// fields are id, from_name, from_email, mailer_type, return_path, return_email, smtp_host, smtp_port, word_wrap, encryption, smtp_keep_alive, authentication, smtp_username, smtp_password
			global $wpdb;
			$wpdb->show_errors();
			$wpdb->suppress_errors();
			$this->options = @$wpdb->get_row ( "SELECT from_name, from_email, mailer_type, smtp_host, smtp_port, encryption, authentication, smtp_username, smtp_password FROM " . $wpdb->prefix . "mail_bank" );
		}
		public function getPluginSlug() {
			return self::SLUG;
		}
		public function getPluginName() {
			return self::PLUGIN_NAME;
		}
		public function getSenderEmail() {
			if (isset ( $this->options->from_email ))
				return $this->options->from_email;
		}
		public function getSenderName() {
			if (isset ( $this->options->from_name )) {
				return stripslashes ( htmlspecialchars_decode ( $this->options->from_name, ENT_QUOTES ) );
			}
		}
		public function getHostname() {
			if (isset ( $this->options->smtp_host ))
				return $this->options->smtp_host;
		}
		public function getPort() {
			if (isset ( $this->options->smtp_port ))
				return $this->options->smtp_port;
		}
		public function getUsername() {
			if (isset ( $this->options->authentication ) && isset ( $this->options->smtp_username ))
				if ($this->options->authentication == 1)
					return $this->options->smtp_username;
		}
		public function getPassword() {
			if (isset ( $this->options->authentication ) && isset ( $this->options->smtp_password )) {
				if ($this->options->authentication == 1)
					return $this->options->smtp_password;
			}
		}
		public function getAuthenticationType() {
			if (isset ( $this->options->authentication )) {
				if ($this->options->authentication == 1) {
					return PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
				} else if ($this->options->authentication == 0) {
					return PostmanOptions::AUTHENTICATION_TYPE_NONE;
				}
			}
		}
		public function getEncryptionType() {
			if (isset ( $this->options->mailer_type )) {
				if ($this->options->mailer_type == 0) {
					switch ($this->options->encryption) {
						case 0 :
							return PostmanOptions::ENCRYPTION_TYPE_NONE;
						case 1 :
							return PostmanOptions::ENCRYPTION_TYPE_SSL;
						case 2 :
							return PostmanOptions::ENCRYPTION_TYPE_TLS;
					}
				}
			}
		}
	}
}