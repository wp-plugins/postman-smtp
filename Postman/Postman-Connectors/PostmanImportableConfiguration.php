<?php
if (! class_exists ( 'PostmanImportableConfiguration' )) {
	
	require_once 'PostmanEasyWpSmtpOptions.php';
	require_once 'PostmanWpSmtpOptions.php';
	require_once 'PostmanWpMailBankOptions.php';
	require_once 'PostmanWpMailSmtpOptions.php';
	
	/**
	 * This class instantiates the Connectors for new users to Postman.
	 * It determines which Connectors can supply configuration data
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanImportableConfiguration {
		private $lazyInit;
		private $availableOptions;
		private $importAvailable;
		function init() {
			if (! $this->lazyInit) {
				$this->queueIfAvailable ( new PostmanEasyWpSmtpOptions () );
				$this->queueIfAvailable ( new PostmanWpSmtpOptions () );
				$this->queueIfAvailable ( new PostmanWpMailBankOptions () );
				$this->queueIfAvailable ( new PostmanWpMailSmtpOptions () );
			}
			$this->lazyInit = true;
		}
		private function queueIfAvailable(PostmanPluginOptions $options) {
			if ($options->isImportable ()) {
				$this->availableOptions [$options->getPluginSlug ()] = $options;
				$this->importAvailable = true;
			}
		}
		public function getAvailableOptions() {
			$this->init ();
			return $this->availableOptions;
		}
		public function isImportAvailable() {
			$this->init ();
			return $this->importAvailable;
		}
	}
}
