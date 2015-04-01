<?php
if (! class_exists ( 'SmtpMapping' )) {
	class SmtpMapping {
		// if an email is in this domain array, it is a known smtp server (easy lookup)
		private $emailDomain = array (
				'gmail.com' => 'smtp.gmail.com',
				'hotmail.com' => 'smtp.live.com',
				'icloud.com' => 'smtp.mail.me.com',
				'mail.com' => 'smtp.mail.com',
				'rocketmail.com' => 'plus.smtp.mail.yahoo.com',
				'yahoo.ca' => 'smtp.mail.yahoo.ca',
				'yahoo.co.id' => 'smtp.mail.yahoo.co.id',
				'yahoo.co.in' => 'smtp.mail.yahoo.co.in',
				'yahoo.co.kr' => 'smtp.mail.yahoo.com',
				'yahoo.com' => 'smtp.mail.yahoo.com',
				'yahoo.com.ar' => 'smtp.mail.yahoo.com.ar',
				'yahoo.com.au' => 'smtp.mail.yahoo.com.au',
				'yahoo.com.br' => 'smtp.mail.yahoo.com.br',
				'yahoo.com.cn' => 'smtp.mail.yahoo.com.cn',
				'yahoo.com.hk' => 'smtp.mail.yahoo.com.hk',
				'yahoo.com.mx' => 'smtp.mail.yahoo.com',
				'yahoo.com.my' => 'smtp.mail.yahoo.com.my',
				'yahoo.com.ph' => 'smtp.mail.yahoo.com.ph',
				'yahoo.com.sg' => 'smtp.mail.yahoo.com.sg',
				'yahoo.com.tw' => 'smtp.mail.yahoo.com.tw',
				'yahoo.com.vn' => 'smtp.mail.yahoo.com.vn',
				'yahoo.co.nz' => 'smtp.mail.yahoo.com.au',
				'yahoo.co.th' => 'smtp.mail.yahoo.co.th',
				'yahoo.co.uk' => 'smtp.mail.yahoo.co.uk',
				'yahoo.de' => 'smtp.mail.yahoo.de',
				'yahoo.es' => 'smtp.correo.yahoo.es',
				'yahoo.fr' => 'smtp.mail.yahoo.fr',
				'yahoo.ie' => 'smtp.mail.yahoo.co.uk',
				'yahoo.it' => 'smtp.mail.yahoo.it' 
		);
		// if an email's mx is in this domain array, it is a known smtp server (dns lookup)
		// useful for custom domains that map to a mail service
		private $mx = array (
				'google.com' => 'smtp.gmail.com',
				'icloud.com' => 'smtp.mail.me.com',
				'hotmail.com' => 'smtp.live.com',
				'mx-eu.mail.am0.yahoodns.net' => 'smtp.mail.yahoo.com',
				'mail.protection.outlook.com' => 'smtp.office365.com',
				'biz.mail.am0.yahoodns.net' => 'smtp.bizmail.yahoo.com',
				'hushmail.com' => 'smtp.hushmail.com',
				'gmx.net' => 'mail.gmx.com',
				'mandrillapp.com' => 'smtp.mandrillapp.com',
				'secureserver.net' => 'relay-hosting.secureserver.net' 
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