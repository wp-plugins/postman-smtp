<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "SendTestEmailController.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanAuthorizationToken.php';
	require_once 'Wizard/PortTest.php';
	require_once 'Wizard/SmtpDiscovery.php';
	require_once 'PostmanInputSanitizer.php';
	
	//
	class PostmanAdminController {
		
		// this is the slug used in the URL
		const POSTMAN_MENU_SLUG = 'postman';
		
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
		
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;
		private $testOptions;
		
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
				
				case 'configure_manually' :
					$this->registerAdminMenu ( 'generateManualConfigurationContent' );
					break;
				
				case 'start_wizard' :
					$this->registerAdminMenu ( 'generateWizardContent' );
					break;
				
				case 'send_test_email' :
					// $this->registerAdminMenu ( 'generateSendTestEmailContent' );
					$this->registerAdminMenu ( 'generateTestEmailWizardContent' );
					break;
				
				case 'run_port_test' :
					$this->registerAdminMenu ( 'generatePortTestContent' );
					break;
				
				case 'delete_data' :
					$this->registerAdminMenu ( 'generatePurgeDataContent' );
					break;
				
				default :
					// Ajax handlers
					if (is_admin ()) {
						$this->registerAjaxHandler ( 'wp_ajax_test_port', 'getAjaxPortStatus' );
						$this->registerAjaxHandler ( 'wp_ajax_check_email', 'getAjaxHostnameByEmail' );
						$this->registerAjaxHandler ( 'wp_ajax_get_redirect_url', 'getAjaxRedirectUrl' );
						$this->registerAjaxHandler ( 'wp_ajax_send_test_email', 'sendTestEmailViaAjax' );
					}
					
					// this outputs the HTML content for the 'home' landing page
					$this->registerAdminMenu ( 'generateDefaultContent' );
					
					// intercepts calls to test_mail action
					$this->registerAdminPostAction ( 'test_mail', 'handleTestEmailAction' );
					
					// intercepts calls to purge_data action
					$this->registerAdminPostAction ( 'purge_data', 'handlePurgeDataAction' );
			}
		}
		
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerAjaxHandler($actionName, $callbackName) {
			$this->logger->debug ( 'Registering ' . $actionName . ' Ajax handler' );
			add_action ( $actionName, array (
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
			unset ( $_SESSION [PostmanGmailAuthenticationManager::POSTMAN_AUTHORIZATION_IN_PROGRESS] );
			
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $options, $authorizationToken );
			try {
				if ($authenticationManager->handleAuthorizatinGrantCode ()) {
					$logger->debug ( 'Authorization successful' );
					// save to database
					$authorizationToken->save ();
				} else {
					$this->messageHandler->addError ( 'Your email provider did not grant Postman permission. Try again.' );
				}
			} catch ( Exception $e ) {
				$logger->error ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				$this->messageHandler->addError ( 'Error authenticating with this Client ID - please create a new one. [<em>' . $e->getMessage () . '</em>]' );
			}
			// redirect home
			postmanRedirect ( POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		
		/**
		 * Add options page
		 */
		public function generatePurgeDataContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputPurgeDataContent' );
		}
		public function generateManualConfigurationContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputManualConfigurationContent' );
		}
		public function generateWizardContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputWizardContent' );
		}
		public function generateSendTestEmailContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputSendTestEmailContent' );
		}
		public function generatePortTestContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputPortTestContent' );
		}
		public function generateDefaultContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputDefaultContent' );
		}
		public function generateTestEmailWizardContent() {
			$this->addLinkToWordPressSettingsAdminMenu ( 'outputTestEmailWizardContent' );
		}
		public function addLinkToWordPressSettingsAdminMenu($pageContentCallback) {
			// This page will be under "Settings"
			$page = add_options_page ( PostmanAdminController::PAGE_TITLE, PostmanAdminController::MENU_TITLE, 'manage_options', PostmanAdminController::POSTMAN_MENU_SLUG, array (
					$this,
					$pageContentCallback 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueStylesheet' 
			) );
			return $page;
		}
		/**
		 * Render the stylesheet
		 */
		function enqueueStylesheet() {
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_script ( 'postman_script' );
			if (isset ( $_GET [PostmanAdminController::POSTMAN_ACTION] )) {
				$action = $_GET [PostmanAdminController::POSTMAN_ACTION];
				if ($action == 'configure_manually') {
					wp_enqueue_script ( 'postman_manual_config_script' );
				} else if ($action == 'start_wizard') {
					wp_enqueue_script ( 'postman_wizard_script' );
				} else if ($action == 'send_test_email') {
					wp_enqueue_script ( 'postman_test_email_wizard_script' );
				} else if ($action == 'run_port_test') {
					wp_enqueue_script ( 'postman_port_test_script' );
				}
			}
		}
		
		/**
		 * This method sends a test e-mail and redirects to the homepage
		 */
		function handleTestEmailAction() {
			$this->logger->debug ( 'in handleTestEmailAction()' );
			$recipient = $_POST [PostmanAdminController::TEST_OPTIONS] ['test_email'];
			if (! empty ( $recipient )) {
				$testEmailController = new PostmanSendTestEmailController ();
				$testEmailController->send ( $this->options, $this->authorizationToken, $recipient, $this->messageHandler );
			}
			$this->logger->debug ( 'Redirecting to home page' );
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
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery.steps.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			
			wp_register_script ( 'postman_script', plugins_url ( 'script/postman.js', __FILE__ ), array (
					'jquery',
					'jquery_steps_script',
					'jquery_validation' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery.steps.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery.validate.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_wizard_script', plugins_url ( 'script/postman_wizard.js', __FILE__ ), array (
					'jquery',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_test_email_wizard_script', plugins_url ( 'script/postman_test_email_wizard.js', __FILE__ ), array (
					'jquery',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_manual_config_script', plugins_url ( 'script/postman_manual_config.js', __FILE__ ), array (
					'jquery',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_port_test_script', plugins_url ( 'script/postman_port_test.js', __FILE__ ), array (
					'jquery',
					'postman_script' 
			), POSTMAN_PLUGIN_VERSION );
			wp_localize_script ( 'postman_script', 'postman_port_check_timeout', PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT . '' );
			
			wp_localize_script ( 'postman_script', 'postman_smtp_section_element_name', 'div#smtp_section' );
			wp_localize_script ( 'postman_script', 'postman_oauth_section_element_name', 'div#oauth_section' );
			
			// user input
			wp_localize_script ( 'postman_script', 'postman_input_sender_email', '#input_' . PostmanOptions::SENDER_EMAIL );
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
			wp_localize_script ( 'postman_script', 'postman_redirect_url_el', '#oauth_redirect_url' );
			wp_localize_script ( 'postman_script', 'postman_input_auth_type', '#input_' . PostmanOptions::AUTHENTICATION_TYPE );
			wp_localize_script ( 'postman_script', 'postman_auth_none', PostmanOptions::AUTHENTICATION_TYPE_NONE );
			wp_localize_script ( 'postman_script', 'postman_auth_login', PostmanOptions::AUTHENTICATION_TYPE_LOGIN );
			wp_localize_script ( 'postman_script', 'postman_auth_plain', PostmanOptions::AUTHENTICATION_TYPE_PLAIN );
			wp_localize_script ( 'postman_script', 'postman_auth_crammd5', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 );
			wp_localize_script ( 'postman_script', 'postman_auth_oauth2', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			// these are the ids for the <option>s in the auth <select>
			wp_localize_script ( 'postman_script', 'postman_auth_option_oauth2_id', '#input_auth_type_oauth2' );
			wp_localize_script ( 'postman_script', 'postman_auth_option_none_id', '#input_auth_type_none' );
			
			//
			$sanitizer = new PostmanInputSanitizer ( $this->options );
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
					$sanitizer,
					'sanitize' 
			) );
			
			// Sanitize
			add_settings_section ( PostmanAdminController::SMTP_SECTION, __ ( 'SMTP Settings', 'postman' ), array (
					$this,
					'printSmtpSectionInfo' 
			), PostmanAdminController::SMTP_OPTIONS );
			
			add_settings_field ( PostmanOptions::AUTHENTICATION_TYPE, 'Authentication', array (
					$this,
					'authentication_type_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::SENDER_NAME, 'Sender Name', array (
					$this,
					'sender_name_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			add_settings_field ( PostmanOptions::SENDER_EMAIL, 'Sender Email Address', array (
					$this,
					'sender_email_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::HOSTNAME, 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_field ( PostmanOptions::PORT, 'Port', array (
					$this,
					'port_callback' 
			), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
			
			add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, 'Authentication Settings', array (
					$this,
					'printBasicAuthSectionInfo' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, 'Encryption', array (
					$this,
					'encryption_type_for_password_section_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_USERNAME, 'Username', array (
					$this,
					'basic_auth_username_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			add_settings_field ( PostmanOptions::BASIC_AUTH_PASSWORD, 'Password', array (
					$this,
					'basic_auth_password_callback' 
			), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
			
			// the OAuth section
			add_settings_section ( PostmanAdminController::OAUTH_SECTION, 'Authentication Settings', array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::OAUTH_OPTIONS );
			
			add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, 'Encryption', array (
					$this,
					'encryption_type_for_oauth2_section_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( 'redirect_url', 'Redirect URI', array (
					$this,
					'redirect_url_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_ID, 'Client ID', array (
					$this,
					'oauth_client_id_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			add_settings_field ( PostmanOptions::CLIENT_SECRET, 'Client Secret', array (
					$this,
					'oauth_client_secret_callback' 
			), PostmanAdminController::OAUTH_OPTIONS, PostmanAdminController::OAUTH_SECTION );
			
			// the Advanced section
			add_settings_section ( PostmanAdminController::ADVANCED_SECTION, 'Advanced Settings', array (
					$this,
					'printAdvancedSectionInfo' 
			), PostmanAdminController::ADVANCED_OPTIONS );
			
			add_settings_field ( 'connection_timeout', 'Connection Timeout (sec)', array (
					$this,
					'connection_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( 'read_timeout', 'Read Timeout (sec)', array (
					$this,
					'read_timeout_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			add_settings_field ( PostmanOptions::REPLY_TO, 'Reply-To Email Address', array (
					$this,
					'reply_to_callback' 
			), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
			// the Port Test section
			add_settings_section ( PostmanAdminController::PORT_TEST_SECTION, 'TCP Port Test', array (
					$this,
					'printPortTestSectionInfo' 
			), PostmanAdminController::PORT_TEST_OPTIONS );
			
			add_settings_field ( PostmanOptions::HOSTNAME, 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::PORT_TEST_OPTIONS, PostmanAdminController::PORT_TEST_SECTION );
			
			// the Test Email section
			register_setting ( 'email_group', PostmanAdminController::TEST_OPTIONS, array (
					$sanitizer,
					'testSanitize' 
			) );
			
			add_settings_section ( 'TEST_EMAIL', 'Test Your Setup', array (
					$this,
					'printTestEmailSectionInfo' 
			), PostmanAdminController::POSTMAN_TEST_SLUG );
			
			add_settings_field ( 'test_email', 'Recipient Email Address', array (
					$this,
					'test_email_callback' 
			), PostmanAdminController::POSTMAN_TEST_SLUG, 'TEST_EMAIL' );
			
			// the Purge Data section
			add_settings_section ( 'PURGE_DATA', 'Delete All Data', array (
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
			if (isset ( $_POST ['timeout'] )) {
				$timeout = intval ( $_POST ['timeout'] );
			} else {
				$timeout = PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT;
			}
			$this->logger->debug ( 'testing port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ();
			$success = $portTest->testSmtpPorts ( $hostname, $port, $timeout );
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
			$success = $emailTester->simeplSend ( $this->options, $this->authorizationToken, $email );
			$response = array (
					'message' => $emailTester->getMessage (),
					'transcript' => $emailTester->getTranscript (),
					'success' => $success 
			);
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
			$response = $this->getRedirectUrl ( $hostname );
			wp_send_json ( $response );
		}
		
		/**
		 * This function returns a resonse array containing the redirectUrl and helpText for a given SMTP hostname
		 *
		 * @param unknown $hostname        	
		 * @return multitype:string
		 */
		private function getRedirectUrl($hostname) {
			$redirectUrl = PostmanSmtpHostProperties::getRedirectUrl ( $hostname );
			if (PostmanSmtpHostProperties::isGmail ( $hostname )) {
				$help = '<p id="wizard_oauth2_help"><span class="normal">Open the <a href="https://console.developers.google.com/" target="_new">Google Developer Console</a>, create a Client ID
				using the Redirect URI below, and enter the Client ID and Client
				Secret. See <a
					href="https://wordpress.org/plugins/postman-smtp/faq/"
					target="_new">How do I get a Google Client ID?</a> in the F.A.Q.
				for help.</span></p>';
			} else if (PostmanSmtpHostProperties::isHotmail ( $hostname )) {
				$help = '<p id="wizard_oauth2_help"><span class="normal">Open the <a
					href="https://account.live.com/developers/applications/index"
					target="_new">Microsoft Developer Center</a>, create an Application
				using the Redirect URI below, and enter the Client ID and Client
				Secret. See <a
					href="https://wordpress.org/plugins/postman-smtp/faq/"
					target="_new">How do I get a Windows Live Client ID?</a> in the F.A.Q.
				for help.</span></p>';
			} else {
				$help = '<p id="wizard_oauth2_help"><span class="error">You must enter an Outgoing Mail Server with OAuth 2.0 capabilities.</span></p>';
			}
			$response = array (
					'redirect_url' => (! empty ( $redirectUrl ) ? $redirectUrl : ''),
					'help_text' => $help 
			);
			return $response;
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
			print '<p><span>This test determines which ports are open for Postman to use. A</span> <span style="color:red">Closed</span><span> port indicates either <ol><li>Your host has placed a firewall between this site and the SMTP server or</li><li>The SMTP server has no service running on that port</li></ol></span></p><p><span><b>If the port you are trying to use is </span> <span style="color:red"><b>Closed</b></span><span>, Postman can not deliver mail. Contact your host to get the port opened.</b></span></p><p><span class="fine_print">Each test is given ' . PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT . ' seconds to complete and the entire test will take up to ' . (PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT * 3) . ' seconds to run. Javascript is required.</span></p>';
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
			$response = $this->getRedirectUrl ( $this->options->getHostname () );
			print $response ['help_text'];
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
			print 'Are you sure?';
		}
		
		/**
		 * Print the Section text
		 */
		public function printAdvancedSectionInfo() {
			print 'Additional email properties';
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
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input type="text" id="input_hostname" name="postman_options[hostname]" value="%s" class="required"/>', null !== $this->options->getHostname () ? esc_attr ( $this->options->getHostname () ) : '' );
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
			printf ( '<input type="text" id="input_sender_email" name="postman_options[sender_email]" value="%s" size="40" class="required email"/>', null !== $this->options->getSenderEmail () ? esc_attr ( $this->options->getSenderEmail () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function redirect_url_callback() {
			printf ( '<textarea onClick="this.setSelectionRange(0, this.value.length)" readonly="readonly" type="text" id="oauth_redirect_url" cols="60" >%s</textarea>', PostmanSmtpHostProperties::getRedirectUrl ( $this->options->getHostname () ) );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function basic_auth_username_callback() {
			printf ( '<input type="text" id="input_basic_auth_username" name="postman_options[basic_auth_username]" value="%s" class="required"/>', null !== $this->options->getUsername () ? esc_attr ( $this->options->getUsername () ) : '' );
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
			printf ( '<input type="text" id="input_reply_to" name="postman_options[reply_to]" value="%s" />', null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function connection_timeout_callback() {
			printf ( '<input type="text" readonly="readonly" id="input_connection_timeout" name="postman_options[connection_timeout]" value="%s" />', PostmanMain::POSTMAN_TCP_CONNECTION_TIMEOUT );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function read_timeout_callback() {
			printf ( '<input type="text" readonly="readonly" id="input_read_timeout" name="postman_options[read_timeout]" value="%s" />', PostmanMain::POSTMAN_TCP_READ_TIMEOUT );
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
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" class="required email" size="40"/>', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
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
				if (! $this->options->isAuthTypeNone ()) {
					print '<p style="margin:10px 10px"><span>Please note: <em>When authentication is enabled, WordPress may override the sender name only</em>.</span></p>';
				}
			} else {
				print '<p><span style="color:red; padding:2px 5px; font-size:1.1em">Status: Postman is not sending mail.</span></p>';
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
			do_settings_sections ( PostmanAdminController::ADVANCED_OPTIONS );
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
			print '<input type="hidden" name="action" value="purge_data" />';
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
			if (WP_DEBUG_DISPLAY) {
				print '<p><span style="color:red">You should disable WP_DEBUG_DISPLAY mode or the port test may not work.';
			}
			print '<form id="port_test_form_id" method="post">';
			do_settings_sections ( PostmanAdminController::PORT_TEST_OPTIONS );
			// This prints out all hidden setting fields
			submit_button ( 'Begin Test', 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table">';
			// print '<tr><th>Port</th><th>State</th>';
			print '<tr><td class="port">Port 25</td><td id="port-test-port-25">Unknown</td>';
			print '<tr><td class="port">Port 465</td><td id="port-test-port-465">Unknown</td>';
			print '<tr><td class="port">Port 587</td><td id="port-test-port-587">Unknown</td>';
			print '</table>';
			print '</div>';
		}
		
		/**
		 */
		public function outputSendTestEmailContent() {
			print '<div class="wrap">';
			screen_icon ();
			print '<h2>' . PostmanAdminController::PAGE_TITLE . '</h2>';
			$this->displayTopNavigation ();
			// set default recipient for test emails
			$testEmail = $this->testOptions [PostmanOptions::TEST_EMAIL];
			if (! isset ( $testEmail )) {
				$this->testOptions [PostmanOptions::TEST_EMAIL] = wp_get_current_user ()->user_email;
			}
			// display the HTML
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			print '<input type="hidden" name="action" value="test_mail" />';
			do_settings_sections ( PostmanAdminController::POSTMAN_TEST_SLUG );
			if (! $this->options->isSendingEmailAllowed ( PostmanAuthorizationToken::getInstance () )) {
				$disabled = 'disabled="disabled"';
			} else {
				$disabled = '';
			}
			submit_button ( 'Send Test Email', 'primary', 'submit', true, $disabled );
			print '</form>';
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
					href="<?php echo POSTMAN_HOME_PAGE_ABSOLUTE_URL ?>&postman_action=start_wizard">Start
					the Wizard</a>
				<p class="">
					or, <a
						href="<?php echo POSTMAN_HOME_PAGE_ABSOLUTE_URL ?>&postman_action=configure_manually">configure
						manually</a>.
				</p>
			</div>
			<div class="welcome-panel-column">
				<h4>Actions</h4>
				<ul>
					<li><?php
			$emailCompany = 'Request OAuth Permission';
			if ($this->options->isSmtpHostGmail ()) {
				$emailCompany = 'Request permission from
								Google';
			} else if ($this->options->isSmtpHostHotmail ()) {
				$emailCompany = 'Request permission from
								Microsoft';
			}
			if ($this->options->isRequestOAuthPermissionAllowed ()) {
				printf ( '<a href="%s&postman_action=%s" class="welcome-icon send-test-email">%s</a>', POSTMAN_HOME_PAGE_ABSOLUTE_URL, PostmanAdminController::POSTMAN_REQUEST_OAUTH_PERMISSION_ACTION, $emailCompany );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print $emailCompany;
				print '</div>';
			}
			?></li>
					<li><a
						href="<?php echo POSTMAN_HOME_PAGE_ABSOLUTE_URL ?>&postman_action=delete_data"
						class="welcome-icon oauth-authorize">Delete plugin data</a></li>

				</ul>
			</div>
			<div class="welcome-panel-column welcome-panel-last">
				<h4>Troubleshooting</h4>
				<ul>
					<li><?php
			
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<a
							href="%s&postman_action=send_test_email"
							class="welcome-icon send_test_email">Send a Test Email</a>', POSTMAN_HOME_PAGE_ABSOLUTE_URL );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print 'Send a Test Email';
				print '</div>';
			}
			
			?></li>
					<li><a
						href="<?php echo POSTMAN_HOME_PAGE_ABSOLUTE_URL ?>&postman_action=run_port_test"
						class="welcome-icon run-port-test">Run a Port Test</a></li>
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
	<?php settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME ); ?>
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
			<label for="redirect_uri">Redirect URI</label><br />
			<?php echo $this->redirect_url_callback(); ?><br /> 
			<?php echo $this->encryption_type_for_oauth2_section_callback(); ?>
			<label for="client_id">Client ID</label><br />
			<?php echo $this->oauth_client_id_callback(); ?><br /> <label
				for="client_id">Client Secret</label> <br />
			<?php echo $this->oauth_client_secret_callback(); ?><br />
		</section>

		<section class="wizard-auth-basic">
			<p class="port-explanation-ssl">Enter your credentials to login to
				the SMTP server when sending mail. Your username is most likely your
				email address.</p>
			<p class="port-explanation-tls">Choose Basic (TLS) authentication to
				login to the SMTP server when sending mail. Your username is most
				likely your email address.</p>
			<p class="port-explanation-tls">Choose None for no authentication at
				all (not recommended).</p>
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
		<section class="wizard-auth-oauth2">
			<p>Once you click Finish below, these settings will be saved. Then at
				the main Postman Settings screen, be sure to:</p>
			<ul style='margin-left: 20px'>
				<li>Request permission from the Email Provider to allow Postman to
					send email and</li>
				<li>Send yourself a Test Email to make sure everything is working!</li>
			</ul>
		</section>
		<section class="wizard-auth-basic">
			<p>Once you click Finish below, these settings will be saved. Then at
				the main Postman Settings screen, be sure to send yourself a Test
				Email to make sure everything is working!</p>
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
		<p>This utility allows you to send an email for testing. It may take up to <?php echo PostmanMain::POSTMAN_TCP_READ_TIMEOUT * 2?> seconds to complete.</p>
		
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
				<label>Message</label>
			</p>
			<textarea id="postman_test_message_error_message" readonly="readonly"
				cols="70" rows="2"></textarea>
			<p>
				<label for="postman_test_message_transcript">Transcript</label>
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
