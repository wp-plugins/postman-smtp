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
		const TOO_LONG_SEC = 2592000; // 30 days
		                              
		// the options fields
		const VERSION = 'version';
		const INSTALL_DATE = 'install_date';
		const FILE_LOCKING_ENABLED = 'locking_enabled';
		
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
		/**
		 * Shows the review feature of Postman up to thirty days after install
		 *
		 * @return boolean
		 */
		public function isTimeToReviewPostman() {
			if (! empty ( $this->options [self::INSTALL_DATE] )) {
				$successful = PostmanStats::getInstance ()->getSuccessfulDeliveries () > 0;
				$maxTime = $this->options [self::INSTALL_DATE] + self::TOO_LONG_SEC;
				return $successful && time () <= $maxTime;
			}
		}
		public function isFileLockingEnabled() {
			if (isset ( $this->options [self::FILE_LOCKING_ENABLED] ))
				return $this->options [self::FILE_LOCKING_ENABLED];
			else
				return false;
		}
		public function setFileLockingEnabled($enabled) {
			$this->options [self::FILE_LOCKING_ENABLED] = $enabled;
			$this->save ();
		}
		public function getVersion() {
			if (! empty ( $this->options [self::VERSION] )) {
				return $this->options [self::VERSION];
			}
		}
	}
}