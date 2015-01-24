<?php

namespace Postman {

	require_once "SmtpMappings.php";
	class SmtpDiscovery {
		public function getPrimaryMxHost($hostname) {
			$b_mx_avail = getmxrr ( $hostname, $mx_records, $mx_weight );
			if ($b_mx_avail && sizeof ( $mx_records ) > 0) {
				// copy mx records and weight into array $mxs
				$mxs = array ();
				
				for($i = 0; $i < count ( $mx_records ); $i ++) {
					$mxs [$mx_weight [$i]] = $mx_records [$i];
				}
				
				// sort array mxs to get servers with highest prio
				ksort ( $mxs, SORT_NUMERIC );
				reset ( $mxs );
				return array_shift ( array_values ( $mxs ) );
			} else {
				return false;
			}
		}
		public function validateEmail($email) {
			$exp = "^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$";
			return eregi ( $exp, $email );
		}
		public function getSmtpServer($email) {
			$hostname = substr ( strrchr ( $email, "@" ), 1 );
			$mapping = new SmtpMapping ();
			$smtp = $mapping->getSmtpFromEmail ( $email );
			if (! $this->validateEmail ( $email )) {
				return false;
			}
			if ($smtp) {
				// print $email . ' smtp=' . $smtp . "\n";
				return $smtp;
			} else {
				$host = $this->getPrimaryMxHost ( $hostname );
				if ($host) {
					$smtp = $mapping->getSmtpFromMx ( $host );
					if ($smtp) {
						// print $email . " mx=" . $host . ' smtp=' . $smtp . "\n";
						return $smtp;
					} else {
						// print $email . ' :( ask user for SMTP - I have no idea' . "\n";
						return false;
					}
				} else {
					// print $email . ' :( ask user for SMTP - I have no idea' . "\n";
					return false;
				}
			}
		}
	}
	
	check ( 'jason@jason@hendriks.ca' );
	check ( 'test@hotmail.com' );
	check ( 'test@office365.com' );
	check ( 'test@gmail.com' );
	check ( 'test@hendriks.ca' );
	check ( 'test@yahoo.com.co' );
	check ( 'test@yahoo.com' );
	check ( 'test@hendriksandcregg.com' );
	check ( 'test@yahoo.co.uk' );
	check ( 'test@yahoo.com.au' );
	check ( 'test@ibm.com' );
	check ( 'test@sdlkfjsdl.co.uk' );
	check ( 'test@sdlkfjsdl.org' );
	check ( 'test@sdlkfjsdl.gov' );
	check ( 'test@sdlkfjsdl.com' );
	function check($email) {
		$d = new SmtpDiscovery ();
		$smtp = $d->getSmtpServer ( $email );
		if ($smtp) {
			print $email . '=' . $smtp . "\n";
		} else {
			print $email . ' ASK USER' . "\n";
		}
	}
}

namespace {
	
	// support windows platforms
	if (! function_exists ( 'getmxrr' )) {
		function getmxrr($hostname, &$mxhosts, &$mxweight) {
			if (! is_array ( $mxhosts )) {
				$mxhosts = array ();
			}
			$hostname = escapeshellarg($hostname);
			if (! empty ( $hostname )) {
				$output = "";
				@exec ( "nslookup.exe -type=MX $hostname.", $output );
				$imx = - 1;
				
				foreach ( $output as $line ) {
					$imx ++;
					$parts = "";
					if (preg_match ( "/^$hostname\tMX preference = ([0-9]+), mail exchanger = (.*)$/", $line, $parts )) {
						$mxweight [$imx] = $parts [1];
						$mxhosts [$imx] = $parts [2];
					}
				}
				return ($imx != - 1);
			}
			return false;
		}
	}
}

?>