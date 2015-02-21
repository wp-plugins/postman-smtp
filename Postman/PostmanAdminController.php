<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "PostmanSendTestEmail.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanOAuthToken.php';
	require_once 'Postman-Wizard/PortTest.php';
	require_once 'Postman-Wizard/SmtpDiscovery.php';
	require_once 'PostmanInputSanitizer.php';
	require_once 'Postman-Connectors/PostmanImportableConfiguration.php';
	require_once 'PostmanOAuthHelper.php';
	
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
		const PORT_TEST_OPTIONS = 'postman_port_test_options';
		const PORT_TEST_SECTION = 'postman_port_test_section';
		
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
				$this->messageHandler->addMessage ( __ ( 'Settings saved.' ) );
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
			$transport = PostmanTransportDirectory::getInstance ()->getCurrentTransport ();
			$this->oauthScribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $transport, $this->options->getAuthorizationType (), $this->options->getHostname () );
			
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
				$this->registerAjaxHandler ( 'test_port', 'getAjaxPortStatus' );
				$this->registerAjaxHandler ( 'check_email', 'getAjaxHostnameByEmail' );
				$this->registerAjaxHandler ( 'get_redirect_url', 'getAjaxRedirectUrl' );
				$this->registerAjaxHandler ( 'send_test_email', 'sendTestEmailViaAjax' );
				$this->registerAjaxHandler ( 'get_configuration', 'getConfigurationViaAjax' );
			}
			
			// register content handlers
			$this->registerAdminMenu ( 'generateDefaultContent' );
			$this->registerAdminMenu ( 'addSetupWizardSubmenu' );
			$this->registerAdminMenu ( 'addConfigurationSubmenu' );
			$this->registerAdminMenu ( 'addEmailTestSubmenu' );
			$this->registerAdminMenu ( 'addPortTestSubmenu' );
			$this->registerAdminMenu ( 'addPurgeDataSubmenu' );
			
			// register action handlers
			$this->registerAdminPostAction ( self::PURGE_DATA_SLUG, 'handlePurgeDataAction' );
			$this->registerAdminPostAction ( self::REQUEST_OAUTH2_GRANT_SLUG, 'handleOAuthPermissionRequestAction' );
		}
		
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerAjaxHandler($actionName, $callbackName) {
			$fullname = 'wp_ajax_' . $actionName;
			// $this->logger->debug ( 'Registering ' . 'wp_ajax_' . $fullname . ' Ajax handler' );
			add_action ( $fullname, array (
					$this,
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
			return array_merge ( $links, $mylinks );
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
			
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options, $authorizationToken );
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
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $this->options, $this->authorizationToken );
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
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_check_timeout', Postman::POSTMAN_TCP_CONNECTION_TIMEOUT . '' );
			
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
			// this is for both the label and input of encryption type
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_encryption_group', '.input_encryption_type' );
			
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
			
			if (false && $this->options->isNew () && $this->importableConfiguration->isImportAvailable ()) {
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
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( 'Outgoing Mail Server (SMTP)', 'Configuration Input Field', 'postman-smtp' ), array (
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
			
			add_settings_field ( 'connection_timeout', _x ( 'Connection Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'connection_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( 'read_timeout', _x ( 'Read Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
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
			
			add_settings_field ( PostmanOptions::PRINT_ERRORS, _x ( 'Show Error Page', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'print_errors_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			// the Port Test section
			add_settings_section ( PostmanAdminController::PORT_TEST_SECTION, _x ( 'Port Connection Test', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'printPortTestSectionInfo' 
			), PostmanAdminController::PORT_TEST_OPTIONS );
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( 'Outgoing Mail Server (SMTP)', 'Configuration Input Field', 'postman-smtp' ), array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::PORT_TEST_OPTIONS, PostmanAdminController::PORT_TEST_SECTION );
			
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
			$this->logger->debug ( 'testing port success=' . $success );
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
				$message = sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanSmtpEngine::EOL, PostmanSmtpEngine::EOL, sprintf ( _x ( 'Sent by Postman v%s', 'Test Email Tagline' ), POSTMAN_PLUGIN_VERSION ) );
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
		function getConfigurationViaAjax() {
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
		 * This Ajax function retrieves the OAuth redirectUrl and help text for based on the SMTP hostname supplied
		 */
		function getAjaxRedirectUrl() {
			$hostname = $_POST ['hostname'];
			$transport = $_POST ['transport'];
			$this->logger->debug ( 'ajaxRedirectUrl hostname:' . $hostname );
			$this->logger->debug ( 'ajaxRedirectUrl transport:' . $transport );
			// don't care about what's in the database, i need a scribe based on the ajax parameter assuming this is OAUTH2
			$scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( PostmanTransportDirectory::getInstance()->getTransport($transport), PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, $hostname );
			if (isset ( $_POST ['referer'] )) {
				$this->logger->debug ( 'ajaxRedirectUrl referer:' . $_POST ['referer'] );
				// this must be wizard or config from an oauth-related change
				if ($_POST ['referer'] == 'wizard') {
					$avail [25] = filter_var ( $_POST ['avail25'], FILTER_VALIDATE_BOOLEAN );
					$avail [465] = filter_var ( $_POST ['avail465'], FILTER_VALIDATE_BOOLEAN );
					$avail [587] = filter_var ( $_POST ['avail587'], FILTER_VALIDATE_BOOLEAN );
				} else if ($_POST ['referer'] == 'manual_config') {
					$avail [25] = true;
					$avail [465] = true;
					$avail [587] = true;
				}
				$configureOAuth = false;
				$configureOAuth |= $_POST ['referer'] == 'manual_config';
				$configureOAuth |= $scribe->isOauthHost () && $avail [$scribe->getOAuthPort ()];
				if ($configureOAuth) {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
					$port = $scribe->getOAuthPort ();
					$encType = $scribe->getEncryptionType ();
				} else if ($avail [465]) {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
					$encType = PostmanOptions::ENCRYPTION_TYPE_SSL;
					$port = 465;
				} else if ($avail [587]) {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
					$encType = PostmanOptions::ENCRYPTION_TYPE_TLS;
					$port = 587;
				} else {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_NONE;
					$encType = PostmanOptions::ENCRYPTION_TYPE_NONE;
					$port = 25;
				}
				$response = array (
						'redirect_url' => $scribe->getCallbackUrl (),
						'callback_domain' => $scribe->getCallbackDomain (),
						'help_text' => $scribe->getOAuthHelp (),
						'client_id_label' => $scribe->getClientIdLabel (),
						'client_secret_label' => $scribe->getClientSecretLabel (),
						'redirect_url_label' => $scribe->getCallbackUrlLabel (),
						'callback_domain_label' => $scribe->getCallbackDomainLabel (),
						PostmanOptions::AUTHENTICATION_TYPE => $authType,
						PostmanOptions::ENCRYPTION_TYPE => $encType,
						PostmanOptions::PORT => $port,
						'success' => true 
				);
			} else {
				$response = array (
						'redirect_url' => $scribe->getCallbackUrl (),
						'help_text' => $this->getOAuthHelp ( $hostname ),
						'success' => true 
				);
			}
			wp_send_json ( $response );
		}
		
		/**
		 * Print the Transport section info
		 */
		public function printTransportSectionInfo() {
			print __ ( 'Select the transport and enter the sender\'s name and email address:' );
		}
		/**
		 * Print the Section text
		 */
		public function print_section_info() {
			print __ ( 'Enter your settings below:' );
		}
		/**
		 * Print the Section text
		 */
		public function printSmtpSectionInfo() {
			print __ ( 'Select the Authentication Type and enter the SMTP server hostname and port:' );
		}
		
		/**
		 * Print the Port Test text
		 */
		public function printPortTestSectionInfo() {
			print '<p>';
			print __ ( 'This test determines which ports are open for Postman to use.', 'postman-smtp' );
			print ' ';
			/* translators: where %d is an amount of time, in seconds */
			printf ( _n ( 'Each test is given %d second to complete.', 'Each test is given %d seconds to complete.', $this->options->getConnectionTimeout (), 'postman-smtp' ), $this->options->getConnectionTimeout () );
			print ' ';
			/* translators: where %d is an amount of time, in seconds */
			printf ( __ ( 'The entire test will take up to %d seconds.' ), ($this->options->getConnectionTimeout () * 3) );
			print ' ';
			print __ ( 'A <span style="color:red">Closed</span> port indicates either:', 'postman-smtp' );
			print '<ol>';
			printf ( '<li>%s</li>', __ ( 'Your host has placed a firewall between this site and the SMTP server or', 'postman-smtp' ) );
			printf ( '<li>%s</li>', __ ( 'The SMTP server has no service running on that port', 'postman-smtp' ) );
			printf ( '</ol></p><p><b>%s</b></p>', __ ( 'If the port you are trying to use is <span style="color:red">Closed</span>, Postman can not deliver mail. Contact your host to get the port opened.', 'postman-smtp' ) );
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
			print $this->oauthScribe->getOAuthHelp ();
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
			$authType = $this->options->getAuthorizationType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			printf ( '<option class="input_auth_type_none" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_NONE, $authType == PostmanOptions::AUTHENTICATION_TYPE_NONE ? 'selected="selected"' : '', _x ( 'None', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_plain" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_PLAIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN ? 'selected="selected"' : '', _x ( 'Plain', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_login" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_LOGIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN ? 'selected="selected"' : '', _x ( 'Login', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_crammd5" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5, $authType == PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 ? 'selected="selected"' : '', _x ( 'CRAMMD5', 'Authentication Type', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_oauth2" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 ? 'selected="selected"' : '', _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' ) );
			print '</select>';
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
			printf ( '<td><input type="radio" name="input_enc_type" class="input_enc_type_none" value="%s"/></td><td><label> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_NONE, _x ( 'None', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" name="input_enc_type" class="input_enc_type_ssl" value="%s"/></td><td> <label class="input_enc_type_ssl"> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_SSL, _x ( 'SSL', 'Encryption Type', 'postman-smtp' ) );
			printf ( '<td><input type="radio" name="input_enc_type" class="input_enc_type_tls" value="%s"/></td><td> <label> %s</label></td>', PostmanOptions::ENCRYPTION_TYPE_TLS, _x ( 'TLS', 'Encryption Type', 'postman-smtp' ) );
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
		public function port_callback($args) {
			printf ( '<input type="text" id="input_port" name="postman_options[port]" value="%s" class="required" %s/>', null !== $this->options->getPort () ? esc_attr ( $this->options->getPort () ) : '', isset ( $args ['style'] ) ? $args ['style'] : '' );
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
			// test features
			$sslRequirement = extension_loaded ( 'openssl' );
			$splAutoloadRegisterRequirement = function_exists ( 'spl_autoload_register' );
			$phpVersionRequirement = PHP_VERSION_ID >= 50300;
			$arrayObjectRequirement = class_exists ( 'ArrayObject' );
			$getmxrrRequirement = function_exists ( 'getmxrr' );
			$displayErrors = ini_get ( 'display_errors' );
			$errorReporting = ini_get ( 'error_reporting' );
			
			// Set class property
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<p><span style="color:green;padding:2px 5px; font-size:1.2em">%s</span></p>', __ ( 'Postman is configured.', 'postman-smtp' ) );
				if ($this->options->isAuthTypeOAuth2 ()) {
					$authDesc = _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' );
				} else if ($this->options->isAuthTypeNone ()) {
					$authDesc = _x ( 'no', 'Authentication Type', 'postman-smtp' );
				} else {
					/* translators: where %s is the Authentication Type (e.g. plain, login or crammd5) */
					$authDesc = sprintf ( _x ( 'Password (%s)', 'Authentication Type', 'postman-smtp' ), $this->options->getAuthorizationType () );
				}
				/* translators: where %1$s is the SMTP server and %2$s is the Authentication Type (e.g. Postman will send mail via smtp.gmail.com:465 using OAuth 2.0 authentication.) */
				$deliveryDetails = PostmanTransportDirectory::getInstance ()->getCurrentTransport ()->getDeliveryDetails ();
				printf ( '<p style="margin:0 10px"><span>%s</span></p>', sprintf ( __ ( 'Postman will send mail via %1$s using %2$s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails . '</b>', '<b>' . $authDesc . '</b>' ) );
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
					printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'Let\'s get started! All users are strongly encouraged to start by <a href="%s">running the Setup Wizard</a>.', 'postman-smtp' ), $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ) ) );
					if ($this->importableConfiguration->isImportAvailable ()) {
						/* translators: where %s is the URL of the Manual Configuration */
						printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'However, if you wish, Postman can <a href="%s">import your SMTP configuration</a> from another plugin. You can run the Wizard later if you need to.', 'postman-smtp' ), $this->getPageUrl ( self::CONFIGURATION_SLUG ) ) );
					}
				}
			}
			
			if (true || ! $sslRequirement || ! $splAutoloadRegisterRequirement || ! $arrayObjectRequirement) {
				// printf ( '<div style="padding: 10px;"><b style="color: red">%s</b><ul>', __ ( 'Your system seems to be missing one or more pre-requisites - something may fail:', 'postman-smtp' ) );
				printf ( '<div style="padding: 10px;"><b style="color: red">%s</b><ul>', __ ( 'System Info:', 'postman-smtp' ) );
				/* translators: where %s is the PHP version */
				printf ( '<li>PHP v5.3: %s (%s)</li>', ($phpVersionRequirement ? __ ( 'Yes', 'postman-smtp' ) : __ ( 'No', 'postman-smtp' )), PHP_VERSION );
				printf ( '<li>PHP SSL Extension: %s</li>', ($sslRequirement ? __ ( 'Yes', 'postman-smtp' ) : __ ( 'No', 'postman-smtp' )) );
				printf ( '<li>PHP spl_autoload_register: %s</li>', ($splAutoloadRegisterRequirement ? __ ( 'Yes', 'postman-smtp' ) : __ ( 'No', 'postman-smtp' )) );
				printf ( '<li>PHP ArrayObject: %s</li>', ($arrayObjectRequirement ? __ ( 'Yes', 'postman-smtp' ) : __ ( 'No', 'postman-smtp' )) );
				printf ( '<li>PHP display_errors: %s</li>', $displayErrors );
				printf ( '<li>PHP errorReporting: %s</li>', $errorReporting );
				printf ( '<li>WordPress WP_DEBUG: %s</li>', WP_DEBUG );
				printf ( '<li>WordPress WP_DEBUG_LOG: %s</li>', WP_DEBUG_LOG );
				printf ( '<li>WordPress WP_DEBUG_DISPLAY: %s</li>', WP_DEBUG_DISPLAY );
				print '<ul></div>';
			}
			print '</div>';
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
			$authType = $this->options->getAuthorizationType ();
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
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			print '<form id="port_test_form_id" method="post">';
			do_settings_sections ( PostmanAdminController::PORT_TEST_OPTIONS );
			// This prints out all hidden setting fields
			submit_button ( _x ( 'Begin Test', 'Button Label', 'postman-smtp' ), 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table">';
			printf ( '<tr><td class="port">Port 25</td><td id="port-test-port-25">%s</td>', _x ( 'Unknown', 'Port Test Status', 'postman-smtp' ) );
			printf ( '<tr><td class="port">Port 465</td><td id="port-test-port-465">%s</td>', _x ( 'Unknown', 'Port Test Status', 'postman-smtp' ) );
			printf ( '<tr><td class="port">Port 587</td><td id="port-test-port-587">%s</td>', _x ( 'Unknown', 'Port Test Status', 'postman-smtp' ) );
			print '</table>';
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
			if ($this->options->isRequestOAuthPermissionAllowed ()) {
				printf ( '<li><a href="%s" class="welcome-icon send-test-email">%s</a></li>', $this->getActionUrl ( self::REQUEST_OAUTH2_GRANT_SLUG ), $this->oauthScribe->getRequestPermissionLinkText () );
			} else {
				printf ( '<li><div class="welcome-icon send_test_emaail">%s</div></li>', $this->oauthScribe->getRequestPermissionLinkText () );
			}
			printf ( '<li><a href="%s" class="welcome-icon oauth-authorize">%s</a></li>', $this->getPageUrl ( self::PURGE_DATA_SLUG ), _x ( 'Delete plugin settings', 'Main Menu', 'postman-smtp' ) );
			print '</ul>';
			print '</div>';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', _x ( 'Troubleshooting', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send_test_email">%s</a></li>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ), _x ( 'Send a Test Email', 'Main Menu', 'postman-smtp' ) );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', _x ( 'Send a Test Email', 'Main Menu', 'postman-smtp' ) );
			}
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::PORT_TEST_SLUG ), _x ( 'Run a Port Connection Test', 'Main Menu', 'postman-smtp' ) );
			printf ( '<li><a href="https://wordpress.org/plugins/postman-smtp/other_notes/" class="welcome-icon postman_support">%s</a></li>', _x ( 'Online Support', 'Main Menu', 'postman-smtp' ) );
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
			printf ( '<h3></h3>', _x ( 'Postman Setup Wizard', 'Page Title', 'postman-smtp' ) );
			print '<form id="postman_wizard" method="post" action="options.php">';
			printf ( '<input type="hidden" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
			printf ( '<input type="hidden" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			
			// Wizard Step 1
			printf ( '<h5>%s</h5>', _x ( 'Sender Address Details', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Sender Address Details', 'Wizard Step Title', 'postman-smtp' ) );
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
			printf ( '<legend>%s</legend>', _x ( 'SMTP Server Hostname', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'This is the server that Postman will use to deliver your mail.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->hostname_callback ();
			print '</fieldset>';
			
			// Wizard Step 3
			printf ( '<h5>%s</h5>', _x ( 'SMTP Server Port', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'SMTP Server Port', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Your email provider will dictate which port to use.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Port', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->port_callback ( array (
					'style' => 'style="display:none"' 
			) );
			print '<table>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 25', 'postman-smtp' ) );
			print '<td><input type="radio" id="wizard_port_25" name="wizard-port" value="25" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_25_status">%s</td>', _x ( 'Unknown', 'TCP Port Status', 'postman-smtp' ) );
			print '</tr>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 465', 'postman-smtp' ) );
			print '<td><input type="radio" id="wizard_port_465" name="wizard-port" value="465" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_465_status">%s</td>', _x ( 'Unknown', 'TCP Port Status', 'postman-smtp' ) );
			print '</tr>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 587', 'postman-smtp' ) );
			print '<td><input type="radio" id="wizard_port_587" name="wizard-port" value="587" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_587_status">%s</td>', _x ( 'Unknown', 'TCP Port Status', 'postman-smtp' ) );
			print '</tr>';
			print '</table>';
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
			printf ( '<p class="port-explanation-ssl">%s</p>', __ ( 'Choose Login authentication unless you\'ve been instructed otherwise. Your username is most likely your email address.', 'postman-smtp' ) );
			printf ( '<label class="input_authorization_type" for="auth_type">%s</label>', _x ( 'Authentication', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->authentication_type_callback ();
			print '<br />';
			printf ( '<label class="input_encryption_type" for="enc_type">%s</label>', _x ( 'Encryption', 'Configuration Input Field', 'postman-smtp' ) );
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
			printf ( '<legend>%s</legend>', __ ( 'Choose the Recipient', 'postman-smtp' ) );
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
			printf ( '<p><label>%s</label></p>', __ ( 'Status Message' ) );
			print '<textarea id="postman_test_message_error_message" readonly="readonly" cols="65" rows="2"></textarea>';
			if (PostmanTransportDirectory::getInstance ()->getCurrentTransport ()->isTranscriptSupported ()) {
				printf ( '<p><label for="postman_test_message_transcript">%s</label></p>', __ ( 'SMTP Session Transcript', 'postman-smtp' ) );
				print '<textarea readonly="readonly" id="postman_test_message_transcript" cols="65" rows="10"></textarea>';
			}
			print '</section>';
			print '</fieldset>';
			print '</form>';
		}
	}
}