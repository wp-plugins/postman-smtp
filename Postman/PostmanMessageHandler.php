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
			$this->logger->debug ( 'Construct' );
			$this->options = $options;
			$this->authToken = $authToken;
			
			// we'll let the 'init' functions run first; some of them may end the request
			// we'll look for messages at 'admin_init'
			add_action ( 'admin_init', array (
					$this,
					'init' 
			) );
		}
		function init() {
			$transport = PostmanTransportUtils::getCurrentTransport ();
			$this->scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $this->options->getHostname () );
			
			if (isset ( $_GET ['page'] ) && substr ( $_GET ['page'], 0, 7 ) === 'postman') {
				
				if (WP_DEBUG_LOG && WP_DEBUG_DISPLAY) {
					add_action ( 'admin_notices', Array (
							$this,
							'displayDebugDisplayIsEnabled' 
					) );
				}
				
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
						'displayErrorSessionMessage' 
				) );
			}
			
			if ($session->isSetWarningMessage ()) {
				$this->logger->debug ( 'Queueing warning messages for output' );
				add_action ( 'admin_notices', Array (
						$this,
						'displayWarningSessionMessage' 
				) );
			}
			
			if ($session->isSetSuccessMessage ()) {
				$this->logger->debug ( 'Queueing success messages for output' );
				add_action ( 'admin_notices', Array (
						$this,
						'displaySuccessSessionMessage' 
				) );
			}
		}
		function addError($message) {
			PostmanSession::getInstance ()->setErrorMessage ( $message );
		}
		function addWarning($message) {
			PostmanSession::getInstance ()->setWarningMessage ( $message );
		}
		function addMessage($message) {
			PostmanSession::getInstance ()->setSuccessMessage ( $message );
		}
		public function displayConfigurationRequiredWarning() {
			/* translators: where %s is the URL to the Postman Settings page */
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: Postman is <em>not</em> intercepting mail requests. <a href="%s">Configure</a> the plugin.', 'postman-smtp' ), POSTMAN_HOME_PAGE_ABSOLUTE_URL ) );
		}
		public function displayDebugDisplayIsEnabled() {
			/* translators: where %s is the URL to the WordPress documentation for WP_DEBUG */
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: Debug messages are being piped into the HTML output. This is a <span style="color:red"><b>serious security risk</b></span> and may hang Postman\'s remote AJAX calls. Disable <a href="%s">WP_DEBUG_DISPLAY</a>.', 'postman-smtp' ), 'http://codex.wordpress.org/WP_DEBUG#WP_DEBUG_LOG_and_WP_DEBUG_DISPLAY' ) );
		}
		//
		public function displaySuccessSessionMessage() {
			$message = PostmanSession::getInstance ()->getSuccessMessage ();
			PostmanSession::getInstance ()->unsetSuccessMessage ();
			$this->displaySuccessMessage ( $message, 'updated' );
		}
		public function displayErrorSessionMessage() {
			$message = PostmanSession::getInstance ()->getErrorMessage ();
			PostmanSession::getInstance ()->unsetErrorMessage ();
			$this->displayErrorMessage ( $message, 'error' );
		}
		public function displayWarningSessionMessage() {
			$message = PostmanSession::getInstance ()->getWarningMessage ();
			PostmanSession::getInstance ()->unsetWarningMessage ();
			$this->displayWarningMessage ( $message, 'update-nag' );
		}
		//
		public function displaySuccessMessage($message) {
			$this->displayMessage ( $message, 'updated' );
		}
		public function displayErrorMessage($message) {
			$this->displayMessage ( $message, 'error' );
		}
		public function displayWarningMessage($message) {
			$this->displayMessage ( $message, 'update-nag' );
		}
		private function displayMessage($message, $className) {
			printf ( '<div class="%s"><p>%s</p></div>', $className, $message );
		}
	}
}
