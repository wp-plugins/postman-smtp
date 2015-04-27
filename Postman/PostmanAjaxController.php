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
		 */
		protected function getRequestParameter($parameterName) {
			if (isset ( $_POST [$parameterName] )) {
				$value = $_POST [$parameterName];
				$this->logger->debug ( 'Found parameter name ' . $parameterName );
				$this->logger->debug ( $value );
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
		 * @param PostmanOptionsInterface $options        	
		 */
		function __construct(PostmanOptionsInterface $options, PostmanOAuthTokenInterface $authorizationToken) {
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
			$activePlugins = ('WordPress Plugins');
			// from http://stackoverflow.com/questions/20488264/how-do-i-get-activated-plugin-list-in-wordpress-plugin-development
			$apl = get_option ( 'active_plugins' );
			$plugins = get_plugins ();
			$activated_plugins = array ();
			foreach ( $apl as $p ) {
				if (isset ( $plugins [$p] )) {
					$activePlugins .= ' : ' . $plugins [$p] ['Name'];
				}
			}
			return $activePlugins;
		}
		private function getPhpDependencies() {
			$activePlugins = ('PHP Dependencies');
			$apl = PostmanPreRequisitesCheck::getState ();
			foreach ( $apl as $p ) {
				$activePlugins .= ' : ' . $p ['name'] . '=' . ($p ['ready'] ? 'Yes' : 'No');
			}
			return $activePlugins;
		}
		private function getTransports() {
			$transports = '';
			foreach ( PostmanTransportDirectory::getInstance ()->getTransports () as $transport ) {
				$transports .= ' : ' . $transport->getName ();
				if (method_exists ( $transport, 'getVersion' )) {
					$transports .= ' (' . $transport->getVersion () . ')';
				}
			}
			return $transports;
		}
		
		/**
		 * Diagnostic Data test to current SMTP server
		 * @return string
		 */
		private function testConnectivity() {
			$transport = PostmanTransportUtils::getCurrentTransport ();
			if ($transport->isConfigured ( $this->options, $this->authorizationToken ) && method_exists ( $transport, 'getHostname' ) && method_exists ( $transport, 'getHostPort' )) {
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
			$this->addToDiagnostics ( sprintf ( 'OS: %s', php_uname () ) );
			$this->addToDiagnostics ( sprintf ( 'HTTP User Agent: %s', $_SERVER ['HTTP_USER_AGENT'] ) );
			$this->addToDiagnostics ( sprintf ( 'Platform: PHP %s %s / WordPress %s %s', PHP_OS, PHP_VERSION, get_bloginfo ( 'version' ), get_locale () ) );
			$this->addToDiagnostics ( $this->getPhpDependencies () );
			$this->addToDiagnostics ( $this->getActivePlugins () );
			$this->addToDiagnostics ( sprintf ( 'WordPress Theme: %s', wp_get_theme () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Version: %s', POSTMAN_PLUGIN_VERSION ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Sender: %s', (postmanObfuscateEmail ( $this->options->getSenderEmail () )) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport URI: %s', PostmanTransportUtils::getDeliveryUri ( PostmanTransportUtils::getCurrentTransport () ) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport Status (Configured|Ready|Connected): %s|%s|%s', PostmanTransportUtils::getCurrentTransport ()->isConfigured ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', PostmanTransportUtils::getCurrentTransport ()->isReady ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', $this->testConnectivity () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Deliveries (Success|Fail): %d|%d', PostmanStats::getInstance ()->getSuccessfulDeliveries (), PostmanStats::getInstance ()->getFailedDeliveries () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Bind (Success|Fail): %s|%s', (PostmanWpMailBinder::getInstance ()->isBound () ? 'Yes' : 'No'), (PostmanWpMailBinder::getInstance ()->isUnboundDueToException () ? 'Yes' : 'No') ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Available Transports%s', $this->getTransports () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman LogLevel: %s', $this->options->getLogLevel () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman TCP Timeout (Connection|Read): %d|%d', $this->options->getConnectionTimeout (), $this->options->getReadTimeout () ) );
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
			$queryHostname = '';
			if (isset ( $_POST ['hostname'] )) {
				$queryHostname = $_POST ['hostname'];
			}
			$hosts = PostmanTransportUtils::getHostsToTest ( $queryHostname );
			$this->logger->debug ( 'hostsToTest:' );
			$this->logger->debug ( $hosts );
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
		function __construct() {
			parent::__construct ();
			$this->registerAjaxHandler ( 'check_email', $this, 'getAjaxHostnameByEmail' );
		}
		/**
		 * This Ajax function retrieves the smtp hostname for a give e-mail address
		 */
		function getAjaxHostnameByEmail() {
			$email = $this->getRequestParameter ( 'email' );
			$d = new SmtpDiscovery ();
			$smtp = $d->getSmtpServer ( $email );
			$this->logger->debug ( 'given email ' . $email . ', smtp server is ' . $smtp );
			$response = array (
					'hostname' => ! empty ( $smtp ) ? $smtp : '' 
			);
			wp_send_json ( $response );
		}
	}
}

if (! class_exists ( 'PostmanPortTestAjaxController' )) {
	class PostmanPortTestAjaxController extends PostmanAbstractAjaxHandler {
		private $options;
		/**
		 * Constructor
		 *
		 * @param PostmanOptionsInterface $options        	
		 */
		function __construct(PostmanOptionsInterface $options) {
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
			$hostname = trim ( $this->getRequestParameter ( 'hostname' ) );
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$this->logger->debug ( 'testing TCP port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ( $hostname, $port );
			$success = $portTest->testPortQuiz ();
			$this->buildResponse ( $hostname, $port, $portTest, $success );
		}
		
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function runSmtpTest() {
			$hostname = trim ( $this->getRequestParameter ( 'hostname' ) );
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$portTest = new PostmanPortTest ( $hostname, $port );
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
			$this->logger->debug ( 'Ajax response:' );
			$this->logger->debug ( $response );
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
		 * @param PostmanOptionsInterface $options        	
		 */
		function __construct(PostmanOptionsInterface $options) {
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
							PostmanOptions::SENDER_EMAIL => $this->options->getSenderEmail (),
							PostmanOptions::SENDER_NAME => $this->options->getSenderName (),
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
		private $options;
		private $authorizationToken;
		private $oauthScribe;
		
		/**
		 * Constructor
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthTokenInterface $authorizationToken        	
		 * @param PostmanConfigTextHelper $oauthScribe        	
		 */
		function __construct(PostmanOptionsInterface $options, PostmanOAuthTokenInterface $authorizationToken, PostmanConfigTextHelper $oauthScribe) {
			parent::__construct ();
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->oauthScribe = $oauthScribe;
			$this->registerAjaxHandler ( 'send_test_email', $this, 'sendTestEmailViaAjax' );
		}
		
		/**
		 * This Ajax sends a test email
		 */
		function sendTestEmailViaAjax() {
			$email = $this->getRequestParameter ( 'email' );
			$method = $this->getRequestParameter ( 'method' );
			try {
				$emailTester = new PostmanSendTestEmailController ();
				$serverName = postmanGetServerName ();
				/* translators: where %s is the domain name of the site */
				$subject = sprintf ( _x ( 'Postman SMTP Test (%s)', 'Test Email Subject', 'postman-smtp' ), $serverName );
				// Englsih - Mandarin - French - Hindi - Spanish - Portuguese - Russian - Japanese
				/* translators: where %s is the Postman plugin version number (e.g. 1.4) */
				$message1 = sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanMessage::EOL, PostmanMessage::EOL, sprintf ( _x ( 'Sent by Postman %s', 'Test Email Tagline', 'postman-smtp' ), POSTMAN_PLUGIN_VERSION ) );
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
											<div style="margin:50px 0 0 300px; width:300px; font-size:2em;">Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は</div>' . sprintf ( '<div style="text-align:right;font-size: 1.4em; color:black;margin:150px 0 0 200px;">%s<br/><span style="font-size: 0.8em"><a style="color:#3f73b9" href="https://wordpress.org/plugins/postman-smtp/">https://wordpress.org/plugins/postman-smtp/</a></span></div>', sprintf ( _x ( 'Sent by Postman %s', 'Test Email Tagline', 'postman-smtp' ), POSTMAN_PLUGIN_VERSION ) ) . '</div>
									</td>
								</tr>
							</tbody>
						</table> <br><span style="font-size:0.9em;color:#94c0dc;">Image source: <a style="color:#94c0dc" href="http://poofytoo.com">poofytoo.com</a> - Used with permission</span></td>
				</tr>
			</tbody>
		</table>
</body>

</html>
				';
				$header = 'Content-Type: multipart/alternative;';
				$startTime = microtime ( true ) * 1000;
				$success = $emailTester->sendTestEmail ( $this->options, $this->authorizationToken, $email, $this->oauthScribe->getServiceName (), $subject, $message2, $header );
				$endTime = microtime ( true ) * 1000;
				if ($success) {
					$statusMessage = sprintf ( __ ( 'Your message was delivered (%d ms) to the SMTP server! Congratulations :)', 'postman-smtp' ), ($endTime - $startTime) );
				} else {
					$statusMessage = $emailTester->getMessage ();
				}
				$this->logger->debug ( 'statusmessage: ' . $statusMessage );
				$response = array (
						'message' => $statusMessage,
						'transcript' => $emailTester->getTranscript (),
						'success' => $success 
				);
			} catch ( PostmanSendMailCommunicationError334 $e ) {
				/* translators: where %s is the email service name (e.g. Gmail) */
				$response = array (
						'message' => sprintf ( __ ( 'Communication Error [334] - make sure the Sender Email belongs to the account which provided the %s OAuth 2.0 consent.', 'postman-smtp' ), $this->oauthScribe->getServiceName () ),
						'transcript' => $emailTester->getTranscript (),
						'success' => false 
				);
				$this->logger->error ( "SMTP session transcript follows:\n" . $emailTester->getTranscript () );
			} catch ( PostmanSendMailInexplicableException $e ) {
				$response = array (
						'message' => __ ( 'The impossible is possible; sending through wp_mail() failed, but sending through internal engine succeeded.', 'postman-smtp' ),
						'transcript' => $emailTester->getTranscript (),
						'success' => false 
				);
				$this->logger->error ( "SMTP session transcript follows:\n" . $emailTester->getTranscript () );
			}
			wp_send_json ( $response );
		}
	}
}