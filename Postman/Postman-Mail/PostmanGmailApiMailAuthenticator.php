<?php
if (! class_exists ( 'PostmanGmailApiMailAuthenticator' )) {
	class PostmanGmailApiMailAuthenticator extends PostmanOAuth2MailAuthenticator {
		private function getEncryptionType() {
			return PostmanGmailApiTransport::ENCRYPTION_TYPE;
		}
		private function getPort() {
			return PostmanGmailApiTransport::PORT;
		}
	}
}
