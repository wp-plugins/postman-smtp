<?php

namespace Postman {

	interface SmtpEngine {
		public function __construct(&$options);
		public function send();
	}

}