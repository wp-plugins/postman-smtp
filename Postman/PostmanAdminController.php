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
	require_once 'PostmanAjaxController.php';
	require_once 'PostmanViewController.php';
	require_once 'PostmanPreRequisitesCheck.php';
	
	//
	class PostmanAdminController {
		public static function getActionUrl($slug) {
			return get_admin_url () . 'admin-post.php?action=' . $slug;
		}
		public static function getPageUrl($slug) {
			return get_admin_url () . 'options-general.php?page=' . $slug;
		}
		
		// this is the slug used in the URL
		const REQUEST_OAUTH2_GRANT_SLUG = 'postman/requestOauthGrant';
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
		
		// slugs
		const POSTMAN_TEST_SLUG = 'postman-test';
		
		// logging
		private $logger;
		
		// Holds the values to be used in the fields callbacks
		private $basename;
		private $options;
		private $authorizationToken;
		private $importableConfiguration;
		
		// helpers
		private $messageHandler;
		private $oauthScribe;
		private $wpMailBinder;
		
		/**
		 * Start up
		 */
		public function __construct($basename, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanMessageHandler $messageHandler, PostmanWpMailBinder $binder) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->messageHandler = $messageHandler;
			$this->basename = $basename;
			$this->wpMailBinder = $binder;
			
			// check if the user saved data, and if validation was successful
			$session = PostmanSession::getInstance ();
			if ($session->isSetAction ()) {
				$this->logger->debug ( sprintf ( 'session action: %s', $session->getAction () ) );
			}
			if ($session->getAction () == PostmanInputSanitizer::VALIDATION_SUCCESS) {
				$session->unsetAction ();
				$this->registerInitFunction ( 'handleSuccessfulSave' );
				$this->messageHandler->addMessage ( _x ( 'Settings saved.', 'The plugin successfully saved new settings.', 'postman-smtp' ) );
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
			new PostmanManageConfigurationAjaxHandler ();
			new PostmanGetHostnameByEmailAjaxController ();
			new PostmanGetPortsToTestViaAjax ();
			new PostmanPortTestAjaxController ( $this->options );
			new PostmanImportConfigurationAjaxController ( $this->options );
			new PostmanGetDiagnosticsViaAjax ( $this->options, $this->authorizationToken );
			new PostmanSendTestEmailAjaxController ( $this->options, $this->authorizationToken, $this->oauthScribe );
			
			// register content handlers
			$viewController = new PostmanViewController ( $this->options, $this->authorizationToken, $this->oauthScribe, $this );
			
			// register action handlers
			$this->registerAdminPostAction ( self::PURGE_DATA_SLUG, 'handlePurgeDataAction' );
			$this->registerAdminPostAction ( self::REQUEST_OAUTH2_GRANT_SLUG, 'handleOAuthPermissionRequestAction' );
			
			add_action ( 'wp_dashboard_setup', array (
					$this,
					'example_add_dashboard_widgets' 
			) );
			
			if (isset ( $_REQUEST ['page'] ) && substr ( $_REQUEST ['page'], 0, 7 ) == 'postman') {
				$this->checkPreRequisites ();
			}
		}
		private function checkPreRequisites() {
			$states = PostmanPreRequisitesCheck::getState ();
			foreach ( $states as $state ) {
				if (! $state ['ready']) {
					$message = sprintf ( __ ( 'This PHP installation needs <b>%1$s</b>.' ), $state ['name'] );
					if ($state ['required']) {
						$this->messageHandler->addError ( $message );
					} else {
						// $this->messageHandler->addWarning ( $message );
					}
				}
			}
		}
		
		/**
		 * Add a widget to the dashboard.
		 *
		 * This function is hooked into the 'wp_dashboard_setup' action below.
		 */
		public function example_add_dashboard_widgets() {
			wp_add_dashboard_widget ( 'example_dashboard_widget', _x ( 'Postman SMTP', 'Postman Dashboard  Widget Title', 'postman-smtp' ), array (
					$this,
					'example_dashboard_widget_function' 
			) ); // Display function.
		}
		
		/**
		 * Create the function to output the contents of our Dashboard Widget.
		 */
		public function example_dashboard_widget_function() {
			$goToSettings = sprintf ( '[<a href="%s">%s</a>]', POSTMAN_HOME_PAGE_ABSOLUTE_URL, _x ( 'Settings', 'Dashboard Widget Settings Link label', 'postman-smtp' ) );
			if (! PostmanPreRequisitesCheck::isReady ()) {
				printf ( '<p><span style="color:red">%s</span> %s</p>', __ ( 'Postman is missing a required PHP library.', 'postman-smtp' ), $goToSettings );
			} else if ($this->wpMailBinder->isUnboundDueToException ()) {
				printf ( '<p><span style="color:red">%s</span></p>', __ ( 'Postman is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', 'postman-smtp' ) );
			} else {
				if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
					printf ( '<p class="wp-menu-image dashicons-before dashicons-email"> %s</p>', sprintf ( _n ( '<span style="color:green">Postman is configured</span> and has delivered <span style="color:green">%d</span> email.', '<span style="color:green">Postman is configured</span> and has delivered <span style="color:green">%d</span> emails.', PostmanStats::getInstance ()->getSuccessfulDeliveries (), 'postman-smtp' ), PostmanStats::getInstance ()->getSuccessfulDeliveries () ) );
					$currentTransport = PostmanTransportUtils::getCurrentTransport ();
					$deliveryDetails = $currentTransport->getDeliveryDetails ( $this->options );
					printf ( '<p>%s %s</p>', $deliveryDetails, $goToSettings );
				} else {
					printf ( '<p><span style="color:red">%s</span> %s</p>', __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ), $goToSettings );
				}
			}
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
			$this->messageHandler->addMessage ( __ ( 'All plugin settings were removed.', 'postman-smtp' ) );
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
			
			add_settings_field ( PostmanOptions::PORT, _x ( 'SMTP Server Port', 'Configuration Input Field', 'postman-smtp' ), array (
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
			printf ( '<input type="text" id="input_test_email" name="postman_test_options[test_email]" value="%s" class="required email" size="40"/>', wp_get_current_user ()->user_email );
		}
	}
}