<?php
if (! class_exists ( "PostmanDashboardWidgetController" )) {
	
	//
	class PostmanDashboardWidgetController {
		private $rootPluginFilenameAndPath;
		private $options;
		private $authorizationToken;
		private $wpMailBinder;
		
		/**
		 * Start up
		 */
		public function __construct($rootPluginFilenameAndPath, PostmanOptions $options, PostmanOAuthToken $authorizationToken, PostmanWpMailBinder $binder) {
			assert ( ! empty ( $rootPluginFilenameAndPath ) );
			assert ( ! empty ( $options ) );
			assert ( ! empty ( $authorizationToken ) );
			assert ( ! empty ( $binder ) );
			$this->rootPluginFilenameAndPath = $rootPluginFilenameAndPath;
			$this->options = $options;
			$this->authorizationToken = $authorizationToken;
			$this->wpMailBinder = $binder;
			
			if (is_admin ()) {
				
				add_action ( 'wp_dashboard_setup', array (
						$this,
						'addDashboardWidget' 
				) );
				
				add_action ( 'wp_network_dashboard_setup', array (
						$this,
						'addNetworkDashboardWidget' 
				) );
				
				// dashboard glance mod
				if ($this->options->isMailLoggingEnabled ()) {
					add_filter ( 'dashboard_glance_items', array (
							$this,
							'customizeAtAGlanceDashboardWidget' 
					), 10, 1 );
				}
			}
		}
		
		/**
		 * Add a widget to the dashboard.
		 *
		 * This function is hooked into the 'wp_dashboard_setup' action below.
		 */
		public function addDashboardWidget() {
			wp_add_dashboard_widget ( 'example_dashboard_widget', __ ( 'Postman SMTP', 'postman-smtp' ), array (
					$this,
					'printDashboardWidget' 
			) ); // Display function.
		}
		public function addNetworkDashboardWidget() {
			wp_add_dashboard_widget ( 'example_dashboard_widget', __ ( 'Postman SMTP', 'postman-smtp' ), array (
					$this,
					'printNetworkDashboardWidget' 
			) ); // Display function.
		}
		
		/**
		 * Create the function to output the contents of our Dashboard Widget.
		 */
		public function printDashboardWidget() {
			$goToSettings = sprintf ( '<a href="%s">%s</a>', PostmanUtils::getSettingsPageUrl (), _x ( 'Settings', 'The configuration page of the plugin', 'postman-smtp' ) );
			$goToEmailLog = sprintf ( '%s', _x ( 'Email Log', 'The log of Emails that have been delivered', 'postman-smtp' ) );
			if ($this->options->isMailLoggingEnabled ()) {
				$goToEmailLog = sprintf ( '<a href="%s">%s</a>', PostmanUtils::getEmailLogPageUrl (), $goToEmailLog );
			}
			if (! PostmanPreRequisitesCheck::isReady ()) {
				printf ( '<p><span style="color:red">%s</span></p>', __ ( 'Postman is missing a required PHP library.', 'postman-smtp' ) );
			} else if ($this->wpMailBinder->isUnboundDueToException ()) {
				printf ( '<p><span style="color:red">%s</span></p>', __ ( 'Postman is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.', 'postman-smtp' ) );
			} else {
				if (PostmanTransportUtils::isPostmanReadyToSendEmail ( $this->options, $this->authorizationToken )) {
					printf ( '<p class="wp-menu-image dashicons-before dashicons-email"> %s </p>', sprintf ( _n ( '<span style="color:green">Postman is configured</span> and has delivered <span style="color:green">%d</span> email.', '<span style="color:green">Postman is configured</span> and has delivered <span style="color:green">%d</span> emails.', PostmanStats::getInstance ()->getSuccessfulDeliveries (), 'postman-smtp' ), PostmanStats::getInstance ()->getSuccessfulDeliveries () ) );
					$currentTransport = PostmanTransportUtils::getCurrentTransport ();
					$deliveryDetails = $currentTransport->getDeliveryDetails ( $this->options );
					printf ( '<p>%s</p>', $deliveryDetails );
				} else {
					printf ( '<p><span style="color:red">%s</span></p>', __ ( 'Postman is <em>not</em> handling email delivery.', 'postman-smtp' ) );
				}
			}
			printf ( '<p>%s | %s</p>', $goToEmailLog, $goToSettings );
		}
		
		/**
		 * Create the function to output the contents of our Dashboard Widget.
		 */
		public function printNetworkDashboardWidget() {
			printf ( '<p class="wp-menu-image dashicons-before dashicons-email"> %s</p>', __ ( 'Postman is operating in per-site mode.', 'postman-smtp' ) );
		}
		
		/**
		 * From http://www.hughlashbrooke.com/2014/02/wordpress-add-items-glance-widget/
		 * http://coffeecupweb.com/how-to-add-custom-post-types-to-at-a-glance-dashboard-widget-in-wordpress/
		 *
		 * @param unknown $items        	
		 * @return string
		 */
		function customizeAtAGlanceDashboardWidget($items = array()) {
			$post_types = array (
					PostmanEmailLogService::POSTMAN_CUSTOM_POST_TYPE_SLUG 
			);
			
			foreach ( $post_types as $type ) {
				
				if (! post_type_exists ( $type ))
					continue;
				
				$num_posts = wp_count_posts ( $type );
				
				if ($num_posts) {
					
					$published = intval ( $num_posts->publish );
					$privated = intval ( $num_posts->private );
					$post_type = get_post_type_object ( $type );
					
					$text = _n ( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $privated, 'postman-smtp' );
					$text = sprintf ( $text, number_format_i18n ( $privated ) );
					
					$items [] = sprintf ( '<a class="%1$s-count" href="%3$s">%2$s</a>', $type, $text, PostmanUtils::getEmailLogPageUrl () ) . "\n";
				}
			}
			
			return $items;
		}
	}
}