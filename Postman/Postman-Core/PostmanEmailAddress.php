<?php
if (! class_exists ( 'PostmanEmailAddress' )) {
	class PostmanEmailAddress {
		private $name;
		private $email;
		public function __construct($email, $name = null) {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			if (preg_match ( '/(.*)<(.+)>/', $email, $matches )) {
				if (count ( $matches ) == 3) {
					$name = $matches [1];
					$email = $matches [2];
				}
			}
			$this->setEmail ( $email );
			$this->setName ( $name );
		}
		public function getName() {
			return $this->name;
		}
		public function getEmail() {
			return $this->email;
		}
		public function setName($name) {
			$this->name = $name;
		}
		public function setEmail($email) {
			if (! $this->validateEmail ( $email )) {
				throw new Exception ( "invalid email=" . $email );
			}
			$this->email = $email;
		}
		/**
		 * Validate an email address
		 *
		 * @param unknown $email        	
		 * @return number
		 */
		public function validateEmail($email) {
			$exp = "/^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$/i";
			return preg_match ( $exp, $email );
		}
		
		/**
		 * Accept a String of addresses or an array and return an array
		 *
		 * @param unknown $recipientList        	
		 * @param unknown $recipients        	
		 */
		public static function convertToArray($emails) {
			if (! is_array ( $emails )) {
				// http://tiku.io/questions/955963/splitting-comma-separated-email-addresses-in-a-string-with-commas-in-quotes-in-p
				$t = $this->stringGetCsvAlternate ( $emails );
				foreach ( $t as $k => $v ) {
					if (strpos ( $v, ',' ) !== false) {
						$t [$k] = '"' . str_replace ( ' <', '" <', $v );
					}
					$tokenizedEmail = trim ( $t [$k] );
					$this->logger->debug ( 'User Added recipient: ' . $tokenizedEmail );
					array_push ( $recipientList, $this->createPostmanEmailAddress ( $tokenizedEmail ) );
				}
			}
			return $emails;
		}
	}
}