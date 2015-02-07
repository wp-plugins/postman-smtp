<?php
if (! class_exists ( "PostmanLogger" )) {
	
	if (! isset ( $_SESSION )) {
		session_start ();
	}
	//
	class PostmanLogger {
		private $name;
		function __construct($name) {
			$this->name = $name;
		}
		function debug($text) {
			error_log ( 'DEBUG ' . $this->name . ': ' . $text );
		}
		function error($text) {
			error_log ( 'ERROR ' . $this->name . ': ' . $text );
		}
	}
}
?>