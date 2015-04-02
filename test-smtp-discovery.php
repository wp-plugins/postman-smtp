<?php
require_once 'Postman/postman-common-functions.php';
require_once 'Postman/Postman-Wizard/Postman-SmtpDiscovery.php';
function test() {
	check ( 'test@andrethierry.com', 'relay-hosting.secureserver.net' );
	check ( 'test@apple.com' );
	check ( 'test@artegennaro.com', 'relay-hosting.secureserver.net' );
	check ( 'test@btclick.com', 'smtp.office365.com' );
	check ( 'test@btconnect.com', 'smtp.office365.com' );
	check ( 'test@dunsire.com', 'smtp.office365.com' );
	check ( 'test@gmail.com', 'smtp.gmail.com' );
	check ( 'test@hendriksandcregg.com' );
	check ( 'test@hendriks.ca', 'smtp.gmail.com' );
	check ( 'test@hotmail.com', 'smtp.live.com' );
	check ( 'test@hushmail.com', 'smtp.hushmail.com' );
	check ( 'test@ibm.com' );
	check ( 'test@icloud.com', 'smtp.mail.me.com' );
	check ( 'test@live.com', 'smtp.live.com' );
	check ( 'test@mac.com', 'smtp.mail.me.com' );
	check ( 'test@markoneill.com', 'smtp.bizmail.yahoo.com' );
	check ( 'test@me.com', 'smtp.mail.me.com' );
	check ( 'test@office365.com' );
	check ( 'test@outlook.com', 'smtp.live.com' );
	check ( 'test@rocketmail.com', 'plus.smtp.mail.yahoo.com' );
	check ( 'test@ronwilsoninsurance.com', 'smtp.office365.com' );
	check ( 'test@rogers.com', 'smtp.broadband.rogers.com' );
	check ( 'test@ryerson.ca' );
	check ( 'test@sdlkfjsdl.com' );
	check ( 'test@sdlkfjsdl.co.uk' );
	check ( 'test@sdlkfjsdl.gov' );
	check ( 'test@sdlkfjsdl.org' );
	check ( 'test@sendgrid.com', 'smtp.gmail.com' );
	check ( 'test@yahoo.ca', 'smtp.mail.yahoo.ca' );
	check ( 'test@yahoo.co.id', 'smtp.mail.yahoo.co.id' );
	check ( 'test@yahoo.co.in', 'smtp.mail.yahoo.co.in' );
	check ( 'test@yahoo.co.kr', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.com', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.com.ar', 'smtp.mail.yahoo.com.ar' );
	check ( 'test@yahoo.com.au', 'smtp.mail.yahoo.com.au' );
	check ( 'test@yahoo.com.br', 'smtp.mail.yahoo.com.br' );
	check ( 'test@yahoo.com.cn', 'smtp.mail.yahoo.com.cn' );
	check ( 'test@yahoo.com.co' );
	check ( 'test@yahoo.com.hk', 'smtp.mail.yahoo.com.hk' );
	check ( 'test@yahoo.com.mx', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.com.my', 'smtp.mail.yahoo.com.my' );
	check ( 'test@yahoo.com.ph', 'smtp.mail.yahoo.com.ph' );
	check ( 'test@yahoo.com.sg', 'smtp.mail.yahoo.com.sg' );
	check ( 'test@yahoo.com.tw', 'smtp.mail.yahoo.com.tw' );
	check ( 'test@yahoo.com.vn', 'smtp.mail.yahoo.com.vn' );
	check ( 'test@yahoo.co.nz', 'smtp.mail.yahoo.com.au' );
	check ( 'test@yahoo.co.th', 'smtp.mail.yahoo.co.th' );
	check ( 'test@yahoo.co.uk', 'smtp.mail.yahoo.co.uk' );
	check ( 'test@yahoo.de', 'smtp.mail.yahoo.de' );
	check ( 'test@yahoo.dk', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.es', 'smtp.correo.yahoo.es' );
	check ( 'test@yahoo.fr', 'smtp.mail.yahoo.fr' );
	check ( 'test@yahoo.ie', 'smtp.mail.yahoo.co.uk' );
	check ( 'test@yahoo.it', 'smtp.mail.yahoo.it' );
	check ( 'test@yahoo.no', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.pl', 'smtp.mail.yahoo.com' );
	check ( 'test@yahoo.se', 'smtp.mail.yahoo.com' );
}
function check($email, $expectedSmtp = null) {
	$d = new SmtpDiscovery ();
	$smtp = $d->getSmtpServer ( $email );
	$displaySmtp = $smtp;
	$displaySuccess = 'fail';
	if ($smtp == $expectedSmtp) {
		$displaySuccess = 'pass';
	}
	if (! $smtp) {
		$displaySmtp = 'ASK USER';
	}
	print sprintf ( "%s: %s mx=%s smtp=%s\n", $displaySuccess, $email, $d->getPrimaryMx (), $displaySmtp );
}
test ();
