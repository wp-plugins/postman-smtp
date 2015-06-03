<?php
if (! class_exists ( 'PostmanManageConfigurationAjaxHandler' )) {
	class PostmanManageConfigurationAjaxHandler extends PostmanAbstractAjaxHandler {
		function __construct() {
			parent::__construct();
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
			$transport = PostmanTransportRegistry::getInstance ()->getTransport ( $queryTransportType );
			if (! $transport) {
				throw new Exception ( 'Unable to find transport ' . $queryTransportType );
			}
			
			// create the scribe
			$scribe = PostmanConfigTextHelperFactory::createScribe ( $queryHostname, $transport );
			
			// create the response
			$response = array ();
			$response ['referer'] = 'manual_config';
			$this->populateResponseFromScribe ( $scribe, $response );
			
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
			$this->logger->debug ( 'in getWizardConfiguration' );
			$originalSmtpServer = $this->getRequestParameter ( 'original_smtp_server' );
			$queryHostData = $this->getHostDataFromRequest ();
			$userPortOverride = $this->getUserPortOverride ();
			$userAuthOverride = $this->getUserAuthOverride ();
			
			// determine a configuration recommendation
			$winningRecommendation = $this->getWinningRecommendation ( $queryHostData, $userPortOverride, $userAuthOverride, $originalSmtpServer );
			$this->logger->trace ( 'winning recommendation:' );
			$this->logger->trace ( $winningRecommendation );
			
			// create user override menu
			$overrideMenu = $this->createOverrideMenu ( $queryHostData, $winningRecommendation, $userPortOverride, $userAuthOverride );
			$this->logger->trace ( 'override menu:' );
			$this->logger->trace ( $overrideMenu );
			
			// create the reponse
			$response = array ();
			$configuration = array ();
			$response ['referer'] = 'wizard';
			if (isset ( $userPortOverride ) || isset ( $userAuthOverride )) {
				$configuration ['user_override'] = true;
			}
			
			if (isset ( $winningRecommendation )) {
				// create an appropriate (theoretical) transport
				$transport = PostmanTransportRegistry::getInstance ()->getTransport ( $winningRecommendation ['transport'] );
				$scribe = PostmanConfigTextHelperFactory::createScribe ( $winningRecommendation ['hostname'], $transport );
				$this->populateResponseFromScribe ( $scribe, $configuration );
				$this->populateResponseFromTransport ( $winningRecommendation, $configuration );
				$response ['override_menu'] = $overrideMenu;
				$response ['configuration'] = $configuration;
				$this->logger->trace ( 'configuration:' );
				$this->logger->trace ( $configuration );
				wp_send_json_success ( $response );
			} else {
				/* translators: where %s is the URL to the Connectivity Test page */
				$configuration ['message'] = sprintf ( __ ( 'Postman can\'t find any way to send mail on your system. Run a <a href="%s">connectivity test</a>.', 'postman-smtp' ), PostmanViewController::getPageUrl ( PostmanViewController::PORT_TEST_SLUG ) );
				$response ['configuration'] = $configuration;
				$this->logger->trace ( 'configuration:' );
				$this->logger->trace ( $configuration );
				wp_send_json_error ( $response );
			}
		}
		
		/**
		 *
		 * @param unknown $queryHostData        	
		 * @return multitype:
		 */
		private function createOverrideMenu($queryHostData, $winningRecommendation, $userSocketOverride, $userAuthOverride) {
			$overrideMenu = array ();
			foreach ( $queryHostData as $id => $value ) {
				if (filter_var ( $value ['success'], FILTER_VALIDATE_BOOLEAN )) {
					$overrideItem = array ();
					$overrideItem ['secure'] = PostmanUtils::parseBoolean ( $value ['secure'] );
					$overrideItem ['mitm'] = PostmanUtils::parseBoolean ( $value ['mitm'] );
					$overrideItem ['hostname_domain_only'] = $value ['hostname_domain_only'];
					$overrideItem ['reported_hostname_domain_only'] = $value ['reported_hostname_domain_only'];
					$overrideItem ['value'] = sprintf ( '%s_%s', $value ['hostname'], $value ['port'] );
					$selected = ($winningRecommendation ['id'] == $overrideItem ['value']);
					$overrideItem ['selected'] = $selected;
					$hostnameToDisplay = $value ['hostname'];
					$overrideItem ['description'] = sprintf ( '%s:%s', $hostnameToDisplay, $value ['port'] );
					$overrideAuthItems = array ();
					$passwordMode = false;
					$oauth2Mode = false;
					$noAuthMode = false;
					if (isset ( $userAuthOverride ) || isset ( $userSocketOverride )) {
						if ($userAuthOverride == 'password') {
							$passwordMode = true;
						} elseif ($userAuthOverride == 'oauth2') {
							$oauth2Mode = true;
						} else {
							$noAuthMode = true;
						}
					} else {
						if ($winningRecommendation ['display_auth'] == 'password') {
							$passwordMode = true;
						} elseif ($winningRecommendation ['display_auth'] == 'oauth2') {
							$oauth2Mode = true;
						} else {
							$noAuthMode = true;
						}
					}
					if ($selected) {
						if ($value ['auth_crammd5'] || $value ['auth_login'] || $value ['auth_plain']) {
							array_push ( $overrideAuthItems, array (
									'selected' => $passwordMode,
									'name' => __ ( 'Password (requires username and password)', 'postman-smtp' ),
									'value' => 'password' 
							) );
						}
						if ($value ['auth_xoauth'] || $winningRecommendation ['auth'] == 'oauth2') {
							array_push ( $overrideAuthItems, array (
									'selected' => $oauth2Mode,
									'name' => __ ( 'OAuth 2.0 (requires Client ID and Client Secret)', 'postman-smtp' ),
									'value' => 'oauth2' 
							) );
						}
						if ($value ['auth_none']) {
							array_push ( $overrideAuthItems, array (
									'selected' => $noAuthMode,
									'name' => _x ( 'None', 'As in type used: None', 'postman-smtp' ),
									'value' => 'none' 
							) );
						}
						
						// marks at least one item as selected if none are selected
						$atLeastOneSelected = false;
						$firstItem = null;
						// don't use variable reference see http://stackoverflow.com/questions/15024616/php-foreach-change-original-array-values
						foreach ( $overrideAuthItems as $key => $field ) {
							if (! $firstItem) {
								$firstItem = $key;
							}
							if ($field ['selected']) {
								$atLeastOneSelected = true;
							}
						}
						if (! $atLeastOneSelected) {
							$this->logger->debug ( 'nothing selected - forcing a selection on the *first* overrided auth item' );
							$overrideAuthItems [$firstItem] ['selected'] = true;
						}
						
						// push the authentication options into the $overrideItem structure
						$overrideItem ['auth_items'] = $overrideAuthItems;
					}
					array_push ( $overrideMenu, $overrideItem );
				}
			}
			return $overrideMenu;
		}
		
		/**
		 * // for each successful host/port combination
		 * // ask a transport if they support it, and if they do at what priority is it
		 * // configure for the highest priority you find
		 *
		 * @param unknown $queryHostData        	
		 * @return unknown
		 */
		private function getWinningRecommendation($queryHostData, $userSocketOverride, $userAuthOverride, $originalSmtpServer) {
			$recommendationPriority = - 1;
			$winningRecommendation = null;
			foreach ( $queryHostData as $id => $value ) {
				$available = filter_var ( $value ['success'], FILTER_VALIDATE_BOOLEAN );
				if ($available) {
					$this->logger->debug ( sprintf ( 'Asking for judgement on %s:%s', $value ['hostname'], $value ['port'] ) );
					$recommendation = PostmanTransportRegistry::getInstance ()->getRecommendation ( $value, $userAuthOverride, $originalSmtpServer );
					$recommendationId = sprintf ( '%s_%s', $value ['hostname'], $value ['port'] );
					$recommendation ['id'] = $recommendationId;
					$this->logger->debug ( sprintf ( 'Got a recommendation: [%d] %s', $recommendation ['priority'], $recommendationId ) );
					if (isset ( $userSocketOverride )) {
						if ($recommendationId == $userSocketOverride) {
							$winningRecommendation = $recommendation;
							$this->logger->debug ( sprintf ( 'User chosen socket %s is the winner', $recommendationId ) );
						}
					} elseif ($recommendation && $recommendation ['priority'] > $recommendationPriority) {
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
		private function populateResponseFromScribe($scribe, &$response) {
			// checks to see if the host is an IP address and sticks the result in the response
			// IP addresses are not allowed in the Redirect URL
			$urlParts = parse_url ( $scribe->getCallbackUrl () );
			$response ['dot_notation_url'] = false;
			if (isset ( $urlParts ['host'] )) {
				if (PostmanUtils::isHostAddressNotADomainName ( $urlParts ['host'] )) {
					$response ['dot_notation_url'] = true;
				}
			}
			$response ['redirect_url'] = $scribe->getCallbackUrl ();
			$response ['callback_domain'] = $scribe->getCallbackDomain ();
			$response ['help_text'] = $scribe->getOAuthHelp ();
			$response ['client_id_label'] = $scribe->getClientIdLabel ();
			$response ['client_secret_label'] = $scribe->getClientSecretLabel ();
			$response ['redirect_url_label'] = $scribe->getCallbackUrlLabel ();
			$response ['callback_domain_label'] = $scribe->getCallbackDomainLabel ();
		}
		
		/**
		 *
		 * @param unknown $winningRecommendation        	
		 * @param unknown $response        	
		 */
		private function populateResponseFromTransport($winningRecommendation, &$response) {
			$response [PostmanOptions::TRANSPORT_TYPE] = $winningRecommendation ['transport'];
			$response [PostmanOptions::AUTHENTICATION_TYPE] = $winningRecommendation ['auth'];
			if (isset ( $winningRecommendation ['enc'] )) {
				$response [PostmanOptions::ENCRYPTION_TYPE] = $winningRecommendation ['enc'];
			}
			$response [PostmanOptions::PORT] = $winningRecommendation ['port'];
			$response [PostmanOptions::HOSTNAME] = $winningRecommendation ['hostname'];
			$response ['display_auth'] = $winningRecommendation ['display_auth'];
			$response ['message'] = $winningRecommendation ['message'];
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
		private function getHostDataFromRequest() {
			return $this->getRequestParameter ( 'host_data' );
		}
		
		/**
		 */
		private function getUserPortOverride() {
			return $this->getRequestParameter ( 'user_port_override' );
		}
		
		/**
		 */
		private function getUserAuthOverride() {
			return $this->getRequestParameter ( 'user_auth_override' );
		}
	}
}

