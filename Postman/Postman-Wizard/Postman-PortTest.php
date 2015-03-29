<?php
if (! class_exists ( "PostmanPortTest" )) {
	class PostmanPortTest {
		private $errstr;
		
		/**
		 * Given a hostname, test if it has open ports
		 *
		 * @param string $hostname        	
		 */
		public function testSmtpPorts($hostname, $port, $timeout = 20) {
			$fp = @fsockopen ( $hostname, $port, $errno, $this->errstr, $timeout );
			if (! $fp) {
				return false;
			} else {
				fclose ( $fp );
				return true;
			}
		}
		public function getErrorMessage() {
			return $this->errstr;
		}
	}
}