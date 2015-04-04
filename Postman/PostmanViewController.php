<?php
if (! class_exists ( 'PostmanViewController' )) {
	class PostmanViewController {
		private $logger;
		private $options;
		private $authorizationToken;
		private $oauthScribe;
		private $importableConfiguration;
		private $adminController;
		const POSTMAN_MENU_SLUG = 'postman';
		const CONFIGURATION_SLUG = 'postman/configuration';
		const CONFIGURATION_WIZARD_SLUG = 'postman/configuration_wizard';
		const EMAIL_TEST_SLUG = 'postman/email_test';
		const PORT_TEST_SLUG = 'postman/port_test';
		const DIAGNOSTICS_SLUG = 'postman/diagnostics';
		
		// style sheets and scripts
		const POSTMAN_STYLE = 'postman_style';
		const JQUERY_SCRIPT = 'jquery';
		const POSTMAN_SCRIPT = 'postman_script';
		
		//
		const BACK_ARROW_SYMBOL = '&#11013;';
		
		/**
		 * Constructor
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthTokenInterface $authorizationToken        	
		 * @param PostmanConfigTextHelper $oauthScribe        	
		 */
		function __construct(PostmanOptionsInterface $options, PostmanOAuthTokenInterface $authorizationToken, PostmanConfigTextHelper $oauthScribe, PostmanAdminController $adminController) {
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->oauthScribe = $oauthScribe;
			$this->adminController = $adminController;
			$this->registerAdminMenu ( $this, 'generateDefaultContent' );
			$this->registerAdminMenu ( $this, 'addSetupWizardSubmenu' );
			$this->registerAdminMenu ( $this, 'addConfigurationSubmenu' );
			$this->registerAdminMenu ( $this, 'addEmailTestSubmenu' );
			$this->registerAdminMenu ( $this, 'addPortTestSubmenu' );
			$this->registerAdminMenu ( $this, 'addPurgeDataSubmenu' );
			$this->registerAdminMenu ( $this, 'addDiagnosticsSubmenu' );
			
			// initialize the scripts, stylesheets and form fields
			add_action ( 'admin_init', array (
					$this,
					'initializeAdminPage' 
			) );
		}
		public static function getActionUrl($slug) {
			return PostmanAdminController::getActionUrl ( $slug );
		}
		public static function getPageUrl($slug) {
			return PostmanAdminController::getPageUrl ( $slug );
		}
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		private function registerAdminMenu($viewController, $callbackName) {
			// $this->logger->debug ( 'Registering admin menu ' . $callbackName );
			add_action ( 'admin_menu', array (
					$viewController,
					$callbackName 
			) );
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
					'enqueueWizardResources' 
			) );
		}
		function enqueueWizardResources() {
			$this->importableConfiguration = new PostmanImportableConfiguration ();
			$startPage = 1;
			if ($this->importableConfiguration->isImportAvailable ()) {
				$startPage = 0;
			}
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_setup_wizard', array (
					'start_page' => $startPage 
			) );
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_wizard_script' );
			if (startsWith ( get_locale (), 'fr' )) {
				wp_enqueue_script ( 'jquery_validation_fr' );
			} elseif (startsWith ( get_locale (), 'it' )) {
				wp_enqueue_script ( 'jquery_validation_it' );
			}
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
			wp_enqueue_style ( 'jquery_ui_style' );
			wp_enqueue_script ( 'postman_manual_config_script' );
			wp_enqueue_script ( 'jquery-ui-tabs' );
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
			wp_enqueue_style ( 'postman_send_test_email' );
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
					'enqueueDiagnosticsScreenStylesheet' 
			) );
		}
		function enqueueDiagnosticsScreenStylesheet() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_diagnostics_script' );
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
			$page = add_submenu_page ( null, _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ), 'Postman SMTP', 'manage_options', PostmanAdminController::PURGE_DATA_SLUG, array (
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
		 * Register and add settings
		 */
		public function initializeAdminPage() {
			// register the stylesheet and javascript external resources
			wp_register_style ( self::POSTMAN_STYLE, plugins_url ( 'style/postman.css', __FILE__ ), null, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'jquery_ui_style', plugins_url ( 'style/jquery-ui.css', __FILE__ ), self::POSTMAN_STYLE, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'postman_send_test_email', plugins_url ( 'style/postman_send_test_email.css', __FILE__ ), self::POSTMAN_STYLE, POSTMAN_PLUGIN_VERSION );
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery.steps.css', __FILE__ ), self::POSTMAN_STYLE, '1.1.0' );
			
			wp_register_script ( self::POSTMAN_SCRIPT, plugins_url ( 'script/postman.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'sprintf', plugins_url ( 'script/sprintf.min.js', __FILE__ ), null, '1.0.2' );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery.steps.min.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), '1.1.0' );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery.validate.min.js', __FILE__ ), array (
					self::JQUERY_SCRIPT 
			), '1.13.1' );
			wp_register_script ( 'jquery_validation_fr', plugins_url ( 'script/jquery-validate/messages_fr.js', __FILE__ ), array (
					'jquery_validation' 
			), '1.13.1' );
			wp_register_script ( 'jquery_validation_it', plugins_url ( 'script/jquery-validate/messages_it.js', __FILE__ ), array (
					'jquery_validation' 
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
					self::POSTMAN_SCRIPT,
					'sprintf' 
			), POSTMAN_PLUGIN_VERSION );
			wp_register_script ( 'postman_diagnostics_script', plugins_url ( 'script/postman_diagnostics.js', __FILE__ ), array (
					self::JQUERY_SCRIPT,
					self::POSTMAN_SCRIPT 
			), POSTMAN_PLUGIN_VERSION );
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_test_in_progress', _x ( 'Checking..', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_open', _x ( 'Open', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_closed', _x ( 'Closed', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_done', _x ( 'Done.', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_yes', _x ( 'Yes', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_no', _x ( 'No', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port', _x ( 'Port', 'TCP Port Test Status', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_email_test', array (
					'not_started' => _x ( 'In Outbox', 'Email Test Status', 'postman-smtp' ),
					'sending' => _x ( 'Sending...', 'Email Test Status', 'postman-smtp' ),
					'success' => _x ( 'Success', 'Email Test Status', 'postman-smtp' ),
					'failed' => _x ( 'Failed', 'Email Test Status', 'postman-smtp' ) 
			) );
			/* translators: where %d is a port number */
			wp_localize_script ( 'postman_port_test_script', 'postman_port_blocked', __ ( 'Port %d is blocked. Contact your host for a solution, such as using their local SMTP server or opening the port.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is a hostname */
			wp_localize_script ( 'postman_port_test_script', 'postman_try_dif_smtp', __ ( 'Port %d can\'t send mail with %s. Try a different SMTP server.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is a hostname */
			wp_localize_script ( 'postman_port_test_script', 'postman_smtp_success', __ ( 'Port %d can be used for SMTP to %s.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is the URL for the Postman Gmail Extension */
			wp_localize_script ( 'postman_port_test_script', 'postman_443_open', sprintf ( __ ( 'Port %d can be used to send Gmail with the <a href="%s">Postman Gmail Extension</a>.', 'postman-smtp' ), 443, 'https://wordpress.org/plugins/postman-gmail-extension/' ) );
			/* translators: where %d is a port number */
			wp_localize_script ( 'postman_port_test_script', 'postman_443_closed', sprintf ( __ ( 'Port %d is blocked. Contact your host for a solution, such as opening the port.', 'postman-smtp' ), 443 ) );
			wp_localize_script ( 'postman_wizard_script', 'postman_wizard_wait', __ ( 'Please wait for the port test to finish', 'postman-smtp' ) );
			wp_localize_script ( 'postman_wizard_script', 'postman_wizard_no_ports', __ ( 'No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.', 'postman-smtp' ) );
			wp_localize_script ( 'postman_wizard_script', 'postman_wizard_bad_redirect_url', __ ( 'You are about to configure OAuth 2.0 with an IP address instead of a domain name. This is not permitted. Either assign a real domain name to your site or add a fake one in your local host file.', 'postman-smtp' ) );
			
			wp_localize_script ( 'jquery_steps_script', 'steps_current_step', _x ( 'current step:', 'Wizard Label', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_pagination', _x ( 'Pagination', 'Wizard Label', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_finish', _x ( 'Finish', 'Wizard Label', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_next', _x ( 'Next', 'Wizard Label', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_previous', _x ( 'Previous', 'Wizard Label', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_loading', _x ( 'Loading ...', 'Wizard Label', 'postman-smtp' ) );
			
			// user input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_email', '#input_' . PostmanOptions::SENDER_EMAIL );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_name', '#input_' . PostmanOptions::SENDER_NAME );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_element_name', '#input_' . PostmanOptions::PORT );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_hostname_element_name', '#input_' . PostmanOptions::HOSTNAME );
			
			// the enc input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_enc_for_password_el', '#input_enc_type_password' );
			// these are the ids for the <option>s in the encryption <select>
			
			// the password inputs
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_basic_username', '#input_' . PostmanOptions::BASIC_AUTH_USERNAME );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_basic_password', '#input_' . PostmanOptions::BASIC_AUTH_PASSWORD );
			
			// the auth input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_redirect_url_el', '#input_oauth_redirect_url' );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_auth_type', '#input_' . PostmanOptions::AUTHENTICATION_TYPE );
		}
		
		/**
		 * Options page callback
		 */
		public function outputDefaultContent() {
			// Set class property
			print '<div class="wrap">';
			$this->displayTopNavigation ();
			if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken ) && PostmanPreRequisitesCheck::isReady ()) {
				printf ( '<p><span style="color:green;padding:2px 5px; font-size:1.2em">%s</span></p>', __ ( 'Postman is configured.', 'postman-smtp' ) );
				$currentTransport = PostmanTransportUtils::getCurrentTransport ();
				$deliveryDetails = $currentTransport->getDeliveryDetails ( $this->options );
				printf ( '<p style="margin:0 10px"><span>%s</span></p>', $deliveryDetails );
				if ($this->options->isAuthTypeOAuth2 ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins and themes are forbidden from overriding the sender email address.</em>', 'postman-smtp' ) );
				} else if ($this->options->isAuthTypePassword ()) {
					printf ( '<p style="margin:10px 10px"><span>%s</span></p>', __ ( 'Please note: <em>When composing email, other WordPress plugins and themes may override the sender name and email address causing rejection with some email services, such as Yahoo Mail. If you experience problems, try leaving the sender email address empty in these plugins or themes.</em>', 'postman-smtp' ) );
				}
				if (PostmanState::getInstance ()->isTimeToReviewPostman () && ! PostmanOptions::getInstance ()->isNew ()) {
					/* translators: where %d is the number of emails delivered */
					print '</br><hr width="70%"></br>';
					printf ( '<p style="margin:10px 10px"><span>%s', sprintf ( _n ( 'Postman has delivered <span style="color:green">%d</span> email for you!', 'Postman has delivered <span style="color:green">%d</span> emails for you!', PostmanStats::getInstance ()->getSuccessfulDeliveries (), 'postman-smtp' ), PostmanStats::getInstance ()->getSuccessfulDeliveries () ) );
					print ' ';
					/* translators: where %s is the URL to the WordPress.org review and ratings page */
					printf ( '%s</span></p>', sprintf ( __ ( 'Please consider leaving a <a href="%s">review of Postman SMTP</a> to help spread the word about the new way to send email from WordPress! :D', 'postman-smtp' ), 'https://wordpress.org/support/view/plugin-reviews/postman-smtp?filter=5' ) );
				}
			} else {
				printf ( '<p><span style="color:red; padding:2px 5px; font-size:1.1em">%s</span></p>', __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ) );
				if ($this->options->isNew ()) {
					printf ( '<h3>%s</h3>', __ ( 'Thank-you for choosing Postman!', 'postman-smtp' ) );
					/* translators: where %s is the URL of the Setup Wizard */
					printf ( '<p><span>%s</span></p>', sprintf ( __ ( 'Let\'s get started! All users are strongly encouraged to <a href="%s">run the Setup Wizard</a>.', 'postman-smtp' ), $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ) ) );
				}
			}
		}
		private function outputChildPageHeader($title) {
			printf ( '<h2>%s</h2>', _x ( 'Postman Settings', 'Page Title', 'postman-smtp' ) );
			print '<div id="welcome-panel" class="welcome-panel">';
			print '<div class="welcome-panel-content">';
			print '<div class="welcome-panel-column-container">';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', $title );
			print '</div>';
			printf ( '<p style="text-align:right;margin-top:25px">%s <a id="back_to_menu_link" href="%s">%s</a></p>', self::BACK_ARROW_SYMBOL, POSTMAN_HOME_PAGE_ABSOLUTE_URL, _x ( 'Back To Main Menu', 'Return to main menu link', 'postman-smtp' ) );
			print '</div></div></div>';
		}
		
		/**
		 */
		public function outputManualConfigurationContent() {
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Manual Configuration', 'Page Title', 'postman-smtp' ) );
			print '<div id="config_tabs"><ul>';
			print sprintf ( '<li><a href="#account_config">%s</a></li>', _x ( 'Account', 'Manual Configuration Tab Label', 'postman-smtp' ) );
			print sprintf ( '<li><a href="#message_config">%s</a></li>', _x ( 'Message', 'Manual Configuration Tab Label', 'postman-smtp' ) );
			print sprintf ( '<li><a href="#advanced_options_config">%s</a></li>', _x ( 'Advanced', 'Manual Configuration Tab Label', 'postman-smtp' ) );
			print '</ul>';
			print '<form method="post" action="options.php">';
			// This prints out all hidden setting fields
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			print '<section id="account_config">';
			if (sizeof ( PostmanTransportDirectory::getInstance ()->getTransports () ) > 1) {
				do_settings_sections ( 'transport_options' );
			} else {
				printf ( '<input id="input_%2$s" type="hidden" name="%1$s[%2$s]" value="%3$s"/>', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE, PostmanSmtpTransport::SLUG );
			}
			print '<div id="smtp_config">';
			do_settings_sections ( PostmanAdminController::SMTP_OPTIONS );
			print '</div>';
			print '<div id="password_settings">';
			do_settings_sections ( PostmanAdminController::BASIC_AUTH_OPTIONS );
			print '</div>';
			print '<div id="oauth_settings">';
			do_settings_sections ( PostmanAdminController::OAUTH_OPTIONS );
			print '</div>';
			print '</section>';
			print '<section id="message_config">';
			do_settings_sections ( PostmanAdminController::MESSAGE_SENDER_OPTIONS );
			do_settings_sections ( PostmanAdminController::MESSAGE_OPTIONS );
			do_settings_sections ( PostmanAdminController::MESSAGE_HEADERS_OPTIONS );
			print '</section>';
			print '<section id="advanced_options_config">';
			do_settings_sections ( PostmanAdminController::NETWORK_OPTIONS );
			do_settings_sections ( PostmanAdminController::ADVANCED_OPTIONS );
			print '</section>';
			submit_button ();
			print '</form>';
			print '</div>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPurgeDataContent() {
			print '<div class="wrap">';
			$this->outputChildPageHeader ( _x ( 'Delete plugin settings', 'Page Title', 'postman-smtp' ) );
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			printf ( '<input type="hidden" name="action" value="%s" />', PostmanAdminController::PURGE_DATA_SLUG );
			printf ( '<p><span>%s</span></p><p><span>%s</span></p>', __ ( 'This will purge all of Postman\'s settings, including SMTP server info, username/password and OAuth Credentials.', 'postman-smtp' ), __ ( 'Are you sure?', 'postman-smtp' ) );
			submit_button ( _x ( 'Delete All Data', 'Button Label', 'postman-smtp' ), 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPortTestContent() {
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Connectivity Test', 'Page Title', 'postman-smtp' ) );
			
			print '<p>';
			print __ ( 'This test determines which well-known ports are available for Postman to use.', 'postman-smtp' );
			print '<form id="port_test_form_id" method="post">';
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ) );
			$this->adminController->port_test_hostname_callback ();
			submit_button ( _x ( 'Begin Test', 'Button Label', 'postman-smtp' ), 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="connectivity_test_table">';
			print sprintf ( '<tr><th colspan="2" class="test">%s</th><th class="port_25">%s</th><th class="port_443">%s</th><th class="port_465">%s</th><th class="port_587">%s</th></tr>', _x ( 'Test', 'Connectivity Test Table', 'postman-smtp' ), sprintf ( _x ( 'Port %s', 'Port Test', 'postman-smtp' ), 25 ), sprintf ( _x ( 'Port %s', 'Port Test', 'postman-smtp' ), 443 ), sprintf ( _x ( 'Port %s', 'Port Test', 'postman-smtp' ), 465 ), sprintf ( _x ( 'Port %s', 'Port Test', 'postman-smtp' ), 587 ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="port-test-port-25">-</td><td id="port-test-port-443">-</td><td id="port-test-port-465">-</td><td id="port-test-port-587">-</td></tr>', _x ( 'Outbound to Internet', 'Connectivity Test Table', 'postman-smtp' ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="smtp_test_port_25">-</td><td id="smtp_test_port_443">-</td><td id="smtp_test_port_465">-</td><td id="smtp_test_port_587">-</td></tr>', _x ( 'Service Available', 'Connectivity Test Table', 'postman-smtp' ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="starttls_test_port_25">-</td><td id="starttls_test_port_443">-</td><td id="starttls_test_port_465">-</td><td id="starttls_test_port_587">-</td></tr>', _x ( 'STARTTLS', 'Connectivity Test Table', 'postman-smtp' ) );
			print sprintf ( '<tr><th rowspan="5">%s</th><th>%s</th><td id="auth_none_test_port_25">-</td><td id="auth_none_test_port_443">-</td><td id="auth_none_test_port_465">-</td><td id="auth_none_test_port_587">-</td></tr>', _x ( 'Auth', 'Connectivity Test Table', 'postman-smtp' ), _x ( 'None', 'Authentication Type', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_login_test_port_25">-</td><td id="auth_login_test_port_443">-</td><td id="auth_login_test_port_465">-</td><td id="auth_login_test_port_587">-</td></tr>', _x ( 'Login', 'Authentication Type', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_plain_test_port_25">-</td><td id="auth_plain_test_port_443">-</td><td id="auth_plain_test_port_465">-</td><td id="auth_plain_test_port_587">-</td></tr>', _x ( 'Plain', 'Authentication Type', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_crammd5_test_port_25">-</td><td id="auth_crammd5_test_port_443">-</td><td id="auth_crammd5_test_port_465">-</td><td id="auth_crammd5_test_port_587">-</td></tr>', _x ( 'CRAM-MD5', 'Authentication Type', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_xoauth_test_port_25">-</td><td id="auth_xoauth_test_port_443">-</td><td id="auth_xoauth_test_port_465">-</td><td id="auth_xoauth_test_port_587">-</td></tr>', _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' ) );
			print '</table>';
			print '<section id="conclusion" style="display:none">';
			print sprintf ( '<h3>%s:</h3>', __ ( 'Conclusion', 'postman-smtp' ) );
			print '<ol class="conclusion">';
			print '</ol>';
			print '</section>';
			print '<section id="blocked-port-help" style="display:none">';
			print sprintf ( '<p><b>%s</b></p>', __ ( 'A <span style="color:red">Closed</span> port indicates one or more of these issues:' ), 'postman-smtp' );
			print '<ol>';
			printf ( '<li>%s</li>', __ ( 'Your host has placed a firewall between this site and the Internet', 'postman-smtp' ) );
			/* translators: where %s is the URL to the PHP documentation on 'allow-url-fopen' */
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">PHP configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen' ) );
			/* translators: where %s is the URL to an article on disabling external requests in WordPress */
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">WordPress configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://wp-mix.com/disable-external-url-requests/' ) );
			print '</ol></p>';
			print '</section>';
			print '</div>';
		}
		
		/**
		 */
		public function outputDiagnosticsContent() {
			// test features
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Tips and Diagnostic Info', 'Page Title', 'postman-smtp' ) );
			
			printf ( '<h4>%s</h4>', __ ( 'Are you having issues with Postman?', 'postman-smtp' ) );
			/* translators: where %1$s and %2$s are the URLs to the Troubleshooting and Support Forums on WordPress.org */
			printf ( '<p style="margin:0 10px">%s</p>', sprintf ( __ ( 'Please check the <a href="%1$s">troubleshooting and error messages</a> page and the <a href="%2$s">support forum</a>.</br>If you write for help, please include the following diagnostic information:', 'postman-smtp' ), 'https://wordpress.org/plugins/postman-smtp/other_notes/', 'https://wordpress.org/support/plugin/postman-smtp' ) );
			printf ( '<h4>%s</h4>', _x ( 'Diagnostic Information', 'Page Title', 'postman-smtp' ) );
			printf ( '<textarea readonly="readonly" id="diagnostic-text" cols="80" rows="10">%s</textarea>', _x ( 'Loading ...', 'Wizard Label', 'postman-smtp' ) );
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
				printf ( '<li><a href="%s" class="welcome-icon send-test-email">%s</a></li>', $this->getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $this->oauthScribe->getRequestPermissionLinkText () );
			} else {
				printf ( '<li><div class="welcome-icon send_test_emaail">%s</div></li>', $this->oauthScribe->getRequestPermissionLinkText () );
			}
			if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send_test_email">%s</a></li>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ), _x ( 'Send a Test Email', 'Page Title', 'postman-smtp' ) );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', _x ( 'Send a Test Email', 'Page Title', 'postman-smtp' ) );
			}
			printf ( '<li><a href="%s" class="welcome-icon oauth-authorize">%s</a></li>', $this->getPageUrl ( PostmanAdminController::PURGE_DATA_SLUG ), _x ( 'Delete plugin settings', 'Main Menu', 'postman-smtp' ) );
			print '</ul>';
			print '</div>';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', _x ( 'Troubleshooting', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::DIAGNOSTICS_SLUG ), _x ( 'Tips and Diagnostic Info', 'Page Title', 'postman-smtp' ) );
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
			
			$this->outputChildPageHeader ( _x ( 'Postman Setup Wizard', 'Page Title', 'postman-smtp' ) );
			
			print '<form id="postman_wizard" method="post" action="options.php">';
			printf ( '<input type="hidden" id="input_reply_to" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, null !== $this->options->getReplyTo () ? esc_attr ( $this->options->getReplyTo () ) : '' );
			printf ( '<input type="hidden" id="input_connection_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_read_timeout" name="%s[%s]" value="%s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			
			// Wizard Step 0
			printf ( '<h5>%s</h5>', _x ( 'Import Configuration', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Import configuration from another plugin?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'If you had a working configuration with another Plugin, the Setup Wizard can begin with those settings.', 'postman-smtp' ) );
			print '<table class="input_auth_type">';
			printf ( '<tr><td><input type="radio" id="import_none" name="input_plugin" value="%s" checked="checked"></input></td><td><label> %s</label></td></tr>', 'none', _x ( 'None', 'Plugin to Import Configuration from', 'postman-smtp' ) );
			
			if ($this->importableConfiguration->isImportAvailable ()) {
				foreach ( $this->importableConfiguration->getAvailableOptions () as $options ) {
					printf ( '<tr><td><input type="radio" name="input_plugin" value="%s"/></td><td><label> %s</label></td></tr>', $options->getPluginSlug (), $options->getPluginName () );
				}
			}
			print '</table>';
			print '</fieldset>';
			
			// Wizard Step 1
			printf ( '<h5>%s</h5>', _x ( 'Sender Details', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Who is the mail coming from?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Please enter the email address and name you\'d like to send mail from.', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Please note that to combat Spam, many email services will <em>not</em> let you send from an e-mail address other than the one you authenticate with in step 5.', 'postman-smtp' ) );
			printf ( '<label for="postman_options[sender_email]">%s</label>', _x ( 'Sender Email Address', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->adminController->sender_email_callback ();
			printf ( '<label for="postman_options[sender_name]">%s</label>', _x ( 'Sender Email Name', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->adminController->sender_name_callback ();
			print '</fieldset>';
			
			// Wizard Step 2
			printf ( '<h5>%s</h5>', _x ( 'SMTP Server Hostname', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Which host will relay the mail?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'This is the Outgoing Mail Server, also known as a Mail Submission Agent (MSA), that Postman will use to deliver your mail. If possible, Postman will try to determine this hostname based on the e-mail address.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', _x ( 'SMTP Server Hostname', 'Configuration Input Field', 'postman-smtp' ) );
			print $this->adminController->hostname_callback ();
			print '</fieldset>';
			
			// Wizard Step 3
			printf ( '<h5>%s</h5>', _x ( 'Connectivity Test', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'How will the connection to the MSA be established?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Your connection to the SMTP server depends on what your email service provider offers, and what your WordPress host allows. Postman will attempt to determine which options are available to you.', 'postman-smtp' ) );
			printf ( '<p>%s: <span id="port_test_status">%s</span></p>', _x ( 'Connectivity Test', 'Wizard Action', 'postman-smtp' ), _x ( 'Ready', 'TCP Port Test Status', 'postman-smtp' ) );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::PORT );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::ENCRYPTION_TYPE );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			print '<p id="wizard_recommendation"></p>';
			/* Translators: Where %1$s is the socket identifier and %2$s is the authentication type */
			printf ( '<p id="user_override" style="display:none"><span>%s</span></p>', sprintf ( __ ( 'Configuration will proceed on socket %1$s using %2$s authentication.' ), '<select id="user_socket_override"></select>', '<select id="user_auth_override"></select>' ) );
			print '</fieldset>';
			
			// Wizard Step 4
			printf ( '<h5>%s</h5>', _x ( 'Authentication', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'How will you prove your identity to the MSA?', 'Wizard Step Title', 'postman-smtp' ) );
			
			print '<section class="wizard-auth-oauth2">';
			print '<p id="wizard_oauth2_help"></p>';
			printf ( '<label id="callback_domain" for="callback_domain">%s</label>', $this->oauthScribe->getCallbackDomainLabel () );
			print '<br />';
			print $this->adminController->callback_domain_callback ();
			print '<br />';
			printf ( '<label id="redirect_url" for="redirect_uri">%s</label>', $this->oauthScribe->getCallbackUrlLabel () );
			print '<br />';
			print $this->adminController->redirect_url_callback ();
			print '<br />';
			printf ( '<label id="client_id" for="client_id">%s</label>', $this->oauthScribe->getClientIdLabel () );
			print '<br />';
			print $this->adminController->oauth_client_id_callback ();
			print '<br />';
			printf ( '<label id="client_secret" for="client_secret">%s</label>', $this->oauthScribe->getClientSecretLabel () );
			print '<br />';
			print $this->adminController->oauth_client_secret_callback ();
			print '<br />';
			print '</section>';
			
			print '<section class="wizard-auth-basic">';
			printf ( '<p class="port-explanation-ssl">%s</p>', __ ( 'Enter your credentials. Your username is most likely your email address.', 'postman-smtp' ) );
			printf ( '<label for="username">%s</label>', _x ( 'Username', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->adminController->basic_auth_username_callback ();
			print '<br />';
			printf ( '<label for="password">%s</label>', _x ( 'Password', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->adminController->basic_auth_password_callback ();
			print '</section>';
			
			print '</fieldset>';
			
			// Wizard Step 5
			printf ( '<h5>%s</h5>', _x ( 'Finish', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'You\'re Done!', 'Wizard Step Title', 'postman-smtp' ) );
			print '<section>';
			printf ( '<p>%s</p>', __ ( 'Click Finish to save these settings, then:', 'postman-smtp' ) );
			print '<ul style="margin-left: 20px">';
			printf ( '<li class="wizard-auth-oauth2">%s</li>', __ ( 'Request permission from the Email Provider to allow Postman to send email and', 'postman-smtp' ) );
			printf ( '<li>%s</li>', __ ( 'Send yourself a Test Email to make sure everything is working!', 'postman-smtp' ) );
			print '</ul>';
			print '</section>';
			print '</fieldset>';
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputTestEmailWizardContent() {
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Send a Test Email', 'Page Title', 'postman-smtp' ) );
			
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
			print $this->adminController->test_email_callback ();
			print '</fieldset>';
			
			// Step 2
			printf ( '<h5>%s</h5>', __ ( 'Send The Message', 'postman-smtp' ) );
			print '<fieldset>';
			print '<legend>';
			print __ ( 'Sending the message:', 'postman-smtp' );
			printf ( ' <span id="postman_test_message_status">%s</span>', _x ( 'In Outbox', 'Email Test Status', 'postman-smtp' ) );
			print '</legend>';
			print '<section>';
			printf ( '<p><label>%s</label></p>', __ ( 'Status Message', 'postman-smtp' ) );
			print '<textarea id="postman_test_message_error_message" readonly="readonly" cols="65" rows="2"></textarea>';
			print '</section>';
			print '</fieldset>';
			
			// Step 3
			if (PostmanTransportUtils::getCurrentTransport ()->isTranscriptSupported ()) {
				printf ( '<h5>%s</h5>', __ ( 'Session Transcript', 'postman-smtp' ) );
				print '<fieldset>';
				printf ( '<legend>%s</legend>', __ ( 'Examine the SMTP Session Transcript if you need to.', 'postman-smtp' ) );
				printf ( '<p>%s', __ ( 'This is the conversation between Postman and your SMTP server. It can be useful for diagnosing problems. <b>DO NOT</b> post it on-line, it may contain your shared secret (password) in encoded form.', 'postman-smtp' ) );
				print '<section>';
				printf ( '<p><label for="postman_test_message_transcript">%s</label></p>', __ ( 'SMTP Session Transcript', 'postman-smtp' ) );
				print '<textarea readonly="readonly" id="postman_test_message_transcript" cols="65" rows="8"></textarea>';
				print '</section>';
				print '</fieldset>';
			}
			
			print '</form>';
			print '</div>';
		}
	}
}
		