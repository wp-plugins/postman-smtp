<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "SendTestEmailController.php";
	require_once 'PostmanOptions.php';
	require_once 'PostmanAuthorizationToken.php';
	
	//
	class PostmanAdminController {
		// UI defaults
		const DEFAULT_GMAIL_OAUTH_HOSTNAME = 'smtp.gmail.com';
		const DEFAULT_GMAIL_OAUTH_PORT = 465;
		
		// The Postman Group is used for saving data, make sure it is globally unique
		const SETTINGS_GROUP_NAME = 'postman_group';
		
		// a database entry specifically for the form that sends test e-mail
		const TEST_OPTIONS = 'postman_test_options';
		
		// page titles
		const NAME = 'Postman SMTP';
		const PAGE_TITLE = 'Postman SMTP Settings';
		const MENU_TITLE = 'Postman SMTP';
		
		// slugs
		const POSTMAN_SLUG = 'postman';
		const POSTMAN_TEST_SLUG = 'postman-test';
		
		//
		private $logger;
		
		// the Authorization Token
		private $authorizationToken;
		
		// the message handler
		private $postmanMessageHandler;
		
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;
		private $testOptions;
		
		/**
		 * Start up
		 */
		public function __construct($basename, PostmanOptions $options, PostmanAuthorizationToken $authorizationToken, PostmanMessageHandler $postmanMessageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $postmanMessageHandler ) );
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->postmanMessageHandler = $postmanMessageHandler;
			
			// Adds "Settings" link to the plugin action page
			add_filter ( 'plugin_action_links_' . $basename, array (
					$this,
					'add_action_links' 
			) );
			
			add_action ( 'admin_menu', array (
					$this,
					'add_plugin_page' 
			) );
			add_action ( 'admin_init', array (
					$this,
					'page_init' 
			) );
			
			add_action ( 'admin_post_test_mail', array (
					$this,
					'handleTestEmailAction' 
			) );
			
			add_action ( 'admin_post_gmail_auth', array (
					$this,
					'handleGoogleAuthenticationAction' 
			) );
			add_action ( 'admin_post_purge_data', array (
					$this,
					'handlePurgeDataAction' 
			) );
		}
		//
		public function add_action_links($links) {
			$mylinks = array (
					'<a href="' . esc_url ( POSTMAN_HOME_PAGE_URL ) . '">Settings</a>' 
			);
			return array_merge ( $links, $mylinks );
		}
		public function handlePurgeDataAction() {
			delete_option ( PostmanOptions::POSTMAN_OPTIONS );
			delete_option ( PostmanAuthorizationToken::OPTIONS_NAME );
			delete_option ( PostmanAdminController::TEST_OPTIONS );
			header ( 'Location: ' . esc_url ( POSTMAN_HOME_PAGE_URL ) );
			exit ();
		}
		
		//
		private function setDefaults() {
			$this->options->setHostnameIfEmpty ( PostmanAdminController::DEFAULT_GMAIL_OAUTH_HOSTNAME );
			$this->options->setPortIfEmpty ( PostmanAdminController::DEFAULT_GMAIL_OAUTH_PORT );
			$this->options->setSmtpTypeIfEmpty ( 'gmail' );
			$this->options->setSenderEmailIfEmpty ( wp_get_current_user ()->user_email );
			if (! isset ( $this->testOptions [PostmanOptions::TEST_EMAIL] )) {
				$this->testOptions [PostmanOptions::TEST_EMAIL] = wp_get_current_user ()->user_email;
			}
		}
		
		/**
		 * Add options page
		 */
		public function add_plugin_page() {
			// This page will be under "Settings"
			add_options_page ( PostmanAdminController::PAGE_TITLE, PostmanAdminController::MENU_TITLE, 'manage_options', PostmanAdminController::POSTMAN_SLUG, array (
					$this,
					'create_admin_page' 
			) );
		}
		public function handleTestEmailAction() {
			$recipient = $_POST [PostmanAdminController::TEST_OPTIONS] ['test_email'];
			$testEmailController = new PostmanSendTestEmailController ();
			$testEmailController->send ( $this->options, $this->authorizationToken, $recipient, $this->postmanMessageHandler );
		}
		public function handleGoogleAuthenticationAction() {
			$authenticationManager = PostmanAuthenticationManagerFactory::getInstance ()->createAuthenticationManager ( $this->options->getClientId (), $this->options->getClientSecret (), $this->authorizationToken );
			$authenticationManager->authenticate ( $this->options->getSenderEmail () );
		}
		
		/**
		 * Options page callback
		 */
		public function create_admin_page() {
			
			// test features
			$sslRequirement = extension_loaded ( 'openssl' );
			$splAutoloadRegisterRequirement = function_exists ( 'spl_autoload_register' );
			$phpVersionRequirement = PHP_VERSION_ID >= 50300;
			$arrayObjectRequirement = class_exists ( 'ArrayObject' );
			
			// Set class property
			$this->setDefaults ();
			?>
<div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo PostmanAdminController::PAGE_TITLE ?></h2>

            <?php
			if (! $sslRequirement || ! $splAutoloadRegisterRequirement || ! $arrayObjectRequirement) {
				?><div style="background-color: white; padding: 10px;">
		<b style='color: red'>Warning, your system does not meet the
			pre-requisites - something may fail:</b>
		<ul><?php
				print '<li>PHP v5.3: ' . ($phpVersionRequirement ? 'Yes' : 'No (' . PHP_VERSION . ')') . '</li>';
				print '<li>SSL Extension: ' . ($sslRequirement ? 'Yes' : 'No') . '</li>';
				print '<li>spl_autoload_register: ' . ($splAutoloadRegisterRequirement ? 'Yes' : 'No') . '</li>';
				print '<li>ArrayObject: ' . ($arrayObjectRequirement ? 'Yes' : 'No') . '</li>';
				print '</div>';
			}
			?>
	
            <form method="post" action="options.php">
	<?php
			// This prints out all hidden setting fields
			settings_fields ( PostmanAdminController::SETTINGS_GROUP_NAME );
			do_settings_sections ( PostmanAdminController::POSTMAN_SLUG );
			submit_button ();
			?>
			</form>
			<form method="POST" action="<?php get_admin_url()?>admin-post.php">
				<input type='hidden' name='action' value='gmail_auth' />
            <?php
			$disabled = '';
			if (! $this->options->isRequestOAuthPermissiongAllowed ()) {
				$disabled = "disabled='disabled'";
			}
			submit_button ( 'Request Permission from Google', 'primary', 'submit', true, $disabled );
			?>
	</form>
			<form method="POST" action="<?php get_admin_url()?>admin-post.php">
				<input type='hidden' name='action' value='test_mail' />
            <?php
			do_settings_sections ( PostmanAdminController::POSTMAN_TEST_SLUG );
			if (! $this->options->isSendingEmailAllowed ( PostmanAuthorizationToken::getInstance () )) {
				$disabled = "disabled='disabled'";
			}
			submit_button ( 'Send Test Email', 'primary', 'submit', true, $disabled );
			?>
	</form>
			<form method="POST" action="<?php get_admin_url()?>admin-post.php">
				<input type='hidden' name='action' value='purge_data' />
            <?php
			submit_button ( 'Delete All Data', 'delete', 'submit', true, 'style="background-color:red;color:white"' );
			?>
	</form>
	
	</div>
<?php
		}
		/**
		 * Register and add settings
		 */
		public function page_init() {
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanOptions::POSTMAN_OPTIONS, array (
					$this,
					'sanitize' 
			) );
			
			// Sanitize
			add_settings_section ( 'SMTP_SETTINGS', 'SMTP Settings', array (
					$this,
					'printSmtpSectionInfo' 
			), PostmanAdminController::POSTMAN_SLUG );
			
			add_settings_field ( 'smtp_type', 'Type', array (
					$this,
					'smtp_type_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( PostmanOptions::SENDER_EMAIL, 'Sender Email Address', array (
					$this,
					'sender_email_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( PostmanOptions::HOSTNAME, 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( PostmanOptions::PORT, 'SSL Port', array (
					$this,
					'port_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_section ( 'OAUTH_SETTINGS', 'OAuth Settings', array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::POSTMAN_SLUG );
			
			add_settings_field ( PostmanOptions::CLIENT_ID, 'Client ID', array (
					$this,
					'oauth_client_id_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( PostmanOptions::CLIENT_SECRET, 'Client Secret', array (
					$this,
					'oauth_client_secret_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
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
			
			if (isset ( $input ['smtp_type'] ))
				$new_input ['smtp_type'] = sanitize_text_field ( $input ['smtp_type'] );
			
			if (isset ( $input [PostmanOptions::HOSTNAME] ))
				$new_input [PostmanOptions::HOSTNAME] = sanitize_text_field ( $input [PostmanOptions::HOSTNAME] );
			
			if (isset ( $input [PostmanOptions::PORT] ))
				$new_input [PostmanOptions::PORT] = absint ( $input [PostmanOptions::PORT] );
			
			if (isset ( $input [PostmanOptions::SENDER_EMAIL] ))
				$new_input [PostmanOptions::SENDER_EMAIL] = sanitize_text_field ( $input [PostmanOptions::SENDER_EMAIL] );
			
			if (isset ( $input [PostmanOptions::CLIENT_ID] ))
				$new_input [PostmanOptions::CLIENT_ID] = sanitize_text_field ( $input [PostmanOptions::CLIENT_ID] );
			
			if (isset ( $input [PostmanOptions::CLIENT_SECRET] ))
				$new_input [PostmanOptions::CLIENT_SECRET] = sanitize_text_field ( $input [PostmanOptions::CLIENT_SECRET] );
			
			return $new_input;
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
			print 'Note: Gmail will NOT let you send from any email address <b>other than your own</b>.';
		}
		
		/**
		 * Print the Section text
		 */
		public function printOAuthSectionInfo() {
			print 'You can create a Client ID for your Gmail account at the <a href="https://console.developers.google.com/">Google Developers Console</a> (look under APIs -> Credentials). The Redirect URI to use is <b>' . POSTMAN_HOME_PAGE_URL . '</b>. There are <a href="https://wordpress.org/plugins/postman-smtp/installation/">additional instructions</a> on the Postman homepage.';
		}
		
		/**
		 * Print the Section text
		 */
		public function printTestEmailSectionInfo() {
			print 'Test your setup here. ';
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function smtp_type_callback() {
			printf ( '<select disabled="true" id="smtp_type" name="postman_options[smtp_type]" /><option name="gmail">%s</option></select>', null !== $this->options->getSmtpType () ? esc_attr ( $this->options->getSmtpType () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input readonly="readonly" type="text" id="hostname" name="postman_options[hostname]" value="%s" />', null !== $this->options->getHostname () ? esc_attr ( $this->options->getHostname () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_callback() {
			printf ( '<input readonly="readonly" type="text" id="port" name="postman_options[port]" value="%s" />', null !== $this->options->getPort () ? esc_attr ( $this->options->getPort () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="text" id="sender_email" name="postman_options[sender_email]" value="%s" />', null !== $this->options->getSenderEmail () ? esc_attr ( $this->options->getSenderEmail () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_id_callback() {
			printf ( '<input type="text" id="oauth_client_id" name="postman_options[oauth_client_id]" value="%s" size="71" />', null !== $this->options->getClientId () ? esc_attr ( $this->options->getClientId () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="24"/>', null !== $this->options->getClientSecret () ? esc_attr ( $this->options->getClientSecret () ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" />', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
	}
}
