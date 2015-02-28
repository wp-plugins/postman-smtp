<?php
if (! class_exists ( "PostmanState" )) {
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 *
	 * Make sure these emails are permitted (see http://en.wikipedia.org/wiki/E-mail_address#Internationalization):
	 */
	class PostmanState {
		
		// the option database name
		const SLUG = 'postman_state';
		
		// the options fields
		const VERSION = 'version';
		const INSTALL_DATE = 'install_date';
		
		// options data
		private $options;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanState ();
			}
			return $inst;
		}
		
		/**
		 * private constructor
		 */
		private function __construct() {
			$this->options = get_option ( self::SLUG );
		}
		//
		public function save() {
			update_option ( self::SLUG, $this->options );
		}
		public function isTimeToReviewPostman() {
			if (! empty ( $this->options [self::INSTALL_DATE] )) {
				return $this->options [self::INSTALL_DATE] + PostmanSmtp::LONG_ENOUGH_SEC < time ();
			}
		}
	}
}