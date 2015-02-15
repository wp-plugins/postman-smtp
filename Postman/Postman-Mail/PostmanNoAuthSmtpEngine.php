<?php
if (! class_exists ( "PostmanNoAuthSmtpEngine" )) {
	
	require_once 'PostmanAbstractSmtpEngine.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanNoAuthSmtpEngine extends PostmanAbstractSmtpEngine implements PostmanSmtpEngine {
		
		/**
		 */
		function __construct() {
			$this->setLogger ( new PostmanLogger ( get_class ( $this ) ) );
		}
		
		/**
		 * (non-PHPdoc)
		 * 
		 * @see PostmanAbstractSmtpEngine::filterSender()
		 */
		function filterSender(PostmanEmailAddress $sender) {
			return $sender;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAbstractSmtpEngine::createConfig()
		 */
		public function createConfig(PostmanEmailAddress $sender, $hostname, $port) {
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
			$config = array (
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port 
			);
			$this->getLogger ()->debug ( sprintf ( 'Routing mail via %1$s:%2$s using no auth', $hostname, $port ) );
			return $config;
		}
	}
}
