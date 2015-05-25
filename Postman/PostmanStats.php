<?php
if (! class_exists ( "PostmanStats" )) {
	
	/**
	 * http://stackoverflow.com/questions/23880928/use-oauth-refresh-token-to-obtain-new-access-token-google-api
	 * http://pastebin.com/jA9sBNTk
	 */
	class PostmanStats {
		// the option database name
		const POSTMAN_STATS_DATA = 'postman_stats';
		
		// the options fields
		const DELIVERY_SUCCESS_TOTAL = 'delivery_success_total';
		const DELIVERY_FAILURE_TOTAL = 'delivery_fail_total';
		
		// options data
		private $options;
		private $logger;
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanStats ();
			}
			return $inst;
		}
		
		/**
		 * private constructor
		 */
		private function __construct() {
			$this->options = get_option ( PostmanStats::POSTMAN_STATS_DATA );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		//
		public function save() {
			update_option ( PostmanStats::POSTMAN_STATS_DATA, $this->options );
		}
		function getSuccessfulDeliveries() {
			if (isset ( $this->options [PostmanStats::DELIVERY_SUCCESS_TOTAL] ))
				return $this->options [PostmanStats::DELIVERY_SUCCESS_TOTAL];
			else
				return 0;
		}
		function setSuccessfulDelivery($total) {
			$this->options [PostmanStats::DELIVERY_SUCCESS_TOTAL] = $total;
		}
		function incrementSuccessfulDelivery() {
			$this->setSuccessfulDelivery ( $this->getSuccessfulDeliveries () + 1 );
			$this->logger->debug ( 'incrementing success count: ' . $this->getSuccessfulDeliveries () );
			$this->save ();
		}
		function getFailedDeliveries() {
			if (isset ( $this->options [PostmanStats::DELIVERY_FAILURE_TOTAL] ))
				return $this->options [PostmanStats::DELIVERY_FAILURE_TOTAL];
			else
				return 0;
		}
		function setFailedDelivery($total) {
			$this->options [PostmanStats::DELIVERY_FAILURE_TOTAL] = $total;
		}
		function incrementFailedDelivery() {
			$this->setFailedDelivery ( $this->getFailedDeliveries () + 1 );
			$this->logger->debug ( 'incrementing failure count: ' . $this->getFailedDeliveries () );
			$this->save ();
		}
	}
}