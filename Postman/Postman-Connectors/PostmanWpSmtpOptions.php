<?php

// wp_smtp_options
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:3:"ssl";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:15:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:3:"tls";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:15:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:0:"";s:4:"port";s:3:"465";s:8:"smtpauth";s:3:"yes";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:9:"cleartext";s:10:"deactivate";s:0:"";}
// a:9:{s:4:"from";s:19:"postman@hendriks.ca";s:8:"fromname";s:7:"WP SMTP";s:4:"host";s:14:"smtp.gmail.com";s:10:"smtpsecure";s:0:"";s:4:"port";s:3:"465";s:8:"smtpauth";s:2:"no";s:8:"username";s:19:"postman@hendriks.ca";s:8:"password";s:9:"cleartext";s:10:"deactivate";s:0:"";}

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