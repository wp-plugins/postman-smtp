<?php
if (! class_exists ( 'SmtpDiscovery' )) {
	
	require_once "SmtpMappings.php";

	class SmtpDiscovery {
		private $primaryMx;
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
				$mxs_vals = array_values ( $mxs );
				return array_shift ( $mxs_vals );
			} else {
				return false;
			}
		}
		public function validateEmail($email) {
			return postmanValidateEmail($email);
		}
		public function getSmtpServer($email) {
			$hostname = substr ( strrchr ( $email, "@" ), 1 );
			$mapping = new SmtpMapping ();
			$smtp = $mapping->getSmtpFromEmail ( $email );
			if (! $this->validateEmail ( $email )) {
				return false;
			}
			if ($smtp) {
				return $smtp;
			} else {
				$host = $this->getPrimaryMxHost ( $hostname );
				$this->primaryMx = $host;
				if ($host) {
					$smtp = $mapping->getSmtpFromMx ( $host );
					if ($smtp) {
						return $smtp;
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		public function getPrimaryMx() {
			return $this->primaryMx;
		}
	}
}

// support windows platforms
if (! function_exists ( 'getmxrr' )) {
	function getmxrr($hostname, &$mxhosts, &$mxweight) {
		if (! is_array ( $mxhosts )) {
			$mxhosts = array ();
		}
		$hostname = escapeshellarg ( $hostname );
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
