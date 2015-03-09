<?php
if (! class_exists ( 'PostmanMessageHandler' )) {
	require_once ('PostmanOptions.php');
	require_once ('PostmanSession.php');
	require_once ('Postman-Mail/PostmanTransportUtils.php');
	class PostmanMessageHandler {
		
		// The Session variables that carry messages
		const ERROR_MESSAGE = 'POSTMAN_ERROR_MESSAGE';
		const WARNING_MESSAGE = 'POSTMAN_WARNING_MESSAGE';
		const SUCCESS_MESSAGE = 'POSTMAN_SUCCESS_MESSAGE';
		const ERROR_CLASS = 'error';
		const WARNING_CLASS = 'update-nag';
		const SUCCESS_CLASS = 'updated';
		private $logger;
		private $options;
		private $authToken;
		private $scribe;
		
		/**
		 *
		 * @param unknown $options        	
		 */
		function __construct(PostmanOptions $options, PostmanOAuthToken $authToken) {
			assert ( isset ( $options ) );
			assert ( isset ( $authToken ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authToken = $authToken;
			
			// we'll let the 'init' functions run first; some of them may end the request
			// we'll look for messages at 'admin_init'
			add_action ( 'admin_init', array (
					$this,
					'init' 
			) );
		}
		public function init() {
			$transport = PostmanTransportUtils::getCurrentTransport ();
			$this->scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $this->options->getHostname () );
			
			if (isset ( $_GET ['page'] ) && substr ( $_GET ['page'], 0, 7 ) == 'postman') {
				
				if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authToken )) {
					// no configuration errors to show
				} else if (! $this->options->isNew ()) {
					// show the errors as long as this is not a virgin install
					$message = PostmanTransportUtils::getCurrentTransport ()->getMisconfigurationMessage ( $this->scribe, $this->options, $this->authToken );
					if ($message) {
						$this->logger->debug ( 'Transport has a configuration error: ' . $message );
						$this->addError ( $message );
					}
				}
			} else {
				if (! PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authToken )) {
					add_action ( 'admin_notices', Array (
							$this,
							'displayConfigurationRequiredWarning' 
					) );
				}
			}
			
			$session = PostmanSession::getInstance ();
			if ($session->isSetErrorMessage ()) {
				$this->logger->debug ( 'Queueing error messages for output' );
				add_action ( 'admin_notices', Array (
						$this,
						'displayAllMessages' 
				) );
			}
		}
		/**
		 *
		 * @param unknown $message        	
		 */
		public function addError($message) {
			$this->storeMessage ( $message, 'error' );
		}
		/**
		 *
		 * @param unknown $message        	
		 */
		public function addWarning($message) {
			$this->storeMessage ( $message, 'warning' );
		}
		/**
		 *
		 * @param unknown $message        	
		 */
		public function addMessage($message) {
			$this->storeMessage ( $message, 'notify' );
		}
		
		/**
		 * store messages for display later
		 *
		 * @param unknown $message        	
		 * @param unknown $type        	
		 */
		private function storeMessage($message, $type) {
			$messageArray = array ();
			$oldMessageArray = PostmanSession::getInstance ()->getErrorMessage ();
			if (isset ( $oldMessageArray )) {
				$messageArray = $oldMessageArray;
			}
			$weGotIt = false;
			foreach ( $messageArray as $storedMessage ) {
				if ($storedMessage ['message'] === $message) {
					$weGotIt = true;
				}
			}
			if (! $weGotIt) {
				$m = array (
						'type' => $type,
						'message' => $message 
				);
				array_push ( $messageArray, $m );
				PostmanSession::getInstance ()->setErrorMessage ( $messageArray );
			}
		}
		/**
		 * A callback function
		 */
		public function displayConfigurationRequiredWarning() {
			/* translators: where %s is the URL to the Postman Settings page */
			$message = sprintf ( __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ) );
			$message .= ' ';
			$message .= sprintf ( __ ( '<a href="%s">Configure</a> the plugin.', 'postman-smtp' ), POSTMAN_HOME_PAGE_ABSOLUTE_URL );
			$this->printMessage ( $message, self::WARNING_CLASS );
		}
		/**
		 * Retrieve the messages and show them
		 */
		public function displayAllMessages() {
			$messageArray = PostmanSession::getInstance ()->getErrorMessage ();
			PostmanSession::getInstance ()->unsetErrorMessage ();
			foreach ( $messageArray as $m ) {
				$type = $m ['type'];
				switch ($type) {
					case 'error' :
						$className = self::ERROR_CLASS;
						break;
					case 'warning' :
						$className = self::WARNING_CLASS;
						break;
					default :
						$className = self::SUCCESS_CLASS;
						break;
				}
				$message = $m ['message'];
				$this->printMessage ( $message, $className );
			}
		}
		
		/**
		 * putput message
		 *
		 * @param unknown $message        	
		 * @param unknown $className        	
		 */
		private function printMessage($message, $className) {
			printf ( '<div class="%s"><p>%s</p></div>', $className, $message );
		}
	}
}
