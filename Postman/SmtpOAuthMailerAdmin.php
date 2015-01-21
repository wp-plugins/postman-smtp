<?php

namespace Postman {
	
	//
	class SmtpOAuthMailerAdmin {
		/**
		 * Holds the values to be used in the fields callbacks
		 */
		private $options;
		private $testOptions;
		
		/**
		 * Start up
		 */
		public function __construct() {
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
			
			if (empty ( $this->options ['access_token'] )) {
				add_action ( 'admin_notices', Array (
						$this,
						'ga_admin_auth_message' 
				) );
				if (is_multisite ()) {
					add_action ( 'network_admin_notices', Array (
							$this,
							'ga_admin_auth_message' 
					) );
				}
			}
		}
		public function ga_admin_auth_message() {
			echo '<div class="error"><p>';
			echo sprintf ( __ ( POSTMAN_NAME.' is <em>not</em> intercepting mail requests. Go to the <a href="%s">Settings</a> to configure the plugin.', POSTMAN_PLUGIN_DIRECTORY ), esc_url ( HOME_PAGE_URL ) );
			echo '</p></div>';
		}
		
		//
		private function setDefaults() {
			if ($this->options ['hostname'] == '') {
				$this->options ['hostname'] = 'smtp.gmail.com';
			}
			if ($this->options ['port'] == '') {
				$this->options ['port'] = '587';
			}
			
			if ($this->testOptions ['test_email'] == '') {
				$current_user = wp_get_current_user ();
				$this->testOptions ['test_email'] = $current_user->user_email;
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
			print "<h2>Sending Test email</h2><br/>";
			
			$recipient = $_POST ['postman_test_options'] ['test_email'];
			$hostname = $this->options ['hostname'];
			$port = $this->options ['port'];
			$from = $this->options ['oauth_email'];
			$subject = 'WordPress SMTP OAuth Mailer Test';
			$body = "Hello, World!";
			
			print "Server: " . $hostname . ":" . $port . "<br/>";
			?><br /><?php
			print "From: " . $from . "<br/>";
			print "To: " . $recipient . "<br/>";
			print "Subject: " . $subject . "<br/>";
			print "<br/>";
			print $body . "<br/>";
			
			$engine = new WordpressMailEngine ();
			$engine->setAuthEmail ( $from );
			$engine->setAuthToken ( $this->options ['access_token'] );
			$engine->setServer ( $hostname );
			$engine->setBodyText ( $body );
			$engine->setSubject ( $subject );
			$engine->setFrom ( $from );
			$engine->addTo ( $recipient );
			$result = $engine->send ();
			// $result = true;
			
			if ($result) {
				print "<h3>No Errors :)</h3>";
			} else {
				print "<h3>Failed |(</h3>";
			}
			
			?><a href="options-general.php?page=postman">Back to Plugin</a><?php
			
			//
			// wp_redirect( $_SERVER['HTTP_REFERER'] );
			// exit();
		}
		public function handleGoogleAuthenticationAction() {
			print "<h2>Authenticating</h2><br/>";
			require_once 'GmailAuthenticationManager.php';
			$mailer = new GmailAuthenticationManager ();
			$mailer->authenticate ();
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
			submit_button ( 'Authenticate with Google' );
			?>
	</form>
	<form method="POST" action="<?php get_admin_url()?>admin-post.php">
		<input type='hidden' name='action' value='test_mail' />
            <?php
			do_settings_sections ( POSTMAN_TEST_SLUG );
			submit_button ( 'Send Test Email' );
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
			
			add_settings_field ( 'oauth_email', 'Email', array (
					$this,
					'oauth_email_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( 'hostname', 'Outgoing Mail Server (SMTP)', array (
					$this,
					'hostname_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_field ( 'port', 'SSL Port', array (
					$this,
					'port_callback' 
			), POSTMAN_SLUG, 'SMTP_SETTINGS' );
			
			add_settings_section ( 'OAUTH_SETTINGS', 'OAuth Settings', array (
					$this,
					'printOAuthSectionInfo' 
			), POSTMAN_SLUG );
			
			add_settings_field ( 'oauth_client_id', 'Client ID', array (
					$this,
					'oauth_client_id_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( 'oauth_client_secret', 'Client Secret', array (
					$this,
					'oauth_client_secret_callback' 
			), POSTMAN_SLUG, 'OAUTH_SETTINGS' );
			
			add_settings_field ( 'access_token', 'Access Token', array (
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
			
			if (isset ( $input ['hostname'] ))
				$new_input ['hostname'] = sanitize_text_field ( $input ['hostname'] );
			
			if (isset ( $input ['port'] ))
				$new_input ['port'] = absint ( $input ['port'] );
			
			if (isset ( $input ['oauth_email'] ))
				$new_input ['oauth_email'] = sanitize_text_field ( $input ['oauth_email'] );
			
			if (isset ( $input ['oauth_client_id'] ))
				$new_input ['oauth_client_id'] = sanitize_text_field ( $input ['oauth_client_id'] );
			
			if (isset ( $input ['oauth_client_secret'] ))
				$new_input ['oauth_client_secret'] = sanitize_text_field ( $input ['oauth_client_secret'] );
			
			if (isset ( $input ['refresh_token'] ))
				$new_input ['refresh_token'] = sanitize_text_field ( $input ['refresh_token'] );
			
			if (isset ( $input ['access_token'] ))
				$new_input ['access_token'] = sanitize_text_field ( $input ['access_token'] );
			
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
			print 'Enter the details of your mail provider below. At this time, <b>only Gmail is supported</b>.';
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
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function hostname_callback() {
			printf ( '<input readonly="true" type="text" id="hostname" name="postman_options[hostname]" value="%s" />', isset ( $this->options ['hostname'] ) ? esc_attr ( $this->options ['hostname'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function port_callback() {
			printf ( '<input readonly="true" type="text" id="port" name="postman_options[port]" value="%s" />', isset ( $this->options ['port'] ) ? esc_attr ( $this->options ['port'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_email_callback() {
			printf ( '<input type="text" id="oauth_email" name="postman_options[oauth_email]" value="%s" />', isset ( $this->options ['oauth_email'] ) ? esc_attr ( $this->options ['oauth_email'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_id_callback() {
			printf ( '<input type="text" id="oauth_client_id" name="postman_options[oauth_client_id]" value="%s" size="71" />', isset ( $this->options ['oauth_client_id'] ) ? esc_attr ( $this->options ['oauth_client_id'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function oauth_client_secret_callback() {
			printf ( '<input type="text" autocomplete="off" id="oauth_client_secret" name="postman_options[oauth_client_secret]" value="%s" size="24"/>', isset ( $this->options ['oauth_client_secret'] ) ? esc_attr ( $this->options ['oauth_client_secret'] ) : '' );
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
			printf ( '<input readonly="true" type="text" id="access_token" name="postman_options[access_token]" value="%s" size="83" />', isset ( $this->options ['access_token'] ) ? esc_attr ( $this->options ['access_token'] ) : '' );
		}
		
		/**
		 * Get the settings option array and print one of its values
		 */
		public function test_email_callback() {
			printf ( '<input type="text" id="test_email" name="postman_test_options[test_email]" value="%s" />', isset ( $this->testOptions ['test_email'] ) ? esc_attr ( $this->testOptions ['test_email'] ) : '' );
		}
	}
	
	$smtpOAuthMailerAdmin = new SmtpOAuthMailerAdmin ();
}