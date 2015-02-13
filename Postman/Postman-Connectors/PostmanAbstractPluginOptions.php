<?php
if (! class_exists ( 'PostmanAbstractPluginOptions' )) {
	
	require_once 'PostmanPluginOptions.php';
	
	/**
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractPluginOptions implements PostmanPluginOptions {
		public function isValid() {
			$valid = true;
			$host = $this->getHostname ();
			$port = $this->getPort ();
			$senderEmail = $this->getSenderEmail ();
			$senderName = $this->getSenderName ();
			$auth = $this->getAuthenticationType ();
			$enc = $this->getEncryptionType ();
			$username = $this->getUsername ();
			$password = $this->getPassword ();
			$valid &= ! empty ( $host );
			$valid &= ! empty ( $port );
			$valid &= ! empty ( $senderEmail );
			$valid &= ! empty ( $senderName );
			$valid &= ! empty ( $auth );
			$valid &= ! empty ( $enc );
			$valid &= ! empty ( $username );
			$valid &= ! empty ( $password );
			return $valid;
		}
	}
}