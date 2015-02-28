<?php
if (! class_exists ( 'PostmanAbstractAjaxHandler' )) {
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
			$fullname = 'wp_ajax_' . $actionName;
			// $this->logger->debug ( 'Registering ' . 'wp_ajax_' . $fullname . ' Ajax handler' );
			add_action ( $fullname, array (
					$class,
					$callbackName 
			) );
		}
		
		/**
		 */
		protected function getRequestParameter($parameterName) {
			if (isset ( $_POST [$parameterName] )) {
				$value = $_POST [$parameterName];
				$this->logger->debug ( sprintf ( 'found ajax parameter %s:%s', $parameterName, $value ) );
				return $value;
			}
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
			$queryAuthType = $this->getAuthenticationTypeFromRequest ();
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
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $winningRecommendation['hostname'] );
				$this->populateResponseFromScribe ( $scribe, $response, $userOverride );
				$this->populateResponseFromTransport ( $winningRecommendation, $response );
				$this->logger->debug ( 'ajaxRedirectUrl answer hide_auth:' . $response ['hide_auth'] );
				$this->logger->debug ( 'ajaxRedirectUrl answer hide_enc:' . $response ['hide_enc'] );
				$this->logger->debug ( 'ajaxRedirectUrl answer message:' . $response ['message'] );
			} else {
				/* translators: where %s is the URL to the Connectivity Test page */
				$response ['message'] = sprintf ( __ ( 'Postman can\'t find any way to send mail on your system. Run a <a href="%s">connectivity test</a>.', 'postman-smtp' ), PostmanAdminController::getPageUrl ( PostmanAdminController::PORT_TEST_SLUG ) );
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
			$parameterName = 'host_data';
			if (isset ( $_POST [$parameterName] )) {
				$value = $_POST [$parameterName];
				$this->logger->debug ( sprintf ( 'found ajax parameter %s', $parameterName ) );
				return $value;
			}
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