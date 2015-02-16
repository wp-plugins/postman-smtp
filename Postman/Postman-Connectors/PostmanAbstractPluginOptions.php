<?php
if (! class_exists ( 'PostmanAbstractPluginOptions' )) {
	
	require_once 'PostmanPluginOptions.php';
	
	/**
	 *
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractPluginOptions implements PostmanPluginOptions {
		protected $options;
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
			$valid &= ! empty ( $port ) && absint ( $port ) > 0 && absint ( $port ) <= 65535;
			$valid &= ! empty ( $senderEmail );
			$valid &= ! empty ( $senderName );
			$valid &= ! empty ( $auth );
			$valid &= ! empty ( $enc );
			if ($auth != PostmanOptions::AUTHENTICATION_TYPE_NONE) {
				$valid &= ! empty ( $username );
				$valid &= ! empty ( $password );
			}
			return $valid;
		}
		public function isImportable() {
			$scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $this->getAuthenticationType (), $this->getHostname () );
			$hostHasOAuthPotential = $scribe->isGoogle () || $scribe->isMicrosoft () || $scribe->isYahoo ();
			return ! $hostHasOAuthPotential && $this->isValid ();
		}
	}
}