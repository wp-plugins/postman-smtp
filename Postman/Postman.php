<?php

// setup the main entry point
if (! class_exists ( 'Postman' )) {
	
	require_once 'PostmanOptions.php';
	require_once 'PostmanLogger.php';
	require_once 'PostmanUtils.php';
	require_once 'postman-common-functions.php';
	require_once 'Postman-Common.php';
	require_once 'Postman-Mail/PostmanTransportRegistry.php';
	require_once 'Postman-Mail/PostmanSmtpTransport.php';
	require_once 'Postman-Mail/PostmanGoogleMailApiTransport.php';
	require_once 'PostmanOAuthToken.php';
	require_once 'PostmanConfigTextHelper.php';
	require_once 'PostmanMessageHandler.php';
	require_once 'PostmanWpMailBinder.php';
	require_once 'PostmanAdminController.php';
	require_once 'Postman-Controller/PostmanDashboardWidgetController.php';
	require_once 'PostmanActivationHandler.php';
	require_once 'Postman-Email-Log/PostmanEmailLogController.php';
	require_once 'Postman-Controller/PostmanAdminPointer.php';
	
	/**
	 * This is the "main" class for Postman.
	 * All execution begins here.
	 *
	 * @author jasonhendriks
	 * @copyright Jan 16, 2015
	 */
	class Postman {
		const LONG_ENOUGH_SEC = 432000;
		private $logger;
		private $messageHandler;
		private $options;
		private $authToken;
		private $wpMailBinder;
		private $pluginData;
		
		/**
		 * The constructor
		 *
		 * @param unknown $rootPluginFilenameAndPath
		 *        	- the __FILE__ of the caller
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
			
			// create and store an instance of the MessageHandler
			$this->messageHandler = new PostmanMessageHandler ();
			
			// store an instance of the WpMailBinder
			$this->wpMailBinder = PostmanWpMailBinder::getInstance ();
			
			// register the SMTP transport
			$this->registerTransport ( $this->pluginData );
			
			// bind to wp_mail - this has to happen before the "init" action
			$this->wpMailBinder->bind ();
			
			// the following code should be restricted to the admin user
			if (is_admin ()) {
				new PostmanDashboardWidgetController ( $rootPluginFilenameAndPath, $this->options, $this->authToken, $this->wpMailBinder );
				$adminController = new PostmanAdminController ( $rootPluginFilenameAndPath, $this->pluginData, $this->options, $this->authToken, $this->messageHandler, $this->wpMailBinder );
				new PostmanEmailLogController ( $rootPluginFilenameAndPath, $this->pluginData );
				new PostmanAdminPointer ( $rootPluginFilenameAndPath );
				
				// register the Postman signature (only if we're on a postman admin screen) on the in_admin_footer event
				if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
					add_action ( 'in_admin_footer', array (
							&$this,
							'print_signature' 
					) );
				}
			}
			
			// register activation handler on the activation event
			new PostmanActivationHandler ( $rootPluginFilenameAndPath, $this->pluginData ['Version'] );
			
			// register the shortcode handler on the add_shortcode event
			add_shortcode ( 'postman-version', array (
					$this,
					'version_shortcode' 
			) );
			
			// register the check for configuration errors on the init hook
			add_action ( 'init', array (
					$this,
					'check_for_configuration_errors' 
			) );
		}
		
		/**
		 * Check for configuration errors and displays messages to the user
		 */
		public function check_for_configuration_errors() {
			// did Postman fail binding to wp_mail()?
			if ($this->wpMailBinder->isUnboundDueToException ()) {
				// this message gets printed on ANY WordPress admin page, as it's a pretty fatal error that
				// may occur just by activating a new plugin
				
				// I noticed the wpMandrill and SendGrid plugins have the exact same error message here
				// I've decided to adopt their error message as well, for shits and giggles .... :D
				$this->messageHandler->addError ( __ ( 'Postman: wp_mail has been declared by another plugin or theme, so you won\'t be able to use Postman until the conflict is resolved.', 'postman-smtp' ) );
				// $this->messageHandler->addError ( __ ( 'Error: Postman is properly configured, but the current theme or another plugin is preventing service.', 'postman-smtp' ) );
			}
			
			$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $this->options->getHostname (), $transport );
			$readyToSend = PostmanTransportRegistry::getInstance ()->isPostmanReadyToSendEmail ( $this->options, $this->authToken );
			
			// on pages that are Postman admin pages only, show this error message
			if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
				
				$virgin = $this->options->isNew ();
				if (! $readyToSend && ! $virgin) {
					// if the configuration is broken, and the user has started to configure the plugin
					// show this error message
					$message = PostmanTransportRegistry::getInstance ()->getCurrentTransport ()->getMisconfigurationMessage ( $scribe, $this->options, $this->authToken );
					if ($message) {
						// output the error message
						$this->logger->trace ( 'Transport has a configuration error: ' . $message );
						$this->messageHandler->addError ( $message );
					}
				}
			} else if (! $readyToSend) {
				// on pages that are *NOT* Postman admin pages only....
				// if the configuration is broken
				// show this error message
				add_action ( 'admin_notices', Array (
						$this,
						'display_configuration_required_warning' 
				) );
			}
		}
		
		/**
		 * This is the general message that Postman requires configuration, to warn users who think
		 * the plugin is ready-to-go as soon as it is activated.
		 * This message only goes away once
		 * the plugin is configured.
		 */
		public function display_configuration_required_warning() {
			$this->logger->debug ( 'Displaying configuration required warning' );
			$message = sprintf ( __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ) );
			$message .= ' ';
			/* translators: where %s is the URL to the Postman Setup page */
			$message .= sprintf ( __ ( '<a href="%s">Configure</a> the plugin.', 'postman-smtp' ), PostmanUtils::getSettingsPageUrl () );
			$this->messageHandler->printMessage ( $message, PostmanMessageHandler::WARNING_CLASS );
		}
		
		/**
		 * Register the built-in transports
		 */
		private function registerTransport($pluginData) {
			assert ( isset ( $pluginData ) );
			PostmanTransportRegistry::getInstance ()->registerTransport ( new PostmanSmtpTransport ( $pluginData ) );
			PostmanTransportRegistry::getInstance ()->registerTransport ( new PostmanGoogleMailApiTransport ( $pluginData ) );
		}
		
		/**
		 * Print the Postman signature on the bottom of the page
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
			return $this->pluginData ['Version'];
		}
	}
}
