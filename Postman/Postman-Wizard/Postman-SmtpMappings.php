<?php
if (! class_exists ( 'SmtpMapping' )) {
	class SmtpMapping {
		// if an email is in this domain array, it is a known smtp server (easy lookup)
		private $emailDomain = array (
				'gmail.com' => 'smtp.gmail.com',
				'hotmail.com' => 'smtp.live.com',
				'outlook.com' => 'smtp.live.com',
				'yahoo.co.uk' => 'smtp.mail.yahoo.co.uk',
				'yahoo.com.au' => 'smtp.mail.yahoo.com.au',
				'yahoo.com' => 'smtp.mail.yahoo.com',
				'icloud.com' => 'smtp.mail.me.com',
				'mail.com' => 'smtp.mail.com' 
		);
		// if an email's mx is in this domain array, it is a known smtp server (dns lookup)
		// useful for custom domains that map to a mail service
		private $mx = array (
				'google.com' => 'smtp.gmail.com',
				'icloud.com' => 'smtp.mail.me.com',
				'hotmail.com' => 'smtp.live.com',
				'hushmail.com' => 'smtp.hushmail.com',
				'secureserver.net' => 'smtp.secureserver.net'
		);
		public function getSmtpFromEmail($email) {
			$hostname = substr ( strrchr ( $email, "@" ), 1 );
			while ( list ( $domain, $smtp ) = each ( $this->emailDomain ) ) {
				if (strcasecmp ( $hostname, $domain ) == 0) {
					return $smtp;
				}
			}
			return null;
		}
		public function getSmtpFromMx($mx) {
			while ( list ( $domain, $smtp ) = each ( $this->mx ) ) {
				if ($this->endswith ( $mx, $domain )) {
					return $smtp;
				}
			}
			return false;
		}
		function endswith($string, $test) {
			$strlen = strlen ( $string );
			$testlen = strlen ( $test );
			if ($testlen > $strlen)
				return false;
			return substr_compare ( $string, $test, $strlen - $testlen, $testlen, true ) === 0;
		}
	}
}