<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "SendTestEmailController.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanAuthorizationToken.php';
	require_once 'Wizard/PortTest.php';
	require_once 'Wizard/SmtpDiscovery.php';
	
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
		
		// page titles
		const NAME = 'Postman SMTP';
		const PAGE_TITLE = 'Postman SMTP Settings';
		const MENU_TITLE = 'Postman SMTP';
		
		// slugs
		const POSTMAN_TEST_SLUG = 'postman-test';
		
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
		
		//
		private $displayConfigure;
		private $displayPurgeData;
		private $displayTest;
		private $displayPortTest;
		private $displayWizard;
		
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
			
			// The Port Test Ajax handler
			if (is_admin ()) {
				// todo i think we need to add a lot more is_admin() around
				add_action ( 'wp_ajax_test_port', array (
						$this,
						'testPort' 
				) );
				add_action ( 'wp_ajax_check_email', array (
						$this,
						'checkEmail' 
				) );
			}
			
			add_action ( 'admin_menu', array (
					$this,
					'addLinkToWordPressSettingsAdminMenu' 
			) );
			add_action ( 'admin_init', array (
					$this,
					'initializeAdminPage' 
			) );
			
			add_action ( 'admin_post_test_mail', array (
					$this,
					'handleTestEmailAction' 
			) );
			
			add_action ( 'admin_post_purge_data', array (
					$this,
					'handlePurgeDataAction' 
			) );
			add_action ( 'admin_post_configure', array (
					$this,
					'handleConfigureAction' 
			) );
			if (isset ( $_SESSION ['postman_action'] )) {
				$action = $_SESSION ['postman_action'];
				unset ( $_SESSION ['postman_action'] );
				$this->logger->debug ( "Got Session action " . $action );
				if ($action == 'save_success') {
					unset ( $_GET ['postman_action'] );
				}
			}
			if (isset ( $_GET ['postman_action'] )) {
				$action = $_GET ['postman_action'];
				$this->logger->debug ( "Got Get action " . $action );
				if ($action == 'configure_manually') {
					$this->displayConfigure = true;
				} else if ($action == 'oauth_request_permission') {
					$this->handleGoogleAuthenticationAction ();
				} else if ($action == 'send_test_email') {
					$this->displayTest = true;
				} else if ($action == 'delete_data') {
					$this->displayPurgeData = true;
				} else if ($action == 'run_port_test') {
					$this->displayPortTest = true;
				} else if ($action == 'start_wizard') {
					$this->displayWizard = true;
				}
			}
		}
		public function handlePurgeDataAction() {
			delete_option ( PostmanOptions::POSTMAN_OPTIONS );
			delete_option ( PostmanAuthorizationToken::OPTIONS_NAME );
			delete_option ( PostmanAdminController::TEST_OPTIONS );
			header ( 'Location: ' . esc_url ( POSTMAN_HOME_PAGE_URL ) );
			exit ();
		}
		
		/**
		 * Add options page
		 */
		public function addLinkToWordPressSettingsAdminMenu() {
			// This page will be under "Settings"
			$page = add_options_page ( PostmanAdminController::PAGE_TITLE, PostmanAdminController::MENU_TITLE, 'manage_options', PostmanAdminController::POSTMAN_MENU_SLUG, array (
					$this,
					'generateHtml' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueStylesheet' 
			) );
		}
		/**
		 * Render the stylesheet
		 */
		function enqueueStylesheet() {
			wp_enqueue_style ( 'postman_style' );
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_script ( 'postman_script' );
		}
		public function handleTestEmailAction() {
			$this->logger->debug ( 'in handleTestEmailAction()' );
			$recipient = $_POST [PostmanAdminController::TEST_OPTIONS] ['test_email'];
			if (! empty ( $recipient )) {
				$testEmailController = new PostmanSendTestEmailController ();
				$testEmailController->send ( $this->options, $this->authorizationToken, $recipient, $this->messageHandler );
			}
			$this->logger->debug ( 'Redirecting to home page' );
			wp_redirect ( POSTMAN_HOME_PAGE_URL );
			exit ();
		}
		public function handleGoogleAuthenticationAction() {
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $this->options, $this->authorizationToken );
			$authenticationManager->requestVerificationCode ();
		}
		public function handleConfigureAction() {
			$this->logger->debug ( 'handle configure action' );
			$this->displayConfigure = true;
		}
		
		/**
		 * Register and add settings
		 */
		public function initializeAdminPage() {
			// register the stylesheet and javascript external resources
			wp_register_style ( 'postman_style', plugins_url ( 'style/postman.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery.steps.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			
			wp_register_script ( 'postman_script', plugins_url ( 'script/postman.js', __FILE__ ), array (
					'postman_wizard_script',
					'jquery',
					'jquery_steps_script',
					'jquery_validation' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery.steps.min.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery.validate.min.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_wizard_script', plugins_url ( 'script/postman_wizard.js', __FILE__ ), array (
					'jquery' 
			), POSTMAN_PLUGIN_VERSION );
			wp_localize_script ( 'postman_script', 'postman_smtp_section_element_name', 'div#smtp_section' );
			wp_localize_script ( 'postman_script', 'postman_oauth_section_element_name', 'div#oauth_section' );
			
			// user input
			wp_localize_script ( 'postman_script', 'postman_input_sender_email', '#input_' . PostmanOptions::SENDER_EMAIL );
			wp_localize_script ( 'postman_script', 'postman_port_element_name', '#input_' . PostmanOptions::PORT );
			wp_localize_script ( 'postman_script', 'postman_hostname_element_name', '#input_' . PostmanOptions::HOSTNAME );
			wp_localize_script ( 'postman_script', 'postman_enc_for_password_el', '#input_enc_type_' . PostmanOptions::AUTHENTICATION_TYPE_PASSWORD );
			wp_localize_script ( 'postman_script', 'postman_enc_for_oauth2_el', '#input_enc_type_' . PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			
			// the password inputs
			wp_localize_script ( 'postman_script', 'postman_input_basic_username', '#input_basic_auth_' . PostmanOptions::BASIC_AUTH_USERNAME );
			wp_localize_script ( 'postman_script', 'postman_input_basic_password', '#input_basic_auth_' . PostmanOptions::BASIC_AUTH_PASSWORD );
			
			// the auth input
			wp_localize_script ( 'postman_script', 'postman_input_auth_type', '#input_' . PostmanOptions::AUTHENTICATION_TYPE );
			wp_localize_script ( 'postman_script', 'postman_auth_none', PostmanOptions::AUTHENTICATION_TYPE_NONE );
			wp_localize_script ( 'postman_script', 'postman_auth_login', PostmanOptions::AUTHENTICATION_TYPE_LOGIN );
			wp_localize_script ( 'postman_script', 'postman_auth_plain', PostmanOptions::AUTHENTICATION_TYPE_PLAIN );
			wp_localize_script ( 'postman_script', 'postman_auth_crammd5', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 );
			wp_localize_script ( 'postman_script', 'postman_auth_oauth2', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 );
			
			//
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
					$this,
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
			
			add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, 'Basic Auth Settings', array (
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
			add_settings_section ( PostmanAdminController::OAUTH_SECTION, 'OAuth Settings', array (
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
			
			// add_settings_field ( 'prevent_sender_name_override', 'Prevent other Plugins from Overriding the Sender', array (
			// $this,
			// 'prevent_sender_name_override_callback'
			// ), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
			
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
					$this,
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
		 * Sanitize each setting field as needed
		 *
		 * @param array $input
		 *        	Contains all settings fields as array keys
		 */
		public function sanitize($input) {
			$this->logger->debug ( "Sanitizing data before storage" );
			
			$new_input = array ();
			$success = true;
			
			$this->sanitizeString ( 'Encryption Type', PostmanOptions::ENCRYPTION_TYPE, $input, $new_input );
			$this->sanitizeString ( 'Hostname', PostmanOptions::HOSTNAME, $input, $new_input );
			if (isset ( $input [PostmanOptions::PORT] )) {
				$port = absint ( $input [PostmanOptions::PORT] );
				if ($port > 0) {
					$this->sanitizeInt ( 'Port', PostmanOptions::PORT, $input, $new_input );
				} else {
					$new_input [PostmanOptions::PORT] = $this->options->getPort ();
					add_settings_error ( PostmanOptions::PORT, PostmanOptions::PORT, 'Invalid TCP Port', 'error' );
					$success = false;
				}
			}
			// check the auth type AFTER the hostname because we reset the hostname if auth is bad
			if (isset ( $input [PostmanOptions::AUTHENTICATION_TYPE] )) {
				$newAuthType = $input [PostmanOptions::AUTHENTICATION_TYPE];
				$this->logger->debug ( 'Sanitize Authorization Type ' . $newAuthType );
				if ($newAuthType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2) {
					if (isset ( $input [PostmanOptions::HOSTNAME] ) && PostmanSmtpHostProperties::isOauthHost ( $input [PostmanOptions::HOSTNAME] )) {
						$this->sanitizeString ( 'Authorization Type', PostmanOptions::AUTHENTICATION_TYPE, $input, $new_input );
					} else {
						$new_input [PostmanOptions::AUTHENTICATION_TYPE] = $this->options->getAuthorizationType ();
						$new_input [PostmanOptions::HOSTNAME] = $this->options->getHostname ();
						add_settings_error ( PostmanOptions::AUTHENTICATION_TYPE, PostmanOptions::AUTHENTICATION_TYPE, 'Your host must either be Gmail or Windows Live to enable OAuth2', 'error' );
						$success = false;
					}
				}
			}
			$this->sanitizeString ( 'Sender Name', PostmanOptions::SENDER_NAME, $input, $new_input );
			$this->sanitizeString ( 'Client ID', PostmanOptions::CLIENT_ID, $input, $new_input );
			$this->sanitizeString ( 'Client Secret', PostmanOptions::CLIENT_SECRET, $input, $new_input );
			$this->sanitizeString ( 'Username', PostmanOptions::BASIC_AUTH_USERNAME, $input, $new_input );
			$this->sanitizeString ( 'Password', PostmanOptions::BASIC_AUTH_PASSWORD, $input, $new_input );
			$this->sanitizeString ( 'Reply-To', PostmanOptions::REPLY_TO, $input, $new_input );
			$this->sanitizeString ( 'Sender Name Override', PostmanOptions::PREVENT_SENDER_NAME_OVERRIDE, $input, $new_input );
			
			if (isset ( $input [PostmanOptions::SENDER_EMAIL] )) {
				$newEmail = $input [PostmanOptions::SENDER_EMAIL];
				$this->logger->debug ( 'Sanitize Sender Email ' . $newEmail );
				if ($this->validateEmail ( $newEmail )) {
					$new_input [PostmanOptions::SENDER_EMAIL] = sanitize_text_field ( $newEmail );
				} else {
					$new_input [PostmanOptions::SENDER_EMAIL] = $this->options->getSenderEmail ();
					add_settings_error ( PostmanOptions::SENDER_EMAIL, PostmanOptions::SENDER_EMAIL, 'You have entered an invalid e-mail address', 'error' );
					$success = false;
				}
			}
			
			// set a request parameter
			if ($success) {
				$this->logger->debug ( 'Validation Success' );
				$_SESSION ['postman_action'] = 'save_success';
			} else {
				$this->logger->debug ( 'Validation Failure' );
				$_SESSION ['postman_action'] = 'save_failure';
			}
			return $new_input;
		}
		private function sanitizeString($desc, $key, $input, &$new_input) {
			if (isset ( $input [$key] )) {
				$this->logSanitize ( $desc, $input [$key] );
				$new_input [$key] = sanitize_text_field ( $input [$key] );
			}
		}
		private function sanitizeInt($desc, $key, $input, &$new_input) {
			if (isset ( $input [$key] )) {
				$this->logSanitize ( $desc, $input [$key] );
				$new_input [$key] = absint ( $input [$key] );
			}
		}
		private function logSanitize($desc, $value) {
			$this->logger->debug ( 'Sanitize ' . $desc . ' ' . $value );
		}
		
		//
		private function setWizardDefaults() {
			$this->options->setSenderEmailIfEmpty ( wp_get_current_user ()->user_email );
			$this->options->setSenderNameIfEmpty ( wp_get_current_user ()->display_name );
		}
		
		/**
		 * Options page callback
		 */
		public function generateHtml() {
			
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
			if ($this->displayConfigure) {
				$this->generateManuallyConfigureHtml ();
			} else if ($this->displayTest) {
				$this->displayTest ();
			} else if ($this->displayPurgeData) {
				$this->displayPurgeData ();
			} else if ($this->displayPortTest) {
				$this->generatePortTestHtml ();
			} else if ($this->displayWizard) {
				$this->generateWizardHtml ();
			} else {
				$this->generateStatusHtml ();
			}
			print '</div>';
		}
		
		/**
		 */
		public function generateStatusHtml() {
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				print '<p><span style="color:green;padding:2px 5px; font-size:1.2em">Postman is configured.</span><p style="margin:0 10px">Sending mail from <b>' . $this->options->getSenderEmail () . '</b> via <b>' . $this->options->getHostname () . '</b>';
				if ($this->options->getAuthorizationType () == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2) {
					print ' using OAuth 2.0 authentication.</span></p>';
					print '<p style="margin:10px 10px"><span>Please note: <em>Plugins are forbidden from overriding the sender email address in OAuth 2.0 mode</em>.</span></p>';
				} else if ($this->options->getAuthorizationType () == PostmanOptions::ENCRYPTION_TYPE_SSL) {
					print ' using Basic authentication.</span></p>';
				} else if ($this->options->getAuthorizationType () == PostmanOptions::ENCRYPTION_TYPE_TLS) {
					print ' using Basic authentication.</span></p>';
				} else {
					print ' using no authentication.</span></p>';
				}
				if ($this->options->getAuthorizationType () != PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 && $this->options->getHostname () == PostmanGmailAuthenticationManager::SMTP_HOSTNAME) {
					print '<p><span style="color:yellow; background-color:#AAA; padding:2px 5px; font-size:1.2em">Warning</span><p><span style="margin:0 10px">Google may silently discard messages sent with basic authentication. Change your authentication type to OAuth 2.0.</span></p>';
				}
			} else if ($this->options->isPermissionNeeded ( $this->authorizationToken )) {
				print '<p><span style="color:red; padding:2px 5px; font-size:1.1em">Status: You have entered a Client ID and Client Secret, but you have not received permission from Google.</span></p>';
			} else {
				print '<p><span style="color:red; padding:2px 5px; font-size:1.1em">Status: Postman is not properly configured.</span></p>';
			}
		}
		function checkEmail() {
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
		 */
		public function generateManuallyConfigureHtml() {
			print '<form method="post" action="options.php">';
			// This prints out all hidden setting fields
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			do_settings_sections ( PostmanAdminController::SMTP_OPTIONS );
			$authType = $this->options->getAuthorizationType ();
			if (isset ( $authType ) && $authType != PostmanOptions::AUTHENTICATION_TYPE_NONE) {
				print '<div id="smtp_section">';
				do_settings_sections ( PostmanAdminController::BASIC_AUTH_OPTIONS );
				print ('</div>') ;
				print '<div id="oauth_section">';
				do_settings_sections ( PostmanAdminController::OAUTH_OPTIONS );
				print ('</div>') ;
				do_settings_sections ( PostmanAdminController::ADVANCED_OPTIONS );
			}
			submit_button ();
			print '</form>';
		}
		
		/**
		 */
		public function displayPurgeData() {
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			print '<input type="hidden" name="action" value="purge_data" />';
			do_settings_sections ( 'PURGE_DATA' );
			submit_button ( 'Delete All Data', 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			print '</form>';
		}
		
		/**
		 */
		public function generatePortTestHtml() {
			if (WP_DEBUG_DISPLAY) {
				print '<p><span style="color:red">You should disable WP_DEBUG_DISPLAY mode or the port test may not work.';
			}
			print '<form method="post">';
			do_settings_sections ( PostmanAdminController::PORT_TEST_OPTIONS );
			// This prints out all hidden setting fields
			submit_button ( 'Begin Test', 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="testing_table" style="width:300px; font-size:1.2em; display:none">';
			// print '<tr><th>Port</th><th>State</th>';
			print '<tr><td>Port 25</td><td id="port-test-port-25">Unknown</td>';
			print '<tr><td>Port 465</td><td id="port-test-port-465">Unknown</td>';
			print '<tr><td>Port 587</td><td id="port-test-port-587">Unknown</td>';
			print '</table>';
		}
		
		/**
		 */
		public function displayTest() {
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
		}
		// Same handler function...
		function testPort() {
			$hostname = $_POST ['hostname'];
			$port = intval ( $_POST ['port'] );
			if (isset ( $_POST ['timeout'] )) {
				$timeout = intval ( $_POST ['timeout'] );
			} else {
				$timeout = 20;
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
		 * Validate an email address
		 *
		 * @param unknown $email        	
		 * @return number
		 */
		public function validateEmail($email) {
			$exp = "/^[a-z\'0-9]+([._-][a-z\'0-9]+)*@([a-z0-9]+([._-][a-z0-9]+))+$/i";
			return preg_match ( $exp, $email );
		}
		
		/**
		 * Sanitize each setting field as needed
		 *
		 * @param array $input
		 *        	Contains all settings fields as array keys
		 */
		public function testSanitize($input) {
			$new_input = array ();
			
			if (isset ( $input ['test_email'] ))
				$new_input ['test_email'] = sanitize_text_field ( $input ['test_email'] );
			
			return $new_input;
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
			print '<p><span>This test determines which ports are open for Postman to use. A failed test indicates either that your host has placed a firewall between this site and the SMTP server, or that the SMTP server has no service running on that port.</span></p><p><span>Each test is given twenty seconds to complete and the entire test will take up to one minute to run. Javascript is required.</span></p>';
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
			if ($this->options->getHostname () == PostmanGmailAuthenticationManager::SMTP_HOSTNAME) {
				print 'You can create a Client ID for your Gmail account at the <a href="https://console.developers.google.com/">Google Developers Console</a> (Use ). The Redirect URI to use is show below. There are <a href="https://wordpress.org/plugins/postman-smtp/installation/">additional instructions</a> on the Postman homepage.';
				print ' Note: Gmail will NOT let you send from any email address <b>other than your own</b>.';
			} else {
				print 'You can create a Client ID for your Hotmail account at the <a href="https://account.live.com/developers/applications/create">Microsoft account Developer Center</a>. In API Settings, use the Redirect URL shown below. Then copy the Client ID and Client Secret from App Settings to here. There are <a href="https://wordpress.org/plugins/postman-smtp/installation/">additional instructions</a> on the Postman homepage.';
				print ' Note: Hotmail will NOT let you send from any email address <b>other than your own</b>.';
			}
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
			print '<select id="input_enc_type_' . $section . '" class="input_authorization_type" name="postman_options[enc_type]">';
			print '<option id="input_enc_type_none" value="' . PostmanOptions::ENCRYPTION_TYPE_NONE . '"';
			printf ( '%s', $authType == PostmanOptions::ENCRYPTION_TYPE ? 'selected="selected"' : '' );
			print '>None</option>';
			print '<option id="input_enc_type_ssl" value="' . PostmanOptions::ENCRYPTION_TYPE_SSL . '"';
			printf ( '%s', $authType == PostmanOptions::ENCRYPTION_TYPE_SSL ? 'selected="selected"' : '' );
			print '>SSL</option>';
			print '<option id="input_enc_type_tls" value="' . PostmanOptions::ENCRYPTION_TYPE_TLS . '"';
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
			printf ( '<input type="text" id="input_sender_name" name="postman_options[sender_name]" value="%s"/>', null !== $this->options->getSenderName () ? esc_attr ( $this->options->getSenderName () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="text" id="input_sender_email" name="postman_options[sender_email]" value="%s" class="required email"/>', null !== $this->options->getSenderEmail () ? esc_attr ( $this->options->getSenderEmail () ) : '' );
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
			printf ( '<input type="text" readonly="readonly" id="input_connection_timeout" name="postman_options[connection_timeout]" value="30" />' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function read_timeout_callback() {
			printf ( '<input type="text" readonly="readonly" id="input_read_timeout" name="postman_options[read_timeout]" value="30" />' );
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
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" />', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
		
		/**
		 */
		public function displayTopNavigation() {
			?>
<div id="welcome-panel" class="welcome-panel">
	<div class="welcome-panel-content">
		<div class="welcome-panel-column-container">
			<div class="welcome-panel-column">
				<h4>Get Started</h4>
				<a
					class="button button-primary button-hero load-customize hide-if-no-customize"
					href="<?php echo POSTMAN_HOME_PAGE_URL ?>&postman_action=start_wizard">Start
					the Wizard</a>
				<p class="hide-if-no-customize">
					or, <a
						href="<?php echo POSTMAN_HOME_PAGE_URL ?>&postman_action=configure_manually">configure
						manually</a>.
				</p>
			</div>
			<div class="welcome-panel-column">
				<h4>Actions</h4>
				<ul>
					<li><a
						href="<?php echo POSTMAN_HOME_PAGE_URL ?>&postman_action=delete_data"
						class="welcome-icon oauth-authorize">Delete plugin data</a></li>
					<li><?php
			$emailCompany = 'Request OAuth Permission';
			if ($this->options->getHostname () == PostmanGmailAuthenticationManager::SMTP_HOSTNAME) {
				$emailCompany = 'Request Permission from
								Google';
			} else if ($this->options->getHostname () == PostmanHotmailAuthenticationManager::SMTP_HOSTNAME) {
				$emailCompany = 'Request Permission from
								Microsoft';
			}
			if ($this->options->isRequestOAuthPermissionAllowed ()) {
				printf ( '<a
							href="%s&postman_action=oauth_request_permission"
							class="welcome-icon send-test-email">' . $emailCompany . '</a>', POSTMAN_HOME_PAGE_URL );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print $emailCompany;
				print '</div>';
			}
			?></li>

				</ul>
			</div>
			<div class="welcome-panel-column welcome-panel-last">
				<h4>Troubleshooting</h4>
				<ul>
					<li><a href="https://wordpress.org/support/plugin/postman-smtp"
						class="welcome-icon postman_support">Postman Support Forum</a></li>
					<li><a
						href="<?php echo POSTMAN_HOME_PAGE_URL ?>&postman_action=run_port_test"
						class="welcome-icon run-port-test">Run a Port Test</a></li>
					<li><?php
			
			if ($this->options->isSendingEmailAllowed ( $this->authorizationToken )) {
				printf ( '<a
							href="%s&postman_action=send_test_email"
							class="welcome-icon send_test_email">Send an Email</a>', POSTMAN_HOME_PAGE_URL );
			} else {
				print '<div class="welcome-icon send_test_emaail">';
				print 'Send a Test Email';
				print '</div>';
			}
			
			?></li>
				</ul>
			</div>
		</div>
	</div>
</div><?php
		}
		/**
		 */
		public function generateWizardHtml() {
			// Set class property
			$this->setWizardDefaults ();
			
			?>
<h3>Postman Setup Wizard</h3>

<form id="postman_wizard" method="post" action="options.php">
	<?php settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME ); ?>
	<h1>Email Address</h1>
	<fieldset>
		<legend>Enter your Email Address </legend>
		<p>Let's begin! Please enter the email address and name you'd like to
			send mail from.</p>
		<p>
			Please note that to reduce Spam, many email services will <em>not</em>
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
		<p>Your email provider will dictate which port to use. Normally, Port
			25 (SMTP) is plaintext, Port 465 (SMTPS-SSL) is encrypted and Port
			587 (SMTPS-TLS/STARTTLS) offers both.</p>

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
			<p>
			<?php if($this->options->getHostname() == 'smtp.gmail.com') {?>
				Open the <a href="https://console.developers.google.com/"
					target="_new">Google Developer Console</a>, create a Client ID
				using the Redirect URI below, and enter the Client ID and Client
				Secret. See <a
					href="https://wordpress.org/plugins/postman-smtp/faq/"
					target="_new">How do I get a Google Client ID?</a> in the F.A.Q.
				for help.
			<?php } else { ?>
				Open the <a
					href="https://account.live.com/developers/applications/create"
					target="_new">Microsoft Developer Center</a>, create an Application
				using the Redirect URI below, and enter the Client ID and Client
				Secret. See <a
					href="https://wordpress.org/plugins/postman-smtp/faq/"
					target="_new">How do I get a Windows Live Client ID?</a> in the F.A.Q.
				for help.
			<?php } ?>
			</p>
			<label for="redirect_uri">Redirect URI</label><br />
			<?php echo $this->redirect_url_callback(); ?><br /> <label
				for="client_id">Client ID</label><br />
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
				<li>Request Permission from Google to allow Postman to send email
					and</li>
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
	}
}
