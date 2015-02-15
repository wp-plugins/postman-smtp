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
		
		// action names
		const POSTMAN_REQUEST_OAUTH_PERMISSION_ACTION = 'oauth_request_permission';
		
		// page titles
		const NAME = 'Postman SMTP';
		const PAGE_TITLE = 'Postman Settings';
		const MENU_TITLE = 'Postman SMTP';
		
		// slugs
		const POSTMAN_TEST_SLUG = 'postman-test';
		const POSTMAN_ACTION = 'postman_action';
		
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
			$action = null;
			
			// check to see if there is a session action..
			if (isset ( $_SESSION [PostmanAdminController::POSTMAN_ACTION] )) {
				$action = $_SESSION [PostmanAdminController::POSTMAN_ACTION];
				unset ( $_SESSION [PostmanAdminController::POSTMAN_ACTION] );
				$this->logger->debug ( "Got \$_SESSION action " . $action );
				// special handling for handling of authorization grant
				if (PostmanAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS == $action) {
					if (isset ( $_GET ['code'] )) {
						// redirect to plugin setting page and exit()
						$this->logger->debug ( 'Found authorization grant code' );
					} else {
						// the user must have clicked cancel... abort the grant token check
						$this->logger->debug ( 'Found NO authorization grant code -- user must probably cancelled' );
						$action = '';
					}
				}
			}
			
			// if the session action was Save Failure, then we can ignore it to "reload" whatever the user was just doing..
			if (! isset ( $action ) || $action == PostmanInputSanitizer::SAVE_FAILURE) {
				if (isset ( $_REQUEST [PostmanAdminController::POSTMAN_ACTION] )) {
					$action = $_REQUEST [PostmanAdminController::POSTMAN_ACTION];
					$this->logger->debug ( "Got \$_REQUEST action " . $action );
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
			
			// determine which actions to perform
			switch ($action) {
				case PostmanInputSanitizer::SAVE_SUCCESS :
					$this->registerInitFunction ( 'handleSuccessfulSave' );
					break;
				
				case PostmanAdminController::POSTMAN_REQUEST_OAUTH_PERMISSION_ACTION :
					$this->registerInitFunction ( 'handleOAuthPermissionRequestAction' );
					break;
				
				case PostmanAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS :
					$this->registerInitFunction ( 'handleAuthorizationGrant' );
					break;
				
				default :
					// Ajax handlers
					if (is_admin ()) {
						$this->registerAjaxHandler ( 'test_port', 'getAjaxPortStatus' );
						$this->registerAjaxHandler ( 'check_email', 'getAjaxHostnameByEmail' );
						$this->registerAjaxHandler ( 'get_redirect_url', 'getAjaxRedirectUrl' );
						$this->registerAjaxHandler ( 'send_test_email', 'sendTestEmailViaAjax' );
						$this->registerAjaxHandler ( 'get_configuration', 'getConfigurationViaAjax' );
					}
					
					$this->registerAdminMenu ( 'generateDefaultContent' );
					$this->registerAdminMenu ( 'addSetupWizardSubmenu' );
					$this->registerAdminMenu ( 'addConfigurationSubmenu' );
					$this->registerAdminMenu ( 'addEmailTestSubmenu' );
					$this->registerAdminMenu ( 'addPortTestSubmenu' );
					$this->registerAdminMenu ( 'addPurgeDataSubmenu' );
					
					// intercepts calls to purge_data action
					$this->registerAdminPostAction ( self::PURGE_DATA_SLUG, 'handlePurgeDataAction' );
					$this->registerAdminPostAction ( self::REQUEST_OAUTH2_GRANT_SLUG, 'handleOAuthPermissionRequestAction' );
			}
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
					'<a href="' . esc_url ( POSTMAN_HOME_PAGE_ABSOLUTE_URL ) . '">Settings</a>' 
			);
			return array_merge ( $links, $mylinks );
		}
		
		/**
		 * Add options page
		 */
		public function generateDefaultContent() {
			// This page will be under "Settings"
			$page = add_options_page ( PostmanAdminController::PAGE_TITLE, PostmanAdminController::MENU_TITLE, 'manage_options', self::POSTMAN_MENU_SLUG, array (
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
			wp_enqueue_style ( 'postman_style' );
		}
		
		/**
		 * Register the Configuration screen
		 */
		public function addConfigurationSubmenu() {
			$page = add_submenu_page ( null, 'My Custom Submenu Page', 'My Custom Submenu Page', 'manage_options', self::CONFIGURATION_SLUG, array (
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
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_script ( 'postman_manual_config_script' );
		}
		
		/**
		 * Register the Setup Wizard screen
		 */
		public function addSetupWizardSubmenu() {
			$page = add_submenu_page ( null, 'My Custom Submenu Page', 'My Custom Submenu Page', 'manage_options', self::CONFIGURATION_WIZARD_SLUG, array (
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
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_script ( 'postman_wizard_script' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addEmailTestSubmenu() {
			$page = add_submenu_page ( null, 'My Custom Submenu Page', 'My Custom Submenu Page', 'manage_options', self::EMAIL_TEST_SLUG, array (
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
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_script ( 'postman_test_email_wizard_script' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPortTestSubmenu() {
			$page = add_submenu_page ( null, 'My Custom Submenu Page', 'My Custom Submenu Page', 'manage_options', self::PORT_TEST_SLUG, array (
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
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_script ( 'postman_port_test_script' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPurgeDataSubmenu() {
			$page = add_submenu_page ( null, 'My Custom Submenu Page', 'My Custom Submenu Page', 'manage_options', self::PURGE_DATA_SLUG, array (
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
			unset ( $_SESSION [PostmanGoogleAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS] );
			
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options, $authorizationToken );
			try {
				if ($authenticationManager->processAuthorizationGrantCode ()) {
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
			$authenticationManager->requestVerificationCode ();
		}
		
		/**
		 * Register and add settings
		 */
		public function initializeAdminPage() {
			// register the stylesheet and javascript external resources
			wp_register_style ( 'postman_style', plugins_url ( 'style/postman.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery.steps.css', __FILE__ ), 'postman_style', '1.1.0' );
			
			wp_register_script ( 'postman_script', plugins_url ( 'script/postman.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery.steps.js', __FILE__ ), array (
					'jquery' 
			), '1.1.0' );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery.validate.js', __FILE__ ), array (
					'jquery' 
			), '1.13.1' );
			wp_register_script ( 'postman_wizard_script', plugins_url ( 'script/postman_wizard.js', __FILE__ ), array (
					'jquery',
					'jquery_validation',
					'jquery_steps_script',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_test_email_wizard_script', plugins_url ( 'script/postman_test_email_wizard.js', __FILE__ ), array (
					'jquery',
					'jquery_validation',
					'jquery_steps_script',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_manual_config_script', plugins_url ( 'script/postman_manual_config.js', __FILE__ ), array (
					'jquery',
					'jquery_validation',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_port_test_script', plugins_url ( 'script/postman_port_test.js', __FILE__ ), array (
					'jquery',
					'jquery_validation',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_localize_script ( 'postman_script', 'postman_port_check_timeout', PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT . '' );
			
			wp_localize_script ( 'postman_script', 'postman_smtp_section_element_name', 'div#smtp_section' );
			wp_localize_script ( 'postman_script', 'postman_oauth_section_element_name', 'div#oauth_section' );
			
			// user input
			wp_localize_script ( 'postman_script', 'postman_input_sender_email', '#input_' . PostmanOptions::SENDER_EMAIL );
			wp_localize_script ( 'postman_script', 'postman_input_sender_name', '#input_' . PostmanOptions::SENDER_NAME );
			wp_localize_script ( 'postman_script', 'postman_port_element_name', '#input_' . PostmanOptions::PORT );
			wp_localize_script ( 'postman_script', 'postman_hostname_element_name', '#input_' . PostmanOptions::HOSTNAME );
			
			// the enc input
			wp_localize_script ( 'postman_script', 'postman_enc_for_password_el', '#input_enc_type_' . PostmanOptions::AUTHENTICATION_TYPE_PASSWORD );
			wp_localize_script ( 'postman_script', 'postman_enc_for_oauth2_el', '#input_enc_type_' . PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			wp_localize_script ( 'postman_script', 'postman_enc_none', PostmanOptions::ENCRYPTION_TYPE_NONE );
			wp_localize_script ( 'postman_script', 'postman_enc_ssl', PostmanOptions::ENCRYPTION_TYPE_SSL );
			wp_localize_script ( 'postman_script', 'postman_enc_tls', PostmanOptions::ENCRYPTION_TYPE_TLS );
			// these are the ids for the <option>s in the encryption <select>
			
			wp_localize_script ( 'postman_script', 'postman_enc_option_ssl_id', '.input_enc_type_ssl' );
			wp_localize_script ( 'postman_script', 'postman_enc_option_tls_id', '.input_enc_type_tls' );
			wp_localize_script ( 'postman_script', 'postman_enc_option_none_id', '.input_enc_type_none' );
			// this is for both the label and input of encryption type
			wp_localize_script ( 'postman_script', 'postman_encryption_group', '.input_encryption_type' );
			
			// the password inputs
			wp_localize_script ( 'postman_script', 'postman_input_basic_username', '#input_' . PostmanOptions::BASIC_AUTH_USERNAME );
			wp_localize_script ( 'postman_script', 'postman_input_basic_password', '#input_' . PostmanOptions::BASIC_AUTH_PASSWORD );
			
			// the auth input
			wp_localize_script ( 'postman_script', 'postman_redirect_url_el', '#input_oauth_redirect_url' );
			wp_localize_script ( 'postman_script', 'postman_input_auth_type', '#input_' . PostmanOptions::AUTHENTICATION_TYPE );
			wp_localize_script ( 'postman_script', 'postman_auth_none', PostmanOptions::AUTHENTICATION_TYPE_NONE );
			wp_localize_script ( 'postman_script', 'postman_auth_login', PostmanOptions::AUTHENTICATION_TYPE_LOGIN );
			wp_localize_script ( 'postman_script', 'postman_auth_plain', PostmanOptions::AUTHENTICATION_TYPE_PLAIN );
			wp_localize_script ( 'postman_script', 'postman_auth_crammd5', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 );
			wp_localize_script ( 'postman_script', 'postman_auth_oauth2', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			// these are the ids for the <option>s in the auth <select>
			wp_localize_script ( 'postman_script', 'postman_auth_option_oauth2_id', '#input_auth_type_oauth2' );
			wp_localize_script ( 'postman_script', 'postman_auth_option_none_id', '#input_auth_type_none' );
			
			// test email input
			wp_localize_script ( 'postman_script', 'postman_input_test_email', '#input_' . PostmanOptions::TEST_EMAIL );
			
			//
			$sanitizer = new PostmanInputSanitizer ( $this->options );
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
					$sanitizer,
					'sanitize' 
			) );
			
			// Sanitize
			add_settings_section ( PostmanAdminController::SMTP_SECTION, _x ( 'SMTP Settings', 'Configuration Section', 'postman-smtp' ), array (
					$this,
					'printSmtpSectionInfo' 
			), PostmanAdminController::SMTP_OPTIONS );
			
			if ($this->options->isNew () && $this->importableConfiguration->isImportAvailable ()) {
				add_settings_field ( 'import_configuration', _x ( 'Import from Plugin', 'Configuration Input Field' ), array (
						$this,
						'import_configuration_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			}
			
			add_settings_field ( PostmanOptions::AUTHENTICATION_TYPE, _x ( 'Authentication', 'Configuration Input Field' ), array (
					$this,
					'authentication_type_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::SENDER_NAME, _x ( 'Sender Name', 'Configuration Input Field' ), array (
					$this,
					'sender_name_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::SENDER_EMAIL, _x ( 'Sender Email Address', 'Configuration Input Field' ), array (
					$this,
					'sender_email_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::HOSTNAME, _x ( 'Outgoing Mail Server (SMTP)', 'Configuration Input Field' ), array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::PORT, _x ( 'Port', 'Configuration Input Field' ), array (
					$this,
					'port_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, _x ( 'Authentication Settings', 'Configuration Section' ), array (
					$this,
					'printBasicAuthSectionInfo' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( 'Encryption', 'Configuration Input Field' ), array (
					$this,
					'encryption_type_for_password_section_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_USERNAME, _x ( 'Username', 'Configuration Input Field' ), array (
					$this,
					'basic_auth_username_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_PASSWORD, _x ( 'Password', 'Configuration Input Field' ), array (
					$this,
					'basic_auth_password_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			// the OAuth section
			add_settings_section ( PostmanAdminController::OAUTH_SECTION, _x ( 'Authentication Settings', 'Configuration Section' ), array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::OAUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( 'Encryption', 'Configuration Input Field' ), array (
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
			add_settings_section ( PostmanAdminController::ADVANCED_SECTION, _x ( 'Advanced Settings', 'Configuration Section' ), array (
					$this,
					'printAdvancedSectionInfo' 
			), PostmanAdminController::ADVANCED_OPTIONS );
			
			add_settings_field ( 'connection_timeout', _x ( 'Connection Timeout (sec)', 'Configuration Input Field' ), array (
					$this,
					'connection_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( 'read_timeout', _x ( 'Read Timeout (sec)', 'Configuration Input Field' ), array (
					$this,
					'read_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::REPLY_TO, _x ( 'Reply-To Email Address', 'Configuration Input Field' ), array (
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
						'redirect_url' => PostmanSmtpHostProperties::getRedirectUrl ( $hostname ),
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
			print '<p><span>This test determines which ports are open for Postman to use. A</span> <span style="color:red">Closed</span><span> port indicates either <ol><li>Your host has placed a firewall between this site and the SMTP server or</li><li>The SMTP server has no service running on that port</li></ol></span></p><p><span><b>If the port you are trying to use is </span> <span style="color:red"><b>Closed</b></span><span>, Postman can not deliver mail. Contact your host to get the port opened.</b></span></p><p><span class="fine_print">Each test is given ' . $this->options->getConnectionTimeout () . ' seconds to complete and the entire test will take up to ' . ($this->options->getConnectionTimeout () * 3) . ' seconds to run. Javascript is required.</span></p>';
		}
		
		/**
		 * Print the Section text
		 */
		public function printBasicAuthSectionInfo() {
			print 'Enter the username (email address) and password you use to send email';
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
			print 'You will receive an email from Postman with the subject "WordPress Postman SMTP Test."';
		}
		
		/**
		 * Print the Section text
		 */
		public function printPurgeDataSectionInfo() {
			print '<p><span>This will purge all of Postman\'s settings, including SMTP server info, username/password and Client ID.</span></p><p><span>Are you sure?</span></p>';
		}
		
		/**
		 * Print the Section text
		 */
		public function printAdvancedSectionInfo() {
			print 'Increase the read timeout if your host is intermittenly failing to send mail. Be careful, this also correlates to how long your user must wait if your mail server is unreachable.';
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function authentication_type_callback() {
			$authType = $this->options->getAuthorizationType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			print '<option id="input_auth_type_none" value="' . PostmanOptions::AUTHENTICATION_TYPE_NONE . '"';
			printf ( '%s', $authType == PostmanOptions::AUTHENTICATION_TYPE_NONE ? 'selected="selected"' : '' );
			print '>None</option>';
			print '<option id="input_auth_type_plain" value="' . PostmanOptions::AUTHENTICATION_TYPE_PLAIN . '"';
			printf ( '%s', $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN ? 'selected="selected"' : '' );
			print '>Plain</option>';
			print '<option id="input_auth_type_login" value="' . PostmanOptions::AUTHENTICATION_TYPE_LOGIN . '"';
			printf ( '%s', $authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN ? 'selected="selected"' : '' );
			print '>Login</option>';
			print '<option id="input_auth_type_crammd5" value="' . PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 . '"';
			printf ( '%s', $authType == PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 ? 'selected="selected"' : '' );
			print '>CRAM-MD5</option>';
			print '<option id="input_auth_type_oauth2" value="' . PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 . '"';
			printf ( '%s', $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 ? 'selected="selected"' : '' );
			print '>OAuth 2.0</option>';
			print '</select>';
		}
		/**
		 * Get the settings option array and print one of its values
		 */
		public function encryption_type_for_password_section_callback() {
			$this->encryption_type_callback ( PostmanOptions::AUTHENTICATION_TYPE_PASSWORD );
		}
		public function encryption_type_for_oauth2_section_callback() {
			$this->encryption_type_callback ( PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
		}
		public function encryption_type_callback($section) {
			$authType = $this->options->getEncryptionType ();
			print '<select id="input_enc_type_' . $section . '" class="input_encryption_type" name="postman_options[enc_type]">';
			print '<option class="input_enc_type_none" value="' . PostmanOptions::ENCRYPTION_TYPE_NONE . '"';
			printf ( '%s', $authType == PostmanOptions::ENCRYPTION_TYPE ? 'selected="selected"' : '' );
			print '>None</option>';
			print '<option class="input_enc_type_ssl" value="' . PostmanOptions::ENCRYPTION_TYPE_SSL . '"';
			printf ( '%s', $authType == PostmanOptions::ENCRYPTION_TYPE_SSL ? 'selected="selected"' : '' );
			print '>SSL</option>';
			print '<option class="input_enc_type_tls" value="' . PostmanOptions::ENCRYPTION_TYPE_TLS . '"';
			printf ( '%s', $authType == PostmanOptions::ENCRYPTION_TYPE_TLS ? 'selected="selected"' : '' );
			print '>TLS</option>';
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
			printf ( '<textarea type="text" onClick="this.setSelectionRange(0, this.value.length)" id="oauth_client_id" name="postman_options[oauth_client_id]" cols="60" class="required">%s</textarea>', null !== $this->options->getClientId () ? esc_attr ( $this->options->getClientId () ) : '' );
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
			
			// Set class property
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			if (! $sslRequirement || ! $splAutoloadRegisterRequirement || ! $arrayObjectRequirement) {
				print '<div style="background-color: white; padding: 10px;"><b style="color: red">Warning, your system does not meet the pre-requisites - something may fail:</b><ul>';
				print '<li>PHP v5.3: ' . ($phpVersionRequirement ? 'Yes' : 'No (' . PHP_VERSION . ')') . '</li>';
				print '<li>SSL Extension: ' . ($sslRequirement ? 'Yes' : 'No') . '</li>';
				print '<li>spl_autoload_register: ' . ($splAutoloadRegisterRequirement ? 'Yes' : 'No') . '</li>';
				print '<li>ArrayObject: ' . ($arrayObjectRequirement ? 'Yes' : 'No') . '</li>';
				print '<ul></div>';
			}
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				print '<p><span style="color:green;padding:2px 5px; font-size:1.2em">Postman is configured.</span><p style="margin:0 10px">Postman will send mail  via <b>' . $this->options->getHostname () . ':' . $this->options->getPort () . '</b>';
				if ($this->options->isAuthTypeOAuth2 ()) {
					print ' using <b>OAuth 2.0</b> authentication.</span></p>';
				} else if ($this->options->isAuthTypeNone ()) {
					print ' using no authentication.</span></p>';
				} else {
					print ' using <b>Password</b> (' . $this->options->getAuthorizationType () . ') authentication.</span></p>';
				}
				if ($this->options->isAuthTypeOAuth2 ()) {
					print '<p style="margin:10px 10px"><span>Please note: <em>When OAuth 2.0 is enabled, WordPress may override the sender name only</em>.</span></p>';
				}
			} else {
				print '<p><span style="color:red; padding:2px 5px; font-size:1.1em">Status: Postman is not sending mail.</span></p>';
				if ($this->options->isNew ()) {
					print '<h3>Thank-you for choosing Postman!</h3>';
					print '<p><span>Let\'s get started! All users are strongly encouraged to start by <a href="' . POSTMAN_HOME_PAGE_ABSOLUTE_URL . '/configuration_wizard">running the Setup Wizard</a>.</span></p>';
					if ($this->importableConfiguration->isImportAvailable ()) {
						print 'However, if you wish, Postman can <a href="' . POSTMAN_HOME_PAGE_ABSOLUTE_URL . '/configuration">import your SMTP configuration</a> from another plugin. You can run the Wizard later if the imported settings don\'t work.';
					}
				}
			}
			print '</div>';
		}
		/**
		 */
		public function outputManualConfigurationContent() {
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
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
			print '<p id="advanced_options_configuration_display" class="fineprint"><span><a href="#">Show Advanced Settings</a></span></p>';
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
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			printf ( '<input type="hidden" name="action" value="%s" />', self::PURGE_DATA_SLUG );
			do_settings_sections ( 'PURGE_DATA' );
			submit_button ( 'Delete All Data', 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPortTestContent() {
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			print '<form id="port_test_form_id" method="post">';
			do_settings_sections ( PostmanAdminController::PORT_TEST_OPTIONS );
			// This prints out all hidden setting fields
			submit_button ( 'Begin Test', 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table">';
			print '<tr><td class="port">Port 25</td><td id="port-test-port-25">Unknown</td>';
			print '<tr><td class="port">Port 465</td><td id="port-test-port-465">Unknown</td>';
			print '<tr><td class="port">Port 587</td><td id="port-test-port-587">Unknown</td>';
			print '</table>';
			print '</div>';
		}
		
		/**
		 */
		private function displayTopNavigation() {
			?>
<div id="welcome-panel" class="welcome-panel">
	<div class="welcome-panel-content">
		<div class="welcome-panel-column-container">
			<div class="welcome-panel-column">
				<h4>Get Started</h4>
				<a class="button button-primary button-hero"
					href="<?php echo $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ) ?>">Start
					the Wizard</a>
				<p class="">
					or, <a
						href="<?php echo $this->getPageUrl(self::CONFIGURATION_SLUG) ?>">configure
						manually</a>.
				</p>
			</div>
			<div class="welcome-panel-column">
				<h4>Actions</h4>
				<ul>
					<li><?php
			$emailCompany = __ ( 'Request OAuth Permission' );
			if ($this->options->isSmtpHostGmail ()) {
				$emailCompany = __ ( 'Request permission from Google' );
			} else if ($this->options->isSmtpHostHotmail ()) {
				$emailCompany = __ ( 'Request permission from Microsoft' );
			} else if ($this->options->isSmtpHostYahoo ()) {
				$emailCompany = __ ( 'Request permission from Yahoo' );
			}
			if ($this->options->isRequestOAuthPermissionAllowed ()) {
				printf ( '<a href="%s" class="welcome-icon send-test-email">%s</a>', $this->getActionUrl ( self::REQUEST_OAUTH2_GRANT_SLUG ), $emailCompany );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print $emailCompany;
				print '</div>';
			}
			?></li>
					<li><a
						href="<?php echo $this->getPageUrl(self::PURGE_DATA_SLUG); ?>"
						class="welcome-icon oauth-authorize">Delete plugin settings</a></li>

				</ul>
			</div>
			<div class="welcome-panel-column welcome-panel-last">
				<h4>Troubleshooting</h4>
				<ul>
					<li><?php
			
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<a
							href="%s"
							class="welcome-icon send_test_email">Send a Test Email</a>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ) );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print 'Send a Test Email';
				print '</div>';
			}
			
			?></li>
					<li><a
						href="<?php echo $this->getPageUrl ( self::PORT_TEST_SLUG ) ?>"
						class="welcome-icon run-port-test">Run a Port Connection Test</a></li>
					<li><a
						href="https://wordpress.org/plugins/postman-smtp/other_notes/"
						class="welcome-icon postman_support">Online Support</a></li>
				</ul>
			</div>
		</div>
	</div>
</div><?php
		}
		/**
		 */
		public function outputWizardContent() {
			// Set class property
			$this->options->setSenderEmailIfEmpty ( wp_get_current_user ()->user_email );
			$this->options->setSenderNameIfEmpty ( wp_get_current_user ()->display_name );
			
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			?>
<h3>Postman Setup Wizard</h3>

<form id="postman_wizard" method="post" action="options.php">
	<input type="hidden" name="purge_auth_token" value="purge_auth_token" />
	<?php
			printf ( '<input type="hidden" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
			printf ( '<input type="hidden" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			?>
	<h1>Sender Address Details</h1>
	<fieldset>
		<legend>Enter your Email Address </legend>
		<p>Let's begin! Please enter the email address and name you'd like to
			send mail from.</p>
		<p>
			Please note that to combat Spam, many email services will <em>not</em>
			let you send from an e-mail address that is not your own.
		</p>

		<label for="postman_options[sender_email]">Sender Email Address</label>
		<?php echo $this->sender_email_callback(); ?>
		<label for="postman_options[sender_name]">Sender Email Name</label>
		<?php echo $this->sender_name_callback(); ?>
	</fieldset>

	<h1>SMTP Server Hostname</h1>
	<fieldset>
		<legend>Enter your SMTP hostname. </legend>
		<p>This is the server that Postman will use to deliver your mail.</p>
		<label for="hostname">SMTP Server Hostname</label>
		<?php echo $this->hostname_callback(); ?>
	
	
	</fieldset>

	<h1>SMTP Server Port</h1>
	<fieldset>
		<legend>Choose an SMTP port</legend>
		<p>Your email provider will dictate which port to use.</p>

		<label for="hostname">SMTP Server Port</label>
		<?php echo $this->port_callback(array('style'=>'style="display:none"')); ?>
		<table>
			<tr>
				<td><span>Port 25 </span></td>
				<td><input type="radio" id="wizard_port_25" name="wizard-port"
					value="25" class="required" style="margin-top: 0px" /></td>
				<td id="wizard_port_25_status">Unknown</td>
			</tr>
			<tr>
				<td><span>Port 465</span></td>
				<td><input type="radio" id="wizard_port_465" name="wizard-port"
					value="465" class="required" style="margin-top: 0px" /></td>
				<td id="wizard_port_465_status">Unknown</td>
			</tr>
			<tr>
				<td><span>Port 587</span></td>
				<td><input type="radio" id="wizard_port_587" name="wizard-port"
					value="587" class="required" style="margin-top: 0px" /></td>
				<td id="wizard_port_587_status">Unknown</td>
			</tr>
		</table>
	</fieldset>

	<h1>Authentication</h1>
	<fieldset>
		<legend> Setup Authentication </legend>
		<section class="wizard-auth-oauth2">
			<p id="wizard_oauth2_help">Help.</p>
			<label id="callback_domain" for="callback_domain"><?php echo $this->oauthScribe->getCallbackDomainLabel();?></label><br />
			<?php echo $this->callback_domain_callback(); ?><br /> <label
				id="redirect_url" for="redirect_uri"><?php echo $this->oauthScribe->getCallbackUrlLabel();?></label><br />
			<?php echo $this->redirect_url_callback(); ?><br /> 
						<?php echo $this->encryption_type_for_oauth2_section_callback(); ?>
			<label id="client_id" for="client_id"><?php echo $this->oauthScribe->getClientIdLabel();?></label><br />
			<?php echo $this->oauth_client_id_callback(); ?><br /> <label
				id="client_secret" for="client_id"><?php echo $this->oauthScribe->getClientSecretLabel();?></label>
			<br />
			<?php echo $this->oauth_client_secret_callback(); ?><br />
		</section>

		<section class="wizard-auth-basic">
			<p class="port-explanation-ssl">Unless you've been told otherwise,
				choose Login authentication. Your username is most likely your email
				address.</p>
			<label class="input_authorization_type" for="auth_type">Authentication
				Type</label>
			<?php echo $this->authentication_type_callback(); ?>
			<label class="input_encryption_type" for="enc_type">Encryption Type</label>
			<?php echo $this->encryption_type_for_password_section_callback(); ?>
			<br /> <label for="username">Username</label>
			<?php echo $this->basic_auth_username_callback();?>
			<label for="password">Password</label>
			<?php echo $this->basic_auth_password_callback();?>
			</section>
	</fieldset>

	<h1>Finish</h1>
	<fieldset>
		<legend>All done!</legend>
		<section>
			<p>Click Finish to save these settings. Then:</p>
			<ul style='margin-left: 20px'>
				<li class="wizard-auth-oauth2">Request permission from the Email
					Provider to allow Postman to send email and</li>
				<li>Send yourself a Test Email to make sure everything is working!</li>
			</ul>
		</section>
	</fieldset>

</form>

<?php
		}
		/**
		 */
		public function outputTestEmailWizardContent() {
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			
			// set default recipient for test emails
			$testEmail = $this->testOptions [PostmanOptions::TEST_EMAIL];
			if (! isset ( $testEmail )) {
				$this->testOptions [PostmanOptions::TEST_EMAIL] = wp_get_current_user ()->user_email;
			}
			?>
<h3>Send a Test Email</h3>

<form id="postman_test_email_wizard" method="post"
	action="<?php echo POSTMAN_HOME_PAGE_ABSOLUTE_URL ?>">
	<h1>Input Email Address</h1>
	<fieldset>
		<legend>Enter your Email Address </legend>
		<p>This utility allows you to send an email for testing. It may take up to <?php echo $this->options->getReadTimeout() * 2?> seconds to complete.</p>

		<label for="postman_test_options[test_email]">Recipient Email Address</label>
		<?php echo $this->test_email_callback(); ?>
	</fieldset>

	<h1>Deliver The Message</h1>
	<fieldset>
		<legend>
			Sending the message: <span id="postman_test_message_status">In Outbox</span>
		</legend>
		<section id="test-success">
			<p>Your message was delivered to the SMTP server! Congratulations :)</p>
		</section>
		<section id="test-fail">
			<p>
				<label>Error Message</label>
			</p>
			<textarea id="postman_test_message_error_message" readonly="readonly"
				cols="70" rows="2"></textarea>
			<p>
				<label for="postman_test_message_transcript">SMTP Session Transcript</label>
			</p>
			<textarea readonly="readonly" id="postman_test_message_transcript"
				cols="70" rows="6"></textarea>
		</section>
	</fieldset>

</form>

<?php
		}
	}
}
