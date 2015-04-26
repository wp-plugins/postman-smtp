<?php
require_once ("registered-domain-libs-master/PHP/effectiveTLDs.inc.php");
require_once ("registered-domain-libs-master/PHP/regDomain.inc.php");

/**
 *
 * @author jasonhendriks
 *        
 */
class PostmanPortTest {
	private $errstr;
	private $logger;
	private $hostname;
	public $hostnameDomainOnly;
	private $port;
	public $reportedHostname;
	public $reportedHostnameDomainOnly;
	public $protocol;
	public $http;
	public $https;
	public $smtp;
	public $smtps;
	public $startTls;
	public $authLogin;
	public $authPlain;
	public $authCrammd5;
	public $authXoauth;
	public $authNone;
	public $trySmtps;
	const DEBUG = true;
	
	/**
	 */
	public function __construct($hostname, $port) {
		$this->logger = new PostmanLogger ( get_class ( $this ) );
		$this->hostname = $hostname;
		$this->hostnameDomainOnly = getRegisteredDomain ( $hostname );
		$this->port = $port;
	}
	
	/**
	 *
	 * @param number $timeout        	
	 * @return boolean
	 */
	public function genericConnectionTest($timeout = 10) {
		// test if the port is open
		$socket = sprintf ( '%s:%s', $this->hostname, $this->port );
		$stream = @stream_socket_client ( $socket, $errno, $errstr, $timeout );
		$this->debug ( 'connect to %s: %s', $socket, ($stream ? 'yes' : 'no') );
		if (! $stream) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 *
	 * @param number $timeout        	
	 * @return boolean
	 */
	public function testPortQuiz($timeout = 10) {
		// test if the port is open
		$stream = @stream_socket_client ( sprintf ( 'portquiz.net:%s', $this->port ), $errno, $errstr, $timeout );
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
	public function testHttpPorts($connectTimeout = 10, $readTimeout = 10) {
		$connectionString = "ssl://%s:%s";
		$stream = @stream_socket_client ( sprintf ( $connectionString, $this->hostname, $this->port ), $errno, $errstr, $connectTimeout );
		@stream_set_timeout ( $stream, $readTimeout );
		$serverName = postmanGetServerName ();
		if (! $stream) {
			return false;
		} else {
			// see http://php.net/manual/en/transports.inet.php#113244
			$this->sendSmtpCommand ( $stream, sprintf ( 'EHLO %s', $serverName ) );
			$matches = array ();
			$line = fgets ( $stream );
			if (preg_match ( '/^HTTP.*\\s/U', $line, $matches )) {
				$this->protocol = $matches [0];
				$this->http = true;
				$this->https = true;
				return true;
			} else {
				return false;
			}
		}
	}
	/**
	 * Given a hostname, test if it has open ports
	 *
	 * @param string $hostname        	
	 */
	public function testSmtpPorts($connectTimeout = 10, $readTimeout = 10) {
		if ($this->port == 8025) {
			$this->debug ( 'Executing test code for port 8025' );
			$this->protocol = 'SMTP';
			$this->smtp = true;
			$this->authNone = 'true';
			return true;
		}
		$connectionString = "%s:%s";
		$success = $this->talkToMailServer ( $connectionString, $connectTimeout, $readTimeout );
		if ($success) {
			$this->protocol = 'SMTP';
			if (! ($this->authCrammd5 || $this->authLogin || $this->authPlain || $this->authXoauth)) {
				$this->authNone = true;
			}
		} else {
			$this->trySmtps = true;
		}
		return $success;
	}
	
	/**
	 * Given a hostname, test if it has open ports
	 *
	 * @param string $hostname        	
	 */
	public function testSmtpsPorts($connectTimeout = 10, $readTimeout = 10) {
		$connectionString = "ssl://%s:%s";
		$success = $this->talkToMailServer ( $connectionString, $connectTimeout, $readTimeout );
		if ($success) {
			if (! ($this->authCrammd5 || $this->authLogin || $this->authPlain || $this->authXoauth)) {
				$this->authNone = true;
			}
			$this->protocol = 'SMTPS';
			$this->smtps = true;
		}
		return $success;
	}
	
	/**
	 * Given a hostname, test if it has open ports
	 *
	 * @param string $hostname        	
	 */
	private function talkToMailServer($connectionString, $connectTimeout = 10, $readTimeout = 10) {
		$stream = @stream_socket_client ( sprintf ( $connectionString, $this->hostname, $this->port ), $errno, $errstr, $connectTimeout );
		@stream_set_timeout ( $stream, $readTimeout );
		$serverName = postmanGetServerName ();
		if (! $stream) {
			return false;
		} else {
			// see http://php.net/manual/en/transports.inet.php#113244
			// see http://php.net/stream_socket_enable_crypto
			$result = $this->readSmtpResponse ( $stream );
			if ($result) {
				$this->reportedHostname = $result;
				$this->reportedHostnameDomainOnly = getRegisteredDomain ( $this->reportedHostname );
				$this->debug ( sprintf ( 'domain name: %s (%s)', $this->reportedHostname, $this->reportedHostnameDomainOnly ) );
				$this->sendSmtpCommand ( $stream, sprintf ( 'EHLO %s', $serverName ) );
				$done = $this->readSmtpResponse ( $stream );
				if ($done == 'auth') {
					// no-op
				} else if ($done == 'starttls') {
					$this->sendSmtpCommand ( $stream, 'STARTTLS' );
					$this->readSmtpResponse ( $stream );
					$starttlsSuccess = @stream_socket_enable_crypto ( $stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
					if ($starttlsSuccess) {
						$this->startTls = true;
						$this->debug ( 'starttls started' );
						$this->sendSmtpCommand ( $stream, sprintf ( 'EHLO %s', $serverName ) );
						$done = $this->readSmtpResponse ( $stream );
					} else {
						$this->debug ( 'starttls failed' );
					}
				}
				fclose ( $stream );
				$this->debug ( 'return true' );
				return true;
			} else {
				fclose ( $stream );
				$this->debug ( 'return false' );
				return false;
			}
		}
	}
	private function sendSmtpCommand($stream, $message) {
		if (self::DEBUG) {
			$this->debug ( 'tx: ' . $message );
		}
		fputs ( $stream, $message . "\r\n" );
	}
	private function readSmtpResponse($stream) {
		$result = '';
		while ( ($line = fgets ( $stream )) !== false ) {
			if (self::DEBUG) {
				$this->debug ( 'rx: ' . $line );
			}
			if (preg_match ( '/^250.AUTH/', $line )) {
				// $this->debug ( '250-AUTH' );
				if (preg_match ( '/\\sLOGIN\\s/', $line )) {
					$this->authLogin = true;
					$this->debug ( 'authLogin' );
				}
				if (preg_match ( '/\\sPLAIN\\s/', $line )) {
					$this->authPlain = true;
					$this->debug ( 'authPlain' );
				}
				if (preg_match ( '/\\sCRAM-MD5\\s/', $line )) {
					$this->authCrammd5 = true;
					$this->debug ( 'authCrammd5' );
				}
				if (preg_match ( '/\\sXOAUTH.\\s/', $line )) {
					$this->authXoauth = true;
					$this->debug ( 'authXoauth' );
				}
				if (preg_match ( '/\\sANONYMOUS\\s/', $line )) {
					// Postman treats ANONYMOUS login as no authentication.
					$this->authNone = true;
					$this->debug ( 'authAnonymous => authNone' );
				}
				// done
				$result = 'auth';
			} elseif (preg_match ( '/STARTTLS/', $line )) {
				$result = 'starttls';
			} elseif (preg_match ( '/^220.(.*?)\\s/', $line, $matches )) {
				if(empty($result)) $result = $matches [1];
			}
			if (preg_match ( '/^\d\d\d\\s/', $line )) {
				// always exist on last server response line
				// $this->debug ( 'exit' );
				return $result;
			}
		}
		return false;
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
