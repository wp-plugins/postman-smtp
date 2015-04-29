<?php

// setup the main entry point
if (! class_exists ( 'Postman' )) {
	
	require_once 'postman-common-wp-functions.php';
	require_once 'Postman-Common.php';
	require_once 'Postman-Mail/PostmanSmtpTransport.php';
	require_once 'Postman-Mail/PostmanGmailApiTransport.php';
	require_once 'PostmanOAuthToken.php';
	require_once 'PostmanConfigTextHelper.php';
	require_once 'PostmanOptions.php';
	require_once 'PostmanUtils.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'PostmanAdminController.php';
	require_once 'Postman-Controller/PostmanDashboardWidgetController.php';
	require_once 'PostmanActivationHandler.php';
	require_once 'Postman-Email-Log/PostmanEmailLogView.php';
	require_once 'Postman-Controller/PostmanAdminPointer.php';
	
	/**
	 *
	 * @author jasonhendriks
	 *        
	 */
	class Postman {
		const POSTMAN_TCP_READ_TIMEOUT = 60;
		const POSTMAN_TCP_CONNECTION_TIMEOUT = 10;
		const LONG_ENOUGH_SEC = 432000;
		private $logger;
		private $messageHandler;
		private $options;
		private $authToken;
		private $wpMailBinder;
		private $pluginData;
		
		/**
		 *
		 * @param unknown $rootPluginFilenameAndPath        	
		 */
		public function __construct($rootPluginFilenameAndPath) {
			
			// get plugin metadata
			$this->pluginData = get_plugin_data ( $rootPluginFilenameAndPath );
			
			// create an instance of the logger
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->logger->debug ( sprintf ( '%1$s v%2$s starting', $this->pluginData ['Name'], $this->pluginData ['Version'] ) );
			
			// load the text domain
			$this->loadTextDomain ( $rootPluginFilenameAndPath );
			
			// store instances of the Options and OAuthToken
			$this->options = PostmanOptions::getInstance ();
			$this->authToken = PostmanOAuthToken::getInstance ();
			
			// create an instance of the MessageHandler
			$this->messageHandler = new PostmanMessageHandler ();
			
			// create an instance of the EmailLog
			$emailLog = PostmanEmailLogService::getInstance ();
			
			// store an instance of the WpMailBinder
			$this->wpMailBinder = PostmanWpMailBinder::getInstance ();
			
			// register the SMTP transport
			$this->registerTransport ();
			
			// bind to wp_mail - this has to happen before the "init" action
			$this->wpMailBinder->bind ();
			
			if (is_admin ()) {
				// the following classes should only be used if the current user is an admin
				new PostmanDashboardWidgetController ( $rootPluginFilenameAndPath, $this->options, $this->authToken, $this->wpMailBinder );
				$adminController = new PostmanAdminController ( $rootPluginFilenameAndPath, $this->options, $this->authToken, $this->messageHandler, $this->wpMailBinder );
				new PostmanEmailLogView ( $rootPluginFilenameAndPath );
				new PostmanAdminPointer ( $rootPluginFilenameAndPath );
				// do this only if we're on a postman admin screen
				if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
					add_action ( 'in_admin_footer', array (
							&$this,
							'print_signature' 
					) );
				}
			}
			
			// register activation handler on the activation event
			new PostmanActivationHandler ( $rootPluginFilenameAndPath );
			
			// register the shortcode handler on the add_shortcode event
			add_shortcode ( 'postman-version', array (
					$this,
					'version_shortcode' 
			) );
			
			// we'll let the 'init' functions run first; some of them may end the request
			// we'll look for messages at 'admin_init'
			add_action ( 'init', array (
					$this,
					'check_for_configuration_errors' 
			) );
		}
		
		/**
		 */
		public function check_for_configuration_errors() {
			// are we bound?
			if ($this->wpMailBinder->isUnboundDueToException ()) {
				$this->messageHandler->addError ( __ ( 'Postman is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', 'postman-smtp' ) );
			}
			
			$transport = PostmanTransportUtils::getCurrentTransport ();
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $this->options->getHostname () );
			
			// on any Postman page, print the config error messages
			if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
				
				if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authToken )) {
					// no configuration errors to show
				} else if (! $this->options->isNew ()) {
					// show the errors as long as this is not a virgin install
					$message = PostmanTransportUtils::getCurrentTransport ()->getMisconfigurationMessage ( $scribe, $this->options, $this->authToken );
					if ($message) {
						$this->logger->trace ( 'Transport has a configuration error: ' . $message );
						$this->messageHandler->addError ( $message );
					}
				}
			} else {
				if (! PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authToken )) {
					add_action ( 'admin_notices', Array (
							$this,
							'display_configuration_required_warning' 
					) );
				}
			}
		}
		
		/**
		 * A callback function
		 */
		public function display_configuration_required_warning() {
			$this->logger->debug ( 'Displaying configuration required warning' );
			$message = sprintf ( __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ) );
			$message .= ' ';
			/* translators: where %s is the URL to the Postman Setup page */
			$message .= sprintf ( __ ( '<a href="%s">Configure</a> the plugin.', 'postman-smtp' ), POSTMAN_HOME_PAGE_ABSOLUTE_URL );
			$this->messageHandler->printMessage ( $message, PostmanMessageHandler::ERROR_CLASS );
		}
		
		/**
		 * Adds the regular SMTP transport
		 */
		private function registerTransport() {
			PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanSmtpTransport () );
			PostmanTransportDirectory::getInstance ()->registerTransport ( new PostmanGmailApiTransport () );
		}
		
		/**
		 * http://striderweb.com/nerdaphernalia/2008/06/give-your-wordpress-plugin-credit/
		 */
		function print_signature() {
			$pluginData = $this->pluginData;
			printf ( '%s %s<br/>', $pluginData ['Title'], $pluginData ['Version'] );
		}
		
		/**
		 * Loads the appropriate language file
		 */
		private function loadTextDomain($rootPluginFilenameAndPath) {
			$textDomain = 'postman-smtp';
			$langDir = basename ( dirname ( $rootPluginFilenameAndPath ) ) . '/Postman/languages/';
			$success = load_plugin_textdomain ( $textDomain, false, $langDir );
		}
		
		/**
		 * Shortcode to return the current plugin version.
		 * From http://code.garyjones.co.uk/get-wordpress-plugin-version/
		 *
		 * @return string Plugin version
		 */
		function version_shortcode() {
			return POSTMAN_PLUGIN_VERSION;
		}
	}
}
