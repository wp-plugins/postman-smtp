<?php
if (! class_exists ( 'PostmanManageConfigurationAjaxHandler' )) {
	class PostmanManageConfigurationAjaxHandler {
		private $logger;
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		/**
		 * Handle a manual configuration request with Ajax
		 *
		 * @throws Exception
		 */
		function getManualConfigurationViaAjax() {
			$queryTransportType = $this->getTransportTypeFromRequest ();
			$queryAuthType = '';
			if (isset ( $_POST ['auth_type'] )) {
				$queryAuthType = $_POST ['auth_type'];
			}
			// the outgoing server hostname is only required for the SMTP Transport
			// the Gmail API transport doesn't use an SMTP server
			$queryHostname = $this->getHostnameFromRequest ();
			$transport = PostmanTransportUtils::getTransport ( $queryTransportType );
			if (! $transport) {
				throw new Exception ( 'Unable to find transport ' . $queryTransportType );
			}
			$this->logger->debug ( 'ajaxRedirectUrl transport:' . $queryTransportType );
			$this->logger->debug ( 'ajaxRedirectUrl authType:' . $queryAuthType );
			$this->logger->debug ( 'ajaxRedirectUrl hostname:' . $queryHostname );
			
			// don't care about what's in the database, i need a scribe based on the ajax parameter assuming this is OAUTH2
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $queryHostname );
			// this must be wizard or config from an oauth-related change
			$authType = $queryAuthType;
			$encType = null;
			$port = null;
			
			$response = array ();
			$this->populateResponseFromScribe ( $scribe, $response );
			// for manual config.. you need to separate wizard from manual this is a mess
			if ($transport->isOAuthUsed ( $queryAuthType )) {
				$response ['display_auth'] = 'oauth2';
				$this->logger->debug ( 'ajaxRedirectUrl answer display_auth:' . $response ['display_auth'] );
			}
			/* translators: where %s is the URL to the Connectivity Test page */
			$response ['message'] = sprintf ( __ ( 'Postman can\'t find any way to send mail on your system. Run a <a href="%s">connectivity test</a>.', 'postman-smtp' ), PostmanAdminController::getPageUrl ( PostmanAdminController::PORT_TEST_SLUG ) );
			$this->logger->debug ( 'ajaxRedirectUrl answer hide_auth:' . $response ['hide_auth'] );
			$this->logger->debug ( 'ajaxRedirectUrl answer hide_enc:' . $response ['hide_enc'] );
			$this->logger->debug ( 'ajaxRedirectUrl answer message:' . $response ['message'] );
			wp_send_json_success ( $response );
		}
		
		/**
		 * This Ajax function retrieves the OAuth redirectUrl and help text for based on the SMTP hostname supplied
		 */
		function getWizardConfigurationViaAjax() {
			$queryAuthType = '';
			if (isset ( $_POST ['auth_type'] )) {
				$queryAuthType = $_POST ['auth_type'];
			}
			if (isset ( $_POST ['host_data'] )) {
				$queryHostData = $_POST ['host_data'];
			}
			$userOverride = false;
			if (isset ( $_POST ['user_override'] )) {
				$userOverride = filter_var ( $_POST ['user_override'], FILTER_VALIDATE_BOOLEAN );
			}
			
			$this->logger->debug ( 'ajaxRedirectUrl authType:' . $queryAuthType );
			$this->logger->debug ( 'ajaxRedirectUrl hostname:' . $queryHostname );
			
			$winningRecommendation = getConfigurationRecommendation ( $queryHostData );
			
			// create a transport based on the recommendation
			
			$transport = new PostmanDummyTransport ();
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $transport, $queryHostname );
			$this->logger->debug ( 'ajaxRedirectUrl referer:' . $_POST ['referer'] );
			// this must be wizard or config from an oauth-related change
			$authType = $queryAuthType;
			$encType = null;
			$port = null;
			
			$response = array ();
			$this->populateResponseFromScribe ( $scribe, $response );
			
			if ($winningRecommendation) {
				$response [PostmanOptions::TRANSPORT_TYPE] = $winningRecommendation ['transport'];
				$response [PostmanOptions::AUTHENTICATION_TYPE] = $winningRecommendation ['auth'];
				$response [PostmanOptions::ENCRYPTION_TYPE] = $winningRecommendation ['enc'];
				$response [PostmanOptions::PORT] = $winningRecommendation ['port'];
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
				$this->logger->debug ( 'ajaxRedirectUrl answer port_id:' . $response ['port_id'] );
				$this->logger->debug ( 'ajaxRedirectUrl answer display_auth:' . $response ['display_auth'] );
			} else {
				// for manual config.. you need to separate wizard from manual this is a mess
				if ($transport->isOAuthUsed ( $queryAuthType )) {
					$response ['display_auth'] = 'oauth2';
					$this->logger->debug ( 'ajaxRedirectUrl answer display_auth:' . $response ['display_auth'] );
				}
				/* translators: where %s is the URL to the Connectivity Test page */
				$response ['message'] = sprintf ( __ ( 'Postman can\'t find any way to send mail on your system. Run a <a href="%s">connectivity test</a>.', 'postman-smtp' ), PostmanAdminController::getPageUrl ( PostmanAdminController::PORT_TEST_SLUG ) );
			}
			$this->logger->debug ( 'ajaxRedirectUrl answer hide_auth:' . $response ['hide_auth'] );
			$this->logger->debug ( 'ajaxRedirectUrl answer hide_enc:' . $response ['hide_enc'] );
			$this->logger->debug ( 'ajaxRedirectUrl answer message:' . $response ['message'] );
			wp_send_json_success ( $response );
		}
		
		/**
		 */
		private function getTransportTypeFromRequest() {
			if (isset ( $_POST ['transport'] )) {
				return $_POST ['transport'];
			}
		}
		
		/**
		 */
		private function getHostnameFromRequest() {
			if (isset ( $_POST ['hostname'] )) {
				return $_POST ['hostname'];
			}
		}
		
		/**
		 */
		private function getAuthenticationTypeFromRequest() {
			if (isset ( $_POST ['auth_type'] )) {
				return $_POST ['auth_type'];
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
		 * @param unknown $response        	
		 */
		private function populateResponseFromScribe($scribe, $response) {
			$response ['redirect_url'] = $scribe->getCallbackUrl ();
			$response ['callback_domain'] = $scribe->getCallbackDomain ();
			$response ['help_text'] = $scribe->getOAuthHelp ();
			$response ['client_id_label'] = $scribe->getClientIdLabel ();
			$response ['client_secret_label'] = $scribe->getClientSecretLabel ();
			$response ['redirect_url_label'] = $scribe->getCallbackUrlLabel ();
			$response ['callback_domain_label'] = $scribe->getCallbackDomainLabel ();
			$response ['referer'] = 'manual_config';
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
	}
}