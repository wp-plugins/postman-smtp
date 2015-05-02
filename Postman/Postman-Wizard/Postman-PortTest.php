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
	private $connectionTimeout;
	private $readTimeout;
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
	
	/**
	 */
	public function __construct($hostname, $port) {
		$this->logger = new PostmanLogger ( get_class ( $this ) );
		$this->hostname = $hostname;
		$this->hostnameDomainOnly = getRegisteredDomain ( $hostname );
		$this->port = $port;
		$this->connectionTimeout = 3;
		$this->readTimeout = 3;
	}
	public function setConnectionTimeout($timeout) {
		$this->connectionTimeout = $timeout;
		$this->logger->trace ( $this->connectionTimeout );
	}
	public function setReadTimeout($timeout) {
		$this->readTimeout = $timeout;
		$this->logger->trace ( $this->readTimeout );
	}
	private function createStream($connectionString) {
		$stream = @stream_socket_client ( $connectionString, $errno, $errstr, $this->connectionTimeout );
		if ($stream) {
			$this->trace ( sprintf ( 'connected to %s', $connectionString ) );
		} else {
			$this->trace ( sprintf ( 'Could not connect to %s because %s [%s]', $connectionString, $errstr, $errno ) );
		}
		return $stream;
	}
	
	/**
	 *
	 * @param number $timeout        	
	 * @return boolean
	 */
	public function genericConnectionTest() {
		$this->logger->trace('testCustomConnection()');
		// test if the port is open
		$connectionString = sprintf ( '%s:%s', $this->hostname, $this->port );
		$stream = $this->createStream ( $connectionString, $this->connectionTimeout );
		return null != $stream;
	}
	
	/**
	 *
	 * @param number $timeout        	
	 * @return boolean
	 */
	public function testPortQuiz() {
		$this->logger->trace('testPortQuiz()');
		// test if the port is open
		$connectionString = sprintf ( 'portquiz.net:%s', $this->port );
		$stream = $this->createStream ( $connectionString, $this->connectionTimeout );
		return null != $stream;
	}
	
	/**
	 * Given a hostname, test if it has open ports
	 *
	 * @param string $hostname        	
	 */
	public function testHttpPorts() {
		$this->logger->trace('testHttpPorts()');
		$connectionString = sprintf ( "ssl://%s:%s", $this->hostname, $this->port );
		$stream = @stream_socket_client ( sprintf ( $connectionString, $this->hostname, $this->port ), $errno, $errstr, $connectTimeout );
		$stream = $this->createStream ( $connectionString, $this->connectionTimeout );
		if ($stream) {
			@stream_set_timeout ( $stream, $this->readTimeout );
			$serverName = postmanGetServerName ();
			// see http://php.net/manual/en/transports.inet.php#113244
			$this->sendSmtpCommand ( $stream, sprintf ( 'EHLO %s', $serverName ) );
			$matches = array ();
			$line = fgets ( $stream );
			if (preg_match ( '/^HTTP.*\\s/U', $line, $matches )) {
				$this->protocol = $matches [0];
				$this->http = true;
				$this->https = true;
				$this->reportedHostname = $this->hostname;
				$this->reportedHostnameDomainOnly = getRegisteredDomain ( $this->hostname );
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	/**
	 * Given a hostname, test if it has open ports
	 *
	 * @param string $hostname        	
	 */
	public function testSmtpPorts() {
		$this->logger->trace('testSmtpPorts()');
		if ($this->port == 8025) {
			$this->debug ( 'Executing test code for port 8025' );
			$this->protocol = 'SMTP';
			$this->smtp = true;
			$this->authNone = 'true';
			return true;
		}
		$connectionString = sprintf ( "%s:%s", $this->hostname, $this->port );
		$success = $this->talkToMailServer ( $connectionString, $this->connectionTimeout, $this->readTimeout );
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
	public function testSmtpsPorts() {
		$this->logger->trace('testSmtpsPorts()');
		$connectionString = sprintf ( "ssl://%s:%s", $this->hostname, $this->port );
		$success = $this->talkToMailServer ( $connectionString, $this->connectionTimeout, $this->readTimeout );
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
	private function talkToMailServer($connectionString) {
		$this->logger->trace('talkToMailServer()');
		$stream = $this->createStream ( $connectionString, $this->connectionTimeout );
		if ($stream) {
			$serverName = postmanGetServerName ();
			@stream_set_timeout ( $stream, $this->readTimeout );
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
		} else {
			return false;
		}
	}
	private function sendSmtpCommand($stream, $message) {
		$this->trace ( 'tx: ' . $message );
		fputs ( $stream, $message . "\r\n" );
	}
	private function readSmtpResponse($stream) {
		$result = '';
		while ( ($line = fgets ( $stream )) !== false ) {
			$this->trace ( 'rx: ' . $line );
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
				if (empty ( $result ))
					$result = $matches [1];
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
	private function trace($message) {
		$this->logger->trace ( sprintf ( '%s:%s => %s', $this->hostname, $this->port, $message ) );
	}
	private function debug($message) {
		$this->logger->debug ( sprintf ( '%s:%s => %s', $this->hostname, $this->port, $message ) );
	}
	private function error($message) {
		$this->logger->error ( sprintf ( '%s:%s => %s', $this->hostname, $this->port, $message ) );
	}
}
