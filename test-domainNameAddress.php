<?php
require_once 'Postman/postman-common-functions.php';
require_once 'Postman/Postman-Wizard/Postman-SmtpDiscovery.php';
function test() {
	// DNS tests
	check ( 'andrethierry.com', false );
	// IPv4 tests
	check ( '0.0.0.0', true );
	check ( '127.0.0.1', true );
	check ( '255.255.255.255', true );
	check ( '256.256.256.256', false );
	// IPv6 tests
	check ( '2001:0db8:85a3:0000:0000:8a2e:0370:7334', true );
	check ( '2001:db8:85a3:0:0:8a2e:370:7334', true );
	check ( '2001:db8:85a3::8a2e:370:7334', true );
	check ( '::ffff:192.0.2.128', true );
}
function check($ipAddress, $expectedresult) {
	$result = isHostAddressNotADomainName ( $ipAddress );
	$displaySuccess = 'fail';
	if ($result == $expectedresult) {
		$displaySuccess = 'pass';
	}
	print sprintf ( "%s: %s=%s\n", $displaySuccess, $ipAddress, $result );
}
test ();
