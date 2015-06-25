<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once 'PostmanOptions.php';
	require_once 'PostmanState.php';
	require_once 'PostmanStats.php';
	require_once 'PostmanOAuthToken.php';
	require_once 'Postman-Wizard/Postman-PortTest.php';
	require_once 'Postman-Wizard/PostmanSmtpDiscovery.php';
	require_once 'PostmanInputSanitizer.php';
	require_once 'Postman-Connectors/PostmanImportableConfiguration.php';
	require_once 'PostmanConfigTextHelper.php';
	require_once 'PostmanAjaxController.php';
	require_once 'PostmanViewController.php';
	require_once 'PostmanPreRequisitesCheck.php';
	require_once 'Postman-Auth/PostmanAuthenticationManagerFactory.php';
	
	//
	class PostmanAdminController {
		
		// this is the slug used in the URL
		const PURGE_DATA_SLUG = 'postman/purge_data';
		const DEFAULT_PORT_TEST_SMTP_HOSTNAME = 'smtp.gmail.com';
		
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
		const MESSAGE_SENDER_OPTIONS = 'postman_message_sender_options';
		const MESSAGE_SENDER_SECTION = 'postman_message_sender_section';
		const MESSAGE_FROM_OPTIONS = 'postman_message_from_options';
		const MESSAGE_FROM_SECTION = 'postman_message_from_section';
		const MESSAGE_OPTIONS = 'postman_message_options';
		const MESSAGE_SECTION = 'postman_message_section';
		const MESSAGE_HEADERS_OPTIONS = 'postman_message_headers_options';
		const MESSAGE_HEADERS_SECTION = 'postman_message_headers_section';
		const NETWORK_OPTIONS = 'postman_network_options';
		const NETWORK_SECTION = 'postman_network_section';
		const LOGGING_OPTIONS = 'postman_logging_options';
		const LOGGING_SECTION = 'postman_logging_section';
		const MULTISITE_OPTIONS = 'postman_multisite_options';
		const MULTISITE_SECTION = 'postman_multisite_section';
		const ADVANCED_OPTIONS = 'postman_advanced_options';
		const ADVANCED_SECTION = 'postman_advanced_section';
		
		// slugs
		const POSTMAN_TEST_SLUG = 'postman-test';
		
		// logging
		private $logger;
		
		// Holds the values to be used in the fields callbacks
		private $rootPluginFilenameAndPath;
		private $options;
		private $authorizationToken;
		private $importableConfiguration;
		
		// helpers
		private $messageHandler;
		private $oauthScribe;
		private $wpMailBinder;
		
		/**
		 * Constructor
		 *
		 * @param unknown $rootPluginFilenameAndPath        	
		 * @param PostmanOptions $options        	
		 * @param PostmanOAuthToken $authorizationToken        	
		 * @param PostmanMessageHandler $messageHandler        	
		 * @param PostmanWpMailBinder $binder        	
		 */
		public function __construct($rootPluginFilenameAndPath, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanMessageHandler $messageHandler, PostmanWpMailBinder $binder) {
			assert ( ! empty ( $rootPluginFilenameAndPath ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			assert ( ! empty ( $binder ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->messageHandler = $messageHandler;
			$this->rootPluginFilenameAndPath = $rootPluginFilenameAndPath;
			$this->wpMailBinder = $binder;
			
			// check if the user saved data, and if validation was successful
			$session = PostmanSession::getInstance ();
			if ($session->isSetAction ()) {
				$this->logger->debug ( sprintf ( 'session action: %s', $session->getAction () ) );
			}
			if ($session->getAction () == PostmanInputSanitizer::VALIDATION_SUCCESS) {
				// unset the action
				$session->unsetAction ();
				// do a redirect on the init hook
				$this->registerInitFunction ( 'handleSuccessfulSave' );
				// add a saved message to be shown after the redirect
				$this->messageHandler->addMessage ( _x ( 'Settings saved.', 'The plugin successfully saved new settings.', 'postman-smtp' ) );
				return;
			} else {
				// unset the action in the failed case as well
				$session->unsetAction ();
			}
			
			// test to see if an OAuth authentication is in progress
			if ($session->isSetOauthInProgress ()) {
				// there is only a three minute window that Postman will expect a Grant Code, once Grant is clicked by the user
				$this->logger->debug ( 'Looking for grant code' );
				if (isset ( $_GET ['code'] )) {
					$this->logger->debug ( 'Found authorization grant code' );
					// queue the function that processes the incoming grant code
					$this->registerInitFunction ( 'handleAuthorizationGrant' );
					return;
				}
			}
			
			// continue to initialize the AdminController
			add_action ( 'init', array (
					$this,
					'registerHooks' 
			) );
			
			// Adds "Settings" link to the plugin action page
			add_filter ( 'plugin_action_links_' . plugin_basename ( $this->rootPluginFilenameAndPath ), array (
					$this,
					'postmanModifyLinksOnPluginsListPage' 
			) );
			
			// initialize the scripts, stylesheets and form fields
			add_action ( 'admin_init', array (
					$this,
					'initializeAdminPage' 
			) );
		}
		
		/**
		 */
		public function registerHooks() {
			// only administrators should be able to trigger this
			if (PostmanUtils::isAdmin ()) {
				//
				$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
				$this->oauthScribe = PostmanConfigTextHelperFactory::createScribe ( $this->options->getHostname (), $transport );
				
				// register Ajax handlers
				new PostmanManageConfigurationAjaxHandler ();
				new PostmanGetHostnameByEmailAjaxController ();
				new PostmanGetPortsToTestViaAjax ();
				new PostmanPortTestAjaxController ( $this->options );
				new PostmanImportConfigurationAjaxController ( $this->options );
				new PostmanGetDiagnosticsViaAjax ( $this->options, $this->authorizationToken );
				new PostmanSendTestEmailAjaxController ();
				
				// register content handlers
				$viewController = new PostmanViewController ( $this->rootPluginFilenameAndPath, $this->options, $this->authorizationToken, $this->oauthScribe, $this );
				
				// register action handlers
				$this->registerAdminPostAction ( self::PURGE_DATA_SLUG, 'handlePurgeDataAction' );
				$this->registerAdminPostAction ( PostmanUtils::REQUEST_OAUTH2_GRANT_SLUG, 'handleOAuthPermissionRequestAction' );
				
				if (PostmanUtils::isCurrentPagePostmanAdmin ()) {
					$this->checkPreRequisites ();
				}
			}
		}
		private function checkPreRequisites() {
			$states = PostmanPreRequisitesCheck::getState ();
			foreach ( $states as $state ) {
				if (! $state ['ready']) {
					/* Translators: where %1$s is the name of the library */
					$message = sprintf ( __ ( 'This PHP installation requires the <b>%1$s</b> library.', 'postman-smtp' ), $state ['name'] );
					if ($state ['required']) {
						$this->messageHandler->addError ( $message );
					} else {
						// $this->messageHandler->addWarning ( $message );
					}
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
			// only administrators should be able to trigger this
			if (PostmanUtils::isAdmin ()) {
				$mylinks = array (
						sprintf ( '<a href="%s" class="postman_settings">%s</a>', PostmanUtils::getSettingsPageUrl (), _x ( 'Settings', 'The configuration page of the plugin', 'postman-smtp' ) ) 
				);
				return array_merge ( $mylinks, $links );
			}
		}
		
		/**
		 * This function runs after a successful, error-free save
		 */
		public function handleSuccessfulSave() {
			// WordPress likes to keep GET parameters around for a long time
			// (something in the call to settings_fields() does this)
			// here we redirect after a successful save to clear those parameters
			PostmanUtils::redirect ( PostmanUtils::POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		public function handlePurgeDataAction() {
			if (wp_verify_nonce ( $_REQUEST ['_wpnonce'], 'purge-data' )) {
				$this->logger->debug ( 'Purging stored data' );
				delete_option ( PostmanOptions::POSTMAN_OPTIONS );
				delete_option ( PostmanOAuthToken::OPTIONS_NAME );
				delete_option ( PostmanAdminController::TEST_OPTIONS );
				$logPurger = new PostmanEmailLogPurger ();
				$logPurger->removeAll ();
				$this->messageHandler->addMessage ( __ ( 'Plugin data was removed.', 'postman-smtp' ) );
				PostmanUtils::redirect ( PostmanUtils::POSTMAN_HOME_PAGE_RELATIVE_URL );
			} else {
				$this->logger->warn ( sprintf ( 'nonce "%s" failed validation', $_REQUEST ['_wpnonce'] ) );
			}
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
			
			// begin transaction
			PostmanUtils::lock ();
			
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( PostmanTransportRegistry::getInstance ()->getCurrentTransport (), $options, $authorizationToken );
			try {
				if ($authenticationManager->processAuthorizationGrantCode ( $transactionId )) {
					$logger->debug ( 'Authorization successful' );
					// save to database
					$authorizationToken->save ();
					$this->messageHandler->addMessage ( __ ( 'The OAuth 2.0 authorization was successful. Ready to send e-mail.', 'postman-smtp' ) );
				} else {
					$this->messageHandler->addError ( __ ( 'Your email provider did not grant Postman permission. Try again.', 'postman-smtp' ) );
				}
			} catch ( PostmanStateIdMissingException $e ) {
				$this->messageHandler->addError ( __ ( 'The grant code from Google had no accompanying state and may be a forgery', 'postman-smtp' ) );
			} catch ( Exception $e ) {
				$logger->error ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
				/* translators: %s is the error message */
				$this->messageHandler->addError ( sprintf ( __ ( 'Error authenticating with this Client ID. [%s]', 'postman-smtp' ), '<em>' . $e->getMessage () . '</em>' ) );
			}
			
			// clean-up
			PostmanUtils::unlock ();
			PostmanSession::getInstance ()->unsetOauthInProgress ();
			
			// redirect home
			PostmanUtils::redirect ( PostmanUtils::POSTMAN_HOME_PAGE_RELATIVE_URL );
		}
		
		/**
		 * This method is called when a user clicks on a "Request Permission from Google" link.
		 * This link will create a remote API call for Google and redirect the user from WordPress to Google.
		 * Google will redirect back to WordPress after the user responds.
		 */
		public function handleOAuthPermissionRequestAction() {
			$this->logger->debug ( 'handling OAuth Permission request' );
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( PostmanTransportRegistry::getInstance ()->getCurrentTransport (), $this->options, $this->authorizationToken );
			$transactionId = $authenticationManager->generateRequestTransactionId ();
			PostmanSession::getInstance ()->setOauthInProgress ( $transactionId );
			$authenticationManager->requestVerificationCode ( $transactionId );
		}
		
		/**
		 * Register and add settings
		 */
		public function initializeAdminPage() {
			
			// only administrators should be able to trigger this
			if (PostmanUtils::isAdmin ()) {
				//
				$sanitizer = new PostmanInputSanitizer ( $this->options );
				register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
						$sanitizer,
						'sanitize' 
				) );
				
				// Sanitize
				add_settings_section ( 'transport_section', _x ( 'Transport', 'The Transport is the method for sending mail, SMTP or API', 'postman-smtp' ), array (
						$this,
						'printTransportSectionInfo' 
				), 'transport_options' );
				
				add_settings_field ( PostmanOptions::TRANSPORT_TYPE, _x ( 'Transport', 'The Transport is the method for sending mail, SMTP or API', 'postman-smtp' ), array (
						$this,
						'transport_type_callback' 
				), 'transport_options', 'transport_section' );
				
				// Sanitize
				add_settings_section ( PostmanAdminController::SMTP_SECTION, _x ( 'Transport Settings', 'Configuration Section Title', 'postman-smtp' ), array (
						$this,
						'printSmtpSectionInfo' 
				), PostmanAdminController::SMTP_OPTIONS );
				
				add_settings_field ( PostmanOptions::HOSTNAME, __ ( 'Outgoing Mail Server Hostname', 'postman-smtp' ), array (
						$this,
						'hostname_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
				
				add_settings_field ( PostmanOptions::PORT, __ ( 'Outgoing Mail Server Port', 'postman-smtp' ), array (
						$this,
						'port_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
				
				add_settings_field ( PostmanOptions::ENCRYPTION_TYPE, _x ( 'Security', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'encryption_type_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
				
				add_settings_field ( PostmanOptions::AUTHENTICATION_TYPE, _x ( 'Authentication', 'Authentication proves the user\'s identity', 'postman-smtp' ), array (
						$this,
						'authentication_type_callback' 
				), PostmanAdminController::SMTP_OPTIONS, PostmanAdminController::SMTP_SECTION );
				
				add_settings_section ( PostmanAdminController::BASIC_AUTH_SECTION, _x ( 'Authentication', 'Authentication proves the user\'s identity', 'postman-smtp' ), array (
						$this,
						'printBasicAuthSectionInfo' 
				), PostmanAdminController::BASIC_AUTH_OPTIONS );
				
				add_settings_field ( PostmanOptions::BASIC_AUTH_USERNAME, _x ( 'Username', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'basic_auth_username_callback' 
				), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
				
				add_settings_field ( PostmanOptions::BASIC_AUTH_PASSWORD, __ ( 'Password', 'postman-smtp' ), array (
						$this,
						'basic_auth_password_callback' 
				), PostmanAdminController::BASIC_AUTH_OPTIONS, PostmanAdminController::BASIC_AUTH_SECTION );
				
				// the OAuth section
				add_settings_section ( PostmanAdminController::OAUTH_SECTION, _x ( 'Authentication', 'Authentication proves the user\'s identity', 'postman-smtp' ), array (
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
				
				// the Message Sender section
				add_settings_section ( PostmanAdminController::MESSAGE_SENDER_SECTION, _x ( 'Envelope From Address', 'The Envelope Sender Email Address', 'postman-smtp' ), array (
						$this,
						'printMessageSenderSectionInfo' 
				), PostmanAdminController::MESSAGE_SENDER_OPTIONS );
				
				add_settings_field ( PostmanOptions::ENVELOPE_SENDER, __ ( 'Email Address', 'postman-smtp' ), array (
						$this,
						'sender_email_callback' 
				), PostmanAdminController::MESSAGE_SENDER_OPTIONS, PostmanAdminController::MESSAGE_SENDER_SECTION );
				
				// the Message From section
				add_settings_section ( PostmanAdminController::MESSAGE_FROM_SECTION, _x ( 'Message From Address', 'The Message Sender Email Address', 'postman-smtp' ), array (
						$this,
						'printMessageFromSectionInfo' 
				), PostmanAdminController::MESSAGE_FROM_OPTIONS );
				
				add_settings_field ( PostmanOptions::MESSAGE_SENDER_EMAIL, __ ( 'Email Address', 'postman-smtp' ), array (
						$this,
						'from_email_callback' 
				), PostmanAdminController::MESSAGE_FROM_OPTIONS, PostmanAdminController::MESSAGE_FROM_SECTION );
				
				add_settings_field ( PostmanOptions::PREVENT_MESSAGE_SENDER_EMAIL_OVERRIDE, '', array (
						$this,
						'prevent_from_email_override_callback' 
				), PostmanAdminController::MESSAGE_FROM_OPTIONS, PostmanAdminController::MESSAGE_FROM_SECTION );
				
				add_settings_field ( PostmanOptions::MESSAGE_SENDER_NAME, __ ( 'Name', 'postman-smtp' ), array (
						$this,
						'sender_name_callback' 
				), PostmanAdminController::MESSAGE_FROM_OPTIONS, PostmanAdminController::MESSAGE_FROM_SECTION );
				
				add_settings_field ( PostmanOptions::PREVENT_MESSAGE_SENDER_NAME_OVERRIDE, '', array (
						$this,
						'prevent_from_name_override_callback' 
				), PostmanAdminController::MESSAGE_FROM_OPTIONS, PostmanAdminController::MESSAGE_FROM_SECTION );
				
				// the Additional Addresses section
				add_settings_section ( PostmanAdminController::MESSAGE_SECTION, __ ( 'Additional Email Addresses', 'postman-smtp' ), array (
						$this,
						'printMessageSectionInfo' 
				), PostmanAdminController::MESSAGE_OPTIONS );
				
				add_settings_field ( PostmanOptions::REPLY_TO, _x ( 'Reply-To', 'The email address to address replies to', 'postman-smtp' ), array (
						$this,
						'reply_to_callback' 
				), PostmanAdminController::MESSAGE_OPTIONS, PostmanAdminController::MESSAGE_SECTION );
				
				add_settings_field ( PostmanOptions::FORCED_TO_RECIPIENTS, __ ( 'To Recipient(s)', 'postman-smtp' ), array (
						$this,
						'to_callback' 
				), PostmanAdminController::MESSAGE_OPTIONS, PostmanAdminController::MESSAGE_SECTION );
				
				add_settings_field ( PostmanOptions::FORCED_CC_RECIPIENTS, __ ( 'Carbon Copy Recipient(s)', 'postman-smtp' ), array (
						$this,
						'cc_callback' 
				), PostmanAdminController::MESSAGE_OPTIONS, PostmanAdminController::MESSAGE_SECTION );
				
				add_settings_field ( PostmanOptions::FORCED_BCC_RECIPIENTS, __ ( 'Blind Carbon Copy Recipient(s)', 'postman-smtp' ), array (
						$this,
						'bcc_callback' 
				), PostmanAdminController::MESSAGE_OPTIONS, PostmanAdminController::MESSAGE_SECTION );
				
				// the Additional Headers section
				add_settings_section ( PostmanAdminController::MESSAGE_HEADERS_SECTION, __ ( 'Additional Headers', 'postman-smtp' ), array (
						$this,
						'printAdditionalHeadersSectionInfo' 
				), PostmanAdminController::MESSAGE_HEADERS_OPTIONS );
				
				add_settings_field ( PostmanOptions::ADDITIONAL_HEADERS, __ ( 'Custom Headers', 'postman-smtp' ), array (
						$this,
						'headers_callback' 
				), PostmanAdminController::MESSAGE_HEADERS_OPTIONS, PostmanAdminController::MESSAGE_HEADERS_SECTION );
				
				// the Logging section
				add_settings_section ( PostmanAdminController::LOGGING_SECTION, __ ( 'Email Log Settings', 'postman-smtp' ), array (
						$this,
						'printLoggingSectionInfo' 
				), PostmanAdminController::LOGGING_OPTIONS );
				
				add_settings_field ( 'logging_status', __ ( 'Enable Logging', 'postman-smtp' ), array (
						$this,
						'loggingStatusInputField' 
				), PostmanAdminController::LOGGING_OPTIONS, PostmanAdminController::LOGGING_SECTION );
				
				add_settings_field ( 'logging_max_entries', __ ( 'Maximum Number of Log Entries', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'loggingMaxEntriesInputField' 
				), PostmanAdminController::LOGGING_OPTIONS, PostmanAdminController::LOGGING_SECTION );
				
				add_settings_field ( PostmanOptions::TRANSCRIPT_SIZE, __ ( 'Maximum Number of Lines in a Transcript', 'postman-smtp' ), array (
						$this,
						'transcriptSizeInputField' 
				), PostmanAdminController::LOGGING_OPTIONS, PostmanAdminController::LOGGING_SECTION );
				
				// the Network section
				add_settings_section ( PostmanAdminController::NETWORK_SECTION, __ ( 'Network Settings', 'postman-smtp' ), array (
						$this,
						'printNetworkSectionInfo' 
				), PostmanAdminController::NETWORK_OPTIONS );
				
				add_settings_field ( 'connection_timeout', _x ( 'TCP Connection Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'connection_timeout_callback' 
				), PostmanAdminController::NETWORK_OPTIONS, PostmanAdminController::NETWORK_SECTION );
				
				add_settings_field ( 'read_timeout', _x ( 'TCP Read Timeout (sec)', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'read_timeout_callback' 
				), PostmanAdminController::NETWORK_OPTIONS, PostmanAdminController::NETWORK_SECTION );
				
				// the Advanced section
				add_settings_section ( PostmanAdminController::ADVANCED_SECTION, _x ( 'Miscellaneous Settings', 'Configuration Section Title', 'postman-smtp' ), array (
						$this,
						'printAdvancedSectionInfo' 
				), PostmanAdminController::ADVANCED_OPTIONS );
				
				add_settings_field ( PostmanOptions::LOG_LEVEL, _x ( 'PHP Log Level', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'log_level_callback' 
				), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
				
				add_settings_field ( PostmanOptions::RUN_MODE, _x ( 'Delivery Mode', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'runModeCallback' 
				), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
				
				add_settings_field ( PostmanOptions::STEALTH_MODE, _x ( 'Stealth Mode', 'This mode removes the Postman X-Mailer signature from emails', 'postman-smtp' ), array (
						$this,
						'stealthModeCallback' 
				), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
				
				add_settings_field ( PostmanOptions::TEMPORARY_DIRECTORY, __ ( 'Temporary Directory', 'postman-smtp' ), array (
						$this,
						'temporaryDirectoryCallback' 
				), PostmanAdminController::ADVANCED_OPTIONS, PostmanAdminController::ADVANCED_SECTION );
				
				// the Test Email section
				register_setting ( 'email_group', PostmanAdminController::TEST_OPTIONS );
				
				add_settings_section ( 'TEST_EMAIL', _x ( 'Test Your Setup', 'Configuration Section Title', 'postman-smtp' ), array (
						$this,
						'printTestEmailSectionInfo' 
				), PostmanAdminController::POSTMAN_TEST_SLUG );
				
				add_settings_field ( 'test_email', _x ( 'Recipient Email Address', 'Configuration Input Field', 'postman-smtp' ), array (
						$this,
						'test_email_callback' 
				), PostmanAdminController::POSTMAN_TEST_SLUG, 'TEST_EMAIL' );
			}
		}
		
		/**
		 * Print the Transport section info
		 */
		public function printTransportSectionInfo() {
			print __ ( 'Choose SMTP or a vendor-specific API:', 'postman-smtp' );
		}
		/**
		 * Print the Section text
		 */
		public function printSmtpSectionInfo() {
			print __ ( 'Configure the communication with the Mail Submission Agent (MSA):', 'postman-smtp' );
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
			print __ ( 'Enter the account username (email address) and password:', 'postman-smtp' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printOAuthSectionInfo() {
			printf ( '<p id="wizard_oauth2_help">%s</p>', $this->oauthScribe->getOAuthHelp () );
		}
		public function printLoggingSectionInfo() {
			print __ ( 'Configure the delivery audit log:', 'postman-smtp' );
		}
		
		/**
		 * Print the Section text
		 *
		 * @deprecated
		 *
		 */
		public function printTestEmailSectionInfo() {
			// no-op
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
		public function printMessageSenderSectionInfo() {
			print sprintf ( __ ( 'The <b>Envelope</b> From address identifies the account owner to the SMTP server.', 'postman-smtp' ), 'https://support.google.com/mail/answer/22370?hl=en' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printMessageFromSectionInfo() {
			print sprintf ( __ ( 'The <b>Message</b> From address identifies the sender to the recipient. Change this when you are sending on behalf of someone else, for example to use Google\'s <a href="%s">Send Mail As</a> feature. Themes and other plugins, especially Contact Forms, are permitted to modify this field.', 'postman-smtp' ), 'https://support.google.com/mail/answer/22370?hl=en' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printMessageSectionInfo() {
			print __ ( 'Separate multiple <b>to</b>/<b>cc</b>/<b>bcc</b> recipients with commas.', 'postman-smtp' );
		}
		
		/**
		 * Print the Section text
		 */
		public function printNetworkSectionInfo() {
			print __ ( 'Increase the timeouts if your host is intermittenly failing to send mail. Be careful, this also correlates to how long your user must wait if the mail server is unreachable.', 'postman-smtp' );
		}
		/**
		 * Print the Section text
		 */
		public function printAdvancedSectionInfo() {
			print __ ( 'Log Level specifies the level of detail written to the WordPress and PHP logfiles. Delivery mode offers options useful for developing or testing.', 'postman-smtp' );
		}
		/**
		 * Print the Section text
		 */
		public function printAdditionalHeadersSectionInfo() {
			print __ ( 'Specify custom headers (e.g. <code>X-MC-Tags: wordpress-site-A</code>), one per line. Use custom headers with caution as they can negatively affect your Spam score.', 'postman-smtp' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function transport_type_callback() {
			$transportType = $this->options->getTransportType ();
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE );
			foreach ( PostmanTransportRegistry::getInstance ()->getTransports () as $transport ) {
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
			printf ( '<option class="input_auth_type_none" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_NONE, $authType == PostmanOptions::AUTHENTICATION_TYPE_NONE ? 'selected="selected"' : '', _x ( 'None', 'As in type used: None', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_plain" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_PLAIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_PLAIN ? 'selected="selected"' : '', _x ( 'Plain', 'As in type used: Plain', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_login" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_LOGIN, $authType == PostmanOptions::AUTHENTICATION_TYPE_LOGIN ? 'selected="selected"' : '', _x ( 'Login', 'As in type used: Login', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_crammd5" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5, $authType == PostmanOptions::AUTHENTICATION_TYPE_CRAMMD5 ? 'selected="selected"' : '', _x ( 'CRAM-MD5', 'As in type used: CRAM-MD5', 'postman-smtp' ) );
			printf ( '<option class="input_auth_type_oauth2" value="%s" %s>%s</option>', PostmanOptions::AUTHENTICATION_TYPE_OAUTH2, $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 ? 'selected="selected"' : '', _x ( 'OAuth 2.0', 'Authentication Type is OAuth 2.0', 'postman-smtp' ) );
			print '</select>';
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function encryption_type_callback() {
			$encType = $this->options->getEncryptionType ();
			print '<select id="input_enc_type" class="input_encryption_type" name="postman_options[enc_type]">';
			printf ( '<option class="input_enc_type_none" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_NONE, $encType == PostmanOptions::ENCRYPTION_TYPE_NONE ? 'selected="selected"' : '', _x ( 'None', 'As in type used: None', 'postman-smtp' ) );
			printf ( '<option class="input_enc_type_ssl" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_SSL, $encType == PostmanOptions::ENCRYPTION_TYPE_SSL ? 'selected="selected"' : '', 'SMTPS' );
			printf ( '<option class="input_enc_type_tls" value="%s" %s>%s</option>', PostmanOptions::ENCRYPTION_TYPE_TLS, $encType == PostmanOptions::ENCRYPTION_TYPE_TLS ? 'selected="selected"' : '', 'STARTTLS' );
			print '</select>';
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
			$hostname = $this->options->getHostname ();
			if (empty ( $hostname )) {
				$hostname = self::DEFAULT_PORT_TEST_SMTP_HOSTNAME;
			}
			printf ( '<input type="text" id="input_hostname" name="postman_options[hostname]" value="%s" size="40" class="required"/>', $hostname );
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
			printf ( '<input type="text" id="input_sender_name" name="postman_options[sender_name]" value="%s" size="40" />', null !== $this->options->getMessageSenderName () ? esc_attr ( $this->options->getMessageSenderName () ) : '' );
		}
		
		/**
		 */
		public function prevent_from_name_override_callback() {
			$enforced = $this->options->isPluginSenderNameEnforced ();
			printf ( '<input type="checkbox" id="input_prevent_sender_name_override" name="postman_options[prevent_sender_name_override]" %s /> %s', $enforced ? 'checked="checked"' : '', __ ( 'Prevent <b>plugins</b> and <b>themes</b> from changing this', 'postman-smtp' ) );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="email" id="input_envelope_sender_email" name="postman_options[envelope_sender]" value="%s" size="40" class="required"/>', null !== $this->options->getEnvelopeSender () ? esc_attr ( $this->options->getEnvelopeSender () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function from_email_callback() {
			printf ( '<input type="email" id="input_sender_email" name="postman_options[sender_email]" value="%s" size="40" class="required"/>', null !== $this->options->getMessageSenderEmail () ? esc_attr ( $this->options->getMessageSenderEmail () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function prevent_from_email_override_callback() {
			$enforced = $this->options->isPluginSenderEmailEnforced ();
			printf ( '<input type="checkbox" id="input_prevent_sender_email_override" name="postman_options[prevent_sender_email_override]" %s /> %s', $enforced ? 'checked="checked"' : '', __ ( 'Prevent <b>plugins</b> and <b>themes</b> from changing this', 'postman-smtp' ) );
		}
		
		/**
		 * Shows the Mail Logging enable/disabled option
		 */
		public function loggingStatusInputField() {
			// isMailLoggingAllowed
			$disabled = "";
			if (! $this->options->isMailLoggingAllowed ()) {
				$disabled = 'disabled="disabled" ';
			}
			printf ( '<select ' . $disabled . 'id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::MAIL_LOG_ENABLED_OPTION );
			printf ( '<option value="%s" %s>%s</option>', PostmanOptions::MAIL_LOG_ENABLED_OPTION_YES, $this->options->isMailLoggingEnabled () ? 'selected="selected"' : '', __ ( 'Yes', 'postman-smtp' ) );
			printf ( '<option value="%s" %s>%s</option>', PostmanOptions::MAIL_LOG_ENABLED_OPTION_NO, ! $this->options->isMailLoggingEnabled () ? 'selected="selected"' : '', __ ( 'No', 'postman-smtp' ) );
			printf ( '</select>' );
		}
		public function loggingMaxEntriesInputField() {
			printf ( '<input type="text" id="input_logging_max_entries" name="postman_options[%s]" value="%s"/>', PostmanOptions::MAIL_LOG_MAX_ENTRIES, $this->options->getMailLoggingMaxEntries () );
		}
		public function transcriptSizeInputField() {
			printf ( '<input type="text" id="input%2$s" name="%1$s[%2$s]" value="%3$s"/>', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSCRIPT_SIZE, $this->options->getTranscriptSize () );
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
			printf ( '<input type="password" autocomplete="off" id="input_basic_auth_password" name="postman_options[basic_auth_password]" value="%s" size="40" class="required"/>', null !== $this->options->getPassword () ? esc_attr ( PostmanUtils::obfuscatePassword ( $this->options->getPassword () ) ) : '' );
			print ' <input type="button" id="togglePasswordField" value="Show Password" class="button button-secondary" style="visibility:hidden" />';
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
			printf ( '<input type="text" id="input_reply_to" name="%s[%s]" value="%s" size="40" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function to_callback() {
			printf ( '<input type="text" id="input_to" name="%s[%s]" value="%s" size="60" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_TO_RECIPIENTS, null !== $this->options->getForcedToRecipients () ? esc_attr ( $this->options->getForcedToRecipients () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function cc_callback() {
			printf ( '<input type="text" id="input_cc" name="%s[%s]" value="%s" size="60" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_CC_RECIPIENTS, null !== $this->options->getForcedCcRecipients () ? esc_attr ( $this->options->getForcedCcRecipients () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function bcc_callback() {
			printf ( '<input type="text" id="input_bcc" name="%s[%s]" value="%s" size="60" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_BCC_RECIPIENTS, null !== $this->options->getForcedBccRecipients () ? esc_attr ( $this->options->getForcedBccRecipients () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function headers_callback() {
			printf ( '<textarea id="input_headers" name="%s[%s]" cols="60" rows="5" >%s</textarea>', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::ADDITIONAL_HEADERS, null !== $this->options->getAdditionalHeaders () ? esc_attr ( $this->options->getAdditionalHeaders () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function log_level_callback() {
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL );
			$currentKey = $this->options->getLogLevel ();
			$this->printSelectOption ( _x ( 'Off', 'Log Level', 'postman-smtp' ), PostmanLogger::OFF_INT, $currentKey );
			$this->printSelectOption ( _x ( 'Trace', 'Log Level', 'postman-smtp' ), PostmanLogger::TRACE_INT, $currentKey );
			$this->printSelectOption ( _x ( 'Debug', 'Log Level', 'postman-smtp' ), PostmanLogger::DEBUG_INT, $currentKey );
			$this->printSelectOption ( _x ( 'Info', 'Log Level', 'postman-smtp' ), PostmanLogger::INFO_INT, $currentKey );
			$this->printSelectOption ( _x ( 'Warning', 'Log Level', 'postman-smtp' ), PostmanLogger::WARN_INT, $currentKey );
			$this->printSelectOption ( _x ( 'Error', 'Log Level', 'postman-smtp' ), PostmanLogger::ERROR_INT, $currentKey );
			printf ( '</select>' );
		}
		private function printSelectOption($label, $optionKey, $currentKey) {
			$optionPattern = '<option value="%1$s" %2$s>%3$s</option>';
			printf ( $optionPattern, $optionKey, $optionKey == $currentKey ? 'selected="selected"' : '', $label );
		}
		public function runModeCallback() {
			printf ( '<select id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::RUN_MODE );
			$currentKey = $this->options->getRunMode ();
			$this->printSelectOption ( _x ( 'Log Email and Send', 'When the server is online to the public, this is "Production" mode', 'postman-smtp' ), PostmanOptions::RUN_MODE_PRODUCTION, $currentKey );
			$this->printSelectOption ( __ ( 'Log Email and Delete', 'postman-smtp' ), PostmanOptions::RUN_MODE_LOG_ONLY, $currentKey );
			$this->printSelectOption ( __ ( 'Delete All Emails', 'postman-smtp' ), PostmanOptions::RUN_MODE_IGNORE, $currentKey );
			printf ( '</select>' );
		}
		public function stealthModeCallback() {
			printf ( '<input type="checkbox" id="input_%2$s" class="input_%2$s" name="%1$s[%2$s]" %3$s /> %4$s', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::STEALTH_MODE, $this->options->isStealthModeEnabled () ? 'checked="checked"' : '', __ ( 'Remove the Postman X-Header signature from messages', 'postman-smtp' ) );
		}
		public function temporaryDirectoryCallback() {
			printf ( '<input type="text" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TEMPORARY_DIRECTORY, $this->options->getTempDirectory () );
			if (PostmanState::getInstance ()->isFileLockingEnabled ()) {
				printf ( ' <span style="color:green">Valid</span>' );
			} else {
				printf ( ' <span style="color:red">Invalid</span>' );
			}
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
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="input_test_email" name="postman_test_options[test_email]" value="%s" class="required email" size="40"/>', wp_get_current_user ()->user_email );
		}
	}
}