<?php
if (! class_exists ( "PostmanPasswordAuthSmtpEngine" )) {
	
	require_once 'PostmanAbstractSmtpEngine.php';
	
	/**
	 * This class knows how to interface with Wordpress
	 * including loading/saving to the database.
	 *
	 * @author jasonhendriks
	 *        
	 */
	class PostmanPasswordAuthSmtpEngine extends PostmanAbstractSmtpEngine implements PostmanSmtpEngine {
		private $username;
		private $password;
		private $authenticationType;
		private $encryptionType;
		
		/**
		 */
		function __construct($username, $password, $authType, $encType) {
			$this->setLogger ( new PostmanLogger ( get_class ( $this ) ) );
			assert ( ! empty ( $username ) );
			assert ( ! empty ( $password ) );
			assert ( ! empty ( $authType ) );
			assert ( ! empty ( $encType ) );
			$this->username = $username;
			$this->password = $password;
			$this->authenticationType = $authType;
			$this->encryptionType = $encType;
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAbstractSmtpEngine::overrideSender()
		 */
		function overrideSender(PostmanEmailAddress $sender) {
			return $sender->setEmail ( $this->getSender ()->getEmail () );
		}
		
		/**
		 * (non-PHPdoc)
		 *
		 * @see PostmanAbstractSmtpEngine::createConfig()
		 */
		public function createConfig(PostmanEmailAddress $sender, $hostname, $port) {
			assert ( ! empty ( $port ) );
			assert ( ! empty ( $hostname ) );
			// $config = array('ssl' => 'tls', 'port' => 587, 'auth' => 'login', 'username' => 'webmaster@mydomain.com', 'password' => 'password');
			$config = array (
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_SSL => $this->encryptionType,
					PostmanSmtpEngine::ZEND_TRANSPORT_CONFIG_PORT => $port,
					'auth' => $this->authenticationType,
					'username' => $this->username,
					'password' => $this->password 
			);
			$mangledPassword = str_repeat ( '*', strlen ( $this->password ) );
			$this->getLogger ()->debug ( sprintf ( 'Routing mail via %1$s:%2$s using auth:%3$s over ssl:%4$s for user %5$s?%6$s', $hostname, $port, $this->authenticationType, $this->encryptionType, $this->username, $mangledPassword ) );
			return $config;
		}
	}
}
?>
