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
		public function __construct() {
			$this->options = get_option ( 'swpsmtp_options' );
		}
		public function getHostname() {
			if (isset ( $this->options ['smtp_settings'] ['host'] ))
				return $this->options ['smtp_settings'] ['host'];
		}
		public function getPort() {
			if (isset ( $this->options ['smtp_settings'] ['port'] ))
				return $this->options ['smtp_settings'] ['port'];
		}
		public function getSenderEmail() {
			if (isset ( $this->options ['from_email_field'] ))
				return $this->options ['from_email_field'];
		}
		public function getSenderName() {
			if (isset ( $this->options ['from_name_field'] ))
				return $this->options ['from_name_field'];
		}
		public function getAuthenticationType() {
			if (isset ( $this->options ['smtp_settings'] ['autentication'] )) {
				switch ($this->options ['smtp_settings'] ['autentication']) {
					case 'yes' :
						return PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
					case 'no' :
						return PostmanOptions::AUTHENTICATION_TYPE_NONE;
				}
			}
		}
		public function getEncryptionType() {
			if (isset ( $this->options ['smtp_settings'] ['type_encryption'] )) {
				switch ($this->options ['smtp_settings'] ['type_encryption']) {
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
			if (isset ( $this->options ['smtp_settings'] ['username'] ))
				return $this->options ['smtp_settings'] ['username'];
		}
		public function getPassword() {
			if (isset ( $this->options ['smtp_settings'] ['password'] ))
				return $this->options ['smtp_settings'] ['password'];
		}
	}
}