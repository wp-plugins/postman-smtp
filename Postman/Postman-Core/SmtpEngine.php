<?php
if (! interface_exists ( "PostmanSmtpEngine" )) {
	interface PostmanSmtpEngine {
		public function send($hostname, $port);
	}
}
