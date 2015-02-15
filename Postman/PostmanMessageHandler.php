<?php
if (! class_exists ( 'PostmanMessageHandler' )) {
	require_once ('PostmanOptions.php');
	require_once ('PostmanSession.php');
	class PostmanMessageHandler {
		
		// The Session variables that carry messages
		const ERROR_MESSAGE = 'POSTMAN_ERROR_MESSAGE';
		const WARNING_MESSAGE = 'POSTMAN_WARNING_MESSAGE';
		const SUCCESS_MESSAGE = 'POSTMAN_SUCCESS_MESSAGE';
		private $logger;
		private $options;
		
		/**
		 *
		 * @param unknown $options        	
		 */
		function __construct(PostmanOptions $options, PostmanAuthorizationToken $authToken) {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			
			if (isset ( $_GET ['page'] ) && substr ( $_GET ['page'], 0, 7 ) === 'postman') {
				
				if (WP_DEBUG_LOG && WP_DEBUG_DISPLAY) {
					add_action ( 'admin_notices', Array (
							$this,
							'displayDebugDisplayIsEnabled' 
					) );
				}
				if ($this->options->isPermissionNeeded ( $authToken )) {
					add_action ( 'admin_notices', Array (
							$this,
							'displayPermissionNeededWarning' 
					) );
				}
				if (! $this->options->isAuthTypeOAuth2 () && ($this->options->isSmtpHostGmail () || $this->options->isSmtpHostHotmail ())) {
					add_action ( 'admin_notices', Array (
							$this,
							'displaySwitchToOAuthWarning' 
					) );
				}
			} else {
				if (! $options->isSendingEmailAllowed ( $authToken )) {
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
		public function displayPermissionNeededWarning() {
			$scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $this->options->getHostname () );
			$url = sprintf ( __ ( '<a href="%s">%s</a>', 'postman' ), PostmanAdminController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), 'Request permission' );
			$message = sprintf ( 'Warning: You entered a %s and %s, but have not received permission to use it. %s from %s.', $scribe->getClientIdLabel (), $scribe->getClientSecretLabel (), $url, $scribe->getOwnerName () );
			$this->displayWarningMessage ( $message );
		}
		public function displayConfigurationRequiredWarning() {
			$message = 'Warning: Postman is <em>not</em> intercepting mail requests. <a href="' . POSTMAN_HOME_PAGE_ABSOLUTE_URL . '">Configure</a> the plugin.';
			$this->displayWarningMessage ( $message );
		}
		public function displaySwitchToOAuthWarning() {
			$scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $this->options->getHostname () );
			$message = sprintf ( 'Warning: You may experience issues using password authentication with %s. Change your authentication type to OAuth 2.0.</span></p>', $scribe->getServiceName () );
			$this->displayWarningMessage ( $message );
		}
		public function displayDebugDisplayIsEnabled() {
			$message = sprintf ( 'Warning: Debug messages are being piped into the HTML output. This is a <span style="color:red"><b>serious security risk</b></span> and may hang Postman\'s remote AJAX calls. Disable <a href="http://codex.wordpress.org/WP_DEBUG#WP_DEBUG_LOG_and_WP_DEBUG_DISPLAY">WP_DEBUG_DISPLAY</a>.</span></p>' );
			$this->displayWarningMessage ( $message );
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
			echo '<div class="' . $className . '"><p>' . $message . '</p></div>';
		}
	}
}
