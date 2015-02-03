<?php
if (! class_exists ( 'PostmanEmailAddress' )) {
	class PostmanEmailAddress {
		private $name;
		private $email;
		public function __construct($email, $name = null) {
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
	}
}