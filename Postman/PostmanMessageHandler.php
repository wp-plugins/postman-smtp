<?php
if (! class_exists ( 'PostmanMessageHandler' )) {
	require_once ('PostmanOptions.php');
	require_once ('PostmanSession.php');
	require_once ('PostmanTransportDirectory.php');
	require_once ('Postman-Mail/Transport.php');
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
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authToken = $authToken;
			add_action ( 'init', array (
					$this,
					'init' 
			) );
		}
		function init() {
			$transport = PostmanTransportDirectory::getInstance ()->getCurrentTransport ();
			$this->scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $transport, $this->options->getAuthorizationType (), $this->options->getHostname () );
			
			// is the saved transport installed?
			$transportType = $this->options->getTransportType ();
			if (! empty ( $transportType ) && $transport->getSlug () != $this->options->getTransportType ()) {
				add_action ( 'admin_notices', Array (
						$this,
						'canNotFindTransport' 
				) );
			}
			
			if (isset ( $_GET ['page'] ) && substr ( $_GET ['page'], 0, 7 ) === 'postman') {
				
				if (WP_DEBUG_LOG && WP_DEBUG_DISPLAY) {
					add_action ( 'admin_notices', Array (
							$this,
							'displayDebugDisplayIsEnabled' 
					) );
				}
				
				if (PostmanTransportUtils::isPostmanConfiguredToSendEmail ( $this->options, $this->authToken )) {
					// no configuration errors to show
				} else {
					if ($this->options->isSmtpServerRequirementsNotMet () && $this->options->getTransportType () == PostmanSmtpTransport::SLUG) {
						// dont show this warning if this is a brand new install
						add_action ( 'admin_notices', Array (
								$this,
								'displaySmtpServerNeededWarning' 
						) );
					} else if ($this->options->isOAuthRequirementsNotMet ( $this->scribe->isOauthHost () )) {
						add_action ( 'admin_notices', Array (
								$this,
								'displayOauthCredentialsNeededWarning' 
						) );
					} else if ($this->options->isPermissionNeeded ( $this->authToken )) {
						add_action ( 'admin_notices', Array (
								$this,
								'displayPermissionNeededWarning' 
						) );
					} else if ($this->options->isPasswordCredentialsNeeded ()) {
						add_action ( 'admin_notices', Array (
								$this,
								'displayPasswordCredentialsNeededWarning' 
						) );
					} else if (! $this->scribe->isOauthHost () && ($this->scribe->isGoogle () || $this->scribe->isMicrosoft () || $this->scribe->isYahoo ())) {
						add_action ( 'admin_notices', Array (
								$this,
								'displaySwitchToOAuthWarning' 
						) );
					}
				}
			} else {
				if (! PostmanTransportUtils::isPostmanConfiguredToSendEmail ( $this->options, $this->authToken )) {
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
			$scribe = $this->scribe;
			$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
			$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanAdminController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $scribe->getRequestPermissionLinkText () );
			$this->displayWarningMessage ( $message );
		}
		public function canNotFindTransport() {
			$this->displayErrorMessage ( sprintf ( __ ( 'The external Postman transport "%s" is missing. Correct the error immediately or deactive Postman.' ), $this->options->getTransportType () ) );
		}
		public function displayPasswordCredentialsNeededWarning() {
			$this->displayWarningMessage ( __ ( 'Warning: Password authentication (Plain/Login/CRAMMD5) requires a username and password.', 'postman-smtp' ) );
		}
		public function displayOauthCredentialsNeededWarning() {
			$scribe = $this->scribe;
			/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label (e.g. Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, Client ID, and Client Secret.) */
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, %1$s, and %2$s.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () ) );
		}
		public function displaySmtpServerNeededWarning() {
			$scribe = $this->scribe;
			$this->displayWarningMessage ( __ ( 'Warning: Outgoing Mail Server (SMTP) and Port can not be empty.', 'postman-smtp' ) );
		}
		public function displayConfigurationRequiredWarning() {
			/* translators: where %s is the URL to the Postman Settings page */
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: Postman is <em>not</em> intercepting mail requests. <a href="%s">Configure</a> the plugin.', 'postman-smtp' ), POSTMAN_HOME_PAGE_ABSOLUTE_URL ) );
		}
		public function displaySwitchToOAuthWarning() {
			$scribe = $this->scribe;
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: You may experience issues using older authentication. Change your authentication type to OAuth 2.0.', 'postman-smtp' ) ) );
		}
		public function displayDebugDisplayIsEnabled() {
			/* translators: where %s is the URL to the WordPress documentation for WP_DEBUG */
			$this->displayWarningMessage ( sprintf ( __ ( 'Warning: Debug messages are being piped into the HTML output. This is a <span style="color:red"><b>serious security risk</b></span> and may hang Postman\'s remote AJAX calls. Disable <a href="%s">WP_DEBUG_DISPLAY</a>.', 'postman-smtp' ), 'http://codex.wordpress.org/WP_DEBUG#WP_DEBUG_LOG_and_WP_DEBUG_DISPLAY' ) );
		}
		public function displayCouldNotReplaceWpMail() {
			$this->displayWarningMessage ( __ ( 'Postman is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', 'postman-smtp' ) );
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
