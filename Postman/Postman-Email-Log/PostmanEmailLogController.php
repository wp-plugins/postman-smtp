<?php
require_once 'PostmanEmailLogService.php';
require_once 'PostmanEmailLogView.php';

/**
 *
 * @author jasonhendriks
 *        
 */
if (! class_exists ( 'PostmanEmailLogController' )) {
	class PostmanEmailLogController {
		private $rootPluginFilenameAndPath;
		private $logger;
		
		/**
		 */
		function __construct($rootPluginFilenameAndPath) {
			$this->rootPluginFilenameAndPath = $rootPluginFilenameAndPath;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			if (PostmanOptions::getInstance ()->isMailLoggingEnabled ()) {
				add_action ( 'admin_menu', array (
						$this,
						'postmanAddMenuItem' 
				) );
			} else {
				$this->logger->trace ( 'not creating PostmanEmailLog admin menu item' );
			}
			if (PostmanUtils::isCurrentPagePostmanAdmin ( 'postman_email_log' )) {
				$this->logger->trace ( 'on postman email log page' );
				// $this->logger->debug ( 'Registering ' . $actionName . ' Action Post handler' );
				add_action ( 'admin_post_delete', array (
						$this,
						'delete_log_item' 
				) );
				add_action ( 'admin_post_view', array (
						$this,
						'view_log_item' 
				) );
				add_action ( 'admin_post_transcript', array (
						$this,
						'view_transcript_log_item' 
				) );
				add_action ( 'admin_init', array (
						$this,
						'handle_bulk_action' 
				) );
			}
		}
		
		/**
		 * From https://www.skyverge.com/blog/add-custom-bulk-action/
		 */
		function handle_bulk_action() {
			// only do this for administrators
			if (PostmanUtils::isAdmin () && isset ( $_REQUEST ['email_log_entry'] )) {
				$this->logger->trace ( 'handling bulk action' );
				if (wp_verify_nonce ( $_REQUEST ['_wpnonce'], 'bulk-email_log_entries' )) {
					$this->logger->trace ( sprintf ( 'nonce "%s" passed validation', $_REQUEST ['_wpnonce'] ) );
					if (isset ( $_REQUEST ['action'] ) && $_REQUEST ['action'] == 'bulk_delete') {
						$this->logger->trace ( sprintf ( 'handling bulk delete' ) );
						$purger = new PostmanEmailLogPurger ();
						$postids = $_REQUEST ['email_log_entry'];
						foreach ( $postids as $postid ) {
							$purger->verifyLogItemExistsAndRemove ( $postid );
						}
						$mh = new PostmanMessageHandler ();
						$mh->addMessage ( __ ( 'Mail Log Entries were deleted.', 'postman-smtp' ) );
					} else {
						$this->logger->warn ( sprintf ( 'action "%s" not recognized', $_REQUEST ['action'] ) );
					}
				} else {
					$this->logger->warn ( sprintf ( 'nonce "%s" failed validation', $_REQUEST ['_wpnonce'] ) );
				}
				$this->redirectToLogPage ();
			}
		}
		
		/**
		 */
		function delete_log_item() {
			// only do this for administrators
			if (PostmanUtils::isAdmin ()) {
				$this->logger->trace ( 'handling delete item' );
				$postid = $_REQUEST ['email'];
				if (wp_verify_nonce ( $_REQUEST ['_wpnonce'], 'delete_email_log_item_' . $postid )) {
					$this->logger->trace ( sprintf ( 'nonce "%s" passed validation', $_REQUEST ['_wpnonce'] ) );
					$purger = new PostmanEmailLogPurger ();
					$purger->verifyLogItemExistsAndRemove ( $postid );
					$mh = new PostmanMessageHandler ();
					$mh->addMessage ( __ ( 'Mail Log Entry was deleted.', 'postman-smtp' ) );
				} else {
					$this->logger->warn ( sprintf ( 'nonce "%s" failed validation', $_REQUEST ['_wpnonce'] ) );
				}
				$this->redirectToLogPage ();
			}
		}
		
		/**
		 */
		function view_log_item() {
			// only do this for administrators
			if (PostmanUtils::isAdmin ()) {
				$this->logger->trace ( 'handling view item' );
				$postid = $_REQUEST ['email'];
				$post = get_post ( $postid );
				$meta_values = get_post_meta ( $postid );
				// https://css-tricks.com/examples/hrs/
				print '<html><head><style>body {font-family: monospace;} hr {
    border: 0;
    border-bottom: 1px dashed #ccc;
    background: #bbb;
}</style></head><body>';
				print '<table>';
				if (! empty ( $meta_values ['from_header'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'From', 'Who is this message From?', 'postman-smtp' ), esc_html ( $meta_values ['from_header'] [0] ) );
				}
				// show the To header (it's optional)
				if (! empty ( $meta_values ['to_header'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'To', 'Who is this message To?', 'postman-smtp' ), esc_html ( $meta_values ['to_header'] [0] ) );
				}
				// show the Cc header (it's optional)
				if (! empty ( $meta_values ['cc_header'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Cc', 'Who is this message Cc\'d to?', 'postman-smtp' ), esc_html ( $meta_values ['cc_header'] [0] ) );
				}
				// show the Bcc header (it's optional)
				if (! empty ( $meta_values ['bcc_header'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Bcc', 'Who is this message Bcc\'d to?', 'postman-smtp' ), esc_html ( $meta_values ['bcc_header'] [0] ) );
				}
				// show the Reply-To header (it's optional)
				if (! empty ( $meta_values ['reply_to_header'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Reply-To', 'Who do we reply to?', 'postman-smtp' ), esc_html ( $meta_values ['reply_to_header'] [0] ) );
				}
				printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Date', 'What is the date today?', 'postman-smtp' ), $post->post_date );
				printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Subject', 'What is the subject of this message?', 'postman-smtp' ), esc_html ( $post->post_title ) );
				// The Transport UI is always there, in more recent versions that is
				if (! empty ( $meta_values ['transport_uri'] [0] )) {
					printf ( '<tr><th style="text-align:right">%s:</th><td>%s</td></tr>', _x ( 'Delivery-URI', 'What is the unique URI of the configuration?', 'postman-smtp' ), esc_html ( $meta_values ['transport_uri'] [0] ) );
				}
				print '</table>';
				print '<hr/>';
				print '<pre>';
				print esc_html ( $post->post_content );
				print '</pre>';
				print '</body></html>';
				die ();
			}
		}
		
		/**
		 */
		function view_transcript_log_item() {
			// only do this for administrators
			if (PostmanUtils::isAdmin ()) {
				$this->logger->trace ( 'handling view transcript item' );
				$postid = $_REQUEST ['email'];
				$post = get_post ( $postid );
				$meta_values = get_post_meta ( $postid );
				// https://css-tricks.com/examples/hrs/
				print '<html><head><style>body {font-family: monospace;} hr {
    border: 0;
    border-bottom: 1px dashed #ccc;
    background: #bbb;
}</style></head><body>';
				printf ( '<p>%s</p>', __ ( 'This is the conversation between Postman and your SMTP server. It can be useful for diagnosing problems. <b>DO NOT</b> post it on-line, it may contain your account password.', 'postman-smtp' ) );
				print '<hr/>';
				print '<pre>';
				if (! empty ( $meta_values ['session_transcript'] [0] )) {
					print esc_html ( $meta_values ['session_transcript'] [0] );
				} else {
					print __ ( 'n/a', 'postman-smtp' );
				}
				print '</pre>';
				print '</body></html>';
				die ();
			}
		}
		
		/**
		 * For whatever reason, PostmanUtils::get..url doesn't work here? :(
		 */
		function redirectToLogPage() {
			PostmanUtils::redirect ( PostmanUtils::POSTMAN_EMAIL_LOG_PAGE_RELATIVE_URL );
			die ();
		}
		
		/**
		 * Register the page
		 */
		function postmanAddMenuItem() {
			// only do this for administrators
			if (PostmanUtils::isAdmin ()) {
				$this->logger->trace ( 'created PostmanEmailLog admin menu item' );
				$page = add_management_page ( __ ( 'Postman Email Log', 'postman-smtp' ), _x ( 'Email Log', 'The log of Emails that have been delivered', 'postman-smtp' ), 'read_private_posts', 'postman_email_log', array (
						$this,
						'postman_render_email_page' 
				) );
				// When the plugin options page is loaded, also load the stylesheet
				add_action ( 'admin_print_styles-' . $page, array (
						$this,
						'postman_email_log_enqueue_resources' 
				) );
			}
		}
		function postman_email_log_enqueue_resources() {
			$pluginData = apply_filters ( 'postman_get_plugin_metadata', null );
			wp_register_style ( 'postman_email_log', plugins_url ( 'style/postman-email-log.css', $this->rootPluginFilenameAndPath ), null, $pluginData ['version'] );
			wp_enqueue_style ( 'postman_email_log' );
		}
		
		/**
		 * *************************** RENDER TEST PAGE ********************************
		 * ******************************************************************************
		 * This function renders the admin page and the example list table.
		 * Although it's
		 * possible to call prepare_items() and display() from the constructor, there
		 * are often times where you may need to include logic here between those steps,
		 * so we've instead called those methods explicitly. It keeps things flexible, and
		 * it's the way the list tables are used in the WordPress core.
		 */
		function postman_render_email_page() {
			
			// Create an instance of our package class...
			$testListTable = new PostmanEmailLogView ();
			// Fetch, prepare, sort, and filter our data...
			$testListTable->prepare_items ();
			
			?>
<div class="wrap">

	<div id="icon-users" class="icon32">
		<br />
	</div>
	<h2><?php echo __ ( 'Postman Email Log', 'postman-smtp' ) ?></h2>

	<div
		style="background: #ECECEC; border: 1px solid #CCC; padding: 0 10px; margin-top: 5px; border-radius: 5px; -moz-border-radius: 5px; -webkit-border-radius: 5px;">
		<p><?php
			
			echo __ ( 'This is a record of deliveries made to the Mail Submission Agent (MSA). It does not neccessarily indicate sucessful delivery to the recipient.', 'postman-smtp' )?></p>
	</div>

	<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
	<form id="movies-filter" method="get">
		<!-- For plugins, we also need to ensure that the form posts back to our current page -->
		<input type="hidden" name="page"
			value="<?php echo $_REQUEST['page'] ?>" />
		<!-- Now we can render the completed list table -->
            <?php $testListTable->display()?>
        </form>
        
        <?php add_thickbox(); ?>

</div>
<?php
		}
	}
}
