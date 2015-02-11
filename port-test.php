<?php
require_once 'Postman/Wizard/PortTest.php';
test ( 'smtp.live.com', 25 );
test ( 'smtp.live.com', 465 );
test ( 'smtp.live.com', 587 );
function test($host, $port) {
	$p = new PostmanPortTest ();
	$success = $p->testSmtpPorts ( 'smtp.live.com', $port, 20 );
	$message = $p->getErrorMessage ();
	print "\n$host:$port success=$success message=$message\n";
}
