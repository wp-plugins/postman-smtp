<?php
if (! class_exists ( "PostmanAdminController" )) {
	
	require_once "SendTestEmailController.php";
	
	//
	class PostmanAdminController {
		// UI defaults
		const DEFAULT_GMAIL_OAUTH_HOSTNAME = 'smtp.gmail.com';
		const DEFAULT_GMAIL_OAUTH_PORT = 465;
		
		// The Postman Group is used for saving data, make sure it is globally unique
		const SETTINGS_GROUP_NAME = 'postman_group';
		
		// The Session variables that carry messages
		const ERROR_MESSAGE = 'POSTMAN_ERROR_MESSAGE';
		const WARNING_MESSAGE = 'POSTMAN_WARNING_MESSAGE';
		const SUCCESS_MESSAGE = 'POSTMAN_SUCCESS_MESSAGE';
		
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
		private $util;
		
		// the Authorization Token
		private $authorizationToken;
		
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;
		private $testOptions;
		
		/**
		 * Start up
		 */
		public function __construct($basename) {
			$this->logger = new PostmanLogger ();
			$this->util = new PostmanWordpressUtil ();
			$this->options = get_option ( PostmanWordpressUtil::POSTMAN_OPTIONS );
			$this->authorizationToken = new PostmanAuthorizationToken ();
			$this->authorizationToken->load ();
			
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
			
			if (! $this->isRequestOAuthPermissiongAllowed () || ! $this->isSendingEmailAllowed ()) {
				add_action ( 'admin_notices', Array (
						$this,
						'displayConfigurationRequiredWarning' 
				) );
			}
			
			if (isset ( $_SESSION [PostmanAdminController::ERROR_MESSAGE] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'displayErrorSessionMessage' 
				) );
			}
			
			if (isset ( $_SESSION [PostmanAdminController::WARNING_MESSAGE] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'displayWarningSessionMessage' 
				) );
			}
			
			if (isset ( $_SESSION [PostmanAdminController::SUCCESS_MESSAGE] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'displaySuccessSessionMessage' 
				) );
			}
			
			if (isset ( $_SESSION [GmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] )) {
				if (isset ( $_GET ['code'] )) {
					$this->logger->debug ( 'Authorization in progress' );
					unset ( $_SESSION [GmailAuthenticationManager::AUTHORIZATION_IN_PROGRESS] );
					
					$authenticationManager = PostmanAuthenticationManagerFactory::createAuthenticationManager ( $this->options, $this->authorizationToken );
					try {
						if ($authenticationManager->tradeCodeForToken ()) {
							$this->logger->debug ( 'Authorization successful' );
							// save to database
							$this->authorizationToken->save ();
						} else {
							$this->util->addError ( 'Your email provider did not grant Postman permission. Try again.' );
						}
					} catch ( Google_Auth_Exception $e ) {
						$this->logger->debug ( 'Error: ' . get_class ( $e ) . ' code=' . $e->getCode () . ' message=' . $e->getMessage () );
						$this->util->addError ( 'Error authenticating with this Client ID - please create a new one. [<em>' . $e->getMessage () . ' code=' . $e->getCode () . '</em>]' );
					}
					header ( 'Location: ' . esc_url ( POSTMAN_HOME_PAGE_URL ) );
					exit ();
				} else {
					// recover from an aborted Authorization (back button or cancel triggers this)
					unset ( $_SESSION [PostmanAuthenticationManager::AUTHORIZATION_IN_PROGRESS] );
					// continue on with the current request.... do not die()
				}
			}
		}
		//
		public function add_action_links($links) {
			$mylinks = array (
					'<a href="' . esc_url ( POSTMAN_HOME_PAGE_URL ) . '">Settings</a>' 
			);
			return array_merge ( $links, $mylinks );
		}
		public function handlePurgeDataAction() {
			delete_option ( PostmanWordpressUtil::POSTMAN_OPTIONS );
			delete_option ( PostmanAuthorizationToken::OPTIONS_NAME );
			delete_option ( PostmanAdminController::TEST_OPTIONS );
			header ( 'Location: ' . esc_url ( POSTMAN_HOME_PAGE_URL ) );
			exit ();
		}
		public function isRequestOAuthPermissiongAllowed() {
			$clientId = PostmanOptionUtil::getClientId ( $this->options );
			$clientSecret = PostmanOptionUtil::getClientSecret ( $this->options );
			return (! empty ( $clientId ) && ! empty ( $clientSecret ));
		}
		public function isSendingEmailAllowed() {
			$accessToken = $this->authorizationToken->getAccessToken ();
			$refreshToken = $this->authorizationToken->getRefreshToken ();
			$senderEmail = PostmanOptionUtil::getSenderEmail ( $this->options );
			
			return ! empty ( $accessToken ) && ! empty ( $refreshToken ) && ! empty ( $senderEmail );
		}
		
		/**
		 * Handle admin messages
		 */
		public function displayConfigurationRequiredWarning() {
			$message = PostmanAdminController::NAME . ' is activated, but <em>not</em> intercepting mail requests. <a href="' . POSTMAN_HOME_PAGE_URL . '">Configure and Authorize</a> the plugin.';
			$this->displayWarningMessage ( $message );
		}
		//
		public function displaySuccessSessionMessage() {
			$this->displaySuccessMessage ( $this->retrieveSessionMessage ( PostmanAdminController::SUCCESS_MESSAGE ), 'updated' );
		}
		public function displayErrorSessionMessage() {
			$this->displayErrorMessage ( $this->retrieveSessionMessage ( PostmanAdminController::ERROR_MESSAGE ), 'error' );
		}
		public function displayWarningSessionMessage() {
			$this->displayWarningMessage ( $this->retrieveSessionMessage ( PostmanAdminController::WARNING_MESSAGE ), 'update-nag' );
		}
		private function retrieveSessionMessage($sessionVar) {
			$message = $_SESSION [$sessionVar];
			unset ( $_SESSION [$sessionVar] );
			return $message;
		}
		//
		public function displaySuccessMessage($message) {
			$this->displayMessage ( $message, 'updated' );
		}
		public function displayErrorMessage($message) {
			$this->displayMessage ( $message, 'error' );
		}
		public function displayWarningMessage($message) {
			$this->displayMessage ( $message, 'update-nag' );
		}
		private function displayMessage($message, $className) {
			echo '<div class="' . $className . '"><p>' . $message . '</p></div>';
		}
		
		//
		private function setDefaults() {
			if (! isset ( $this->options [PostmanOptionUtil::HOSTNAME] )) {
				$this->options [PostmanOptionUtil::HOSTNAME] = PostmanAdminController::DEFAULT_GMAIL_OAUTH_HOSTNAME;
			}
			if (! isset ( $this->options [PostmanOptionUtil::PORT] )) {
				$this->options [PostmanOptionUtil::PORT] = PostmanAdminController::DEFAULT_GMAIL_OAUTH_PORT;
			}
			if (! isset ( $this->options ['smtp_type'] )) {
				$this->options ['smtp_type'] = 'gmail';
			}
			$defaultFrom = wp_get_current_user ()->user_email;
			// $defaultFrom = createLegacySenderEmail ();
			if (! isset ( $this->options [PostmanOptionUtil::SENDER_EMAIL] )) {
				$this->options [PostmanOptionUtil::SENDER_EMAIL] = $defaultFrom;
			}
			if (! isset ( $this->options [PostmanOptionUtil::TEST_EMAIL] )) {
				$current_user = wp_get_current_user ();
				$this->testOptions [PostmanOptionUtil::TEST_EMAIL] = $current_user->user_email;
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
			$testEmailController->send ( $this->options, $this->authorizationToken, $recipient );
		}
		public function handleGoogleAuthenticationAction() {
			$authenticationManager = PostmanAuthenticationManagerFactory::createAuthenticationManager ( $this->options, $this->authorizationToken );
			$authenticationManager->authenticate ( PostmanOptionUtil::getSenderEmail ( $this->options ) );
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
			}
			?>
	
	</div>
			
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
				if (! $this->isRequestOAuthPermissiongAllowed ()) {
					$disabled = "disabled='disabled'";
				}
				submit_button ( 'Request Permission from Google', 'primary', 'submit', true, $disabled );
				?>
	</form>
	<form method="POST" action="<?php get_admin_url()?>admin-post.php">
		<input type='hidden' name='action' value='test_mail' />
            <?php
				do_settings_sections ( PostmanAdminController::POSTMAN_TEST_SLUG );
				if (! $this->isSendingEmailAllowed ()) {
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
			register_setting ( PostmanAdminController::SETTINGS_GROUP_NAME, PostmanWordpressUtil::POSTMAN_OPTIONS, array (
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
			
			add_settings_field ( PostmanOptionUtil::SENDER_EMAIL, 'Sender Email Address', array (
					$this,
					'sender_email_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( PostmanOptionUtil::HOSTNAME, 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( PostmanOptionUtil::PORT, 'SSL Port', array (
					$this,
					'port_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_section ( 'OAUTH_SETTINGS', 'OAuth Settings', array (
					$this,
					'printOAuthSectionInfo' 
			), PostmanAdminController::POSTMAN_SLUG );
			
			add_settings_field ( PostmanOptionUtil::CLIENT_ID, 'Client ID', array (
					$this,
					'oauth_client_id_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( PostmanOptionUtil::CLIENT_SECRET, 'Client Secret', array (
					$this,
					'oauth_client_secret_callback' 
			), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			// add_settings_field ( PostmanOptionUtil::ACCESS_TOKEN, 'Access Token', array (
			// $this,
			// 'access_token_callback'
			// ), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			// add_settings_field ( PostmanOptionUtil::REFRESH_TOKEN, 'Refresh Token', array (
			// $this,
			// 'refresh_token_callback'
			// ), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			// add_settings_field ( PostmanOptionUtil::TOKEN_EXPIRES, 'Token Expiry Times', array (
			// $this,
			// 'token_expiry_callback'
			// ), PostmanAdminController::POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
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
			
			if (isset ( $input [PostmanOptionUtil::HOSTNAME] ))
				$new_input [PostmanOptionUtil::HOSTNAME] = sanitize_text_field ( $input [PostmanOptionUtil::HOSTNAME] );
			
			if (isset ( $input [PostmanOptionUtil::PORT] ))
				$new_input [PostmanOptionUtil::PORT] = absint ( $input [PostmanOptionUtil::PORT] );
			
			if (isset ( $input [PostmanOptionUtil::SENDER_EMAIL] ))
				$new_input [PostmanOptionUtil::SENDER_EMAIL] = sanitize_text_field ( $input [PostmanOptionUtil::SENDER_EMAIL] );
			
			if (isset ( $input [PostmanOptionUtil::CLIENT_ID] ))
				$new_input [PostmanOptionUtil::CLIENT_ID] = sanitize_text_field ( $input [PostmanOptionUtil::CLIENT_ID] );
			
			if (isset ( $input [PostmanOptionUtil::CLIENT_SECRET] ))
				$new_input [PostmanOptionUtil::CLIENT_SECRET] = sanitize_text_field ( $input [PostmanOptionUtil::CLIENT_SECRET] );
			
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
			printf ( '<select disabled="true" id="smtp_type" name="postman_options[smtp_type]" /><option name="gmail">%s</option></select>', isset ( $this->options [PostmanOptionUtil::SMTP_TYPE] ) ? esc_attr ( $this->options [PostmanOptionUtil::SMTP_TYPE] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input readonly="readonly" type="text" id="hostname" name="postman_options[hostname]" value="%s" />', isset ( $this->options [PostmanOptionUtil::HOSTNAME] ) ? esc_attr ( $this->options [PostmanOptionUtil::HOSTNAME] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_callback() {
			printf ( '<input readonly="readonly" type="text" id="port" name="postman_options[port]" value="%s" />', isset ( $this->options [PostmanOptionUtil::PORT] ) ? esc_attr ( $this->options [PostmanOptionUtil::PORT] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="text" id="sender_email" name="postman_options[sender_email]" value="%s" />', isset ( $this->options [PostmanOptionUtil::SENDER_EMAIL] ) ? esc_attr ( $this->options [PostmanOptionUtil::SENDER_EMAIL] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_id_callback() {
			printf ( '<input type="text" id="oauth_client_id" name="postman_options[oauth_client_id]" value="%s" size="71" />', isset ( $this->options [PostmanOptionUtil::CLIENT_ID] ) ? esc_attr ( $this->options [PostmanOptionUtil::CLIENT_ID] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="24"/>', isset ( $this->options [PostmanOptionUtil::CLIENT_SECRET] ) ? esc_attr ( $this->options [PostmanOptionUtil::CLIENT_SECRET] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function refresh_token_callback() {
			printf ( '<input readonly="true" type="text" id="refresh_token" name="postman_options[refresh_token]" value="%s" size="45" />', isset ( $this->options ['refresh_token'] ) ? esc_attr ( $this->options ['refresh_token'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function token_expiry_callback() {
			printf ( '<input readonly="true" type="text" id="auth_token_expires" name="postman_options[auth_token_expires]" value="%s" size="45" />', isset ( $this->options ['auth_token_expires'] ) ? esc_attr ( $this->options ['auth_token_expires'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function access_token_callback() {
			printf ( '<input readonly="true" type="text" id="access_token" name="postman_options[access_token]" value="%s" size="83" />', isset ( $this->options [PostmanOptionUtil::ACCESS_TOKEN] ) ? esc_attr ( $this->options [PostmanOptionUtil::ACCESS_TOKEN] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" />', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
	}
}
