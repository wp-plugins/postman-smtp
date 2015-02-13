<?php

// "WP Mail SMTP" (aka "Email")
// each field is a new row in options : mail_from, mail_from_name, smtp_host, smtp_port, smtp_ssl, smtp_auth, smtp_user, smtp_pass
// "Easy SMTP Mail" appears to share the data format of "WP Mail SMTP" so no need to create an Options class for it.
// 
// mail_from : sender email
// mail_from_name : sender name
// smtp_host : hostname
// smtp_port : port
// smtp_ssl : none|ssl|tls
// smtp_auth : true|false
// smtp_user : email
// smtp_pass : password (plaintext)

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