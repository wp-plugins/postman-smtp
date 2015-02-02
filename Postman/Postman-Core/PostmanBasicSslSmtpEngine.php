<?php
if (! class_exists ( "PostmanBasicSslSmtpEngine" )) {
	
	require_once 'PostmanAbstractSmtpEngine.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanBasicSslSmtpEngine extends PostmanAbstractSmtpEngine implements PostmanSmtpEngine {
		private $username;
		private $password;
		
		/**
		 */
		function __construct($username, $password) {
			$this->setLogger ( new PostmanLogger ( get_class ( $this ) ) );
			$this->username = $username;
			$this->password = $password;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAbstractSmtpEngine::createConfig()
		 */
		protected function createConfig($hostname, $port) {
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
			// $config = array('ssl' => 'tls', 'port' => 587, 'auth' => 'login', 'username' => 'webmaster@mydomain.com', 'password' => 'password');
			$config = array (
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL,
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					'auth' => 'login',
					'username' => $this->username,
					'password' => $this->password 
			);
			return $config;
		}
	}
}
?>