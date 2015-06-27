<?php
if (! class_exists ( 'PostmanAbstractAjaxHandler' )) {
	
	require_once ('PostmanPreRequisitesCheck.php');
	require_once ('Postman-Mail/PostmanMessage.php');
	
	/**
	 *
	 * @author jasonhendriks
	 */
	abstract class PostmanAbstractAjaxHandler {
		protected $logger;
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		/**
		 *
		 * @param unknown $actionName        	
		 * @param unknown $callbackName        	
		 */
		protected function registerAjaxHandler($actionName, $class, $callbackName) {
			if (is_admin ()) {
				$fullname = 'wp_ajax_' . $actionName;
				// $this->logger->debug ( 'Registering ' . 'wp_ajax_' . $fullname . ' Ajax handler' );
				add_action ( $fullname, array (
						$class,
						$callbackName 
				) );
			}
		}
		
		/**
		 *
		 * @param unknown $parameterName        	
		 * @return mixed
		 */
		protected function getBooleanRequestParameter($parameterName) {
			return filter_var ( $this->getRequestParameter ( $parameterName ), FILTER_VALIDATE_BOOLEAN );
		}
		
		/**
		 *
		 * @param unknown $parameterName        	
		 * @return unknown
		 */
		protected function getRequestParameter($parameterName) {
			if (isset ( $_POST [$parameterName] )) {
				$value = $_POST [$parameterName];
				$this->logger->trace ( sprintf ( 'Found parameter "%s"', $parameterName ) );
				$this->logger->trace ( $value );
				return $value;
			}
		}
	}
}

require_once ('Postman-Controller/PostmanManageConfigurationAjaxHandler.php');

if (! class_exists ( 'PostmanGetDiagnosticsViaAjax' )) {
	class PostmanGetDiagnosticsViaAjax extends PostmanAbstractAjaxHandler {
		private $diagnostics;
		private $options;
		private $authorizationToken;
		/**
		 * Constructor
		 *
		 * @param PostmanOptions $options        	
		 */
		function __construct(PostmanOptions $options, PostmanOAuthToken $authorizationToken) {
			parent::__construct ();
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->diagnostics = '';
			$this->registerAjaxHandler ( 'get_diagnostics', $this, 'getDiagnostics' );
		}
		private function addToDiagnostics($message) {
			$this->diagnostics .= sprintf ( '%s%s', $message, PHP_EOL );
		}
		private function getActivePlugins() {
			// from http://stackoverflow.com/questions/20488264/how-do-i-get-activated-plugin-list-in-wordpress-plugin-development
			$apl = get_option ( 'active_plugins' );
			$plugins = get_plugins ();
			$pluginText = array ();
			foreach ( $apl as $p ) {
				if (isset ( $plugins [$p] )) {
					array_push ( $pluginText, $plugins [$p] ['Name'] );
				}
			}
			return 'WordPress Plugins: ' . implode ( ', ', $pluginText );
		}
		private function getPhpDependencies() {
			$apl = PostmanPreRequisitesCheck::getState ();
			$pluginText = array ();
			foreach ( $apl as $p ) {
				array_push ( $pluginText, $p ['name'] . '=' . ($p ['ready'] ? 'Yes' : 'No') );
			}
			return 'PHP Dependencies: ' . implode ( ', ', $pluginText );
		}
		private function getTransports() {
			$transports = '';
			foreach ( PostmanTransportRegistry::getInstance ()->getTransports () as $transport ) {
				$transports .= ' : ' . $transport->getName ();
			}
			return $transports;
		}
		
		/**
		 * Diagnostic Data test to current SMTP server
		 *
		 * @return string
		 */
		private function testConnectivity() {
			$transport = PostmanTransportRegistry::getInstance ()->getCurrentTransport ();
			if ($transport->isConfigured ( $this->options, $this->authorizationToken )) {
				$portTest = new PostmanPortTest ( $transport->getHostname ( $this->options ), $transport->getHostPort ( $this->options ) );
				$result = $portTest->genericConnectionTest ( $this->options->getConnectionTimeout () );
				if ($result) {
					return 'Yes';
				} else {
					return 'No';
				}
			}
			return 'undefined';
		}
		public function getDiagnostics() {
			$transportRegistry = PostmanTransportRegistry::getInstance ();
			$this->addToDiagnostics ( sprintf ( 'OS: %s', php_uname () ) );
			$this->addToDiagnostics ( sprintf ( 'HTTP User Agent: %s', $_SERVER ['HTTP_USER_AGENT'] ) );
			$this->addToDiagnostics ( sprintf ( 'Platform: PHP %s %s / WordPress %s %s %s', PHP_OS, PHP_VERSION, is_multisite () ? 'Multisite' : '', get_bloginfo ( 'version' ), get_locale () ) );
			$this->addToDiagnostics ( $this->getPhpDependencies () );
			$this->addToDiagnostics ( sprintf ( 'WordPress Theme: %s', wp_get_theme () ) );
			$this->addToDiagnostics ( $this->getActivePlugins () );
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			$this->addToDiagnostics ( sprintf ( 'Postman Version: %s', $pluginData ['version'] ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Sender Domain (Envelope|Message): %s|%s', $hostname = substr ( strrchr ( $this->options->getEnvelopeSender (), "@" ), 1 ), $hostname = substr ( strrchr ( $this->options->getMessageSenderEmail (), "@" ), 1 ) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Prevent Message Sender Override (Email|Name): %s|%s', $this->options->isSenderEmailOverridePrevented () ? 'Yes' : 'No', $this->options->isSenderNameOverridePrevented () ? 'Yes' : 'No' ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport URI: %s', $transportRegistry->getPublicTransportUri ( $transportRegistry->getCurrentTransport () ) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport Status (Configured|Ready|Connected): %s|%s|%s', $transportRegistry->getCurrentTransport ()->isConfigured ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', PostmanTransportRegistry::getInstance ()->getCurrentTransport ()->isReady ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', $this->testConnectivity () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Deliveries (Success|Fail): %d|%d', PostmanStats::getInstance ()->getSuccessfulDeliveries (), PostmanStats::getInstance ()->getFailedDeliveries () ) );
			$bindResult = apply_filters ( 'postman_wp_mail_bind_status', null );
			$wp_mail_file_name = 'n/a';
			if (class_exists ( 'ReflectionFunction' )) {
				$wp_mail = new ReflectionFunction ( 'wp_mail' );
				$wp_mail_file_name = realpath ( $wp_mail->getFileName () );
			}
			$this->addToDiagnostics ( sprintf ( 'Postman Bind (Success|Fail|Path): %s|%s|%s', ($bindResult ['bound'] ? 'Yes' : 'No'), ($bindResult ['bind_error'] ? 'Yes' : 'No'), $wp_mail_file_name ) );
			$this->addToDiagnostics ( sprintf ( 'Postman TCP Timeout (Connection|Read): %d|%d', $this->options->getConnectionTimeout (), $this->options->getReadTimeout () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Email Log (Enabled|Limit|Transcript Size): %s|%d|%d', ($this->options->isMailLoggingEnabled () ? 'Yes' : 'No'), $this->options->getMailLoggingMaxEntries (), $this->options->getTranscriptSize () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Run Mode: %s', $this->options->getRunMode () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman PHP LogLevel: %s', $this->options->getLogLevel () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Stealth Mode: %s', $this->options->isStealthModeEnabled () ? 'Yes' : 'No' ) );
			$this->addToDiagnostics ( sprintf ( 'Postman File Locking (Enabled|Temp Dir): %s|%s', PostmanState::getInstance ()->isFileLockingEnabled () ? 'Yes' : 'No', $this->options->getTempDirectory () ) );
			$response = array (
					'message' => $this->diagnostics 
			);
			wp_send_json_success ( $response );
		}
	}
}

if (! class_exists ( 'PostmanGetPortsToTestViaAjax' )) {
	class PostmanGetPortsToTestViaAjax extends PostmanAbstractAjaxHandler {
		function __construct() {
			parent::__construct ();
			$this->registerAjaxHandler ( 'get_hosts_to_test', $this, 'getPortsToTestViaAjax' );
		}
		/**
		 * This Ajax function determines which hosts/ports to test in the Wizard Port Test
		 *
		 * Given a single outgoing smtp server hostname, return an array of host/port
		 * combinations to run the connectivity test on
		 */
		function getPortsToTestViaAjax() {
			$queryHostname = $this->getRequestParameter ( 'hostname' );
			$isGmail = $this->getBooleanRequestParameter ( PostmanGetHostnameByEmailAjaxController::IS_GOOGLE_PARAMETER );
			$hosts = PostmanTransportRegistry::getInstance ()->getSocketsForSetupWizardToProbe ( $queryHostname, $isGmail );
			$this->logger->trace ( 'hostsToTest:' );
			$this->logger->trace ( $hosts );
			$response = array (
					'hosts' => $hosts,
					'success' => true 
			);
			wp_send_json ( $response );
		}
	}
}
if (! class_exists ( 'PostmanGetHostnameByEmailAjaxController' )) {
	class PostmanGetHostnameByEmailAjaxController extends PostmanAbstractAjaxHandler {
		const IS_GOOGLE_PARAMETER = 'is_google';
		function __construct() {
			parent::__construct ();
			$this->registerAjaxHandler ( 'check_email', $this, 'getAjaxHostnameByEmail' );
		}
		/**
		 * This Ajax function retrieves the smtp hostname for a give e-mail address
		 */
		function getAjaxHostnameByEmail() {
			$goDaddyHostDetected = $this->getBooleanRequestParameter ( 'go_daddy' );
			$email = $this->getRequestParameter ( 'email' );
			$d = new PostmanSmtpDiscovery ( $email );
			$smtp = $d->getSmtpServer ();
			$this->logger->debug ( 'given email ' . $email . ', smtp server is ' . $smtp );
			$this->logger->trace ( $d );
			if ($goDaddyHostDetected && ! $d->isGoogle) {
				// override with the GoDaddy SMTP server
				$smtp = 'relay-hosting.secureserver.net';
				$this->logger->debug ( 'detected GoDaddy SMTP server, smtp server is ' . $smtp );
			}
			$response = array (
					'hostname' => $smtp,
					self::IS_GOOGLE_PARAMETER => $d->isGoogle,
					'is_go_daddy' => $d->isGoDaddy,
					'is_well_known' => $d->isWellKnownDomain 
			);
			$this->logger->trace ( $response );
			wp_send_json_success ( $response );
		}
	}
}

if (! class_exists ( 'PostmanPortTestAjaxController' )) {
	class PostmanPortTestAjaxController extends PostmanAbstractAjaxHandler {
		private $options;
		/**
		 * Constructor
		 *
		 * @param PostmanOptions $options        	
		 */
		function __construct(PostmanOptions $options) {
			parent::__construct ();
			$this->options = $options;
			$this->registerAjaxHandler ( 'wizard_port_test', $this, 'runSmtpTest' );
			$this->registerAjaxHandler ( 'wizard_port_test_smtps', $this, 'runSmtpsTest' );
			$this->registerAjaxHandler ( 'port_quiz_test', $this, 'runPortQuizTest' );
			$this->registerAjaxHandler ( 'test_port', $this, 'runSmtpTest' );
			$this->registerAjaxHandler ( 'test_smtps', $this, 'runSmtpsTest' );
		}
		
		/**
		 * This is the connectivity test that is started from the Setup Wizard
		 */
		function wizardConnectivityTest() {
			$this->runSmtpTest ();
		}
		
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function runPortQuizTest() {
			$hostname = 'portquiz.net';
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$this->logger->debug ( 'testing TCP port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ( $hostname, $port );
			$success = $portTest->genericConnectionTest ();
			$this->buildResponse ( $hostname, $port, $portTest, $success );
		}
		
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function runSmtpTest() {
			$hostname = trim ( $this->getRequestParameter ( 'hostname' ) );
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$timeout = $this->getRequestParameter ( 'timeout' );
			$this->logger->trace ( $timeout );
			$portTest = new PostmanPortTest ( $hostname, $port );
			if (isset ( $timeout )) {
				$portTest->setConnectionTimeout ( intval ( $timeout ) );
				$portTest->setReadTimeout ( intval ( $timeout ) );
			}
			if ($port != 443) {
				$this->logger->debug ( 'testing SMTP port: hostname ' . $hostname . ' port ' . $port );
				$success = $portTest->testSmtpPorts ();
			} else {
				$this->logger->debug ( 'testing HTTPS port: hostname ' . $hostname . ' port ' . $port );
				$success = $portTest->testHttpPorts ();
			}
			$this->buildResponse ( $hostname, $port, $portTest, $success );
		}
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function runSmtpsTest() {
			$hostname = trim ( $this->getRequestParameter ( 'hostname' ) );
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$this->logger->debug ( 'testing SMTPS port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ( $hostname, $port );
			$success = $portTest->testSmtpsPorts ();
			$this->buildResponse ( $hostname, $port, $portTest, $success );
		}
		
		/**
		 *
		 * @param unknown $hostname        	
		 * @param unknown $port        	
		 * @param unknown $success        	
		 */
		private function buildResponse($hostname, $port, PostmanPortTest $portTest, $success) {
			$this->logger->debug ( sprintf ( 'testing port result for %s:%s success=%s', $hostname, $port, $success ) );
			$response = array (
					'hostname' => $hostname,
					'hostname_domain_only' => $portTest->hostnameDomainOnly,
					'port' => $port,
					'protocol' => $portTest->protocol,
					'secure' => ($portTest->secure),
					'mitm' => ($portTest->mitm),
					'reported_hostname' => $portTest->reportedHostname,
					'reported_hostname_domain_only' => $portTest->reportedHostnameDomainOnly,
					'message' => $portTest->getErrorMessage (),
					'start_tls' => $portTest->startTls,
					'auth_plain' => $portTest->authPlain,
					'auth_login' => $portTest->authLogin,
					'auth_crammd5' => $portTest->authCrammd5,
					'auth_xoauth' => $portTest->authXoauth,
					'auth_none' => $portTest->authNone,
					'try_smtps' => $portTest->trySmtps,
					'success' => $success 
			);
			$this->logger->trace ( 'Ajax response:' );
			$this->logger->trace ( $response );
			if ($success) {
				wp_send_json_success ( $response );
			} else {
				wp_send_json_error ( $response );
			}
		}
	}
}
if (! class_exists ( 'PostmanImportConfigurationAjaxController' )) {
	class PostmanImportConfigurationAjaxController extends PostmanAbstractAjaxHandler {
		private $options;
		/**
		 * Constructor
		 *
		 * @param PostmanOptions $options        	
		 */
		function __construct(PostmanOptions $options) {
			parent::__construct ();
			$this->options = $options;
			$this->registerAjaxHandler ( 'import_configuration', $this, 'getConfigurationFromExternalPluginViaAjax' );
		}
		
		/**
		 * This function extracts configuration details form a competing SMTP plugin
		 * and pushes them into the Postman configuration screen.
		 */
		function getConfigurationFromExternalPluginViaAjax() {
			$importableConfiguration = new PostmanImportableConfiguration ();
			$plugin = $this->getRequestParameter ( 'plugin' );
			$this->logger->debug ( 'Looking for config=' . $plugin );
			foreach ( $importableConfiguration->getAvailableOptions () as $this->options ) {
				if ($this->options->getPluginSlug () == $plugin) {
					$this->logger->debug ( 'Sending configuration response' );
					$response = array (
							PostmanOptions::MESSAGE_SENDER_EMAIL => $this->options->getMessageSenderEmail (),
							PostmanOptions::MESSAGE_SENDER_NAME => $this->options->getMessageSenderName (),
							PostmanOptions::HOSTNAME => $this->options->getHostname (),
							PostmanOptions::PORT => $this->options->getPort (),
							PostmanOptions::AUTHENTICATION_TYPE => $this->options->getAuthenticationType (),
							PostmanOptions::ENCRYPTION_TYPE => $this->options->getEncryptionType (),
							PostmanOptions::BASIC_AUTH_USERNAME => $this->options->getUsername (),
							PostmanOptions::BASIC_AUTH_PASSWORD => $this->options->getPassword (),
							'success' => true 
					);
					break;
				}
			}
			if (! isset ( $response )) {
				$response = array (
						'success' => false 
				);
			}
			wp_send_json ( $response );
		}
	}
}
if (! class_exists ( 'PostmanSendTestEmailAjaxController' )) {
	class PostmanSendTestEmailAjaxController extends PostmanAbstractAjaxHandler {
		
		/**
		 * Constructor
		 *
		 * @param PostmanOptions $options        	
		 * @param PostmanOAuthToken $authorizationToken        	
		 * @param PostmanConfigTextHelper $oauthScribe        	
		 */
		function __construct() {
			parent::__construct ();
			$this->registerAjaxHandler ( 'send_test_email', $this, 'sendTestEmailViaAjax' );
		}
		
		/**
		 * Yes, this procedure is just for testing.
		 *
		 * @return boolean
		 */
		function test_mode() {
			return true;
		}
		
		/**
		 * This Ajax sends a test email
		 */
		function sendTestEmailViaAjax() {
			// Postman API: Get the plugin metadata
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			
			// get the email address of the recipient from the HTTP Request
			$email = $this->getRequestParameter ( 'email' );
			
			// get the name of the server from the HTTP Request
			$serverName = PostmanUtils::postmanGetServerName ();
			
			/* translators: where %s is the domain name of the site */
			$subject = sprintf ( _x ( 'Postman SMTP Test (%s)', 'Test Email Subject', 'postman-smtp' ), $serverName );
			
			// the plain-text content
			/* translators: where %s is the Postman plugin version number (e.g. 1.4) */
			// English - Mandarin - French - Hindi - Spanish - Portuguese - Russian - Japanese
			$message1 = sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanMessage::EOL, PostmanMessage::EOL, sprintf ( _x ( 'Sent by Postman %s', 'Test Email Tagline', 'postman-smtp' ), $pluginData ['version'] ) );
			
			// the HTML content
			$message2 = '
Content-Type: text/plain; charset = "UTF-8"
Content-Transfer-Encoding: 8bit

' . $message1 . '

Content-Type: text/html; charset = "UTF-8"
Content-Transfer-Encoding: 8bit

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css" media="all">
.wporg-notification .im {
	color: #888;
} /* undo a GMail-inserted style */
</style>
</head>
<body class="wporg-notification">
	<div
		style="background: #e8f6fe; font-family: &amp; quot; Helvetica Neue&amp;quot; , Helvetica ,Arial,sans-serif; font-size: 14px; color: #666; text-align: center; margin: 0; padding: 0">

		<table border="0" cellspacing="0" cellpadding="0" bgcolor="#e8f6fe"
			style="background: #e8f6fe; width: 100%;">
			<tbody>
				<tr>
					<td>
						<table border="0" cellspacing="0" cellpadding="0" align="center"
							style="padding: 0px; width: 100%;"">
							<tbody>
								<tr>
									<td>
										<div
											style="max-width: 600px; height: 400px; margin: 0 auto; overflow: hidden;background-image:url(\'https://ps.w.org/postman-smtp/assets/email/poofytoo.png\');background-repeat: no-repeat;">
											<div style="margin:50px 0 0 300px; width:300px; font-size:2em;">Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は</div>' . sprintf ( '<div style="text-align:right;font-size: 1.4em; color:black;margin:150px 0 0 200px;">%s<br/><span style="font-size: 0.8em"><a style="color:#3f73b9" href="https://wordpress.org/plugins/postman-smtp/">https://wordpress.org/plugins/postman-smtp/</a></span></div>', sprintf ( _x ( 'Sent by Postman %s', 'Test Email Tagline', 'postman-smtp' ), $pluginData ['version'] ) ) . '</div>
									</td>
								</tr>
							</tbody>
						</table> <br><span style="font-size:0.9em;color:#94c0dc;">' . __ ( 'Image source', 'postman-smtp' ) . ': <a style="color:#94c0dc" href="http://poofytoo.com">poofytoo.com</a> - ' . __ ( 'Used with permission', 'postman-smtp' ) . '</span></td>
				</tr>
			</tbody>
		</table>
</body>

</html>
				';
			// this header specifies that there are many parts (one text part, one html part)
			$header = 'Content-Type: multipart/alternative;';
			
			// Postman API: indicate to Postman this is just for testing
			add_filter ( 'postman_test_email', array (
					$this,
					'test_mode' 
			) );
			
			// send the message
			$success = wp_mail ( $email, $subject, $message2, $header );
			
			// Postman API: remove the testing indicator
			remove_filter ( 'postman_test_email', array (
					$this,
					'test_mode' 
			) );
			
			// Postman API: retrieve the result of sending this message from Postman
			$result = apply_filters ( 'postman_wp_mail_result', null );
			
			// post-handling
			if ($success) {
				$this->logger->debug ( 'Test Email delivered to server' );
				// the message was sent successfully, generate an appropriate message for the user
				$statusMessage = sprintf ( __ ( 'Your message was delivered (%d ms) to the SMTP server! Congratulations :)', 'postman-smtp' ), $result ['time'] );
			} else {
				$this->logger->error ( 'Test Email NOT delivered to server - ' . $result ['exception']->getCode () );
				// the message was NOT sent successfully, generate an appropriate message for the user
				$statusMessage = $result ['exception']->getMessage ();
			}
			$this->logger->debug ( 'statusmessage: ' . $statusMessage );
			
			// compose the JSON response for the caller
			$response = array (
					'message' => $statusMessage,
					'transcript' => $result ['transcript'],
					'success' => $success 
			);
			// send the JSON response
			wp_send_json ( $response );
		}
	}
}