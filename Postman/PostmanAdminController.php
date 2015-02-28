<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "PostmanSendTestEmail.php";
	require_once "Postman-Mail/PostmanSmtpEngine.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanState.php';
	require_once 'PostmanStats.php';
	require_once 'PostmanOAuthToken.php';
	require_once 'Postman-Wizard/PortTest.php';
	require_once 'Postman-Wizard/SmtpDiscovery.php';
	require_once 'PostmanInputSanitizer.php';
	require_once 'Postman-Connectors/PostmanImportableConfiguration.php';
	require_once 'PostmanConfigTextHelper.php';
	require_once 'PostmanManageConfigurationAjaxHandler.php';
	
	//
	class PostmanAdminController {
		public static function getActionUrl($slug) {
			return get_admin_url () . 'admin-post.php?action=' . $slug;
		}
		public static function getPageUrl($slug) {
			return get_admin_url () . 'options-general.php?page=' . $slug;
		}
		
		// this is the slug used in the URL
		const POSTMAN_MENU_SLUG = 'postman';
		const REQUEST_OAUTH2_GRANT_SLUG = 'postman/requestOauthGrant';
		const CONFIGURATION_SLUG = 'postman/configuration';
		const CONFIGURATION_WIZARD_SLUG = 'postman/configuration_wizard';
		const EMAIL_TEST_SLUG = 'postman/email_test';
		const PORT_TEST_SLUG = 'postman/port_test';
		const PURGE_DATA_SLUG = 'postman/purge_data';
		const DIAGNOSTICS_SLUG = 'postman/diagnostics';
		
		// The Postman Group is used for saving data, make sure it is globally unique
		const SETTINGS_GROUP_NAME = 'postman_group';
		
		// a database entry specifically for the form that sends test e-mail
		const TEST_OPTIONS = 'postman_test_options';
		const SMTP_OPTIONS = 'postman_smtp_options';
		const SMTP_SECTION = 'postman_smtp_section';
		const BASIC_AUTH_OPTIONS = 'postman_basic_auth_options';
		const BASIC_AUTH_SECTION = 'postman_basic_auth_section';
		const OAUTH_OPTIONS = 'postman_oauth_options';
		const OAUTH_SECTION = 'postman_oauth_section';
		const ADVANCED_OPTIONS = 'postman_advanced_options';
		const ADVANCED_SECTION = 'postman_advanced_section';
		
		// slugs
		const POSTMAN_TEST_SLUG = 'postman-test';
		
		// style sheets and scripts
		const POSTMAN_STYLE = 'postman_style';
		const JQUERY_SCRIPT = 'jquery';
		const POSTMAN_SCRIPT = 'postman_script';
		
		//
		private $logger;
		
		// the Authorization Token
		private $authorizationToken;
		
		// the message handler
		private $messageHandler;
		
		//
		private $oauthScribe;
		
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $basename;
		private $options;
		private $testOptions;
		private $importableConfiguration;
		
		/**
		 * Start up
		 */
		public function __construct($basename, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->messageHandler = $messageHandler;
			$this->basename = $basename;
			
			// check if the user saved data, and if validation was successful
			$session = PostmanSession::getInstance ();
			if ($session->isSetAction ()) {
				$this->logger->debug ( sprintf ( 'session action: %s', $session->getAction () ) );
			}
			if ($session->getAction () == PostmanInputSanitizer::VALIDATION_SUCCESS) {
				$session->unsetAction ();
				$this->registerInitFunction ( 'handleSuccessfulSave' );
				$this->messageHandler->addMessage ( __ ( 'Settings saved.', 'postman-smtp' ) );
				return;
			}
			
			// test to see if an OAuth authentication is in progress
			if ($session->isSetOauthInProgress ()) {
				if (isset ( $_GET ['code'] )) {
					$this->logger->debug ( 'Found authorization grant code' );
					// queue the function that processes the incoming grant code
					$this->registerInitFunction ( 'handleAuthorizationGrant' );
					return;
				} else {
					// the user must have clicked cancel... abort the grant token check
					$this->logger->debug ( 'Found NO authorization grant code -- user must probably cancelled' );
					$session->unsetOauthInProgress ();
				}
			}
			
			// continue to initialize the AdminController
			add_action ( 'init', array (
					$this,
					'init' 
			) );
		}
		public function init() {
			//
			$transport = PostmanTransportUtils::getCurrentTransport ();
			$this->oauthScribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $this->options->getHostname () );
			
			// import from other plugins
			$this->importableConfiguration = new PostmanImportableConfiguration ();
			
			// Adds "Settings" link to the plugin action page
			add_filter ( 'plugin_action_links_' . $this->basename, array (
					$this,
					'postmanModifyLinksOnPluginsListPage' 
			) );
			
			// initialize the scripts, stylesheets and form fields
			add_action ( 'admin_init', array (
					$this,
					'initializeAdminPage' 
			) );
			
			// register Ajax handlers
			if (is_admin ()) {
				$c = new PostmanManageConfigurationAjaxHandler();
				$this->registerAjaxHandler ( 'test_port', $this, 'getAjaxPortStatus' );
				$this->registerAjaxHandler ( 'check_email', $this, 'getAjaxHostnameByEmail' );
				$this->registerAjaxHandler ( 'manual_config', $c, 'getManualConfigurationViaAjax' );
				$this->registerAjaxHandler ( 'get_wizard_configuration_options', $c, 'getWizardConfigurationViaAjax' );
				$this->registerAjaxHandler ( 'send_test_email', $this, 'sendTestEmailViaAjax' );
				$this->registerAjaxHandler ( 'import_configuration', $this, 'getConfigurationFromExternalPluginViaAjax' );
				$this->registerAjaxHandler ( 'get_hosts_to_test', $this, 'getPortsToTestViaAjax' );
			}
			
			// register content handlers
			$this->registerAdminMenu ( 'generateDefaultContent' );
			$this->registerAdminMenu ( 'addSetupWizardSubmenu' );
			$this->registerAdminMenu ( 'addConfigurationSubmenu' );
			$this->registerAdminMenu ( 'addEmailTestSubmenu' );
			$this->registerAdminMenu ( 'addPortTestSubmenu' );
			$this->registerAdminMenu ( 'addPurgeDataSubmenu' );
			$this->registerAdminMenu ( 'addDiagnosticsSubmenu' );
			
			// register action handlers
			$this->registerAdminPostAction ( self::PURGE_DATA_SLUG, 'handlePurgeDataAction' );
			$this->registerAdminPostAction ( self::REQUEST_OAUTH2_GRANT_SLUG, 'handleOAuthPermissionRequestAction' );
		}
		
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerAjaxHandler($actionName, $class, $callbackName) {
			$fullname = 'wp_ajax_' . $actionName;
			// $this->logger->debug ( 'Registering ' . 'wp_ajax_' . $fullname . ' Ajax handler' );
			add_action ( $fullname, array (
					$class,
					$callbackName 
			) );
		}
		
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerAdminMenu($callbackName) {
			// $this->logger->debug ( 'Registering admin menu ' . $callbackName );
			add_action ( 'admin_menu', array (
					$this,
					$callbackName 
			) );
		}
		
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerInitFunction($callbackName) {
			$this->logger->debug ( 'Registering init function ' . $callbackName );
			add_action ( 'init', array (
					$this,
					$callbackName 
			) );
		}
		
		/**
		 * Registers actions posted by am HTML FORM with the WordPress 'action' parameter
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbankName        	
		 */
		private function registerAdminPostAction($actionName, $callbankName) {
			// $this->logger->debug ( 'Registering ' . $actionName . ' Action Post handler' );
			add_action ( 'admin_post_' . $actionName, array (
					$this,
					$callbankName 
			) );
		}
		
		/**
		 * Add "Settings" link to the plugin action page
		 *
		 * @param unknown $links        	
		 * @return multitype:
		 */
		public function postmanModifyLinksOnPluginsListPage($links) {
			$mylinks = array (
					sprintf ( '<a href="%s">%s</a>', esc_url ( POSTMAN_HOME_PAGE_ABSOLUTE_URL ), _x ( 'Settings', 'Plugin Action Links', 'postman-smtp' ) ) 
			);
			return array_merge ( $mylinks, $links );
		}
		
		/**
		 * Add options page
		 */
		public function generateDefaultContent() {
			// This page will be under "Settings"
			$page = add_options_page ( _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::POSTMAN_MENU_SLUG, array (
					$this,
					'outputDefaultContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueHomeScreenStylesheet' 
			) );
			return $page;
		}
		function enqueueHomeScreenStylesheet() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_script' );
		}
		
		/**
		 * Register the Configuration screen
		 */
		public function addConfigurationSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::CONFIGURATION_SLUG, array (
					$this,
					'outputManualConfigurationContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueConfigurationResources' 
			) );
		}
		function enqueueConfigurationResources() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_manual_config_script' );
		}
		
		/**
		 * Register the Setup Wizard screen
		 */
		public function addSetupWizardSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::CONFIGURATION_WIZARD_SLUG, array (
					$this,
					'outputWizardContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueSetupWizardResources' 
			) );
		}
		function enqueueSetupWizardResources() {
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_wizard_script' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addEmailTestSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::EMAIL_TEST_SLUG, array (
					$this,
					'outputTestEmailWizardContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueEmailTestResources' 
			) );
		}
		function enqueueEmailTestResources() {
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_test_email_wizard_script' );
		}
		
		/**
		 * Register the Diagnostics screen
		 */
		public function addDiagnosticsSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::DIAGNOSTICS_SLUG, array (
					$this,
					'outputDiagnosticsContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueHomeScreenStylesheet' 
			) );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPortTestSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::PORT_TEST_SLUG, array (
					$this,
					'outputPortTestContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueuePortTestResources' 
			) );
		}
		function enqueuePortTestResources() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_port_test_script' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPurgeDataSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', self::PURGE_DATA_SLUG, array (
					$this,
					'outputPurgeDataContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueHomeScreenStylesheet' 
			) );
		}
		
		/**
		 * This function runs after a successful, error-free save
		 */
		public function handleSuccessfulSave() {
			// WordPress likes to keep GET parameters around for a long time
			// (something in the call to settings_fields() does this)
			// here we redirect after a successful save to clear those parameters
			postmanRedirect ( POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		public function handlePurgeDataAction() {
			$this->logger->debug ( 'Purging stored data' );
			delete_option ( PostmanOptions::POSTMAN_OPTIONS );
			delete_option ( PostmanOAuthToken::OPTIONS_NAME );
			delete_option ( PostmanAdminController::TEST_OPTIONS );
			$this->messageHandler->addMessage ( 'All plugin settings were removed.' );
			postmanRedirect ( POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		/**
		 * Handles the authorization grant
		 */
		function handleAuthorizationGrant() {
			$logger = $this->logger;
			$options = $this->options;
			$authorizationToken = $this->authorizationToken;
			$logger->debug ( 'Authorization in progress' );
			$transactionId = PostmanSession::getInstance ()->getOauthInProgress ();
			PostmanSession::getInstance ()->unsetOauthInProgress ();
			
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( PostmanTransportUtils::getCurrentTransport (), $options, $authorizationToken );
			try {
				if ($authenticationManager->processAuthorizationGrantCode ( $transactionId )) {
					$logger->debug ( 'Authorization successful' );
					// save to database
					$authorizationToken->save ();
				} else {
					$this->messageHandler->addError ( __ ( 'Your email provider did not grant Postman permission. Try again.', 'postman-smtp' ) );
				}
			} catch ( PostmanStateIdMissingException $e ) {
				$this->messageHandler->addError ( __ ( 'The grant code from Google had no accompanying state and may be a forgery', 'postman-smtp' ) );
			} catch ( Exception $e ) {
				$logger->error ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				/* translators: %s is the error message */
				$this->messageHandler->addError ( sprintf ( __ ( 'Error authenticating with this Client ID - please create a new one. [%s]', 'postman-smtp' ), '<em>' . $e->getMessage () . '</em>' ) );
			}
			// redirect home
			postmanRedirect ( POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		
		/**
		 * This method is called when a user clicks on a "Request Permission from Google" link.
		 * This link will create a remote API call for Google and redirect the user from WordPress to Google.
		 * Google will redirect back to WordPress after the user responds.
		 */
		public function handleOAuthPermissionRequestAction() {
			$this->logger->debug ( 'handling OAuth Permission request' );
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( PostmanTransportUtils::getCurrentTransport (), $this->options, $this->authorizationToken );
			$transactionId = $authenticationManager->generateRequestTransactionId ();
			PostmanSession::getInstance ()->setOauthInProgress ( $transactionId );
			$authenticationManager->requestVerificationCode ( $transactionId );
		}
		
		/**
		 * Register and add settings
		 */
		public function initializeAdminPage() {
			// register the stylesheet and javascript external resources
			wp_register_style ( self::POSTMAN_STYLE, plugins_url ( 'style/postman.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery.steps.css', __FILE__ ), self::POSTMAN_STYLE, '1.1.0' );
			
			wp_register_script ( self::POSTMAN_SCRIPT, plugins_url ( 'script/postman.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery.steps.min.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), '1.1.0' );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery.validate.min.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), '1.13.1' );
			wp_register_script ( 'postman_wizard_script', plugins_url ( 'script/postman_wizard.js', __FILE__ ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					'jquery_steps_script',
					self::POSTMAN_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_test_email_wizard_script', plugins_url ( 'script/postman_test_email_wizard.js', __FILE__ ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					'jquery_steps_script',
					self::POSTMAN_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_manual_config_script', plugins_url ( 'script/postman_manual_config.js', __FILE__ ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					self::POSTMAN_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_port_test_script', plugins_url ( 'script/postman_port_test.js', __FILE__ ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					self::POSTMAN_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_testing', _x ( 'Checking...', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_open', _x ( 'Ok', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_closed', _x ( 'Closed', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_email_test', array (
					'not_started' => _x ( 'In Outbox', 'Email Test Status', 'postman-smtp' ),
					'sending' => _x ( 'Sending...', 'Email Test Status', 'postman-smtp' ),
					'success' => _x ( 'Success', 'Email Test Status', 'postman-smtp' ),
					'failed' => _x ( 'Failed', 'Email Test Status', 'postman-smtp' ) 
			) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_wizard_wait', __ ( 'Please wait for the port test to finish', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_wizard_no_ports', __ ( 'No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.', 'postman-smtp' ) );
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_check_timeout', PostmanSmtp::POSTMAN_TCP_CONNECTION_TIMEOUT . '' );
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_smtp_section_element_name', 'div#smtp_section' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_oauth_section_element_name', 'div#oauth_section' );
			
			// user input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_email', '#input_' . PostmanOptions::SENDER_EMAIL );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_name', '#input_' . PostmanOptions::SENDER_NAME );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_element_name', '#input_' . PostmanOptions::PORT );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_hostname_element_name', '#input_' . PostmanOptions::HOSTNAME );
			
			// the enc input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_for_password_el', '#input_enc_type_password' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_for_oauth2_el', '#input_enc_type_' . PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_none', PostmanOptions::ENCRYPTION_TYPE_NONE );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_ssl', PostmanOptions::ENCRYPTION_TYPE_SSL );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_tls', PostmanOptions::ENCRYPTION_TYPE_TLS );
			// these are the ids for the <option>s in the encryption <select>
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_option_ssl_id', '.input_enc_type_ssl' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_option_tls_id', '.input_enc_type_tls' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_option_none_id', '.input_enc_type_none' );
			
			// the password inputs
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_basic_username', '#input_' . PostmanOptions::BASIC_AUTH_USERNAME );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_basic_password', '#input_' . PostmanOptions::BASIC_AUTH_PASSWORD );
			
			// the auth input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_redirect_url_el', '#input_oauth_redirect_url' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_auth_type', '#input_' . PostmanOptions::AUTHENTICATION_TYPE );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_none', PostmanOptions::AUTHENTICATION_TYPE_NONE );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_plain', PostmanOptions::AUTHENTICATION_TYPE_PLAIN );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_oauth2', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			// these are the ids for the <option>s in the auth <select>
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_option_oauth2_id', '#input_auth_type_oauth2' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_option_none_id', '#input_auth_type_none' );
			
			// test email input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_test_email', '#input_' . PostmanOptions::TEST_EMAIL );
			
			//
			$sanitizer = new PostmanInputSanitizer ( $this->options );
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
					$sanitizer,
					'sanitize' 
			) );
			
			// Sanitize
			add_settings_section ( 'transport_section', _x ( 'General Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printTransportSectionInfo' 
			), 'transport_options' );
			
			if ($this->options->isNew () && $this->importableConfiguration->isImportAvailable ()) {
				add_settings_field ( 'import_configuration', _x ( 'Import from Plugin', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'import_configuration_callback' 
				), 'transport_options', 'transport_section' );
			}
			
			add_settings_field ( PostmanOptions::TRANSPORT_TYPE, _x ( 'Transport', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'transport_type_callback' 
			), 'transport_options', 'transport_section' );
			
			add_settings_field ( PostmanOptions::SENDER_NAME, _x ( 'Sender Name', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'sender_name_callback' 
			), 'transport_options', 'transport_section' );
			
			add_settings_field ( PostmanOptions::SENDER_EMAIL, _x ( 'Sender Email Address', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'sender_email_callback' 
			), 'transport_options', 'transport_section' );
			
			// Sanitize
			add_settings_section ( PostmanAdminController::SMTP_SECTION, _x ( 'Transport Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printSmtpSectionInfo' 
			), PostmanAdminController::SMTP_OPTIONS );
			
			add_settings_field ( PostmanOptions::AUTHENTICATION_TYPE, _x ( 'Authentication', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'authentication_type_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( 'Security', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'encryption_type_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::PORT, _x ( 'Port', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'port_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, _x ( 'Authentication Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printBasicAuthSectionInfo' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_USERNAME, _x ( 'Username', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'basic_auth_username_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_PASSWORD, _x ( 'Password', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'basic_auth_password_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			// the OAuth section
			add_settings_section ( PostmanAdminController::OAUTH_SECTION, _x ( 'Authentication Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::OAUTH_OPTIONS );
			
			add_settings_field ( 'callback_domain', sprintf ( '<span id="callback_domain">%s</span>', $this->oauthScribe->getCallbackDomainLabel () ), array (
					$this,
					'callback_domain_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( 'redirect_url', sprintf ( '<span id="redirect_url">%s</span>', $this->oauthScribe->getCallbackUrlLabel () ), array (
					$this,
					'redirect_url_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_ID, _x ( $this->oauthScribe->getClientIdLabel (), 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'oauth_client_id_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_SECRET, _x ( $this->oauthScribe->getClientSecretLabel (), 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'oauth_client_secret_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			// the Advanced section
			add_settings_section ( PostmanAdminController::ADVANCED_SECTION, _x ( 'Advanced Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printAdvancedSectionInfo' 
			), PostmanAdminController::ADVANCED_OPTIONS );
			
			add_settings_field ( 'connection_timeout', _x ( 'TCP Connection Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'connection_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( 'read_timeout', _x ( 'TCP Read Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'read_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::REPLY_TO, _x ( 'Reply-To Email Address', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'reply_to_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::LOG_LEVEL, _x ( 'Log Level', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'log_level_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			// the Test Email section
			register_setting ( 'email_group', PostmanAdminController::TEST_OPTIONS, array (
					$sanitizer,
					'testSanitize' 
			) );
			
			add_settings_section ( 'TEST_EMAIL', _x ( 'Test Your Setup', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printTestEmailSectionInfo' 
			), PostmanAdminController::POSTMAN_TEST_SLUG );
			
			add_settings_field ( 'test_email', _x ( 'Recipient Email Address', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'test_email_callback' 
			), PostmanAdminController::POSTMAN_TEST_SLUG, 'TEST_EMAIL' );
			
			// the Purge Data section
			add_settings_section ( 'PURGE_DATA', _x ( 'Delete plugin settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printPurgeDataSectionInfo' 
			), 'PURGE_DATA' );
		}
		
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function getAjaxPortStatus() {
			$hostname = $_POST ['hostname'];
			$port = intval ( $_POST ['port'] );
			$this->logger->debug ( 'testing port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ();
			$success = $portTest->testSmtpPorts ( $hostname, $port, $this->options->getConnectionTimeout () );
			$this->logger->debug ( sprintf ( 'testing port result for %s:%s success=%s', $hostname, $port, $success ) );
			$response = array (
					'message' => $portTest->getErrorMessage (),
					'success' => $success 
			);
			wp_send_json ( $response );
		}
		
		/**
		 * This Ajax sends a test email
		 */
		function sendTestEmailViaAjax() {
			$email = $_POST ['email'];
			$method = $_POST ['method'];
			try {
				$emailTester = new PostmanSendTestEmailController ();
				$subject = _x ( 'WordPress Postman SMTP Test', 'Test Email Subject', 'postman-smtp' );
				// Englsih - Mandarin - French - Hindi - Spanish - Portuguese - Russian - Japanese
				/* translators: where %s is the Postman plugin version number (e.g. 1.4) */
				$message = sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanSmtpEngine::EOL, PostmanSmtpEngine::EOL, sprintf ( _x ( 'Sent by Postman v%s', 'Test Email Tagline', 'postman-smtp' ), POSTMAN_PLUGIN_VERSION ) );
				$startTime = microtime ( true ) * 1000;
				$success = $emailTester->sendTestEmail ( $this->options, $this->authorizationToken, $email, $this->oauthScribe->getServiceName (), $subject, $message );
				$endTime = microtime ( true ) * 1000;
				if ($success) {
					$statusMessage = sprintf ( __ ( 'Your message was delivered (%d ms) to the SMTP server! Congratulations :)', 'postman-smtp' ), ($endTime - $startTime) );
				} else {
					$statusMessage = $emailTester->getMessage ();
				}
				$this->logger->debug ( 'statusmessage: ' . $statusMessage );
				$response = array (
						'message' => $statusMessage,
						'transcript' => $emailTester->getTranscript (),
						'success' => $success 
				);
			} catch ( PostmanSendMailCommunicationError334 $e ) {
				/* translators: where %s is the email service name (e.g. Gmail) */
				$response = array (
						'message' => sprintf ( __ ( 'Communication Error [334] - make sure the Sender Email belongs to the account which provided the %s OAuth 2.0 consent.', 'postman-smtp' ), $this->oauthScribe->getServiceName () ),
						'transcript' => $emailTester->getTranscript (),
						'success' => false 
				);
				$this->logger->error ( "SMTP session transcript follows:\n" . $emailTester->getTranscript () );
			} catch ( PostmanSendMailInexplicableException $e ) {
				$response = array (
						'message' => __ ( 'The impossible is possible; sending through wp_mail() failed, but sending through internal engine succeeded.', 'postman-smtp' ),
						'transcript' => $emailTester->getTranscript (),
						'success' => false 
				);
				$this->logger->error ( "SMTP session transcript follows:\n" . $emailTester->getTranscript () );
			}
			wp_send_json ( $response );
		}
		/**
		 * This function extracts configuration details form a competing SMTP plugin
		 * and pushes them into the Postman configuration screen.
		 */
		function getConfigurationFromExternalPluginViaAjax() {
			$plugin = $_POST ['plugin'];
			$this->logger->debug ( 'Looking for config=' . $plugin );
			foreach ( $this->importableConfiguration->getAvailableOptions () as $options ) {
				if ($options->getPluginSlug () == $plugin) {
					$this->logger->debug ( 'Sending configuration response' );
					$response = array (
							PostmanOptions::SENDER_EMAIL => $options->getSenderEmail (),
							PostmanOptions::SENDER_NAME => $options->getSenderName (),
							PostmanOptions::HOSTNAME => $options->getHostname (),
							PostmanOptions::PORT => $options->getPort (),
							PostmanOptions::AUTHENTICATION_TYPE => $options->getAuthenticationType (),
							PostmanOptions::ENCRYPTION_TYPE => $options->getEncryptionType (),
							PostmanOptions::BASIC_AUTH_USERNAME => $options->getUsername (),
							PostmanOptions::BASIC_AUTH_PASSWORD => $options->getPassword (),
							'success' => true 
					);
					break;
				}
			}
			if (! isset ( $response )) {
				$response = array (
						'success' => false 
				);
			}
			wp_send_json ( $response );
		}
		
		/**
		 * This Ajax function retrieves the smtp hostname for a give e-mail address
		 */
		function getAjaxHostnameByEmail() {
			$email = $_POST ['email'];
			$d = new SmtpDiscovery ();
			$smtp = $d->getSmtpServer ( $email );
			$this->logger->debug ( 'given email ' . $email . ', smtp server is ' . $smtp );
			$response = array (
					'hostname' => ! empty ( $smtp ) ? $smtp : '' 
			);
			wp_send_json ( $response );
		}
		
		/**
		 * This Ajax function determines which hosts/ports to test in the Wizard Port Test
		 *
		 * Given a single outgoing smtp server hostname, return an array of host/port
		 * combinations to run the connectivity test on
		 */
		function getPortsToTestViaAjax() {
			$queryHostname = '';
			if (isset ( $_POST ['hostname'] )) {
				$queryHostname = $_POST ['hostname'];
			}
			$hosts = PostmanTransportUtils::getHostsToTest ( $queryHostname );
			$response = array (
					'hosts' => $hosts,
					'success' => true 
			);
			wp_send_json ( $response );
		}
		/**
		 * Print the Transport section info
		 */
		public function printTransportSectionInfo() {
			$totalTransportsAvailable = sizeof ( PostmanTransportDirectory::getInstance ()->getTransports () );
			/* translators: These two phrases represent the cases for 1) a single transport installed and 2) mulitple transports installed */
			print _n ( 'Enter the sender\'s name and email address:', 'Select the transport and enter the sender\'s name and email address:', $totalTransportsAvailable, 'postman-smtp' );
		}
		/**
		 * Print the Section text
		 */
		public function printSmtpSectionInfo() {
			print __ ( 'Select the authentication method and enter the SMTP server hostname and port:', 'postman-smtp' );
		}
		
		/**
		 * Print the Port Test text
		 */
		public function printPortTestSectionInfo() {
		}
		
		/**
		 * Print the Section text
		 */
		public function printBasicAuthSectionInfo() {
			print __ ( 'Enter the username (email address) and password you use to send email', 'postman-smtp' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printOAuthSectionInfo() {
			printf ( '<p id="wizard_oauth2_help">%s</p>', $this->oauthScribe->getOAuthHelp () );
		}
		
		/**
		 * Print the Section text
		 */
		public function printTestEmailSectionInfo() {
			// this isnt used anymore
			print __ ( 'You will receive an email from Postman with the subject "WordPress Postman SMTP Test."', 'postman-smtp' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printPurgeDataSectionInfo() {
			printf ( '<p><span>%s</span></p><p><span>%s</span></p>', __ ( 'This will purge all of Postman\'s settings, including SMTP server info, username/password and OAuth Credentials.', 'postman-smtp' ), __ ( 'Are you sure?', 'postman-smtp' ) );
		}
		
		/**
		 * Print the Section text
		 */
		public function printAdvancedSectionInfo() {
			print __ ( 'Increase the read timeout if your host is intermittenly failing to send mail. Be careful, this also correlates to how long your user must wait if your mail server is unreachable.', 'postman-smtp' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function transport_type_callback() {
			$transportType = $this->options->getTransportType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE );
			foreach ( PostmanTransportDirectory::getInstance ()->getTransports () as $transport ) {
				printf ( '<option class="input_tx_type_%1$s" value="%1$s" %3$s>%2$s</option>', $transport->getSlug (), $transport->getName (), $transportType == $transport->getSlug () ? 'selected="selected"' : '' );
			}
			print '</select>';
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function authentication_type_callback() {
			$authType = $this->options->getAuthenticationType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			printf ( '<option class="input_auth_type_none" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_NONE, $authType == PostmanOptions::AUTHENTICATION_TYPE_NONE ? 'selected="selected"' : '', _x ( 'None', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_plain" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_PLAIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN ? 'selected="selected"' : '', _x ( 'Plain', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_login" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_LOGIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN ? 'selected="selected"' : '', _x ( 'Login', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_crammd5" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5, $authType == PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 ? 'selected="selected"' : '', _x ( 'CRAMMD5', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_oauth2" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 ? 'selected="selected"' : '', _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' ) );
			print '</select>';
		}
		public function authenticationTypeRadioCallback() {
			$authType = $this->options->getAuthenticationType ();
			print '<table class="input_auth_type"><tr>';
			printf ( '<td><input type="radio" id="input_auth_none"   name="postman_options[auth_type]" class="input_auth_type" value="%s"/></td><td><label> %s</label></td>', PostmanOptions::AUTHENTICATION_TYPE_NONE, _x ( 'None', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" id="input_auth_plain"  name="postman_options[auth_type]" class="input_auth_type" value="%s"/></td><td><label> %s</label></td>', PostmanOptions::AUTHENTICATION_TYPE_PLAIN, _x ( 'Plain', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" id="input_auth_oauth2" name="postman_options[auth_type]" class="input_auth_type" value="%s"/></td><td><label> %s</label></td>', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' ) );
			print '</tr></table>';
		}
		/**
		 * Get the settings option array and print one of its values
		 */
		public function encryption_type_callback() {
			$encType = $this->options->getEncryptionType ();
			print '<select id="input_enc_type" class="input_encryption_type" name="postman_options[enc_type]">';
			printf ( '<option class="input_enc_type_none" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_NONE, $encType == PostmanOptions::ENCRYPTION_TYPE_NONE ? 'selected="selected"' : '', _x ( 'None', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<option class="input_enc_type_ssl" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_SSL, $encType == PostmanOptions::ENCRYPTION_TYPE_SSL ? 'selected="selected"' : '', _x ( 'SSL', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<option class="input_enc_type_tls" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_TLS, $encType == PostmanOptions::ENCRYPTION_TYPE_TLS ? 'selected="selected"' : '', _x ( 'TLS', 'Encryption Type', 'postman-smtp' ) );
			print '</select>';
		}
		public function encryption_type_radio_callback() {
			$encType = $this->options->getEncryptionType ();
			print '<table class="input_encryption_type"><tr>';
			printf ( '<td><input type="radio" id="input_enc_none" name="postman_options[enc_type]" class="input_encryption_type" value="%s"/></td><td><label> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_NONE, _x ( 'None', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" id="input_enc_ssl" name="postman_options[enc_type]" class="input_encryption_type" value="%s"/></td><td> <label class="input_enc_type_ssl"> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_SSL, _x ( 'SSL', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" id="input_enc_tls" name="postman_options[enc_type]" class="input_encryption_type" value="%s"/></td><td> <label> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_TLS, _x ( 'TLS', 'Encryption Type', 'postman-smtp' ) );
			print '</tr></table>';
		}
		
		/**
		 * Import configuration from another plugin
		 */
		public function import_configuration_callback() {
			printf ( '<input type="radio" name="input_plugin" value="" checked="checked"/> No' );
			$this->importableConfiguration->getAvailableOptions ();
			foreach ( $this->importableConfiguration->getAvailableOptions () as $options ) {
				printf ( '<input type="radio" name="input_plugin" class="input_plugin_radio" value="%s"/> <label> %s</label>', $options->getPluginSlug (), $options->getPluginName () );
			}
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input type="text" id="input_hostname" name="postman_options[hostname]" value="%s" size="40" class="required"/>', null !== $this->options->getHostname () ? esc_attr ( $this->options->getHostname () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_test_hostname_callback() {
			printf ( '<input type="text" id="input_hostname" name="postman_options[hostname]" value="portquiz.net" size="40" class="required"/>' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_callback($args) {
			printf ( '<input type="text" id="input_port" name="postman_options[port]" value="%s" %s/>', null !== $this->options->getPort () ? esc_attr ( $this->options->getPort () ) : '', isset ( $args ['style'] ) ? $args ['style'] : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_name_callback() {
			printf ( '<input type="text" id="input_sender_name" name="postman_options[sender_name]" value="%s" size="40" />', null !== $this->options->getSenderName () ? esc_attr ( $this->options->getSenderName () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="text" id="input_sender_email" name="postman_options[sender_email]" value="%s" size="40" class="required"/>', null !== $this->options->getSenderEmail () ? esc_attr ( $this->options->getSenderEmail () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function redirect_url_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" readonly="readonly" id="input_oauth_redirect_url" value="%s" size="60"/>', $this->oauthScribe->getCallbackUrl () );
		}
		private function getCallbackDomain() {
			try {
				return $this->oauthScribe->getCallbackDomain ();
			} catch ( Exception $e ) {
				return __ ( 'Error computing your domain root - please enter it manually', 'postman-smtp' );
			}
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function callback_domain_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" readonly="readonly" id="input_oauth_callback_domain" value="%s" size="60"/>', $this->getCallbackDomain () );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function basic_auth_username_callback() {
			printf ( '<input type="text" id="input_basic_auth_username" name="postman_options[basic_auth_username]" value="%s" size="40" class="required"/>', null !== $this->options->getUsername () ? esc_attr ( $this->options->getUsername () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function basic_auth_password_callback() {
			printf ( '<input type="password" autocomplete="off" id="input_basic_auth_password" name="postman_options[basic_auth_password]" value="%s" class="required"/>', null !== $this->options->getPassword () ? esc_attr ( $this->options->getPassword () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_id_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" id="oauth_client_id" name="postman_options[oauth_client_id]" value="%s" size="60" class="required"/>', null !== $this->options->getClientId () ? esc_attr ( $this->options->getClientId () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="60" class="required"/>', null !== $this->options->getClientSecret () ? esc_attr ( $this->options->getClientSecret () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function reply_to_callback() {
			printf ( '<input type="text" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function log_level_callback() {
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL );
			printf ( '<option value="%s" %s>Off</option>', PostmanLogger::OFF_INT, PostmanLogger::OFF_INT == $this->options->getLogLevel () ? 'selected="selected"' : '' );
			printf ( '<option value="%s" %s>Debug</option>', PostmanLogger::DEBUG_INT, PostmanLogger::DEBUG_INT == $this->options->getLogLevel () ? 'selected="selected"' : '' );
			printf ( '<option value="%s" %s>Errors</option>', PostmanLogger::ERROR_INT, PostmanLogger::ERROR_INT == $this->options->getLogLevel () ? 'selected="selected"' : '' );
			printf ( '</select>' );
		}
		
		/**
		 * Checkbox for Printing Errors
		 */
		public function print_errors_callback() {
			printf ( '<input type="checkbox" id="input_print_errors" name="%1$s[%2$s]" %3$s />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::PRINT_ERRORS, $this->options->isErrorPrintingEnabled () ? 'checked="checked"' : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function connection_timeout_callback() {
			printf ( '<input type="text" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function read_timeout_callback() {
			printf ( '<input type="text" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
		}
		
		/**
		 */
		public function prevent_sender_name_override_callback() {
			printf ( '<input type="checkbox" id="input_prevent_sender_name_override" name="postman_options[prevent_sender_name_override]" %s />', null !== $this->options->isSenderNameOverridePrevented () ? 'checked="checked"' : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="input_test_email" name="postman_test_options[test_email]" value="%s" class="required email" size="40"/>', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
		
		/**
		 * Options page callback
		 */
		public function outputDefaultContent() {
			// Set class property
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
				printf ( '<p><span style="color:green;padding:2px 5px; font-size:1.2em">%s</span></p>', __ ( 'Postman is configured.', 'postman-smtp' ) );
				$currentTransport = PostmanTransportUtils::getCurrentTransport ();
				$deliveryDetails = $currentTransport->getDeliveryDetails ( $this->options );
				printf ( '<p style="margin:0 10px"><span>%s</span></p>', $deliveryDetails );
				if ($this->options->isAuthTypeOAuth2 ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins or themes may override the sender name only.</em>', 'postman-smtp' ) );
				} else if ($this->options->isAuthTypePassword ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins or themes may override the sender name and email address causing rejection with some email services, such as Yahoo Mail. If you experience problems, try leaving the sender email address empty in these plugins or themes.</em>', 'postman-smtp' ) );
				}
			} else {
				printf ( '<p><span style="color:red; padding:2px 5px; font-size:1.1em">%s</span></p>', __ ( 'Status: Postman is not sending mail.', 'postman-smtp' ) );
				if ($this->options->isNew ()) {
					printf ( '<h3>%s</h3>', __ ( 'Thank-you for choosing Postman!', 'postman-smtp' ) );
					/* translators: where %s is the URL of the Setup Wizard */
					printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'Let\'s get started! All users are strongly encouraged to <a href="%s">run the Setup Wizard</a>.', 'postman-smtp' ), $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ) ) );
					if ($this->importableConfiguration->isImportAvailable ()) {
						/* translators: where %s is the URL of the Manual Configuration */
						printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'If you wish, Postman can <a href="%s">import your SMTP configuration</a> from another plugin. You can run the Wizard later if you need to.', 'postman-smtp' ), $this->getPageUrl ( self::CONFIGURATION_SLUG ) ) );
					}
				}
			}
			
			if (PostmanState::getInstance ()->isTimeToReviewPostman ()) {
				printf ( '<h4>%s</h4>', __ ( 'Has Postman been working well for you?', 'postman-smtp' ) );
				/* translators: where %d is the number of emails delivered */
				printf ( '<p style="margin:0 10px">%s', sprintf ( _n ( 'Postman has delivered %d email for you!', 'Postman has delivered %d emails for you!', PostmanStats::getInstance ()->getSuccessfulDeliveries (), 'postman-smtp' ), PostmanStats::getInstance ()->getSuccessfulDeliveries () ) );
				print ' ';
				/* translators: where %s is the URL to the WordPress.org review and ratings page */
				printf ( '%s</p>', sprintf ( __ ( 'Please considering leaving a <a href="%s">review of Postman SMTP</a> at WordPress.org to help spread the word<br/> about the new way to send email from WordPress! :D', 'postman-smtp' ), 'https://wordpress.org/support/view/plugin-reviews/postman-smtp?filter=5' ) );
			}
		}
		
		/**
		 */
		public function outputManualConfigurationContent() {
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			print '<form method="post" action="options.php">';
			// This prints out all hidden setting fields
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			print '<section id="transport_config">';
			do_settings_sections ( 'transport_options' );
			print '</section>';
			print '<section id="smtp_config">';
			do_settings_sections ( PostmanAdminController::SMTP_OPTIONS );
			print '</section>';
			$authType = $this->options->getAuthenticationType ();
			print '<section id="password_auth_config">';
			do_settings_sections ( PostmanAdminController::BASIC_AUTH_OPTIONS );
			print ('</section>') ;
			print '<section id="oauth_auth_config">';
			do_settings_sections ( PostmanAdminController::OAUTH_OPTIONS );
			print ('</section>') ;
			printf ( '<p id="advanced_options_config" class="fineprint"><span><a href="#">%s</a></span></p>', _x ( 'Show Advanced Settings', 'Configuration Section', 'postman-smtp' ) );
			print '<section id="advanced_options_config">';
			do_settings_sections ( PostmanAdminController::ADVANCED_OPTIONS );
			print ('</section>') ;
			submit_button ();
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPurgeDataContent() {
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			printf ( '<input type="hidden" name="action" value="%s" />', self::PURGE_DATA_SLUG );
			do_settings_sections ( 'PURGE_DATA' );
			submit_button ( _x ( 'Delete All Data', 'Button Label', 'postman-smtp' ), 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPortTestContent() {
			$ports = array (
					array (
							'port' => 25,
							'message' => __ ( 'SMTP port; commonly blocked', 'Port Test', 'postman-smtp' ) 
					),
					array (
							'port' => 443,
							'message' => sprintf ( __ ( 'HTTPS port; can be used by the <a href="%s">Postman Gmail Extension</a>', 'Port Test', 'postman-smtp' ), 'https://wordpress.org/plugins/postman-gmail-extension/' ) 
					),
					array (
							'port' => 465,
							'message' => __ ( 'SMTPS-SSL port', 'Port Test', 'postman-smtp' ) 
					),
					array (
							'port' => 587,
							'message' => __ ( 'SMTPS-TLS port', 'Port Test', 'postman-smtp' ) 
					) 
			);
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			printf ( '<h3>%s</h3>', _x ( 'Connectivity Test', 'Page Title', 'postman-smtp' ) );
			print '<p>';
			print __ ( 'This test determines which ports are open for Postman to use.', 'postman-smtp' );
			print ' ';
			/* translators: where %d is an amount of time, in seconds */
			printf ( _n ( 'Each test is given %d second to complete.', 'Each test is given %d seconds to complete.', $this->options->getConnectionTimeout (), 'postman-smtp' ), $this->options->getConnectionTimeout () );
			print '<br/>';
			/* translators: where %d is an amount of time, in seconds */
			printf ( __ ( 'The entire test will take up to %d seconds.', 'postman-smtp' ), ($this->options->getConnectionTimeout () * sizeof ( $ports )) );
			print ' ';
			print __ ( 'A <span style="color:red">Closed</span> port indicates:', 'postman-smtp' );
			print '<ol>';
			printf ( '<li>%s</li>', __ ( 'Your host has placed a firewall between this site and the SMTP server', 'postman-smtp' ) );
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">PHP configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen' ) );
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">WordPress configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://wp-mix.com/disable-external-url-requests/' ) );
			printf ( '<li>%s</li>', __ ( 'The SMTP server has no service running on that port', 'postman-smtp' ) );
			printf ( '</ol></p><p><b>%s</b></p>', __ ( 'If the port you are trying to use is <span style="color:red">Closed</span>, Postman can not deliver mail. Contact your host to get the port opened.', 'postman-smtp' ) );
			print '<form id="port_test_form_id" method="post">';
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ) );
			$this->port_test_hostname_callback ();
			submit_button ( _x ( 'Begin Test', 'Button Label', 'postman-smtp' ), 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table">';
			$portName = _x ( 'Port %s', 'Port Test', 'postman-smtp' );
			$portStatus = _x ( 'Unknown', 'Port Test Status', 'postman-smtp' );
			foreach ( $ports as $port ) {
				printf ( '<tr><td class="port">%2$s</td><td>%3$s</td><td id="port-test-port-%1$d">%4$s</td>', $port ['port'], sprintf ( $portName, $port ['port'] ), $port ['message'], $portStatus );
			}
			print '</table>';
			print '</div>';
		}
		
		/**
		 */
		public function outputDiagnosticsContent() {
			// test features
			$sslRequirement = extension_loaded ( 'openssl' );
			$splAutoloadRegisterRequirement = function_exists ( 'spl_autoload_register' );
			$phpVersionRequirement = PHP_VERSION_ID >= 50300;
			$arrayObjectRequirement = class_exists ( 'ArrayObject' );
			$getmxrrRequirement = function_exists ( 'getmxrr' );
			$displayErrors = ini_get ( 'display_errors' );
			$errorReporting = ini_get ( 'error_reporting' );
			
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			printf ( '<h3>%s</h3>', _x ( 'Tips', 'Page Title', 'postman-smtp' ) );
			$diagnostics = sprintf ( 'PHP v5.3: %s (%s)%s', ($phpVersionRequirement ? 'Yes' : 'No'), PHP_VERSION, PHP_EOL );
			$diagnostics .= sprintf ( 'PHP SSL Extension: %s%s', ($sslRequirement ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'PHP spl_autoload_register: %s%s', ($splAutoloadRegisterRequirement ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'PHP ArrayObject: %s%s', ($arrayObjectRequirement ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'PHP display_errors: %s%s', $displayErrors, PHP_EOL );
			$diagnostics .= sprintf ( 'PHP errorReporting: %s%s', $errorReporting, PHP_EOL );
			$diagnostics .= sprintf ( 'WordPress Version: %s%s', get_bloginfo ( 'version' ), PHP_EOL );
			$diagnostics .= sprintf ( 'WordPress WP_DEBUG: %s%s', WP_DEBUG, PHP_EOL );
			$diagnostics .= sprintf ( 'WordPress WP_DEBUG_LOG: %s%s', WP_DEBUG_LOG, PHP_EOL );
			$diagnostics .= sprintf ( 'WordPress WP_DEBUG_DISPLAY: %s%s', WP_DEBUG_DISPLAY, PHP_EOL );
			$diagnostics .= ('WordPress Active Plugins');
			// from http://stackoverflow.com/questions/20488264/how-do-i-get-activated-plugin-list-in-wordpress-plugin-development
			$apl = get_option ( 'active_plugins' );
			$plugins = get_plugins ();
			$activated_plugins = array ();
			foreach ( $apl as $p ) {
				if (isset ( $plugins [$p] )) {
					$diagnostics .= ' : ' . $plugins [$p] ['Name'];
				}
			}
			$transports = '';
			foreach ( PostmanTransportDirectory::getInstance ()->getTransports () as $transport ) {
				$transports .= ' : ' . $transport->getName ();
			}
			$diagnostics .= (PHP_EOL);
			$diagnostics .= sprintf ( 'Postman Transport: %s%s', $this->options->getTransportType (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Transport Configured: %s%s', PostmanTransportUtils::getCurrentTransport ()->isConfigured ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Transport Ready: %s%s', PostmanTransportUtils::getCurrentTransport ()->isReady ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Authorization Type: %s%s', $this->options->getAuthenticationType (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Encryption Type: %s%s', $this->options->getEncryptionType (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman SMTP Host: %s%s', $this->options->getHostname (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman SMTP Port: %s%s', $this->options->getPort (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Sender Matches user: %s%s', ($this->options->getSenderEmail () == $this->options->getUsername () ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Successful Deliveries: %s%s', PostmanStats::getInstance ()->getSuccessfulDeliveries (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Failed Deliveries: %s%s', PostmanStats::getInstance ()->getFailedDeliveries (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Bound: %s%s', (PostmanWpMailBinder::getInstance ()->isBound () ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Bind failure: %s%s', (PostmanWpMailBinder::getInstance ()->isUnboundDueToException () ? 'Yes' : 'No'), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Available Transports%s%s', $transports, PHP_EOL );
			$diagnostics .= sprintf ( 'Postman LogLevel: %s%s', $this->options->getLogLevel (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Connection Timeout: %d%s', $this->options->getConnectionTimeout (), PHP_EOL );
			$diagnostics .= sprintf ( 'Postman Read Timeout: %s%s', $this->options->getReadTimeout (), PHP_EOL );
			printf ( '<h4>%s</h4>', __ ( 'Are you having any issues with Postman?', 'postman-smtp' ) );
			print '<dl>';
			printf ( '<dt>%s</dt>', __ ( 'The Wizard Can\'t find any Open Ports', 'postman-smtp' ) );
			printf ( '<dd>%s</dd>', sprintf ( __ ( 'Run a <a href="%s">connectivity test</a> to find out what\'s wrong. You may find that the HTTPS port is open.', 'postman-smtp' ), $this->getPageUrl ( self::PORT_TEST_SLUG ) ) );
			print '</dl>';
			print '<dl>';
			printf ( '<dt>%s</dt>', __ ( '"Request OAuth permission" is not working', 'postman-smtp' ) );
			printf ( '<dd>%s</dd>', __ ( 'Please note that the Client ID and Client Secret fields are NOT for your username and password. They are for OAuth Credentials only.', 'postman-smtp' ) );
			print '</dl>';
			print '<dl>';
			printf ( '<dt>%s</dt>', __ ( 'Sometimes sending mail still fails', 'postman-smtp' ) );
			printf ( '<dd>%s</dd>', __ ( 'Your host may have poor connectivity to your email server. Open up the advanced configuration and double the TCP Read Timeout setting.', 'postman-smtp' ) );
			print '</dl>';
			printf ( '<h3>%s</h3>', _x ( 'Diagnostic Info', 'Page Title', 'postman-smtp' ) );
			printf ( '<p style="margin:0 10px">%s</p>', __ ( 'Do the above tips fail to resolve your issue? Pease check the <a href="https://wordpress.org/plugins/postman-smtp/other_notes/">error messages</a> page and the <a href="https://wordpress.org/support/plugin/postman-smtp">support forum</a>.</br>If you write for help, please include the following diagnostic information:', 'postman-smtp' ) );
			print '</br>';
			printf ( '<textarea readonly="readonly" id="diagnostic-text" cols="80" rows="6">%s</textarea>', $diagnostics );
			print '</div>';
		}
		
		/**
		 */
		private function displayTopNavigation() {
			screen_icon ();
			printf ( '<h2>%s</h2>', _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ) );
			print '<div id="welcome-panel" class="welcome-panel">';
			print '<div class="welcome-panel-content">';
			print '<div class="welcome-panel-column-container">';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', _x ( 'Get Started', 'Main Menu', 'postman-smtp' ) );
			printf ( '<a class="button button-primary button-hero" href="%s">%s</a>', $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ), _x ( 'Start the Wizard', 'Button Label', 'postman-smtp' ) );
			printf ( '<p class="">or, <a href="%s">%s</a>. </p>', $this->getPageUrl ( self::CONFIGURATION_SLUG ), _x ( 'configure manually', 'Main Menu', 'postman-smtp' ) );
			print '</div>';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', _x ( 'Actions', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			if (PostmanTransportUtils::isRequestOAuthPermissionAllowed ( $this->options, $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send-test-email">%s</a></li>', $this->getActionUrl ( self::REQUEST_OAUTH2_GRANT_SLUG ), $this->oauthScribe->getRequestPermissionLinkText () );
			} else {
				printf ( '<li><div class="welcome-icon send_test_emaail">%s</div></li>', $this->oauthScribe->getRequestPermissionLinkText () );
			}
			if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send_test_email">%s</a></li>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ), _x ( 'Send a Test Email', 'Main Menu', 'postman-smtp' ) );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', _x ( 'Send a Test Email', 'Main Menu', 'postman-smtp' ) );
			}
			printf ( '<li><a href="%s" class="welcome-icon oauth-authorize">%s</a></li>', $this->getPageUrl ( self::PURGE_DATA_SLUG ), _x ( 'Delete plugin settings', 'Main Menu', 'postman-smtp' ) );
			print '</ul>';
			print '</div>';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', _x ( 'Troubleshooting', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::DIAGNOSTICS_SLUG ), __ ( 'Tips and Diagnostic Info', 'postman-smtp' ) );
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::PORT_TEST_SLUG ), _x ( 'Run a Connectivity Test', 'Main Menu', 'postman-smtp' ) );
			printf ( '<li><a href="https://wordpress.org/support/plugin/postman-smtp" class="welcome-icon postman_support">%s</a></li>', _x ( 'Online Support', 'Main Menu', 'postman-smtp' ) );
			print '</ul></div></div></div></div>';
		}
		
		/**
		 */
		public function outputWizardContent() {
			// Set default values for input fields
			$this->options->setSenderEmailIfEmpty ( wp_get_current_user ()->user_email );
			$this->options->setSenderNameIfEmpty ( wp_get_current_user ()->display_name );
			
			// construct Wizard
			print '<div class="wrap">';
			
			$this->displayTopNavigation ();
			
			printf ( '<h3>%s</h3>', _x ( 'Postman Setup Wizard', 'Page Title', 'postman-smtp' ) );
			print '<form id="postman_wizard" method="post" action="options.php">';
			printf ( '<input type="hidden" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
			printf ( '<input type="hidden" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE, $this->options->getTransportType () );
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			
			// Wizard Step 1
			printf ( '<h5>%s</h5>', _x ( 'Sender Details', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Who is the mail coming from?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Let\'s begin! Please enter the email address and name you\'d like to send mail from.', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Please note that to combat Spam, many email services will <em>not</em> let you send from an e-mail address other than your own.', 'postman-smtp' ) );
			printf ( '<label for="postman_options[sender_email]">%s</label>', _x ( 'Sender Email Address', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->sender_email_callback ();
			printf ( '<label for="postman_options[sender_name]">%s</label>', _x ( 'Sender Email Name', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->sender_name_callback ();
			print '</fieldset>';
			
			// Wizard Step 2
			printf ( '<h5>%s</h5>', _x ( 'SMTP Server Hostname', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Who will relay the mail?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'This is the server, also known as a Mail Submission Agent (MSA), that Postman will use to deliver your mail. If possible, Postman will try to determine this hostname based on the e-mail address.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->hostname_callback ();
			print '</fieldset>';
			
			// Wizard Step 3
			printf ( '<h5>%s</h5>', _x ( 'Connectivity Test', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'How will the connection to the MSA be established?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'The server hostname and port you can use is a combination of what your mail service provider offers, and what your WordPress host allows. Postman will attempt to determine which options are available to you.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', _x ( 'Available routes', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->port_callback ( array (
					'style' => 'style="display:none"' 
			) );
			print '<table id="wizard_port_test">';
			print '</table>';
			print '<br/><p id="wizard_recommendation"></p>';
			print '</fieldset>';
			
			// Wizard Step 4
			printf ( '<h5>%s</h5>', _x ( 'Authentication', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Authentication', 'Wizard Step Title', 'postman-smtp' ) );
			
			print '<section class="wizard-auth-oauth2">';
			print '<p id="wizard_oauth2_help"></p>';
			printf ( '<label id="callback_domain" for="callback_domain">%s</label>', $this->oauthScribe->getCallbackDomainLabel () );
			print '<br />';
			print $this->callback_domain_callback ();
			print '<br />';
			printf ( '<label id="redirect_url" for="redirect_uri">%s</label>', $this->oauthScribe->getCallbackUrlLabel () );
			print '<br />';
			print $this->redirect_url_callback ();
			print '<br />';
			printf ( '<label id="client_id" for="client_id">%s</label>', $this->oauthScribe->getClientIdLabel () );
			print '<br />';
			print $this->oauth_client_id_callback ();
			print '<br />';
			printf ( '<label id="client_secret" for="client_secret">%s</label>', $this->oauthScribe->getClientSecretLabel () );
			print '<br />';
			print $this->oauth_client_secret_callback ();
			print '<br />';
			print '</section>';
			
			print '<section class="wizard-auth-basic">';
			printf ( '<p class="port-explanation-ssl">%s</p>', __ ( 'Enter your credentials. Your username is most likely your email address.', 'postman-smtp' ) );
			printf ( '<label class="input_auth_type" for="auth_type">%s</label>', _x ( 'Authentication', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br class="input_auth_type" />';
			print $this->authenticationTypeRadioCallback ();
			printf ( '<label class="input_encryption_type" for="enc_type">%s</label>', _x ( 'Security', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br class="input_encryption_type" />';
			print $this->encryption_type_radio_callback ();
			printf ( '<label for="username">%s</label>', _x ( 'Username', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->basic_auth_username_callback ();
			print '<br />';
			printf ( '<label for="password">%s</label>', _x ( 'Password', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->basic_auth_password_callback ();
			print '</section>';
			
			print '</fieldset>';
			
			// Wizard Step 5
			printf ( '<h5>%s</h5>', _x ( 'Finish', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Finish', 'Wizard Step Title', 'postman-smtp' ) );
			print '<section>';
			printf ( '<p>%s</p>', __ ( 'Click Finish to save these settings, then:', 'postman-smtp' ) );
			print '<ul style="margin-left: 20px">';
			printf ( '<li class="wizard-auth-oauth2">%s</li>', __ ( 'Request permission from the Email Provider to allow Postman to send email and', 'postman-smtp' ) );
			printf ( '<li>%s</li>', __ ( 'Send yourself a Test Email to make sure everything is working!', 'postman-smtp' ) );
			print '</ul>';
			print '</section>';
			print '</fieldset>';
			print '</form>';
		}
		
		/**
		 */
		public function outputTestEmailWizardContent() {
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			
			// set default recipient for test emails
			$testEmail = $this->testOptions [PostmanOptions::TEST_EMAIL];
			if (! isset ( $testEmail )) {
				$this->testOptions [PostmanOptions::TEST_EMAIL] = wp_get_current_user ()->user_email;
			}
			printf ( '<h3>%s</h3>', _x ( 'Send a Test Email', 'Page Title', 'postman-smtp' ) );
			printf ( '<form id="postman_test_email_wizard" method="post" action="%s">', POSTMAN_HOME_PAGE_ABSOLUTE_URL );
			
			// Step 1
			printf ( '<h5>%s</h5>', __ ( 'Choose the Recipient', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Who is this message going to?', 'postman-smtp' ) );
			printf ( '<p>%s', __ ( 'This utility allows you to send an email message for testing.', 'postman-smtp' ) );
			print ' ';
			/* translators: where %d is an amount of time, in seconds */
			printf ( '%s</p>', sprintf ( _n ( 'If there is a problem, Postman will give up after %d second.', 'If there is a problem, Postman will give up after %d seconds.', $this->options->getReadTimeout () * 2, 'postman-smtp' ), $this->options->getReadTimeout () * 2 ) );
			printf ( '<label for="postman_test_options[test_email]">%s</label>', _x ( 'Recipient Email Address', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->test_email_callback ();
			print '</fieldset>';
			
			// Step 2
			printf ( '<h5>%s</h5>', __ ( 'Send The Message', 'postman-smtp' ) );
			print '<fieldset>';
			print '<legend>';
			print __ ( 'Sending the message:', 'postman-smtp' );
			printf ( ' <span id="postman_test_message_status">%s</span>', __ ( 'In Outbox', 'Send a Test Email', 'postman-smtp' ) );
			print '</legend>';
			print '<section>';
			printf ( '<p><label>%s</label></p>', __ ( 'Status Message', 'postman-smtp' ) );
			print '<textarea id="postman_test_message_error_message" readonly="readonly" cols="65" rows="2"></textarea>';
			if (PostmanTransportUtils::getCurrentTransport ()->isTranscriptSupported ()) {
				printf ( '<p><label for="postman_test_message_transcript">%s</label></p>', __ ( 'SMTP Session Transcript', 'postman-smtp' ) );
				print '<textarea readonly="readonly" id="postman_test_message_transcript" cols="65" rows="10"></textarea>';
			}
			print '</section>';
			print '</fieldset>';
			print '</form>';
		}
	}
}