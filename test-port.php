<?php
require_once 'Postman/Postman-Wizard/PortTest.php';
test ( 'smtp.live.com', 25 );
test ( 'smtp.live.com', 465 );
test ( 'smtp.live.com', 587 );
function test($host, $port) {
	$time = time();
	$p = new PostmanPortTest ();
	$success = $p->testSmtpPorts ( 'smtp.live.com', $port, 20 );
	$message = $p->getErrorMessage ();
	$time = time() - $time;
	print "\n$host:$port time=$time success=$success message=$message\n";
}
