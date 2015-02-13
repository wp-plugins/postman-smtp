<?php

// it's own table : wp_mail_bank
// id, from_name, from_email, mailer_type, return_path, return_email, smtp_host, smtp_port, word_wrap, encryption, smtp_keep_alive, authentication, smtp_username, smtp_password
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '1', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '2', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '0', '1', '1', 'postman@hendriks.ca', 'cleartext'
// '1', 'Wordpress', 'postman@hendriks.ca', '0', '1', '', 'smtp.gmail.com', '465', '50', '0', '1', '0', 'postman@hendriks.ca', 'cleartext'

if (! class_exists ( 'PostmanWpMailSmtpOptions' )) {
	class PostmanWpMailSmtpOptions implements PostmanPluginOptions {
		public function getHostname() {
		}
		public function getPort() {
		}
		public function getSenderEmail() {
		}
		public function getSenderName() {
		}
		public function getAuthorizationType() {
		}
		public function getEncryptionType() {
		}
		public function getUsername() {
		}
		public function getPassword() {
		}
	}
}