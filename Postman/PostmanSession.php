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
		// length of time to keep items around
		const MINUTES_IN_SECONDS = 60;
		
		//
		const OAUTH_IN_PROGRESS = 'oauth_in_progress';
		const ACTION = 'action';
		const ERROR_MESSAGE = 'error_message';
		const WARNING_MESSAGE = 'warning_message';
		const SUCCESS_MESSAGE = 'success_message';
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanSession ();
			}
			return $inst;
		}
		
		/**
		 * OAuth is in progress $state is the randomly generated
		 * transaction ID
		 *
		 * @param unknown $state        	
		 */
		public function isSetOauthInProgress() {
			return get_transient ( self::OAUTH_IN_PROGRESS ) != false;
		}
		public function setOauthInProgress($state) {
			set_transient ( self::OAUTH_IN_PROGRESS, $state, 30 * self::MINUTES_IN_SECONDS );
		}
		public function getOauthInProgress() {
			return get_transient ( self::OAUTH_IN_PROGRESS );
		}
		public function unsetOauthInProgress() {
			delete_transient ( self::OAUTH_IN_PROGRESS );
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $action        	
		 */
		public function isSetAction() {
			return get_transient ( self::ACTION ) != false;
		}
		public function setAction($action) {
			set_transient ( self::ACTION, $action, 30 * self::MINUTES_IN_SECONDS );
		}
		public function getAction() {
			return get_transient ( self::ACTION );
		}
		public function unsetAction() {
			delete_transient ( self::ACTION );
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetErrorMessage() {
			return get_transient ( self::ERROR_MESSAGE ) != false;
		}
		public function setErrorMessage($message) {
			set_transient ( self::ERROR_MESSAGE, $message, 30 * self::MINUTES_IN_SECONDS );
		}
		public function getErrorMessage() {
			return get_transient ( self::ERROR_MESSAGE );
		}
		public function unsetErrorMessage() {
			delete_transient ( self::ERROR_MESSAGE );
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetWarningMessage() {
			return get_transient ( self::WARNING_MESSAGE ) != false;
		}
		public function setWarningMessage($message) {
			set_transient ( self::WARNING_MESSAGE, $message, 30 * self::MINUTES_IN_SECONDS );
		}
		public function getWarningMessage() {
			return get_transient ( self::WARNING_MESSAGE );
		}
		public function unsetWarningMessage() {
			delete_transient ( self::WARNING_MESSAGE );
		}
		
		/**
		 * Sometimes I need to keep track of what I'm doing between requests
		 *
		 * @param unknown $message        	
		 */
		public function isSetSuccessMessage() {
			return get_transient ( self::SUCCESS_MESSAGE ) != false;
		}
		public function setSuccessMessage($message) {
			set_transient ( self::SUCCESS_MESSAGE, $message, 30 * self::MINUTES_IN_SECONDS );
		}
		public function getSuccessMessage() {
			return get_transient ( self::SUCCESS_MESSAGE );
		}
		public function unsetSuccessMessage() {
			delete_transient ( self::SUCCESS_MESSAGE );
		}
	}
}