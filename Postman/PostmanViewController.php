<?php
if (! class_exists ( 'PostmanViewController' )) {
	class PostmanViewController {
		private $logger;
		private $rootPluginFilenameAndPath;
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
		 * @param PostmanOptions $options        	
		 * @param PostmanOAuthToken $authorizationToken        	
		 * @param PostmanConfigTextHelper $oauthScribe        	
		 */
		function __construct($rootPluginFilenameAndPath, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanConfigTextHelper $oauthScribe, PostmanAdminController $adminController) {
			$this->options = $options;
			$this->rootPluginFilenameAndPath = $rootPluginFilenameAndPath;
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
		public static function getPageUrl($slug) {
			return PostmanUtils::getPageUrl ( $slug );
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
			$pageTitle = _x ( 'Postman Setup', 'Page Title', 'postman-smtp' );
			$pluginName = __ ( 'Postman SMTP', 'postman-smtp' );
			$uniqueId = self::POSTMAN_MENU_SLUG;
			$pageOptions = array (
					$this,
					'outputDefaultContent' 
			);
			$mainPostmanSettingsPage = add_options_page ( $pageTitle, $pluginName, 'manage_options', $uniqueId, $pageOptions );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $mainPostmanSettingsPage, array (
					$this,
					'enqueueHomeScreenStylesheet' 
			) );
			add_action ( 'load-' . $mainPostmanSettingsPage, array (
					$this,
					'addHomeScreenHelp' 
			) );
		}
		function enqueueHomeScreenStylesheet() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_script' );
		}
		
		/**
		 * https://codex.wordpress.org/Adding_Contextual_Help_to_Administration_Menus
		 */
		function addHomeScreenHelp() {
			// We are in the correct screen because we are taking advantage of the load-* action (below)
			$screen = get_current_screen ();
			// $screen->remove_help_tabs();
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-welcome',
					'title' => __ ( 'Welcome', 'postman-smtp' ),
					'content' => __ ( 'This is the Settings page for Postman, an SMTP mailer that delivers email from your WordPress site to the Internet. From here you can configure the plugin, and access testing and diagnostic tools.', 'postman-smtp' ) 
			) );
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-online-support',
					'title' => __ ( 'Online Support', 'postman-smtp' ),
					'content' => $this->generateOnlineSupportContent () 
			) );
			// add more help tabs as needed with unique id's
			
			// Help sidebars are optional
			// $screen->set_help_sidebar ( '<p><strong>' . __ ( 'About' ) . '</strong></p>' . '<p>Postman SMTP 1.6.0b1<br/>by Jason Hendriks</p>' );
		}
		private function generateOnlineSupportContent() {
			$onlineSupportText = __ ( 'Having trouble? You can ask for help on our <a href="%1$s" target="_blank">Support Forum</a>. To get the help you need quickly, please post the <a href="%s">Diagnostic Data</a> with your question. You can also check the <a href="%3$s">FAQ</a> and <a href="%4$s">Error Messages</a> pages for answers.', 'postman-smtp' );
			$onlineSupportContent = sprintf ( $onlineSupportText, 'https://wordpress.org/support/plugin/postman-smtp', $this->getPageUrl ( self::DIAGNOSTICS_SLUG ), '#faq', 'https://wordpress.org/plugins/postman-smtp/other_notes/' );
			return $onlineSupportContent;
		}
		
		/**
		 * Register the Setup Wizard screen
		 */
		public function addSetupWizardSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', self::CONFIGURATION_WIZARD_SLUG, array (
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
			if (PostmanUtils::startsWith ( get_locale (), 'fr' )) {
				wp_enqueue_script ( 'jquery_validation_fr' );
			} elseif (PostmanUtils::startsWith ( get_locale (), 'it' )) {
				wp_enqueue_script ( 'jquery_validation_it' );
			} elseif (PostmanUtils::startsWith ( get_locale (), 'tr' )) {
				wp_enqueue_script ( 'jquery_validation_tr' );
			}
		}
		
		/**
		 * Register the Configuration screen
		 */
		public function addConfigurationSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', self::CONFIGURATION_SLUG, array (
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
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', self::EMAIL_TEST_SLUG, array (
					$this,
					'outputTestEmailWizardContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueEmailTestResources' 
			) );
			add_action ( 'load-' . $page, array (
					$this,
					'addTestEmailScreenHelp' 
			) );
		}
		function enqueueEmailTestResources() {
			wp_enqueue_style ( 'jquery_steps_style' );
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_style ( 'postman_send_test_email' );
			wp_enqueue_script ( 'postman_test_email_wizard_script' );
		}
		/**
		 * https://codex.wordpress.org/Adding_Contextual_Help_to_Administration_Menus
		 */
		function addTestEmailScreenHelp() {
			// We are in the correct screen because we are taking advantage of the load-* action (below)
			$screen = get_current_screen ();
			// $screen->remove_help_tabs();
			$content = __ ( 'The Email Test will send an email to you. If the test fails, the full SMTP session transcript is available to you. <br/><br/>Receiving a single test email does not indicate perfect configuration. Some services may dump your email into a black-hole or mark it as Spam if you:', 'postman-smtp' );
			$content .= '<ul>';
			$content .= sprintf ( '<li><b>%s</b>: %s</li>', __ ( 'Violate an SPF record', 'postman-smtp' ), __ ( 'You must use the SMTP server (MSA) approved by your domain to deliver your mail. (eg.) a @gmail.com sender address requires that authentication and delivery always be through smtp.gmail.com.', 'postman-smtp' ) );
			$content .= sprintf ( '<li><b>%s</b>: %s</li>', _x ( 'Forge the From Address', 'Forge as in a forgery (fake) is made', 'postman-smtp' ), sprintf ( __ ( '<a href="%s">Spoofing</a>, when it results in an SPF violation, will get your message binned. Use your own address as the sender (From:) in <em>every</em> email.</li>', 'postman-smtp' ), 'http://en.m.wikipedia.org/wiki/Email_spoofing' ) );
			$content .= '</ul>';
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-connectivity-test',
					'title' => __ ( 'Send a Test Email', 'postman-smtp' ),
					'content' => $content 
			) );
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-online-support',
					'title' => __ ( 'Online Support', 'postman-smtp' ),
					'content' => $this->generateOnlineSupportContent () 
			) );
			// add more help tabs as needed with unique id's
			
			// Help sidebars are optional
			// $screen->set_help_sidebar ( '<p><strong>' . __ ( 'About' ) . '</strong></p>' . '<p>Postman SMTP 1.6.0b1<br/>by Jason Hendriks</p>' );
		}
		
		/**
		 * Register the Diagnostics screen
		 */
		public function addDiagnosticsSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', self::DIAGNOSTICS_SLUG, array (
					$this,
					'outputDiagnosticsContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueueDiagnosticsScreenStylesheet' 
			) );
			add_action ( 'load-' . $page, array (
					$this,
					'addDiagnosticScreenHelp' 
			) );
		}
		function enqueueDiagnosticsScreenStylesheet() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_diagnostics_script' );
		}
		/**
		 * https://codex.wordpress.org/Adding_Contextual_Help_to_Administration_Menus
		 */
		function addDiagnosticScreenHelp() {
			// We are in the correct screen because we are taking advantage of the load-* action (below)
			$screen = get_current_screen ();
			// $screen->remove_help_tabs();
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-connectivity-test',
					'title' => __ ( 'Diagnostic Test', 'postman-smtp' ),
					'content' => __ ( 'Consolidates details of your setup to aid the author in debugging problems, including: operating system details, WordPress configuration, Postman configuration, network connectivity and your domain\'s primary MX and SPF records. Your private authorization credentials are masked.', 'postman-smtp' ) 
			) );
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-online-support',
					'title' => __ ( 'Online Support', 'postman-smtp' ),
					'content' => $this->generateOnlineSupportContent () 
			) );
			// add more help tabs as needed with unique id's
			
			// Help sidebars are optional
			// $screen->set_help_sidebar ( '<p><strong>' . __ ( 'About' ) . '</strong></p>' . '<p>Postman SMTP 1.6.0b1<br/>by Jason Hendriks</p>' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPortTestSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', self::PORT_TEST_SLUG, array (
					$this,
					'outputPortTestContent' 
			) );
			// When the plugin options page is loaded, also load the stylesheet
			add_action ( 'admin_print_styles-' . $page, array (
					$this,
					'enqueuePortTestResources' 
			) );
			add_action ( 'load-' . $page, array (
					$this,
					'addConnectivityTestScreenHelp' 
			) );
		}
		function enqueuePortTestResources() {
			wp_enqueue_style ( self::POSTMAN_STYLE );
			wp_enqueue_script ( 'postman_port_test_script' );
		}
		/**
		 * https://codex.wordpress.org/Adding_Contextual_Help_to_Administration_Menus
		 */
		function addConnectivityTestScreenHelp() {
			// We are in the correct screen because we are taking advantage of the load-* action (below)
			$screen = get_current_screen ();
			// $screen->remove_help_tabs();
			$content = __ ( 'The Connectivity Test will report this site\'s ability to reach a mail server, and interrogate the mail server for it\'s capabilities.', 'postman-smtp' );
			$content .= '<ul>';
			$content .= sprintf ( '<li><b>%s</b>: ', _x ( 'Outbound to Internet', 'Is it possible to create network connections to the Internet?', 'postman-smtp' ) ) . __ ( 'This tests the ability to make outbound connections from your site to the Internet in general. If the result is Closed, then there is a communication problem with the Internet, like a firewall.</li>', 'postman-smtp' );
			$content .= sprintf ( '<li><b>%s</b>: ', _x ( 'Service Available', 'What service is available?', 'postman-smtp' ) ) . __ ( 'This shows the service found for a particular host/port. Possible successful results are <b>SMTP</b>, <b>SMTPS</b> (secure) and <b>HTTPS</b> (secure). If the result is No and the hostname you entered is correct, there was a communication problem with the mail server, like a firewall.</li>', 'postman-smtp' );
			$content .= sprintf ( '<li><b>%s</b>: ', _x ( 'ID', 'What is this server\'s ID?', 'postman-smtp' ) ) . __ ( 'Some hosts redirect mail traffic to their own mail server, breaking authentication and SPF verification. This is revealed by an incorrect server identity.</li>', 'postman-smtp' );
			$content .= sprintf ( '<li><b>%s</b>: ', __ ( 'STARTTLS', 'postman-smtp' ) ) . __ ( 'This indicates whether the server supports protocol-level security. Either STARTTLS, SMTPS or HTTPS is required for secure transmission of your credentials.</li>', 'postman-smtp' );
			$content .= sprintf ( '<li><b>%s</b>: ', _x ( 'Auth', 'Short for Authentication', 'postman-smtp' ) ) . __ ( 'This indicates the authenication methods that the server supports. All are password-based, except for OAuth 2.0, which is token-based.</li>', 'postman-smtp' );
			$content .= '</ul>';
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-connectivity-test',
					'title' => _x ( 'Connectivity Test', 'A testing tool which determines connectivity to the Internet', 'postman-smtp' ),
					'content' => $content 
			) );
			$screen->add_help_tab ( array (
					'id' => 'postman-smtp-online-support',
					'title' => __ ( 'Online Support', 'postman-smtp' ),
					'content' => $this->generateOnlineSupportContent () 
			) );
			// add more help tabs as needed with unique id's
			
			// Help sidebars are optional
			// $screen->set_help_sidebar ( '<p><strong>' . __ ( 'About' ) . '</strong></p>' . '<p>Postman SMTP 1.6.0b1<br/>by Jason Hendriks</p>' );
		}
		
		/**
		 * Register the Email Test screen
		 */
		public function addPurgeDataSubmenu() {
			$page = add_submenu_page ( null, _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ), __ ( 'Postman SMTP', 'postman-smtp' ), 'manage_options', PostmanAdminController::PURGE_DATA_SLUG, array (
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
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			wp_register_style ( self::POSTMAN_STYLE, plugins_url ( 'style/postman.css', $this->rootPluginFilenameAndPath ), null, $pluginData ['version'] );
			wp_register_style ( 'jquery_ui_style', plugins_url ( 'style/jquery-steps/jquery-ui.css', $this->rootPluginFilenameAndPath ), self::POSTMAN_STYLE, '1.1.0' );
			wp_register_style ( 'jquery_steps_style', plugins_url ( 'style/jquery-steps/jquery.steps.css', $this->rootPluginFilenameAndPath ), self::POSTMAN_STYLE, '1.1.0' );
			wp_register_style ( 'postman_send_test_email', plugins_url ( 'style/postman_send_test_email.css', $this->rootPluginFilenameAndPath ), self::POSTMAN_STYLE, $pluginData ['version'] );
			
			wp_register_script ( self::POSTMAN_SCRIPT, plugins_url ( 'script/postman.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT 
			), $pluginData ['version'] );
			wp_register_script ( 'sprintf', plugins_url ( 'script/sprintf/sprintf.min.js', $this->rootPluginFilenameAndPath ), null, '1.0.2' );
			wp_register_script ( 'jquery_steps_script', plugins_url ( 'script/jquery-steps/jquery.steps.min.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT 
			), '1.1.0' );
			wp_register_script ( 'jquery_validation', plugins_url ( 'script/jquery-validate/jquery.validate.min.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT 
			), '1.13.1' );
			wp_register_script ( 'jquery_validation_fr', plugins_url ( 'script/jquery-validate/messages_fr.js', $this->rootPluginFilenameAndPath ), array (
					'jquery_validation' 
			), '1.13.1' );
			wp_register_script ( 'jquery_validation_it', plugins_url ( 'script/jquery-validate/messages_it.js', $this->rootPluginFilenameAndPath ), array (
					'jquery_validation' 
			), '1.13.1' );
			wp_register_script ( 'jquery_validation_tr', plugins_url ( 'script/jquery-validate/messages_tr.js', $this->rootPluginFilenameAndPath ), array (
					'jquery_validation' 
			), '1.13.1' );
			wp_register_script ( 'postman_wizard_script', plugins_url ( 'script/postman_wizard.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					'jquery_steps_script',
					self::POSTMAN_SCRIPT,
					'sprintf' 
			), $pluginData ['version'] );
			wp_register_script ( 'postman_test_email_wizard_script', plugins_url ( 'script/postman_test_email_wizard.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					'jquery_steps_script',
					self::POSTMAN_SCRIPT 
			), $pluginData ['version'] );
			wp_register_script ( 'postman_manual_config_script', plugins_url ( 'script/postman_manual_config.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					self::POSTMAN_SCRIPT 
			), $pluginData ['version'] );
			wp_register_script ( 'postman_port_test_script', plugins_url ( 'script/postman_port_test.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT,
					'jquery_validation',
					self::POSTMAN_SCRIPT,
					'sprintf' 
			), $pluginData ['version'] );
			wp_register_script ( 'postman_diagnostics_script', plugins_url ( 'script/postman_diagnostics.js', $this->rootPluginFilenameAndPath ), array (
					self::JQUERY_SCRIPT,
					self::POSTMAN_SCRIPT 
			), $pluginData ['version'] );
			
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_test_in_progress', _x ( 'Checking..', 'The "please wait" message', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_open', _x ( 'Open', 'The port is open', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port_test_closed', _x ( 'Closed', 'The port is closed', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_yes', __ ( 'Yes', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_no', __ ( 'No', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_port', _x ( 'Port', 'eg. TCP Port 25', 'postman-smtp' ) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_email_test', array (
					'not_started' => _x ( 'In Outbox', 'Email Test Status', 'postman-smtp' ),
					'sending' => _x ( 'Sending...', 'Email Test Status', 'postman-smtp' ),
					'success' => _x ( 'Success', 'Email Test Status', 'postman-smtp' ),
					'failed' => _x ( 'Failed', 'Email Test Status', 'postman-smtp' ) 
			) );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_ajax_fail', __ ( 'The server returned an unexpected result:', 'postman-smtp' ) );
			/* translators: where %d is a port number */
			wp_localize_script ( 'postman_port_test_script', 'postman_port_blocked', __ ( 'No outbound route between this site and the Internet on Port %d.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is a hostname */
			wp_localize_script ( 'postman_port_test_script', 'postman_try_dif_smtp', __ ( 'Port %d is open, but not to %s.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is a hostname */
			wp_localize_script ( 'postman_port_test_script', 'postman_smtp_success', __ ( 'Port %d can be used for SMTP to %s.', 'postman-smtp' ) );
			/* translators: where %s is the name of the SMTP server */
			wp_localize_script ( 'postman_port_test_script', 'postman_smtp_mitm', __ ( 'Warning: connected to %1$s instead of %2$s.', 'postman-smtp' ) );
			/* translators: where %s is the name of the SMTP server */
			wp_localize_script ( 'postman_wizard_script', 'postman_smtp_mitm', __ ( 'Warning: connected to %1$s instead of %2$s.', 'postman-smtp' ) );
			/* translators: where %d is a port number and %s is the URL for the Postman Gmail Extension */
			wp_localize_script ( 'postman_port_test_script', 'postman_https_success', sprintf ( __ ( 'Port %d can be used to send <b>Gmail</b> with the Gmail API.', 'postman-smtp' ), 443 ) );
			/* translators: where %d is a port number */
			wp_localize_script ( 'postman_wizard_script', 'postman_wizard_bad_redirect_url', __ ( 'You are about to configure OAuth 2.0 with an IP address instead of a domain name. This is not permitted. Either assign a real domain name to your site or add a fake one in your local host file.', 'postman-smtp' ) );
			
			wp_localize_script ( 'jquery_steps_script', 'steps_current_step', 'steps_current_step' );
			wp_localize_script ( 'jquery_steps_script', 'steps_pagination', 'steps_pagination' );
			wp_localize_script ( 'jquery_steps_script', 'steps_finish', _x ( 'Finish', 'Press this button to Finish this task', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_next', _x ( 'Next', 'Press this button to go to the next step', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_previous', _x ( 'Previous', 'Press this button to go to the previous step', 'postman-smtp' ) );
			wp_localize_script ( 'jquery_steps_script', 'steps_loading', 'steps_loading' );
			
			// user input
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_email', '#input_' . PostmanOptions::MESSAGE_SENDER_EMAIL );
			wp_localize_script ( self::POSTMAN_SCRIPT, 'postman_input_sender_name', '#input_' . PostmanOptions::MESSAGE_SENDER_NAME );
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
			if (PostmanTransportRegistry::getInstance ()->isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken ) && PostmanPreRequisitesCheck::isReady ()) {
				if ($this->options->getRunMode () != PostmanOptions::RUN_MODE_PRODUCTION) {
					printf ( '<p><span style="background-color:yellow">%s</span></p>', __ ( 'Postman is in <em>non-Production</em> mode and is dumping all emails.', 'postman-smtp' ) );
				} else {
					printf ( '<p><span style="color:green;padding:2px 5px; font-size:1.2em">%s</span></p>', __ ( 'Postman is configured.', 'postman-smtp' ) );
					$currentTransport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
					$deliveryDetails = $currentTransport->getDeliveryDetails ( $this->options );
					printf ( '<p style="margin:0 10px"><span>%s</span></p>', $deliveryDetails );
				}
				/* translators: where %d is the number of emails delivered */
				printf ( '<p style="margin:10px 10px"><span>%s', sprintf ( _n ( 'Postman has delivered <span style="color:green">%d</span> email for you.', 'Postman has delivered <span style="color:green">%d</span> emails for you.', PostmanStats::getInstance ()->getSuccessfulDeliveries (), 'postman-smtp' ), PostmanStats::getInstance ()->getSuccessfulDeliveries () ) );
				if ($this->options->isMailLoggingEnabled ()) {
					print ' ';
					printf ( '<a href="%s">%s</a>.</span></p>', PostmanUtils::getEmailLogPageUrl (), __ ( 'View the log', 'postman-smtp' ) );
				}
				if (PostmanState::getInstance ()->isTimeToReviewPostman () && ! PostmanOptions::getInstance ()->isNew ()) {
					print '</br><hr width="70%"></br>';
					/* translators: where %s is the URL to the WordPress.org review and ratings page */
					printf ( '%s</span></p>', sprintf ( __ ( 'Please consider <a href="%s">leaving a review</a> to help spread the word! :D', 'postman-smtp' ), 'https://wordpress.org/support/view/plugin-reviews/postman-smtp?filter=5' ) );
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
			printf ( '<h2>%s</h2>', _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ) );
			print '<div id="postman-main-menu" class="welcome-panel">';
			print '<div class="welcome-panel-content">';
			print '<div class="welcome-panel-column-container">';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', $title );
			print '</div>';
			printf ( '<p style="text-align:right;margin-top:25px">%s <a id="back_to_menu_link" href="%s">%s</a></p>', self::BACK_ARROW_SYMBOL, PostmanUtils::getSettingsPageUrl (), _x ( 'Back To Main Menu', 'Return to main menu link', 'postman-smtp' ) );
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
			print sprintf ( '<li><a href="#logging_config">%s</a></li>', _x ( 'Logging', 'Manual Configuration Tab Label', 'postman-smtp' ) );
			print sprintf ( '<li><a href="#advanced_options_config">%s</a></li>', _x ( 'Advanced', 'Manual Configuration Tab Label', 'postman-smtp' ) );
			print '</ul>';
			print '<form method="post" action="options.php">';
			// This prints out all hidden setting fields
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			print '<section id="account_config">';
			if (sizeof ( PostmanTransportRegistry::getInstance ()->getTransports () ) > 1) {
				do_settings_sections ( 'transport_options' );
			} else {
				printf ( '<input id="input_%2$s" type="hidden" name="%1$s[%2$s]" value="%3$s"/>', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE, PostmanSmtpModuleTransport::SLUG );
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
			do_settings_sections ( PostmanAdminController::MESSAGE_FROM_OPTIONS );
			do_settings_sections ( PostmanAdminController::MESSAGE_OPTIONS );
			do_settings_sections ( PostmanAdminController::MESSAGE_HEADERS_OPTIONS );
			print '</section>';
			print '<section id="logging_config">';
			do_settings_sections ( PostmanAdminController::LOGGING_OPTIONS );
			print '</section>';
			/*
			 * print '<section id="logging_config">';
			 * do_settings_sections ( PostmanAdminController::MULTISITE_OPTIONS );
			 * print '</section>';
			 */
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
			$this->outputChildPageHeader ( __ ( 'Delete plugin settings', 'postman-smtp' ) );
			print '<form method="POST" action="' . get_admin_url () . 'admin-post.php">';
			wp_nonce_field ( 'purge-data' );
			printf ( '<input type="hidden" name="action" value="%s" />', PostmanAdminController::PURGE_DATA_SLUG );
			printf ( '<p><span>%s</span></p><p><span>%s</span></p>', __ ( 'This will purge all of Postman\'s settings, including account credentials and the mail log.', 'postman-smtp' ), __ ( 'Are you sure?', 'postman-smtp' ) );
			submit_button ( _x ( 'Delete All Data', 'Button Label', 'postman-smtp' ), 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			print '</form>';
			print '</div>';
		}
		
		/**
		 */
		public function outputPortTestContent() {
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Connectivity Test', 'A testing tool which determines connectivity to the Internet', 'postman-smtp' ) );
			
			print '<p>';
			print __ ( 'This test determines which well-known ports are available for Postman to use.', 'postman-smtp' );
			print '<form id="port_test_form_id" method="post">';
			printf ( '<label for="hostname">%s</label>', __ ( 'Outgoing Mail Server Hostname', 'postman-smtp' ) );
			$this->adminController->port_test_hostname_callback ();
			submit_button ( _x ( 'Begin Test', 'Button Label', 'postman-smtp' ), 'primary', 'begin-port-test', true );
			print '</form>';
			print '<table id="connectivity_test_table">';
			/* Translators where %s is the port number */
			$portText = _x ( 'Port %s', 'The port number', 'postman-smtp' );
			print sprintf ( '<tr><th colspan="2">%s</th><th class="port_25">%s</th><th class="port_443">%s</th><th class="port_465">%s</th><th class="port_587">%s</th></tr>', _x ( 'Port', 'eg. TCP Port 25', 'postman-smtp' ), sprintf ( $portText, 25 ), sprintf ( $portText, '443*' ), sprintf ( $portText, 465 ), sprintf ( $portText, 587 ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="port-test-port-25">-</td><td id="port-test-port-443">-</td><td id="port-test-port-465">-</td><td id="port-test-port-587">-</td></tr>', _x ( 'Outbound to Internet', 'Is it possible to create network connections to the Internet?', 'postman-smtp' ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="smtp_test_port_25">-</td><td id="smtp_test_port_443">-</td><td id="smtp_test_port_465">-</td><td id="smtp_test_port_587">-</td></tr>', _x ( 'Service Available', 'What service is available?', 'postman-smtp' ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="server_id_port_25">-</td><td id="server_id_port_443">-</td><td id="server_id_port_465">-</td><td id="server_id_port_587">-</td></tr>', _x ( 'ID', 'What is this server\'s ID?', 'postman-smtp' ) );
			print sprintf ( '<tr><th colspan="2">%s</th><td id="starttls_test_port_25">-</td><td id="starttls_test_port_443">-</td><td id="starttls_test_port_465">-</td><td id="starttls_test_port_587">-</td></tr>', __ ( 'STARTTLS', 'postman-smtp' ) );
			print sprintf ( '<tr><th rowspan="5">%s</th><th>%s</th><td id="auth_none_test_port_25">-</td><td id="auth_none_test_port_443">-</td><td id="auth_none_test_port_465">-</td><td id="auth_none_test_port_587">-</td></tr>', _x ( 'Auth', 'Short for Authentication', 'postman-smtp' ), _x ( 'None', 'Authentication Type', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_login_test_port_25">-</td><td id="auth_login_test_port_443">-</td><td id="auth_login_test_port_465">-</td><td id="auth_login_test_port_587">-</td></tr>', _x ( 'Login', 'As in type used: Login', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_plain_test_port_25">-</td><td id="auth_plain_test_port_443">-</td><td id="auth_plain_test_port_465">-</td><td id="auth_plain_test_port_587">-</td></tr>', _x ( 'Plain', 'As in type used: Plain', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_crammd5_test_port_25">-</td><td id="auth_crammd5_test_port_443">-</td><td id="auth_crammd5_test_port_465">-</td><td id="auth_crammd5_test_port_587">-</td></tr>', _x ( 'CRAM-MD5', 'As in type used: CRAM-MD5', 'postman-smtp' ) );
			print sprintf ( '<tr><th>%s</th><td id="auth_xoauth_test_port_25">-</td><td id="auth_xoauth_test_port_443">-</td><td id="auth_xoauth_test_port_465">-</td><td id="auth_xoauth_test_port_587">-</td></tr>', _x ( 'OAuth 2.0', 'Authentication Type is OAuth 2.0', 'postman-smtp' ) );
			print '</table>';
			print '<section id="conclusion" style="display:none">';
			printf ( '<span style="font-size:0.9em">%s</span>', __ ( '* Port 443 tests against googleapis.com, not the SMTP hostname you enter.', 'postman-smtp' ) );
			print sprintf ( '<h3>%s:</h3>', __ ( 'Summary', 'postman-smtp' ) );
			print '<ol class="conclusion">';
			print '</ol>';
			print '</section>';
			print '<section id="blocked-port-help" style="display:none">';
			print sprintf ( '<p><b>%s</b></p>', __ ( 'A port with Service Available <span style="color:red">"No"</span> indicates one or more of these issues:', 'postman-smtp' ) );
			print '<ol>';
			printf ( '<li>%s</li>', __ ( 'Your web host has placed a firewall between this site and the Internet', 'postman-smtp' ) );
			printf ( '<li>%s</li>', __ ( 'The SMTP hostname is wrong or the mail server does not provide service on this port', 'postman-smtp' ) );
			/* translators: where %s is the URL to the PHP documentation on 'allow-url-fopen' */
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">PHP configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen' ) );
			/* translators: where %s is the URL to an article on disabling external requests in WordPress */
			printf ( '<li>%s</li>', sprintf ( __ ( 'Your <a href="%s">WordPress configuration</a> is preventing outbound connections', 'postman-smtp' ), 'http://wp-mix.com/disable-external-url-requests/' ) );
			print '</ol></p>';
			print sprintf ( '<p><b>%s</b></p>', __ ( 'If the issues above can not be resolved, your last option is to configure Postman to use an email account managed by your web host with an SMTP server managed by your web host.', 'postman-smtp' ) );
			print '</section>';
			print '</div>';
		}
		
		/**
		 */
		public function outputDiagnosticsContent() {
			// test features
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( __ ( 'Diagnostic Test', 'postman-smtp' ) );
			
			printf ( '<h4>%s</h4>', __ ( 'Are you having issues with Postman?', 'postman-smtp' ) );
			/* translators: where %1$s and %2$s are the URLs to the Troubleshooting and Support Forums on WordPress.org */
			printf ( '<p style="margin:0 10px">%s</p>', sprintf ( __ ( 'Please check the <a href="%1$s">troubleshooting and error messages</a> page and the <a href="%2$s">support forum</a>.', 'postman-smtp' ), 'https://wordpress.org/plugins/postman-smtp/other_notes/', 'https://wordpress.org/support/plugin/postman-smtp' ) );
			printf ( '<h4>%s</h4>', __ ( 'Diagnostic Test', 'postman-smtp' ) );
			printf ( '<p style="margin:0 10px">%s</p><br/>', sprintf ( __ ( 'If you write for help, please include the following:', 'postman-smtp' ), 'https://wordpress.org/plugins/postman-smtp/other_notes/', 'https://wordpress.org/support/plugin/postman-smtp' ) );
			printf ( '<textarea readonly="readonly" id="diagnostic-text" cols="80" rows="15">%s</textarea>', _x ( 'Checking..', 'The "please wait" message', 'postman-smtp' ) );
			print '</div>';
		}
		
		/**
		 */
		private function displayTopNavigation() {
			screen_icon ();
			printf ( '<h2>%s</h2>', _x ( 'Postman Setup', 'Page Title', 'postman-smtp' ) );
			print '<div id="postman-main-menu" class="welcome-panel">';
			print '<div class="welcome-panel-content">';
			print '<div class="welcome-panel-column-container">';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', _x ( 'Settings', 'The configuration page of the plugin', 'postman-smtp' ) );
			printf ( '<a class="button button-primary button-hero" href="%s">%s</a>', $this->getPageUrl ( self::CONFIGURATION_WIZARD_SLUG ), __ ( 'Start the Wizard', 'postman-smtp' ) );
			printf ( '<p class="">or, <a href="%s" class="configure_manually">%s</a>. </p>', $this->getPageUrl ( self::CONFIGURATION_SLUG ), _x ( 'configure manually', 'Adjust the Postman settings by hand', 'postman-smtp' ) );
			print '</div>';
			print '<div class="welcome-panel-column">';
			printf ( '<h4>%s</h4>', _x ( 'Actions', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			if (PostmanTransportRegistry::getInstance ()->isRequestOAuthPermissionAllowed ( $this->options, $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send-test-email">%s</a></li>', PostmanUtils::getGrantOAuthPermissionUrl (), $this->oauthScribe->getRequestPermissionLinkText () );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', $this->oauthScribe->getRequestPermissionLinkText () );
			}
			if (PostmanTransportRegistry::getInstance ()->isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
				printf ( '<li><a href="%s" class="welcome-icon send_test_email">%s</a></li>', $this->getPageUrl ( self::EMAIL_TEST_SLUG ), __ ( 'Send a Test Email', 'postman-smtp' ) );
			} else {
				printf ( '<li><div class="welcome-icon send_test_email">%s</div></li>', __ ( 'Send a Test Email', 'postman-smtp' ) );
			}
			$purgeLinkPattern = '<li><a href="%1$s" class="welcome-icon oauth-authorize">%2$s</a></li>';
			if ($this->options->isNew ()) {
				$purgeLinkPattern = '<li>%2$s</li>';
			}
			printf ( $purgeLinkPattern, $this->getPageUrl ( PostmanAdminController::PURGE_DATA_SLUG ), __ ( 'Delete plugin settings', 'postman-smtp' ) );
			print '</ul>';
			print '</div>';
			print '<div class="welcome-panel-column welcome-panel-last">';
			printf ( '<h4>%s</h4>', _x ( 'Troubleshooting', 'Main Menu', 'postman-smtp' ) );
			print '<ul>';
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::PORT_TEST_SLUG ), __ ( 'Connectivity Test', 'postman-smtp' ) );
			printf ( '<li><a href="%s" class="welcome-icon run-port-test">%s</a></li>', $this->getPageUrl ( self::DIAGNOSTICS_SLUG ), __ ( 'Diagnostic Test', 'postman-smtp' ) );
			printf ( '<li><a href="https://wordpress.org/support/plugin/postman-smtp" class="welcome-icon postman_support">%s</a></li>', __ ( 'Online Support', 'postman-smtp' ) );
			print '</ul></div></div></div></div>';
		}
		
		/**
		 */
		public function outputWizardContent() {
			// Set default values for input fields
			$this->options->setMessageSenderEmailIfEmpty ( wp_get_current_user ()->user_email );
			$this->options->setMessageSenderNameIfEmpty ( wp_get_current_user ()->display_name );
			
			// construct Wizard
			print '<div class="wrap">';
			
			$this->outputChildPageHeader ( _x ( 'Postman Setup Wizard', 'Page Title', 'postman-smtp' ) );
			
			print '<form id="postman_wizard" method="post" action="options.php">';
			
			// message tab
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::PREVENT_MESSAGE_SENDER_EMAIL_OVERRIDE, $this->options->isPluginSenderEmailEnforced () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::PREVENT_MESSAGE_SENDER_NAME_OVERRIDE, $this->options->isPluginSenderNameEnforced () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::REPLY_TO, $this->options->getReplyTo () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_TO_RECIPIENTS, $this->options->getForcedToRecipients () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_CC_RECIPIENTS, $this->options->getForcedCcRecipients () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::FORCED_BCC_RECIPIENTS, $this->options->getForcedBccRecipients () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::ADDITIONAL_HEADERS, $this->options->getAdditionalHeaders () );
			
			// logging tab
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::MAIL_LOG_ENABLED_OPTION, $this->options->getMailLoggingEnabled () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::MAIL_LOG_MAX_ENTRIES, $this->options->getMailLoggingMaxEntries () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSCRIPT_SIZE, $this->options->getTranscriptSize () );
			
			// advanced tab
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::CONNECTION_TIMEOUT, $this->options->getConnectionTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::READ_TIMEOUT, $this->options->getReadTimeout () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::LOG_LEVEL, $this->options->getLogLevel () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::RUN_MODE, $this->options->getRunMode () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::STEALTH_MODE, $this->options->isStealthModeEnabled () );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]" value="%3$s" />', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TEMPORARY_DIRECTORY, $this->options->getTempDirectory () );
			
			// display the setting text
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			
			// Wizard Step 0
			printf ( '<h5>%s</h5>', _x ( 'Import Configuration', 'Wizard Step Title', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Import configuration from another plugin?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'If you had a working configuration with another Plugin, the Setup Wizard can begin with those settings.', 'postman-smtp' ) );
			print '<table class="input_auth_type">';
			printf ( '<tr><td><input type="radio" id="import_none" name="input_plugin" value="%s" checked="checked"></input></td><td><label> %s</label></td></tr>', 'none', _x ( 'None', 'As in type used: None', 'postman-smtp' ) );
			
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
			printf ( '<p>%s</p>', __ ( 'Enter the email address and name you\'d like to send mail as.', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Please note that to prevent abuse, many email services will <em>not</em> let you send from an email address other than the one you authenticate with.', 'postman-smtp' ) );
			printf ( '<label for="postman_options[sender_email]">%s</label>', __ ( 'Email Address', 'postman-smtp' ) );
			print $this->adminController->from_email_callback ();
			print '<br/>';
			printf ( '<label for="postman_options[sender_name]">%s</label>', __ ( 'Name', 'postman-smtp' ) );
			print $this->adminController->sender_name_callback ();
			print '</fieldset>';
			
			// Wizard Step 2
			printf ( '<h5>%s</h5>', __ ( 'Outgoing Mail Server Hostname', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'Which host will relay the mail?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'This is the Outgoing (SMTP) Mail Server, or Mail Submission Agent (MSA), which Postman delegates mail delivery to. This server is specific to your email account, and if you don\'t know what to use, ask your email service provider.', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Note that many WordPress hosts, such as GoDaddy, Bluehost and Dreamhost, require that you use their mail accounts with their mail servers, and prevent you from using others.', 'postman-smtp' ) );
			printf ( '<label for="hostname">%s</label>', __ ( 'Outgoing Mail Server Hostname', 'postman-smtp' ) );
			print $this->adminController->hostname_callback ();
			printf ( '<p class="ajax-loader" style="display:none"><img src="%s"/></p>', plugins_url ( 'postman-smtp/style/ajax-loader.gif' ) );
			printf ( '<p id="godaddy_block"><span style="color:red">%s</span></p>', __ ( '<b>Error</b>: Your email address <b>requires</b> access to a remote SMTP server blocked by GoDaddy. Use a different e-mail address.', 'postman-smtp' ) );
			printf ( '<p id="godaddy_spf_required"><span style="background-color:yellow">%s</span></p>', sprintf ( __ ( '<b>Warning</b>: If you own this domain, make sure it has an <a href="%s">SPF record authorizing GoDaddy</a> as a relay, or you will have delivery problems.', 'postman-smtp' ), 'http://www.mail-tester.com/spf/godaddy' ) );
			print '</fieldset>';
			
			// Wizard Step 3
			printf ( '<h5>%s</h5>', _x ( 'Connectivity Test', 'A testing tool which determines connectivity to the Internet', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'How will the connection to the MSA be established?', 'Wizard Step Title', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'Your connection settings depend on what your email service provider offers, and what your WordPress host allows.', 'postman-smtp' ) );
			printf ( '<p id="connectivity_test_status">%s: <span id="port_test_status">%s</span></p>', _x ( 'Connectivity Test', 'A testing tool which determines connectivity to the Internet', 'postman-smtp' ), _x ( 'Ready', 'TCP Port Test Status', 'postman-smtp' ) );
			printf ( '<p class="ajax-loader" style="display:none"><img src="%s"/></p>', plugins_url ( 'postman-smtp/style/ajax-loader.gif' ) );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::TRANSPORT_TYPE );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::PORT );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::ENCRYPTION_TYPE );
			printf ( '<input type="hidden" id="input_%2$s" name="%1$s[%2$s]">', PostmanOptions::POSTMAN_OPTIONS, PostmanOptions::AUTHENTICATION_TYPE );
			print '<p id="wizard_recommendation"></p>';
			/* Translators: Where %1$s is the socket identifier and %2$s is the authentication type */
			printf ( '<p class="user_override" style="display:none"><label><span>%s:</span></label> <table id="user_socket_override" class="user_override"></table></p>', _x ( 'Socket', 'A socket is the network term for host and port together', 'postman-smtp' ) );
			printf ( '<p class="user_override" style="display:none"><label><span>%s:</span></label> <table id="user_auth_override" class="user_override"></table></p>', _x ( 'Authentication', 'Authentication proves the user\'s identity', 'postman-smtp' ) );
			print ('<p><span id="smtp_mitm" style="display:none; background-color:yellow"></span></p>') ;
			printf ( '<p id="smtp_not_secure" style="display:none"><span style="background-color:yellow">%s</span></p>', __ ( 'Warning: This configuration option will send your authorization credentials in the clear.', 'postman-smtp' ) );
			print '</fieldset>';
			
			// Wizard Step 4
			printf ( '<h5>%s</h5>', _x ( 'Authentication', 'Authentication proves the user\'s identity', 'postman-smtp' ) );
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
			printf ( '<p class="port-explanation-ssl">%s</p>', __ ( 'Enter your credentials. Your username is often your email address.', 'postman-smtp' ) );
			printf ( '<label for="username">%s</label>', _x ( 'Username', 'Configuration Input Field', 'postman-smtp' ) );
			print '<br />';
			print $this->adminController->basic_auth_username_callback ();
			print '<br />';
			printf ( '<label for="password">%s</label>', __ ( 'Password', 'postman-smtp' ) );
			print '<br />';
			print $this->adminController->basic_auth_password_callback ();
			print '</section>';
			
			print '</fieldset>';
			
			// Wizard Step 5
			printf ( '<h5>%s</h5>', _x ( 'Finish', 'The final step of the Wizard', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', _x ( 'You\'re Done!', 'Wizard Step Title', 'postman-smtp' ) );
			print '<section>';
			printf ( '<p>%s</p>', __ ( 'Click Finish to save these settings, then:', 'postman-smtp' ) );
			print '<ul style="margin-left: 20px">';
			printf ( '<li class="wizard-auth-oauth2">%s</li>', __ ( 'Grant permission with the Email Provider for Postman to send email and', 'postman-smtp' ) );
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
			
			$this->outputChildPageHeader ( __ ( 'Send a Test Email', 'postman-smtp' ) );
			
			printf ( '<form id="postman_test_email_wizard" method="post" action="%s">', PostmanUtils::getSettingsPageUrl () );
			
			// Step 1
			printf ( '<h5>%s</h5>', __ ( 'Specify the Recipient', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Who is this message going to?', 'postman-smtp' ) );
			printf ( '<p>%s', __ ( 'This utility allows you to send an email message for testing.', 'postman-smtp' ) );
			print ' ';
			/* translators: where %d is an amount of time, in seconds */
			printf ( '%s</p>', sprintf ( _n ( 'If there is a problem, Postman will give up after %d second.', 'If there is a problem, Postman will give up after %d seconds.', $this->options->getReadTimeout (), 'postman-smtp' ), $this->options->getReadTimeout () ) );
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
			printf ( '<p><label>%s</label></p>', _x ( 'Status', 'Was sending this email successful or not?', 'postman-smtp' ) );
			print '<textarea id="postman_test_message_error_message" readonly="readonly" cols="65" rows="4"></textarea>';
			print '</section>';
			print '</fieldset>';
			
			// Step 3
			printf ( '<h5>%s</h5>', __ ( 'Session Transcript', 'postman-smtp' ) );
			print '<fieldset>';
			printf ( '<legend>%s</legend>', __ ( 'Examine the SMTP Session Transcript if you need to.', 'postman-smtp' ) );
			printf ( '<p>%s</p>', __ ( 'This is the conversation between Postman and your SMTP server. It can be useful for diagnosing problems. <b>DO NOT</b> post it on-line, it may contain your account password.', 'postman-smtp' ) );
			print '<section>';
			printf ( '<p><label for="postman_test_message_transcript">%s</label></p>', __ ( 'SMTP Session Transcript', 'postman-smtp' ) );
			print '<textarea readonly="readonly" id="postman_test_message_transcript" cols="65" rows="8"></textarea>';
			print '</section>';
			print '</fieldset>';
			
			print '</form>';
			print '</div>';
		}
	}
}
		