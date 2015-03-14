<?php
require_once 'Postman/postman-common-functions.php';
require_once 'Postman/Postman-Wizard/SmtpDiscovery.php';
function test() {
	check ( 'test@hendriks.ca' );
	check ( 'test@hotmail.com' );
	check ( 'test@live.com' );
	check ( 'test@outlook.com' );
	check ( 'test@office365.com' );
	check ( 'test@gmail.com' );
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
	check ( 'test@apple.com' );
	check ( 'test@icloud.com' );
	check ( 'test@me.com' );
	check ( 'test@mac.com' );
	check ( 'timmy@hushmail.com' );
	check ('test@ryerson.ca');
	check ('art@artegennaro.com');
	check ( 'test@sendgrid.com' );
}
function check($email) {
	$d = new SmtpDiscovery ();
	$smtp = $d->getSmtpServer ( $email );
	if ($smtp) {
		print $email . ' mx=' . $d->getPrimaryMx() . ' smtp=' . $smtp . "\n";
	} else {
		print $email . ' mx=' . $d->getPrimaryMx() . ' smtp=ASK USER' . "\n";
	}
}test ();
