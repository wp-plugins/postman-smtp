<?php
if (! class_exists ( "PostmanPortTest" )) {
	class PostmanPortTest {
		private $errstr;
		private $logger;
		private $hostname;
		private $port;
		
		/**
		 */
		public function __construct($hostname, $port) {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->hostname = $hostname;
			$this->port = $port;
		}
		
		/**
		 *
		 * @param number $timeout        	
		 * @return boolean
		 */
		public function testPortQuiz($timeout = 5) {
			// first test if the port is open
			$stream = @stream_socket_client ( sprintf ( 'portquiz.net:%s', $this->port ), $errno, $errstr, 5 );
			$this->debug ( 'connect to portquiz.net: ' . ($stream ? 'yes' : 'no') );
			if (! $stream) {
				return false;
			} else {
				return true;
			}
		}
		
		/**
		 * Given a hostname, test if it has open ports
		 *
		 * @param string $hostname        	
		 */
		public function testSmtpPorts($timeout = 5) {
			$connectionString = "%s:%s";
			$this->talkToMailServer ( $connectionString, $timeout );
		}
		
		/**
		 * Given a hostname, test if it has open ports
		 *
		 * @param string $hostname        	
		 */
		public function testSmtpsPorts($timeout = 5) {
			$connectionString = "ssl://%s:%s";
			$this->talkToMailServer ( $connectionString, $timeout );
		}
		
		/**
		 * Given a hostname, test if it has open ports
		 *
		 * @param string $hostname        	
		 */
		private function talkToMailServer($connectionString, $timeout = 5) {
			if ($this->port == 443) {
				return false;
			}
			$stream = @stream_socket_client ( sprintf ( $connectionString, $this->hostname, $this->port ), $errno, $errstr, $timeout );
			$serverName = $_SERVER ['SERVER_NAME'];
			if (empty ( $serverName )) {
				$serverName = $_SERVER ['HTTP_HOST'];
			}
			if (! $stream) {
				return false;
			} else {
				// see http://php.net/manual/en/transports.inet.php#113244
				// see http://php.net/stream_socket_enable_crypto
				$done = $this->readSmtpResponse ( $stream );
				if ($done == "smtp") {
					$this->sendSmtpCommand ( $stream, 'EHLO ' . $serverName );
					$done = $this->readSmtpResponse ( $stream );
					if ($done == "auth") {
						// no-op
					} else if ($done == "starttls") {
						$this->sendSmtpCommand ( $stream, 'STARTTLS' );
						$this->readSmtpResponse ( $stream );
						$starttlsSuccess = @stream_socket_enable_crypto ( $stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
						if ($starttlsSuccess) {
							$this->debug ( 'starttls started' );
							$this->sendSmtpCommand ( $stream, 'EHLO ' . $serverName );
							$done = $this->readSmtpResponse ( $stream );
						} else {
							$this->debug ( 'starttls failed' );
						}
					}
				}
				fclose ( $stream );
				return true;
			}
		}
		private function sendSmtpCommand($stream, $message) {
			// $this->debug ( 'tx: ' . $message );
			fputs ( $stream, $message . "\r\n" );
		}
		private function readSmtpResponse($stream) {
			$result = "";
			while ( ($line = fgets ( $stream )) !== false ) {
				// $this->debug ( 'rx: ' . $line );
				if (preg_match ( '/^250-AUTH/', $line )) {
					// $this->debug ( '250-AUTH' );
					if (preg_match ( '/\\sLOGIN\\s/', $line )) {
						$this->authLogin = true;
						$this->debug ( 'authLogin' );
					}
					if (preg_match ( '/\\sPLAIN\\s/', $line )) {
						$this->authPlain = true;
						$this->debug ( 'authPlain' );
					}
					if (preg_match ( '/\\sXOAUTH.\\s/', $line )) {
						$this->authXoauth = true;
						$this->debug ( 'authXoauth' );
					}
					if (preg_match ( '/\\sANONYMOUS\\s/', $line )) {
						$this->authAnonymous = true;
						$this->debug ( 'authAnonymous' );
					}
					// done
					$result = "auth";
				} elseif (preg_match ( '/STARTTLS/', $line )) {
					$result = "starttls";
				} elseif (preg_match ( '/^220\\s/', $line )) {
					$result = "smtp";
				}
				if (preg_match ( '/^\d\d\d\\s/', $line )) {
					// always exist on last server response line
					// $this->debug ( 'exit' );
					return $result;
				}
			}
			$this->debug ( 'FAIL!!' );
		}
		public function getErrorMessage() {
			return $this->errstr;
		}
		private function debug($message) {
			$this->logger->debug ( sprintf ( '%s:%s => %s', $this->hostname, $this->port, $message ) );
		}
		private function error($message) {
			$this->logger->error ( sprintf ( '%s:%s => %s', $this->hostname, $this->port, $message ) );
		}
	}
}