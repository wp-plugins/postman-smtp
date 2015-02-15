<?php
if (! class_exists ( 'PostmanSession' )) {
	
	/**
	 * Persist session state to the database
	 *
	 * I heard plugins are forbidden from writing to the http session
	 * on some hosts, such as WPEngine, so this class write session
	 * state to the database instead.
	 *
	 * What's better about this is I don't have to prefix all my
	 * variables with , in fear of colliding with another
	 * plugin's similiarily named variables.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanSession {
		const POSTMAN_SESSION_SLUG_NAME = 'postman_session';
		const OAUTH_IN_PROGRESS = 'oauth_in_progress';
		const ACTION = 'action';
		const ERROR_MESSAGE = 'error_message';
		const WARNING_MESSAGE = 'warning_message';
		const SUCCESS_MESSAGE = 'success_message';
		
		// options data
		private $options;
		
		/**
		 * private constructor
		 */
		private function __construct() {
			$this->options = get_option ( self::POSTMAN_SESSION_SLUG_NAME );
		}
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanSession ();
			}
			return $inst;
		}
		
		/**
		 * Save the session state to the database
		 */
		private function save() {
			update_option ( self::POSTMAN_SESSION_SLUG_NAME, $this->options );
		}
		
		/**
		 * OAuth is in progress $state is the randomly generated
		 * transaction ID
		 *
		 * @param unknown $state        	
		 */
		public function isSetOauthInProgress() {
			return isset ( $this->options [self::OAUTH_IN_PROGRESS] );
		}
		public function setOauthInProgress($state) {
			$this->options [self::OAUTH_IN_PROGRESS] = $state;
			$this->save ();
		}
		public function getOauthInProgress() {
			if ($this->isSetOauthInProgress ())
				return $this->options [self::OAUTH_IN_PROGRESS];
		}
		public function unsetOauthInProgress() {
			unset ( $this->options [self::OAUTH_IN_PROGRESS] );
			$this->save ();
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $action        	
		 */
		public function isSetAction() {
			return isset ( $this->options [self::ACTION] );
		}
		public function setAction($action) {
			$this->options [self::ACTION] = $action;
			$this->save ();
		}
		public function getAction() {
			if ($this->isSetAction ())
				return $this->options [self::ACTION];
		}
		public function unsetAction() {
			unset ( $this->options [self::ACTION] );
			$this->save ();
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetErrorMessage() {
			return isset ( $this->options [self::ERROR_MESSAGE] );
		}
		public function setErrorMessage($message) {
			$this->options [self::ERROR_MESSAGE] = $message;
			$this->save ();
		}
		public function getErrorMessage() {
			if ($this->isSetErrorMessage ())
				return $this->options [self::ERROR_MESSAGE];
		}
		public function unsetErrorMessage() {
			unset ( $this->options [self::ERROR_MESSAGE] );
			$this->save ();
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetWarningMessage() {
			return isset ( $this->options [self::WARNING_MESSAGE] );
		}
		public function setWarningMessage($message) {
			$this->options [self::WARNING_MESSAGE] = $message;
			$this->save ();
		}
		public function getWarningMessage() {
			if ($this->isSetWarningMessage ())
				return $this->options [self::WARNING_MESSAGE];
		}
		public function unsetWarningMessage() {
			unset ( $this->options [self::WARNING_MESSAGE] );
			$this->save ();
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetSuccessMessage() {
			return isset ( $this->options [self::SUCCESS_MESSAGE] );
		}
		public function setSuccessMessage($message) {
			$this->options [self::SUCCESS_MESSAGE] = $message;
			$this->save ();
		}
		public function getSuccessMessage() {
			if ($this->isSetSuccessMessage ())
				return $this->options [self::SUCCESS_MESSAGE];
		}
		public function unsetSuccessMessage() {
			unset ( $this->options [self::SUCCESS_MESSAGE] );
			$this->save ();
		}
	}
}