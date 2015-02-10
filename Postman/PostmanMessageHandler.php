<?php
require_once ('PostmanOptions.php');
require_once ('AdminController.php');
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
		
		if (isset ( $_GET ['page'] ) && $_GET ['page'] == 'postman') {
			
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
		
		if (isset ( $_SESSION [PostmanMessageHandler::ERROR_MESSAGE] )) {
			add_action ( 'admin_notices', Array (
					$this,
					'displayErrorSessionMessage' 
			) );
		}
		
		if (isset ( $_SESSION [PostmanMessageHandler::WARNING_MESSAGE] )) {
			add_action ( 'admin_notices', Array (
					$this,
					'displayWarningSessionMessage' 
			) );
		}
		
		if (isset ( $_SESSION [PostmanMessageHandler::SUCCESS_MESSAGE] )) {
			add_action ( 'admin_notices', Array (
					$this,
					'displaySuccessSessionMessage' 
			) );
		}
	}
	function addError($message) {
		$_SESSION [PostmanMessageHandler::ERROR_MESSAGE] = $message;
	}
	function addWarning($message) {
		$_SESSION [PostmanMessageHandler::WARNING_MESSAGE] = $message;
	}
	function addMessage($message) {
		$_SESSION [PostmanMessageHandler::SUCCESS_MESSAGE] = $message;
	}
	public function displayPermissionNeededWarning() {
		$url = sprintf ( __ ( '<a href="%s&postman_action=%s">%s</a>', 'postman' ), POSTMAN_HOME_PAGE_ABSOLUTE_URL, PostmanAdminController::POSTMAN_REQUEST_OAUTH_PERMISSION_ACTION, 'Request permission' );
		$message = 'Warning: You entered a Client ID and Client Secret, but have not received permission to use it. ' . $url . ' from ' . PostmanSmtpHostProperties::getServiceName ( $this->options->getHostname () ) . '.';
		$this->displayWarningMessage ( $message );
	}
	public function displayConfigurationRequiredWarning() {
		$message = 'Warning: ' . PostmanAdminController::NAME . ' is <em>not</em> intercepting mail requests. <a href="' . POSTMAN_HOME_PAGE_ABSOLUTE_URL . '">Configure</a> the plugin.';
		$this->displayWarningMessage ( $message );
	}
	public function displaySwitchToOAuthWarning() {
		$message = sprintf ( 'Warning: %s may silently discard messages sent with password authentication. Change your authentication type to OAuth 2.0.</span></p>', PostmanSmtpHostProperties::getServiceName ( $this->options->getHostname () ) );
		$this->displayWarningMessage ( $message );
	}
	//
	public function displaySuccessSessionMessage() {
		$this->displaySuccessMessage ( $this->retrieveSessionMessage ( PostmanMessageHandler::SUCCESS_MESSAGE ), 'updated' );
	}
	public function displayErrorSessionMessage() {
		$this->displayErrorMessage ( $this->retrieveSessionMessage ( PostmanMessageHandler::ERROR_MESSAGE ), 'error' );
	}
	public function displayWarningSessionMessage() {
		$this->displayWarningMessage ( $this->retrieveSessionMessage ( PostmanMessageHandler::WARNING_MESSAGE ), 'update-nag' );
	}
	private function retrieveSessionMessage($sessionVar) {
		$message = $_SESSION [$sessionVar];
		unset ( $_SESSION [$sessionVar] );
		return $message;
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
