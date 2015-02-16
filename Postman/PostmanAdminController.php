<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "PostmanSendTestEmail.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanAuthorizationToken.php';
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
		
		// page titles
		const NAME = 'Postman SMTP';
		const PAGE_TITLE = 'Postman Settings';
		const MENU_TITLE = 'Postman SMTP';
		
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
		private $options;
		private $testOptions;
		private $importableConfiguration;
		
		/**
		 * Start up
		 */
		public function __construct($basename, PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->messageHandler = $messageHandler;
			
			//
			$this->oauthScribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $this->options->getHostname () );
			
			// import from other plugins
			$this->importableConfiguration = new PostmanImportableConfiguration ();
			
			if (isset ( $_POST ['purge_auth_token'] )) {
				// this means the wizard completed successfully and we should destroy the stored auth token
				$this->logger->debug ( 'Found purge auth token key' );
				delete_option ( PostmanAuthorizationToken::OPTIONS_NAME );
			}
			
			$session = PostmanSession::getInstance ();
			$this->logger->debug ( sprintf ( 'sanitize status: %s', $session->getAction () ) );
			
			// check if the user saved data, and if validation was successful
			if ($session->getAction () == PostmanInputSanitizer::VALIDATION_SUCCESS) {
				$session->unsetAction ();
				$this->registerInitFunction ( 'handleSuccessfulSave' );
				$this->messageHandler->addMessage ( 'Settings saved.' );
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
			
			// Adds "Settings" link to the plugin action page
			add_filter ( 'plugin_action_links_' . $basename, array (
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
			$this->logger->debug ( 'Registering ' . 'wp_ajax_' . $fullname . ' Ajax handler' );
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
			$this->logger->debug ( 'Registering admin menu ' . $callbackName );
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
			$this->logger->debug ( 'Registering ' . $actionName . ' Action Post handler' );
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
					sprintf ( '<a href="%s">%s</a>', esc_url ( POSTMAN_HOME_PAGE_ABSOLUTE_URL ), __ ( 'Settings' ) ) 
			);
			return array_merge ( $links, $mylinks );
		}
		
		/**
		 * Add options page
		 */
		public function generateDefaultContent() {
			// This page will be under "Settings"
			$page = add_options_page ( __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::POSTMAN_MENU_SLUG, array (
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
			$page = add_submenu_page ( null, __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::CONFIGURATION_SLUG, array (
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
			$page = add_submenu_page ( null, __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::CONFIGURATION_WIZARD_SLUG, array (
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
			$page = add_submenu_page ( null, __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::EMAIL_TEST_SLUG, array (
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
			$page = add_submenu_page ( null, __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::PORT_TEST_SLUG, array (
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
			$page = add_submenu_page ( null, __ ( 'Postman Settings', 'Page Title' ), PostmanAdminController::MENU_TITLE, 'manage_options', self::PURGE_DATA_SLUG, array (
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
			delete_option ( PostmanAuthorizationToken::OPTIONS_NAME );
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
					$this->messageHandler->addError ( __ ( 'Your email provider did not grant Postman permission. Try again.' ) );
				}
			} catch ( PostmanStateIdMissingException $e ) {
				$this->messageHandler->addError ( __ ( 'The grant code from Google had no accompanying state and may be a forgery' ) );
			} catch ( Exception $e ) {
				$logger->error ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				$this->messageHandler->addError ( sprintf ( __ ( 'Error authenticating with this Client ID - please create a new one. [%s]' ), '<em>' . $e->getMessage () . '</em>' ) );
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
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_check_timeout', PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT . '' );
			
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
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_login', PostmanOptions::AUTHENTICATION_TYPE_LOGIN );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_plain', PostmanOptions::AUTHENTICATION_TYPE_PLAIN );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_auth_crammd5', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 );
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
			add_settings_section ( PostmanAdminController::SMTP_SECTION, _x ( __ ( 'SMTP Settings' ), 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printSmtpSectionInfo' 
			), PostmanAdminController::SMTP_OPTIONS );
			
			if ($this->options->isNew () && $this->importableConfiguration->isImportAvailable ()) {
				add_settings_field ( 'import_configuration', _x ( 'Import from Plugin', 'Configuration Input Field' ), array (
						$this,
						'import_configuration_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			}
			
			add_settings_field ( PostmanOptions::AUTHENTICATION_TYPE, _x ( __ ( 'Authentication' ), 'Configuration Input Field' ), array (
					$this,
					'authentication_type_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::SENDER_NAME, _x ( __ ( 'Sender Name' ), 'Configuration Input Field' ), array (
					$this,
					'sender_name_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::SENDER_EMAIL, _x ( __ ( 'Sender Email Address' ), 'Configuration Input Field' ), array (
					$this,
					'sender_email_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( __ ( 'Outgoing Mail Server (SMTP)' ), 'Configuration Input Field' ), array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::PORT, _x ( __ ( 'Port' ), 'Configuration Input Field' ), array (
					$this,
					'port_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, _x ( __ ( 'Authentication Settings' ), 'Configuration Section' ), array (
					$this,
					'printBasicAuthSectionInfo' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( __ ( 'Encryption' ), 'Configuration Input Field' ), array (
					$this,
					'encryption_type_for_password_section_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_USERNAME, _x ( __ ( 'Username' ), 'Configuration Input Field' ), array (
					$this,
					'basic_auth_username_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_PASSWORD, _x ( __ ( 'Password' ), 'Configuration Input Field' ), array (
					$this,
					'basic_auth_password_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			// the OAuth section
			add_settings_section ( PostmanAdminController::OAUTH_SECTION, _x ( __ ( 'Authentication Settings' ), 'Configuration Section' ), array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::OAUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( __ ( 'Encryption' ), 'Configuration Input Field' ), array (
					$this,
					'encryption_type_for_oauth2_section_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( 'callback_domain', '<span id="callback_domain">' . _x ( $this->oauthScribe->getCallbackDomainLabel () . '</span>', 'Configuration Input Field' ), array (
					$this,
					'callback_domain_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( 'redirect_url', '<span id="redirect_url">' . _x ( $this->oauthScribe->getCallbackUrlLabel () . '</span>', 'Configuration Input Field' ), array (
					$this,
					'redirect_url_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_ID, _x ( $this->oauthScribe->getClientIdLabel (), 'Configuration Input Field' ), array (
					$this,
					'oauth_client_id_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_SECRET, _x ( $this->oauthScribe->getClientSecretLabel (), 'Configuration Input Field' ), array (
					$this,
					'oauth_client_secret_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			// the Advanced section
			add_settings_section ( PostmanAdminController::ADVANCED_SECTION, _x ( __ ( 'Advanced Settings' ), 'Configuration Section' ), array (
					$this,
					'printAdvancedSectionInfo' 
			), PostmanAdminController::ADVANCED_OPTIONS );
			
			add_settings_field ( 'connection_timeout', _x ( __ ( 'Connection Timeout (sec)' ), 'Configuration Input Field' ), array (
					$this,
					'connection_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( 'read_timeout', _x ( __ ( 'Read Timeout (sec)' ), 'Configuration Input Field' ), array (
					$this,
					'read_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::REPLY_TO, _x ( __ ( 'Reply-To Email Address' ), 'Configuration Input Field' ), array (
					$this,
					'reply_to_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::LOG_LEVEL, _x ( 'Log Level', 'Configuration Input Field' ), array (
					$this,
					'log_level_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::PRINT_ERRORS, _x ( 'Show Error Page', 'Configuration Input Field' ), array (
					$this,
					'print_errors_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			// the Port Test section
			add_settings_section ( PostmanAdminController::PORT_TEST_SECTION, _x ( 'Port Connection Test', 'Configuration Input Field' ), array (
					$this,
					'printPortTestSectionInfo' 
			), PostmanAdminController::PORT_TEST_OPTIONS );
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( 'Outgoing Mail Server (SMTP)', 'Configuration Input Field' ), array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::PORT_TEST_OPTIONS, PostmanAdminController::PORT_TEST_SECTION );
			
			// the Test Email section
			register_setting ( 'email_group', PostmanAdminController::TEST_OPTIONS, array (
					$sanitizer,
					'testSanitize' 
			) );
			
			add_settings_section ( 'TEST_EMAIL', _x ( 'Test Your Setup', 'Configuration Section' ), array (
					$this,
					'printTestEmailSectionInfo' 
			), PostmanAdminController::POSTMAN_TEST_SLUG );
			
			add_settings_field ( 'test_email', _x ( 'Recipient Email Address', 'Configuration Input Field' ), array (
					$this,
					'test_email_callback' 
			), PostmanAdminController::POSTMAN_TEST_SLUG, 'TEST_EMAIL' );
			
			// the Purge Data section
			add_settings_section ( 'PURGE_DATA', _x ( 'Delete plugin settings', 'Configuration Section' ), array (
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
			$emailTester = new PostmanSendTestEmailController ();
			$success = $emailTester->simeplSend ( $this->options, $this->authorizationToken, $email, $this->oauthScribe->getServiceName () );
			$response = array (
					'message' => $emailTester->getMessage (),
					'transcript' => $emailTester->getTranscript (),
					'success' => $success 
			);
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
			$this->logger->debug ( 'ajaxRedirectUrl hostname:' . $hostname );
			// don't care about what's in the database, i need a scribe based on the ajax parameter
			$scribe = PostmanOAuthScribeFactory::getInstance ()->createPostmanOAuthScribe ( $hostname );
			if (isset ( $_POST ['referer'] )) {
				$this->logger->debug ( 'ajaxRedirectUrl referer:' . $_POST ['referer'] );
				// this must be wizard or config from an oauth-related change
				if ($_POST ['referer'] == 'wizard') {
					$avail25 = $_POST ['avail25'];
					$avail465 = $_POST ['avail465'];
					$avail587 = $_POST ['avail587'];
				} else if ($_POST ['referer'] == 'manual_config') {
					$avail25 = true;
					$avail465 = true;
					$avail587 = true;
				}
				if ($scribe->isOauthHost () || $_POST ['referer'] == 'manual_config') {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
					$port = $scribe->getOAuthPort ();
					$encType = $scribe->getEncryptionType ();
				} else if ($avail465) {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
					$encType = PostmanOptions::ENCRYPTION_TYPE_SSL;
					$port = 465;
				} else if ($avail587) {
					$authType = PostmanOptions::AUTHENTICATION_TYPE_LOGIN;
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
		 * Print the Section text
		 */
		public function print_section_info() {
			print 'Enter your settings below:';
		}
		/**
		 * Print the Section text
		 */
		public function printSmtpSectionInfo() {
			print '';
		}
		
		/**
		 * Print the Port Test text
		 */
		public function printPortTestSectionInfo() {
			print '<p>';
			print __ ( 'This test determines which ports are open for Postman to use.' );
			print ' ';
			printf ( _n ( 'Each test is given %d second to complete.', 'Each test is given %d seconds to complete.', $this->options->getConnectionTimeout () ), $this->options->getConnectionTimeout () );
			print ' ';
			printf ( 'The entire test will take up to %d seconds.', ($this->options->getConnectionTimeout () * 3) );
			print ' ';
			print __ ( 'A <span style="color:red">Closed</span> port indicates either:' );
			print '<ol>';
			printf ( '<li>%s</li>', __ ( 'Your host has placed a firewall between this site and the SMTP server or' ) );
			printf ( '<li>%s</li>', __ ( 'The SMTP server has no service running on that port' ) );
			printf ( '</ol></p><p><b>%s</b></p>', __ ( 'If the port you are trying to use is  <span style="color:red">Closed</span>, Postman can not deliver mail. Contact your host to get the port opened.' ) );
		}
		
		/**
		 * Print the Section text
		 */
		public function printBasicAuthSectionInfo() {
			print __ ( 'Enter the username (email address) and password you use to send email' );
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
			print __ ( 'You will receive an email from Postman with the subject "WordPress Postman SMTP Test."' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printPurgeDataSectionInfo() {
			printf ( '<p><span>%s.</span></p><p><span>%s</span></p>', __ ( 'This will purge all of Postman\'s settings, including SMTP server info, username/password and OAuth Credentials' ), __ ( 'Are you sure?' ) );
		}
		
		/**
		 * Print the Section text
		 */
		public function printAdvancedSectionInfo() {
			print __ ( 'Increase the read timeout if your host is intermittenly failing to send mail. Be careful, this also correlates to how long your user must wait if your mail server is unreachable.' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function authentication_type_callback() {
			$authType = $this->options->getAuthorizationType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			printf ( '<option class="input_auth_type_none" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_NONE, $authType == PostmanOptions::AUTHENTICATION_TYPE_NONE ? 'selected="selected"' : '', __ ( 'None', 'Authentication Type' ) );
			printf ( '<option class="input_auth_type_plain" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_PLAIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN ? 'selected="selected"' : '', __ ( 'Plain', 'Authentication Type' ) );
			printf ( '<option class="input_auth_type_login" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_LOGIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN ? 'selected="selected"' : '', __ ( 'Login', 'Authentication Type' ) );
			printf ( '<option class="input_auth_type_crammd5" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5, $authType == PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 ? 'selected="selected"' : '', __ ( 'CRAMMD5', 'Authentication Type' ) );
			printf ( '<option class="input_auth_type_oauth2" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 ? 'selected="selected"' : '', __ ( 'OAuth 2.0', 'Authentication Type' ) );
			print '</select>';
		}
		/**
		 * Get the settings option array and print one of its values
		 */
		public function encryption_type_for_password_section_callback() {
			$this->encryption_type_callback ( 'password' );
		}
		public function encryption_type_for_oauth2_section_callback() {
			$this->encryption_type_callback ( PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
		}
		public function encryption_type_callback($section) {
			$encType = $this->options->getEncryptionType ();
			print '<select id="input_enc_type_' . $section . '" class="input_encryption_type" name="postman_options[enc_type]">';
			printf ( '<option class="input_enc_type_none" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_NONE, $encType == PostmanOptions::ENCRYPTION_TYPE_NONE ? 'selected="selected"' : '', __ ( 'None', 'Encryption Type' ) );
			printf ( '<option class="input_enc_type_none" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_SSL, $encType == PostmanOptions::ENCRYPTION_TYPE_SSL ? 'selected="selected"' : '', __ ( 'SSL', 'Encryption Type' ) );
			printf ( '<option class="input_enc_type_none" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_TLS, $encType == PostmanOptions::ENCRYPTION_TYPE_TLS ? 'selected="selected"' : '', __ ( 'TLS', 'Encryption Type' ) );
			print '</select>';
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
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function callback_domain_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" readonly="readonly" id="input_oauth_callback_domain" value="%s" size="60"/>', $this->oauthScribe->getCallbackDomain () );
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
			printf ( '<textarea type="text" onClick="this.setSelectionRange(0, this.value.length)" style="overflow:hidden;resize:none" id="oauth_client_id" name="postman_options[oauth_client_id]" cols="60" class="required">%s</textarea>', null !== $this->options->getClientId () ? esc_attr ( $this->options->getClientId () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" onClick="this.setSelectionRange(0, this.value.length)" style="overflow:hidden;resize:none" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="60" class="required"/>', null !== $this->options->getClientSecret () ? esc_attr ( $this->options->getClientSecret () ) : '' );
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
			
			// Set class property
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<p><span style="color:green;padding:2px 5px; font-size:1.2em">%s</span></p>', __ ( 'Postman is configured.' ) );
				if ($this->options->isAuthTypeOAuth2 ()) {
					$authDesc = __ ( 'OAuth 2.0', 'Authentication Type' );
				} else if ($this->options->isAuthTypeNone ()) {
					$authDesc = __ ( 'no', 'Authentication Type' );
				} else {
					$authDesc = sprintf ( __ ( 'Password (%s)', 'Authentication Type' ), $this->options->getAuthorizationType () );
				}
				printf ( '<p style="margin:0 10px"><span>%s</span></p>', sprintf ( __ ( 'Postman will send mail via %1$s using %2$s authentication.' ), '<b>' . $this->options->getHostname () . ':' . $this->options->getPort () . '</b>', '<b>' . $authDesc . '</b>' ) );
				if ($this->options->isAuthTypeOAuth2 ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins or themes may override the sender name only</em>.' ) );
				} else if ($this->options->isAuthTypePassword ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins or themes may override the sender name and email address causing rejection with some email services, such as Yahoo Mail. If you experience problems, try leaving the sender email address empty in these plugins or themes.</em>' ) );
				}
			} else {
				printf ( '<p><span style="color:red; padding:2px 5px; font-size:1.1em">%s</span></p>', __ ( 'Status: Postman is not sending mail.' ) );
				if ($this->options->isNew ()) {
					printf ( '<h3>%s</h3>', __ ( 'Thank-you for choosing Postman!' ) );
					printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'Let\'s get started! All users are strongly encouraged to start by <a href="%s">running the Setup Wizard</a>.' ), $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ) ) );
					if ($this->importableConfiguration->isImportAvailable ()) {
						printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'However, if you wish, Postman can <a href="%s">import your SMTP configuration</a> from another plugin. You can run the Wizard later if you need to.' ), $this->getPageUrl ( self::CONFIGURATION_SLUG ) ) );
					}
				}
			}
			
			if (! $sslRequirement || ! $splAutoloadRegisterRequirement || ! $arrayObjectRequirement) {
				printf ( '<div style="padding: 10px;"><b style="color: red">%s:</b><ul>', __ ( 'Your system seems to be missing one or more pre-requisites - something may fail:' ) );
				printf ( '<li>PHP v5.3: %s</li>', ($phpVersionRequirement ? __ ( 'Yes' ) : sprintf ( __ ( 'No (%s)' ), PHP_VERSION )) );
				printf ( '<li>SSL Extension: %s</li>', ($sslRequirement ? __ ( 'Yes' ) : __ ( 'No' )) );
				printf ( '<li>spl_autoload_register: %s</li>', ($splAutoloadRegisterRequirement ? __ ( 'Yes' ) : __ ( 'No' )) );
				printf ( '<li>ArrayObject: %s</li>', ($arrayObjectRequirement ? __ ( 'Yes' ) : __ ( 'No' )) );
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
			do_settings_sections ( PostmanAdminController::SMTP_OPTIONS );
			$authType = $this->options->getAuthorizationType ();
			print '<div id="smtp_section">';
			do_settings_sections ( PostmanAdminController::BASIC_AUTH_OPTIONS );
			print ('</div>') ;
			print '<div id="oauth_section">';
			do_settings_sections ( PostmanAdminController::OAUTH_OPTIONS );
			print ('</div>') ;
			printf ( '<p id="advanced_options_configuration_display" class="fineprint"><span><a href="#">%s</a></span></p>', __ ( 'Show Advanced Settings', 'Configuration Section' ) );
			print '<div id="advanced_options_configuration_section">';
			do_settings_sections ( PostmanAdminController::ADVANCED_OPTIONS );
			print ('</div>') ;
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
			submit_button ( __ ( 'Delete All Data', 'Button Label' ), 'delete', 'submit', true, 'style="background-color:red;color:white"' );
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
			submit_button ( __ ( 'Begin Test', 'Button Label' ), 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table">';
			printf ( '<tr><td class="port">Port 25</td><td id="port-test-port-25">%s</td>', __ ( 'Unknown' ) );
			printf ( '<tr><td class="port">Port 465</td><td id="port-test-port-465">%s</td>', __ ( 'Unknown' ) );
			printf ( '<tr><td class="port">Port 587</td><td id="port-test-port-587">%s</td>', __ ( 'Unknown' ) );
			print '</table>';
			print '</div>';
		}
		
		/**
		 */
		private function displayTopNavigation() {
			screen_icon ();
			printf ( '<h2>%s</h2>', __ ( 'Postman Settings', 'Page Title' ) );
			print '<div id="welcome-panel" class="welcome-panel">';
			print '<div class="welcome-panel-content">';
			print '<div class="welcome-panel-column-container">';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', __ ( 'Get Started', 'Main Menu' ) );
			printf ( '<a class="button button-primary button-hero" href="%s">%s</a>', $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ), __ ( 'Start the Wizard', 'Button Label' ) );
			printf ( '<p class="">or, <a href="%s">%s</a>. </p>', $this->getPageUrl ( self::CONFIGURATION_SLUG ), __ ( 'configure manually', 'Main Menu' ) );
			print '</div>';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', __ ( 'Actions', 'Main Menu' ) );
			print '<ul>';
			if ($this->options->isRequestOAuthPermissionAllowed ()) {
				printf ( '<li><a href="%s" class="welcome-icon send-test-email">%s</a></li>', $this->getActionUrl ( self::REQUEST_OAUTH2_GRANT_SLUG ), $this->oauthScribe->getRequestPermissionLinkText () );
			} else {
				printf ( '<li><div class="welcome-icon send_test_emaail">%s</div></li>', $this->oauthScribe->getRequestPermissionLinkText () );
			}
			printf ( '<li><a href="%s" class="welcome-icon oauth-authorize">%s</a></li>', $this->getPageUrl ( self::PURGE_DATA_SLUG ), __ ( 'Delete plugin settings', 'Main Menu' ) );
			print '</ul>';
			print '</div>';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', __ ( 'Troubleshooting', 'Main Menu' ) );
			print '<ul>';
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send_test_email">%s</a></li>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ), __ ( 'Send a Test Email', 'Main Menu' ) );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', __ ( 'Send a Test Email', 'Main Menu' ) );
			}
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::PORT_TEST_SLUG ), __ ( 'Run a Port Connection Test', 'Main Menu' ) );
			printf ( '<li><a href="https://wordpress.org/plugins/postman-smtp/other_notes/" class="welcome-icon postman_support">%s</a></li>', __ ( 'Online Support', 'Main Menu' ) );
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
			printf ( '<h3></h3>', __ ( 'Postman Setup Wizard', 'Page Title' ) );
			print '<form id="postman_wizard" method="post" action="options.php">';
			print '<input type="hidden" name="purge_auth_token" value="purge_auth_token" />';
			printf ( '<input type="hidden" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
			printf ( '<input type="hidden" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			
			// Wizard Step 1
			printf ( '<h5>%s</h5>', __ ( 'Sender Address Details' ), 'Wizard Step Title' );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Enter your Email Address' ), 'Wizard Step 1' );
			printf ( '<p>%s</p>', __ ( 'Let\'s begin! Please enter the email address and name you\'d like to send mail from.' ) );
			printf ( '<p>%s</p>', __ ( '<p>Please note that to combat Spam, many email services will <em>not</em> let you send from an e-mail address that is not your own.</p>' ) );
			printf ( '<label for="postman_options[sender_email]">%s</label>', __ ( 'Sender Email Address', 'Configuration Input Field' ) );
			print $this->sender_email_callback ();
			printf ( '<label for="postman_options[sender_name]">%s</label>', __ ( 'Sender Email Name', 'Configuration Input Field' ) );
			print $this->sender_name_callback ();
			print '</fieldset>';
			
			// Wizard Step 2
			printf ( '<h5>%s</h5>', __ ( 'SMTP Server Hostname', 'Wizard Step Title' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Enter your SMTP hostname.', 'Wizard Step 2' ) );
			printf ( '<p>%s</p>', __ ( 'This is the server that Postman will use to deliver your mail.' ) );
			printf ( '<label for="hostname">%s</label>', __ ( 'SMTP Server Hostname', 'Configuration Input Field' ) );
			print $this->hostname_callback ();
			print '</fieldset>';
			
			// Wizard Step 3
			printf ( '<h5>%s</h5>', __ ( 'SMTP Server Port', 'Wizard Step Title' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Choose an SMTP port', 'Wizard Step 3' ) );
			printf ( '<p>%s</p>', __ ( 'Your email provider will dictate which port to use.' ) );
			printf ( '<label for="hostname">%s</label>', __ ( 'SMTP Server Port', 'Configuration Input Field' ) );
			print $this->port_callback ( array (
					'style' => 'style="display:none"' 
			) );
			print '<table>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 25' ) );
			print '<td><input type="radio" id="wizard_port_25" name="wizard-port" value="25" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_25_status">%s</td>', __ ( 'Unknown', 'TCP Port Status' ) );
			print '</tr>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 465' ) );
			print '<td><input type="radio" id="wizard_port_465" name="wizard-port" value="465" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_465_status">%s</td>', __ ( 'Unknown', 'TCP Port Status' ) );
			print '</tr>';
			print '<tr>';
			printf ( '<td><span>%s</span></td>', __ ( 'Port 587' ) );
			print '<td><input type="radio" id="wizard_port_587" name="wizard-port" value="587" class="required" style="margin-top: 0px" /></td>';
			printf ( '<td id="wizard_port_587_status">%s</td>', __ ( 'Unknown', 'TCP Port Status' ) );
			print '</tr>';
			print '</table>';
			print '</fieldset>';
			
			// Wizard Step 4
			printf ( '<h5>%s</h5>', __ ( 'Authentication', 'Wizard Step Title' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Setup Authentication', 'Wizard Step 4' ) );
			
			print '<section class="wizard-auth-oauth2">';
			printf ( '<p id="%s</p>', __ ( 'wizard_oauth2_help">Help.' ) );
			printf ( '<label id="callback_domain" for="callback_domain">%s</label>', $this->oauthScribe->getCallbackDomainLabel );
			print '<br />';
			print $this->callback_domain_callback ();
			print '<br />';
			printf ('<label id="redirect_url" for="redirect_uri">%s</label>', $this->oauthScribe->getCallbackUrlLabel());
			print '<br />';
			print $this->redirect_url_callback ();
			print '<br />';
			print $this->encryption_type_for_oauth2_section_callback ();
			printf ( '<label id="client_id" for="client_id">%s</label>', $this->oauthScribe->getClientIdLabel () );
			print '<br />';
			print $this->oauth_client_id_callback ();
			print '<br />';
			printf ( '<label id="client_secret" for="client_id">%s</label>', $this->oauthScribe->getClientSecretLabel () );
			print '<br />';
			print $this->oauth_client_secret_callback ();
			print '<br />';
			print '</section>';
			
			print '<section class="wizard-auth-basic">';
			printf ( '<p class="port-explanation-ssl">%s</p>', __ ( 'Choose Login authentication unless you\'ve been instructed otherwise. Your username is most likely your email address.' ) );
			printf ( '<label class="input_authorization_type" for="auth_type">%s</label>', __ ( 'Authentication Type', 'Configuration Input Field' ) );
			print $this->authentication_type_callback ();
			printf ( '<label class="input_encryption_type" for="enc_type">%s</label>', __ ( 'Encryption Type', 'Configuration Input Field' ) );
			print $this->encryption_type_for_password_section_callback ();
			print '<br />';
			printf ( '<label for="username">%s</label>', __ ( 'Username', 'Configuration Input Field' ) );
			print $this->basic_auth_username_callback ();
			printf ( '<label for="password">%s</label>', __ ( 'Password', 'Configuration Input Field' ) );
			print $this->basic_auth_password_callback ();
			print '</section>';

			print '</fieldset>';
			
			// Wizard Step 5
			printf ( '<h5>%s</h5>', __ ( 'Finish' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'All done!' ) );
			print '<section>';
			printf ( '<p>%s</p>', __ ( 'Click Finish to save these settings. Then:' ) );
			print '<ul style="margin-left: 20px">';
			printf ( '<li class="wizard-auth-oauth2">%s</li>', __ ( 'Request permission from the Email Provider to allow Postman to send email and' ) );
			printf ( '<li>%s</li>', __ ( 'Send yourself a Test Email to make sure everything is working!' ) );
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
			printf ( '<h3>%s</h3>', __ ( 'Send a Test Email', 'Page Title' ) );
			printf ( '<form id="postman_test_email_wizard" method="post" action="%s">', POSTMAN_HOME_PAGE_ABSOLUTE_URL );
			
			// Step 1
			printf ( '<h5>%s</h5>', __ ( 'Choose the Recipient' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Input Email Address' ) );
			printf ( '<p>%s', __ ( 'This utility allows you to send an email message for testing.' ) );
			print ' ';
			printf ( '%s</p>', sprintf ( _n ( 'If there is a problem, Postman will give up after %d second.', 'If there is a problem, Postman will give up after %d seconds.', $this->options->getReadTimeout () * 2 ), $this->options->getReadTimeout () * 2 ) );
			printf ( '<label for="postman_test_options[test_email]">%s</label>', __ ( 'Recipient Email Address', 'Configuration Input Field' ) );
			print $this->test_email_callback ();
			print '</fieldset>';
			
			// Step 2
			printf ( '<h5>%s</h5>', __ ( 'Send The Message' ) );
			print '<fieldset>';
			print '<legend>';
			print __ ( 'Sending the message:' );
			printf ( ' <span id="postman_test_message_status">%s</span>', __ ( 'In Outbox', 'Send a Test Email' ) );
			print '</legend>';
			print '<section id="test-success">';
			printf ( '<p>%s</p>', __ ( 'Your message was delivered to the SMTP server! Congratulations :)' ) );
			print '</section>';
			print '<section id="test-fail">';
			printf ( '<p><label>%s</label></p>', 'Error Message' );
			print '<textarea id="postman_test_message_error_message" readonly="readonly" cols="65" rows="2"></textarea>';
			printf ( '<p><label for="postman_test_message_transcript">%s</label></p>', __ ( 'SMTP Session Transcript' ) );
			print '<textarea readonly="readonly" id="postman_test_message_transcript" cols="65" rows="6"></textarea>';
			print '</section>';
			print '</fieldset>';
			print '</form>';
		}
	}
}