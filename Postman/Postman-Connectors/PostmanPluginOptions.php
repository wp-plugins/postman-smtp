<?php
if (! interface_exists ( 'PostmanPluginOptions' )) {
	interface PostmanPluginOptions {
		public function getHostname();
		public function getPort();
		public function getSenderEmail() ;
		public function getSenderName() ;
		public function getAuthenticationType() ;
		public function getEncryptionType() ;
		public function getUsername() ;
		public function getPassword();
	}
}