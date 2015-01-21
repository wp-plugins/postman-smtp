<?php

namespace Postman {
	
	//
	class PostmanAdminController {
		const DEFAULT_GMAIL_OAUTH_HOSTNAME = 'smtp.gmail.com';
		const DEFAULT_GMAIL_OAUTH_PORT = 465;
		const TEST_EMAIL_SUCCESS = 'POSTMAN_TEST_EMAIL_SUCCESS';
		const TEST_EMAIL_FAILURE = 'POSTMAN_TEST_EMAIL_FAILURE';
		
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;
		private $testOptions;
		
		/**
		 * Start up
		 */
		public function __construct() {
			session_start ();
			$this->options = get_option ( POSTMAN_OPTIONS );
			
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
				// if (is_multisite ()) {
				// add_action ( 'network_admin_notices', Array (
				// $this,
				// 'displayConfigurationRequiredWarning'
				// ) );
				// }
			}
			
			if (isset ( $_SESSION [PostmanAdminController::TEST_EMAIL_SUCCESS] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'displayTestEmailSentMessage' 
				) );
				// if (is_multisite ()) {
				// add_action ( 'network_admin_notices', Array (
				// $this,
				// 'displayTestEmailSentMessage'
				// ) );
				// }
			}
			
			if (isset ( $_SESSION [PostmanAdminController::TEST_EMAIL_FAILURE] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'displayTestEmailFailedMessage' 
				) );
				// if (is_multisite ()) {
				// add_action ( 'network_admin_notices', Array (
				// $this,
				// 'displayTestEmailFailedMessage'
				// ) );
				// }
			}
		}
		public function handlePurgeDataAction() {
			$emptyOptions = array ();
			update_option ( POSTMAN_OPTIONS, $emptyOptions );
			wp_redirect ( HOME_PAGE_URL );
			exit ();
		}
		public function addWarningUnableToImplementWpMail() {
			add_action ( 'admin_notices', array (
					$this,
					'displayUnableToImplementWpMailWarning' 
			) );
		}
		public function isRequestOAuthPermissiongAllowed() {
			return ! empty ( $this->options [Options::CLIENT_ID] ) && ! empty ( $this->options [Options::CLIENT_SECRET] );
		}
		public function isSendingEmailAllowed() {
			return ! empty ( $this->options [Options::ACCESS_TOKEN] ) && ! empty ( $this->options [Options::REFRESH_TOKEN] ) && ! empty ( $this->options [Options::SENDER_EMAIL] );
		}
		public function displayUnableToImplementWpMailWarning() {
			echo '<div class="error"><p>';
			echo sprintf ( __ ( POSTMAN_NAME . ' is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', POSTMAN_PLUGIN_DIRECTORY ), esc_url ( HOME_PAGE_URL ) );
			echo '</p></div>';
		}
		public function displayConfigurationRequiredWarning() {
			echo '<div class="update-nag"><p>';
			echo sprintf ( __ ( POSTMAN_NAME . ' is activated, but <em>not</em> intercepting mail requests. <a href="%s">Configure and Authorize</a> the plugin.', POSTMAN_PLUGIN_DIRECTORY ), esc_url ( HOME_PAGE_URL ) );
			echo '</p></div>';
		}
		public function displayTestEmailSentMessage() {
			unset ( $_SESSION [PostmanAdminController::TEST_EMAIL_SUCCESS] );
			echo '<div class="updated"><p>';
			echo sprintf ( __ ( 'Your message was sent! Congratulations :)', POSTMAN_PLUGIN_DIRECTORY ), esc_url ( HOME_PAGE_URL ) );
			echo '</p></div>';
		}
		public function displayTestEmailFailedMessage() {
			?><div class="error">
	<p>Oh, bother! Your test message failed to send :( ... <?php echo $_SESSION[PostmanAdminController::TEST_EMAIL_FAILURE] ?></p>
</div><?php
			unset ( $_SESSION [PostmanAdminController::TEST_EMAIL_FAILURE] );
		}
		
		//
		private function setDefaults() {
			if ($this->options [Options::HOSTNAME] == '') {
				$this->options [Options::HOSTNAME] = PostmanAdminController::DEFAULT_GMAIL_OAUTH_HOSTNAME;
			}
			if ($this->options [Options::PORT] == '') {
				$this->options [Options::PORT] = PostmanAdminController::DEFAULT_GMAIL_OAUTH_PORT;
			}
			if ($this->options ['smtp_type'] == '') {
				$this->options ['smtp_type'] = 'gmail';
			}
			$defaultFrom = $current_user->user_email;
			// $defaultFrom = createLegacySenderEmail ();
			if (! isset ( $this->options [Options::SENDER_EMAIL] )) {
				$this->options [Options::SENDER_EMAIL] = $defaultFrom;
			}
			if (! isset ( $this->options [Options::TEST_EMAIL] )) {
				$current_user = wp_get_current_user ();
				$this->testOptions [Options::TEST_EMAIL] = $current_user->user_email;
			}
		}
		
		/**
		 * Add options page
		 */
		public function add_plugin_page() {
			// This page will be under "Settings"
			add_options_page ( POSTMAN_PAGE_TITLE, POSTMAN_MENU_TITLE, 'manage_options', POSTMAN_SLUG, array (
					$this,
					'create_admin_page' 
			) );
		}
		public function handleTestEmailAction() {
			$recipient = $_POST ['postman_test_options'] ['test_email'];
			$hostname = $this->options [Options::HOSTNAME];
			$port = $this->options [Options::PORT];
			$from = $this->options [Options::SENDER_EMAIL];
			$subject = 'WordPress SMTP OAuth Mailer Test';
			$message = "Hello, World!";
			
			if (\Postman\DEBUG) {
				print "<h2>Sending Test email</h2><br/>";
				print "Server: " . $hostname . ":" . $port . "<br/>";
				?><br /><?php
				print "From: " . $from . "<br/>";
				print "To: " . $recipient . "<br/>";
				print "Subject: " . $subject . "<br/>";
				print "<br/>";
				print $body . "<br/>";
			}
			
			// send through wp_mail
			// $result = wp_mail ( $recipient, $subject, $message . ' - sent by Postman through wp_mail()' );
			
			// send through our own engine
			$engine = new PostmanOAuthSmtpEngine ();
			$engine->setBodyText ( $message );
			// $engine->setBodyText ( $message . ' - sent by Postman through PostmanOAuthSmtpEngine()' );
			$engine->setSubject ( $subject );
			$engine->addTo ( $recipient );
			$result = $engine->send ();
			
			//
			$url = HOME_PAGE_URL;
			if ($result) {
				$_SESSION [PostmanAdminController::TEST_EMAIL_SUCCESS] = 'true';
				unset ( $_SESSION [PostmanAdminController::TEST_EMAIL_FAILURE] );
				print "<h3>No Errors :)</h3>";
			} else {
				unset ( $_SESSION [PostmanAdminController::TEST_EMAIL_SUCCESS] );
				print "<h3>Failed |(</h3>";
				if ($engine->getException ()->getCode () == 334) {
					$_SESSION [PostmanAdminController::TEST_EMAIL_FAILURE] = 'Communication Error [334].';
				} else {
					$_SESSION [PostmanAdminController::TEST_EMAIL_FAILURE] = $engine->getException ()->getMessage () . ' [' . $engine->getException ()->getCode () . '].';
				}
			}
			
			if (\Postman\DEBUG) {
				?><a href="<?php echo $url ?>">Back to Plugin</a><?php
			} else {
				wp_redirect ( $url );
				exit ();
			}
		}
		public function handleGoogleAuthenticationAction() {
			$authenticationToken = new AuthenticationToken ( get_option ( POSTMAN_OPTIONS ) );
			$authenticationManager = new GmailAuthenticationManager ( $authenticationToken );
			$authenticationManager->authenticate ();
		}
		
		/**
		 * Options page callback
		 */
		public function create_admin_page() {
			
			// Set class property
			$this->setDefaults ();
			?>
<div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo POSTMAN_PAGE_TITLE ?></h2>
	<form method="post" action="options.php">
	<?php
			// This prints out all hidden setting fields
			settings_fields ( 'my_option_group' );
			do_settings_sections ( POSTMAN_SLUG );
			submit_button ();
			?>
			</form>
	<form method="POST" action="<?php get_admin_url()?>admin-post.php">
		<input type='hidden' name='action' value='gmail_auth' />
            <?php
			if (! $this->isRequestOAuthPermissiongAllowed ()) {
				$disabled = "disabled='disabled'";
			}
			submit_button ( 'Request Permission from Google', 'primary', 'submit', true, $disabled );
			?>
	</form>
	<form method="POST" action="<?php get_admin_url()?>admin-post.php">
		<input type='hidden' name='action' value='test_mail' />
            <?php
			do_settings_sections ( POSTMAN_TEST_SLUG );
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
			register_setting ( 'my_option_group', POSTMAN_OPTIONS, array (
					$this,
					'sanitize' 
			) );
			
			// Sanitize
			add_settings_section ( 'SMTP_SETTINGS', 'SMTP Settings', array (
					$this,
					'printSmtpSectionInfo' 
			), POSTMAN_SLUG );
			
			add_settings_field ( 'smtp_type', 'Type', array (
					$this,
					'smtp_type_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( Options::SENDER_EMAIL, 'Sender Email Address', array (
					$this,
					'sender_email_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( Options::HOSTNAME, 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( Options::PORT, 'SSL Port', array (
					$this,
					'port_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_section ( 'OAUTH_SETTINGS', 'OAuth Settings', array (
					$this,
					'printOAuthSectionInfo' 
			), POSTMAN_SLUG );
			
			add_settings_field ( Options::CLIENT_ID, 'Client ID', array (
					$this,
					'oauth_client_id_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( Options::CLIENT_SECRET, 'Client Secret', array (
					$this,
					'oauth_client_secret_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( Options::ACCESS_TOKEN, 'Access Token', array (
					$this,
					'access_token_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( 'refresh_token', 'Refresh Token', array (
					$this,
					'refresh_token_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			register_setting ( 'email_group', POSTMAN_TEST_OPTIONS, array (
					$this,
					'testSanitize' 
			) );
			
			add_settings_section ( 'TEST_EMAIL', 'Test Your Setup', array (
					$this,
					'printTestEmailSectionInfo' 
			), POSTMAN_TEST_SLUG );
			
			add_settings_field ( 'test_email', 'Recipient Email Address', array (
					$this,
					'test_email_callback' 
			), POSTMAN_TEST_SLUG, 'TEST_EMAIL' );
		}
		
		/**
		 * Sanitize each setting field as needed
		 *
		 * @param array $input
		 *        	Contains all settings fields as array keys
		 */
		public function sanitize($input) {
			$new_input = array ();
			
			if (isset ( $input ['smtp_type'] ))
				$new_input ['smtp_type'] = sanitize_text_field ( $input ['smtp_type'] );
			
			if (isset ( $input [Options::HOSTNAME] ))
				$new_input [Options::HOSTNAME] = sanitize_text_field ( $input [Options::HOSTNAME] );
			
			if (isset ( $input [Options::PORT] ))
				$new_input [Options::PORT] = absint ( $input [Options::PORT] );
			
			if (isset ( $input [Options::SENDER_EMAIL] ))
				$new_input [Options::SENDER_EMAIL] = sanitize_text_field ( $input [Options::SENDER_EMAIL] );
			
			if (isset ( $input [Options::CLIENT_ID] ))
				$new_input [Options::CLIENT_ID] = sanitize_text_field ( $input [Options::CLIENT_ID] );
			
			if (isset ( $input [Options::CLIENT_SECRET] ))
				$new_input [Options::CLIENT_SECRET] = sanitize_text_field ( $input [Options::CLIENT_SECRET] );
			
			if (isset ( $input ['refresh_token'] ))
				$new_input ['refresh_token'] = sanitize_text_field ( $input ['refresh_token'] );
			
			if (isset ( $input [Options::ACCESS_TOKEN] ))
				$new_input [Options::ACCESS_TOKEN] = sanitize_text_field ( $input [Options::ACCESS_TOKEN] );
			
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
			print 'You can create a Client ID for your Gmail account at the <a href="https://console.developers.google.com/">Google Developers Console</a> (look under APIs -> Credentials). The Redirect URI to use is <b>' . admin_url ( 'options-general.php' ) . '</b> - detailed instructions will be added soon.';
		}
		
		/**
		 * Print the Section text
		 */
		public function printTestEmailSectionInfo() {
			print 'Test your setup here. ';
			// print 'This will send TWO e-mails; one through Postman\'s own engine, and another through the WordPress wp_mail() call.';
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function smtp_type_callback() {
			printf ( '<select disabled="true" id="smtp_type" name="postman_options[smtp_type]" /><option name="gmail">%s</option></select>', isset ( $this->options [Options::SMTP_TYPE] ) ? esc_attr ( $this->options [Options::SMTP_TYPE] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input type="text" id="hostname" name="postman_options[hostname]" value="%s" />', isset ( $this->options [Options::HOSTNAME] ) ? esc_attr ( $this->options [Options::HOSTNAME] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_callback() {
			printf ( '<input type="text" id="port" name="postman_options[port]" value="%s" />', isset ( $this->options [Options::PORT] ) ? esc_attr ( $this->options [Options::PORT] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function sender_email_callback() {
			printf ( '<input type="text" id="sender_email" name="postman_options[sender_email]" value="%s" />', isset ( $this->options [Options::SENDER_EMAIL] ) ? esc_attr ( $this->options [Options::SENDER_EMAIL] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_id_callback() {
			printf ( '<input type="text" id="oauth_client_id" name="postman_options[oauth_client_id]" value="%s" size="71" />', isset ( $this->options [Options::CLIENT_ID] ) ? esc_attr ( $this->options [Options::CLIENT_ID] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="24"/>', isset ( $this->options [Options::CLIENT_SECRET] ) ? esc_attr ( $this->options [Options::CLIENT_SECRET] ) : '' );
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
		public function access_token_callback() {
			printf ( '<input readonly="true" type="text" id="access_token" name="postman_options[access_token]" value="%s" size="83" />', isset ( $this->options [Options::ACCESS_TOKEN] ) ? esc_attr ( $this->options [Options::ACCESS_TOKEN] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" />', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
	}
}