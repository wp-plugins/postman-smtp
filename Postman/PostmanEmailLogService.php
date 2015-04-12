<?php
include_once (ABSPATH . 'wp-admin/includes/plugin.php');

if (! class_exists ( 'PostmanEmailLog' )) {
	class PostmanEmailLog {
		public $body;
		public $subject;
		public $message;
		public $success;
		public $sender;
		public $recipients;
		public $sessionTranscript;
	}
}

if (! class_exists ( 'PostmanEmailLogService' )) {
	class PostmanEmailLogService {
		
		// constants
		const POSTMAN_CUSTOM_POST_TYPE_SLUG = 'postman_sent_mail';
		
		// member variables
		private $logger;
		private $inst;
		
		/**
		 * Constructor
		 */
		private function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'init', array (
					$this,
					'init' 
			) );
		}
		
		/**
		 * singleton instance
		 */
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanEmailLogService ();
			}
			return $inst;
		}
		
		/**
		 */
		public function init() {
			$this->create_post_type ();
			$this->createTaxonomy ();
		}
		private function truncateEmailLog() {
			$args = array (
					'post_type' => 'self::POSTMAN_CUSTOM_POST_TYPE_SLUG' 
			);
		}
		
		/**
		 * Create a custom post type
		 * Callback function - must be public scope
		 *
		 * register_post_type should only be invoked through the 'init' action.
		 * It will not work if called before 'init', and aspects of the newly
		 * created or modified post type will work incorrectly if called later.
		 *
		 * https://codex.wordpress.org/Function_Reference/register_post_type
		 */
		function create_post_type() {
			register_post_type ( self::POSTMAN_CUSTOM_POST_TYPE_SLUG, array (
					'labels' => array (
							'name' => _x ( 'Sent Emails', 'A List of Emails that have been sent', 'postman-smtp' ) 
					),
					'show_in_nav_menus' => true,
					'show_ui' => true,
					'has_archive' => true 
			) );
			
			$this->logger->debug ( 'Created custom post type \'postman_email\'' );
		}
		
		/**
		 * From http://wordpress.stackexchange.com/questions/8569/wp-insert-post-php-function-and-custom-fields
		 */
		public function writeToEmailLog(PostmanEmailLog $log) {
			// Create post object
			// from http://stackoverflow.com/questions/20444042/wordpress-how-to-sanitize-multi-line-text-from-a-textarea-without-losing-line
			$sanitizedBody = implode ( PHP_EOL, array_map ( 'sanitize_text_field', explode ( PHP_EOL, $log->body ) ) );
			$my_post = array (
					'post_type' => self::POSTMAN_CUSTOM_POST_TYPE_SLUG,
					'post_title' => wp_slash ( sanitize_text_field ( $log->subject ) ),
					'post_content' => wp_slash ( $sanitizedBody ),
					'post_excerpt' => wp_slash ( sanitize_text_field ( $log->message ) ),
					'post_status' => 'private' 
			);
			// Insert the post into the database
			$post_id = wp_insert_post ( $my_post );
			
			// meta
			update_post_meta ( $post_id, 'from_header', wp_slash ( sanitize_text_field ( $log->sender ) ) );
			update_post_meta ( $post_id, 'to_header', wp_slash ( sanitize_text_field ( implode ( ', ', $log->recipients ) ) ) );
			update_post_meta ( $post_id, 'status', sanitize_text_field ( $log->success ) );
			// from http://stackoverflow.com/questions/20444042/wordpress-how-to-sanitize-multi-line-text-from-a-textarea-without-losing-line
			$sanitizedTranscript = implode ( PHP_EOL, array_map ( 'sanitize_text_field', explode ( PHP_EOL, $log->sessionTranscript ) ) );
			update_post_meta ( $post_id, 'session_transcript', wp_slash ( $sanitizedTranscript ) );
		}
		
		/**
		 */
		private function createTaxonomy() {
			// create taxonomy
			$args = array ();
			register_taxonomy ( 'postman_sent_mail_category', 'success', $args );
			register_taxonomy ( 'postman_sent_mail_category', 'fail', $args );
		}
	}
}