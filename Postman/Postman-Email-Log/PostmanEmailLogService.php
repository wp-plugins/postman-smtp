<?php
include_once (ABSPATH . 'wp-admin/includes/plugin.php');

if (! class_exists ( 'PostmanEmailLog' )) {
	class PostmanEmailLog {
		public $sender;
		public $recipients;
		public $subject;
		public $body;
		public $success;
		public $statusMessage;
		public $sessionTranscript;
	}
}

if (! class_exists ( 'PostmanEmailLogFactory' )) {
	class PostmanEmailLogFactory {
		public static function createSuccessLog(PostmanMessage $message, $transcript) {
			return PostmanEmailLogFactory::createLog ( $message, $transcript, __ ( 'Sent' ), true );
		}
		public static function createFailureLog(PostmanMessage $message = null, $transcript, $statusMessage) {
			return PostmanEmailLogFactory::createLog ( $message, $transcript, $statusMessage, false );
		}
		private static function createLog(PostmanMessage $message = null, $transcript, $statusMessage, $success) {
			$log = new PostmanEmailLog ();
			if ($message) {
				$log->sender = $message->getSender ()->getEmail ();
				$log->recipients = PostmanEmailLogFactory::flattenEmails ( $message->getToRecipients () );
				$log->subject = $message->getSubject ();
				$log->body = $message->getBody ();
			}
			$log->success = $success;
			$log->statusMessage = $statusMessage;
			$log->sessionTranscript = 'n/a';
			if (! empty ( $transcript )) {
				$log->sessionTranscript = $transcript;
			}
			return $log;
		}
		private static function flattenEmails(array $addresses) {
			$flat = '';
			$count = 0;
			foreach ( $addresses as $address ) {
				if ($count > 0) {
					$flat .= ', ';
				}
				$flat .= $address->getEmail ();
				$count ++;
			}
			return $flat;
		}
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
			$FORCE_DELETE = true;
			// wp_delete_post( $postid, $FORCE_DELETE );
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
							'name' => _x ( 'Email Log', 'A List of Emails that have been sent', 'postman-smtp' ) 
					)
			) );
			$this->logger->debug('Created custom post type');
		}
		
		/**
		 * From http://wordpress.stackexchange.com/questions/8569/wp-insert-post-php-function-and-custom-fields
		 */
		public function writeToEmailLog(PostmanEmailLog $log) {
			// Create post object
			// from http://stackoverflow.com/questions/20444042/wordpress-how-to-sanitize-multi-line-text-from-a-textarea-without-losing-line
			$sanitizedBody = implode ( PHP_EOL, array_map ( 'sanitize_text_field', explode ( PHP_EOL, $log->body ) ) );
			/*
			 * Private content is published only for your eyes, or the eyes of only those with authorization
			 * permission levels to see private content. Normal users and visitors will not be aware of
			 * private content. It will not appear in the article lists. If a visitor were to guess the URL
			 * for your private post, they would still not be able to see your content. You will only see
			 * the private content when you are logged into your WordPress blog.
			 */
			$my_post = array (
					'post_type' => self::POSTMAN_CUSTOM_POST_TYPE_SLUG,
					'post_title' => wp_slash ( sanitize_text_field ( $log->subject ) ),
					'post_content' => wp_slash ( $sanitizedBody ),
					'post_excerpt' => wp_slash ( sanitize_text_field ( $log->statusMessage ) ),
					'post_status' => 'private' 
			); // publish
			   
			// Insert the post into the database
			$post_id = wp_insert_post ( $my_post );
			$this->logger->debug ( sprintf ( 'Saved message #%s to the database', $post_id ) );
			$this->logger->trace ( $log );
			
			// meta
			update_post_meta ( $post_id, 'from_header', wp_slash ( sanitize_text_field ( $log->sender ) ) );
			update_post_meta ( $post_id, 'to_header', wp_slash ( sanitize_text_field ( $log->recipients ) ) );
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
