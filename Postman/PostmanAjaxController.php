<?php
if (! class_exists ( 'PostmanAbstractAjaxHandler' )) {
	
	require_once ('PostmanPreRequisitesCheck.php');
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
		private function testConnectivity() {
			$transport = PostmanTransportUtils::getCurrentTransport ();
			if ($transport->isConfigured ( $this->options, $this->authorizationToken ) && method_exists ( $transport, 'getHostname' ) && method_exists ( $transport, 'getHostPort' )) {
				$portTest = new PostmanPortTest ();
				$result = $portTest->testSmtpPorts ( $transport->getHostname ( $this->options ), $transport->getHostPort ( $this->options ) );
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
			$this->addToDiagnostics ( sprintf ( 'Platform: PHP %s %s / WordPress %s', PHP_OS, PHP_VERSION, get_bloginfo ( 'version' ) ) );
			$this->addToDiagnostics ( $this->getPhpDependencies () );
			$this->addToDiagnostics ( $this->getActivePlugins () );
			$this->addToDiagnostics ( sprintf ( 'Postman Version: %s', POSTMAN_PLUGIN_VERSION ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Sender: %s', (postmanObfuscateEmail ( $this->options->getSenderEmail () )) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport URI: %s', PostmanTransportUtils::getDeliveryUri ( PostmanTransportUtils::getCurrentTransport () ) ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Transport Status (configured|ready|connected): %s|%s|%s', PostmanTransportUtils::getCurrentTransport ()->isConfigured ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', PostmanTransportUtils::getCurrentTransport ()->isReady ( $this->options, $this->authorizationToken ) ? 'Yes' : 'No', $this->testConnectivity () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Deliveries (Success|Fail): %d|%d', PostmanStats::getInstance ()->getSuccessfulDeliveries (), PostmanStats::getInstance ()->getFailedDeliveries () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Bind (success|failure): %s|%s', (PostmanWpMailBinder::getInstance ()->isBound () ? 'Yes' : 'No'), (PostmanWpMailBinder::getInstance ()->isUnboundDueToException () ? 'Yes' : 'No') ) );
			$this->addToDiagnostics ( sprintf ( 'Postman Available Transports%s', $this->getTransports () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman LogLevel: %s', $this->options->getLogLevel () ) );
			$this->addToDiagnostics ( sprintf ( 'Postman TCP Timeout (Connection|Read): %d|%d', $this->options->getConnectionTimeout (), $this->options->getReadTimeout () ) );
			$this->addToDiagnostics ( sprintf ( 'HTTP User Agent: %s', $_SERVER ['HTTP_USER_AGENT'] ) );
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
			$this->registerAjaxHandler ( 'test_port', $this, 'getAjaxPortStatus' );
		}
		
		/**
		 * This Ajax function retrieves whether a TCP port is open or not
		 */
		function getAjaxPortStatus() {
			$hostname = $this->getRequestParameter ( 'hostname' );
			$port = intval ( $this->getRequestParameter ( 'port' ) );
			$this->logger->debug ( 'testing port: hostname ' . $hostname . ' port ' . $port );
			$portTest = new PostmanPortTest ();
			$success = $portTest->testSmtpPorts ( $hostname, $port, $this->options->getConnectionTimeout () );
			$this->logger->debug ( sprintf ( 'testing port result for %s:%s success=%s', $hostname, $port, $success ) );
			$response = array (
					'message' => $portTest->getErrorMessage (),
					'success' => $success 
			);
			wp_send_json ( $response );
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
if (! class_exists ( 'PostmanManageConfigurationAjaxHandler' )) {
	class PostmanManageConfigurationAjaxHandler extends PostmanAbstractAjaxHandler {
		function __construct() {
			parent::__construct ();
			$this->registerAjaxHandler ( 'manual_config', $this, 'getManualConfigurationViaAjax' );
			$this->registerAjaxHandler ( 'get_wizard_configuration_options', $this, 'getWizardConfigurationViaAjax' );
		}
		
		/**
		 * Handle a manual configuration request with Ajax
		 *
		 * @throws Exception
		 */
		function getManualConfigurationViaAjax() {
			$queryTransportType = $this->getTransportTypeFromRequest ();
			$queryAuthType = $this->getAuthenticationTypeFromRequest ();
			$queryHostname = $this->getHostnameFromRequest ();
			
			// the outgoing server hostname is only required for the SMTP Transport
			// the Gmail API transport doesn't use an SMTP server
			$transport = PostmanTransportUtils::getTransport ( $queryTransportType );
			if (! $transport) {
				throw new Exception ( 'Unable to find transport ' . $queryTransportType );
			}
			
			// create the scribe
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $queryHostname );
			
			// create the response
			$response = array ();
			$response ['referer'] = 'manual_config';
			$this->populateResponseFromScribe ( $scribe, $response, false );
			
			// set the display_auth to oauth2 if the transport needs it
			if ($transport->isOAuthUsed ( $queryAuthType )) {
				$response ['display_auth'] = 'oauth2';
				$this->logger->debug ( 'ajaxRedirectUrl answer display_auth:' . $response ['display_auth'] );
			}
			wp_send_json_success ( $response );
		}
		
		/**
		 * This Ajax function retrieves the OAuth redirectUrl and help text for based on the SMTP hostname supplied
		 */
		function getWizardConfigurationViaAjax() {
			$queryHostData = $this->getHostDataFromRequest ();
			$userOverride = filter_var ( $this->getUserOverrideFromRequest (), FILTER_VALIDATE_BOOLEAN );
			
			// determine a configuration recommendation
			$winningRecommendation = $this->getConfigurationRecommendation ( $queryHostData );
			
			// create the reponse
			$response = array ();
			$response ['referer'] = 'wizard';
			
			if (isset ( $winningRecommendation )) {
				// create an appropriate (theoretical) transport
				$transport = PostmanTransportUtils::getTransport ( $winningRecommendation ['transport'] );
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $winningRecommendation ['hostname'] );
				$this->populateResponseFromScribe ( $scribe, $response, $userOverride );
				$this->populateResponseFromTransport ( $winningRecommendation, $response );
				$this->logger->debug ( 'ajaxRedirectUrl answer hide_auth:' . $response ['hide_auth'] );
				$this->logger->debug ( 'ajaxRedirectUrl answer hide_enc:' . $response ['hide_enc'] );
				$this->logger->debug ( 'ajaxRedirectUrl answer message:' . $response ['message'] );
			} else {
				/* translators: where %s is the URL to the Connectivity Test page */
				$response ['message'] = sprintf ( __ ( 'Postman can\'t find any way to send mail on your system. Run a <a href="%s">connectivity test</a>.', 'postman-smtp' ), PostmanViewController::getPageUrl ( PostmanViewController::PORT_TEST_SLUG ) );
			}
			
			wp_send_json_success ( $response );
		}
		
		/**
		 */
		private function getTransportTypeFromRequest() {
			return $this->getRequestParameter ( 'transport' );
		}
		
		/**
		 */
		private function getHostnameFromRequest() {
			return $this->getRequestParameter ( 'hostname' );
		}
		
		/**
		 */
		private function getAuthenticationTypeFromRequest() {
			return $this->getRequestParameter ( 'auth_type' );
		}
		
		/**
		 */
		private function getUserOverrideFromRequest() {
			return $this->getRequestParameter ( 'user_override' );
		}
		
		/**
		 */
		private function getHostDataFromRequest() {
			return $this->getRequestParameter ( 'host_data' );
		}
		
		/**
		 * // for each successful host/port combination
		 * // ask a transport if they support it, and if they do at what priority is it
		 * // configure for the highest priority you find
		 *
		 * @param unknown $queryHostData        	
		 * @return unknown
		 */
		private function getConfigurationRecommendation($queryHostData) {
			$recommendationPriority = - 1;
			$winningRecommendation = null;
			foreach ( $queryHostData as $id => $value ) {
				$available = filter_var ( $value ['available'], FILTER_VALIDATE_BOOLEAN );
				if ($available) {
					$hostData ['host'] = $value ['host'];
					$hostData ['port'] = $value ['port'];
					$this->logger->debug ( 'Available host: ' . $hostData ['host'] . ':' . $hostData ['port'] . ' port_id ' . $value ['port_id'] );
					$recommendation = PostmanTransportUtils::getConfigurationRecommendation ( $hostData );
					$recommendation ['port_id'] = $value ['port_id'];
					if ($recommendation && $recommendation ['priority'] > $recommendationPriority) {
						$recommendationPriority = $recommendation ['priority'];
						$winningRecommendation = $recommendation;
					}
				}
			}
			return $winningRecommendation;
		}
		
		/**
		 *
		 * @param unknown $scribe        	
		 * @param unknown $response        	
		 * @param unknown $userOverride        	
		 */
		private function populateResponseFromScribe($scribe, &$response, $userOverride) {
			$response ['redirect_url'] = $scribe->getCallbackUrl ();
			$response ['callback_domain'] = $scribe->getCallbackDomain ();
			$response ['help_text'] = $scribe->getOAuthHelp ();
			$response ['client_id_label'] = $scribe->getClientIdLabel ();
			$response ['client_secret_label'] = $scribe->getClientSecretLabel ();
			$response ['redirect_url_label'] = $scribe->getCallbackUrlLabel ();
			$response ['callback_domain_label'] = $scribe->getCallbackDomainLabel ();
			$response ['user_override'] = $userOverride;
			$response ['hide_auth'] = true;
			$response ['hide_enc'] = true;
			$this->logger->debug ( 'ajaxRedirectUrl answer redirect_url:' . $scribe->getCallbackUrl () );
			$this->logger->debug ( 'ajaxRedirectUrl answer callback_domain:' . $scribe->getCallbackDomain () );
			$this->logger->debug ( 'ajaxRedirectUrl answer help_text:' . $scribe->getOAuthHelp () );
			$this->logger->debug ( 'ajaxRedirectUrl answer client_id_label:' . $scribe->getClientIdLabel () );
			$this->logger->debug ( 'ajaxRedirectUrl answer client_secret_label:' . $scribe->getClientSecretLabel () );
			$this->logger->debug ( 'ajaxRedirectUrl answer redirect_url_label:' . $scribe->getCallbackUrlLabel () );
			$this->logger->debug ( 'ajaxRedirectUrl answer callback_domain_label:' . $scribe->getCallbackDomainLabel () );
		}
		
		/**
		 *
		 * @param unknown $winningRecommendation        	
		 * @param unknown $response        	
		 */
		private function populateResponseFromTransport($winningRecommendation, &$response) {
			$response [PostmanOptions::TRANSPORT_TYPE] = $winningRecommendation ['transport'];
			$response [PostmanOptions::AUTHENTICATION_TYPE] = $winningRecommendation ['auth'];
			$response [PostmanOptions::ENCRYPTION_TYPE] = $winningRecommendation ['enc'];
			$response [PostmanOptions::PORT] = $winningRecommendation ['port'];
			$response [PostmanOptions::HOSTNAME] = $winningRecommendation ['hostname'];
			$response ['port_id'] = $winningRecommendation ['port_id'];
			$response ['display_auth'] = $winningRecommendation ['display_auth'];
			$response ['message'] = $winningRecommendation ['message'];
			if ($winningRecommendation ['auth'] != 'oauth2' && $winningRecommendation ['enc'] == 'tls') {
				$response ['hide_auth'] = false;
				$response ['hide_enc'] = false;
			}
			$this->logger->debug ( 'ajaxRedirectUrl answer transport_type:' . $response [PostmanOptions::TRANSPORT_TYPE] );
			$this->logger->debug ( 'ajaxRedirectUrl answer auth_type:' . $response [PostmanOptions::AUTHENTICATION_TYPE] );
			$this->logger->debug ( 'ajaxRedirectUrl answer enc_type:' . $response [PostmanOptions::ENCRYPTION_TYPE] );
			$this->logger->debug ( 'ajaxRedirectUrl answer port:' . $response [PostmanOptions::PORT] );
			$this->logger->debug ( 'ajaxRedirectUrl answer hostname:' . $response [PostmanOptions::HOSTNAME] );
			$this->logger->debug ( 'ajaxRedirectUrl answer port_id:' . $response ['port_id'] );
			$this->logger->debug ( 'ajaxRedirectUrl answer display_auth:' . $response ['display_auth'] );
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
				$subject = _x ( 'WordPress Postman SMTP Test', 'Test Email Subject', 'postman-smtp' );
				// Englsih - Mandarin - French - Hindi - Spanish - Portuguese - Russian - Japanese
				/* translators: where %s is the Postman plugin version number (e.g. 1.4) */
				$message1 = sprintf ( 'Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は%s%s%s - https://wordpress.org/plugins/postman-smtp/', PostmanSmtpEngine::EOL, PostmanSmtpEngine::EOL, sprintf ( _x ( 'Sent by Postman v%s', 'Test Email Tagline', 'postman-smtp' ), POSTMAN_PLUGIN_VERSION ) );
				/* translators: where %s is the Postman plugin version number (e.g. 1.5.7) */
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
											style="max-width: 600px; height: 400px; margin: 0 auto; overflow: hidden;background-image:url(\'http://plugins.svn.wordpress.org/postman-smtp/assets/email/poofytoo.png\');background-repeat: no-repeat;">
											<div style="margin:50px 0 0 300px; width:300px; font-size:2em;">Hello! - 你好 - Bonjour! - नमस्ते - ¡Hola! - Olá - Привет! - 今日は</div>' . sprintf ( '<div style="text-align:right;font-size: 1.4em; color:black;margin:150px 0 0 200px;">%s<br/><span style="font-size: 0.8em"><a style="color:#3f73b9" href="https://wordpress.org/plugins/postman-smtp/">https://wordpress.org/plugins/postman-smtp/</a></span></div>', sprintf ( __ ( 'Sent by <em>Postman</em> v%s', 'Test Email Tagline', 'postman-smtp' ), POSTMAN_PLUGIN_VERSION ) ) . '</div>
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