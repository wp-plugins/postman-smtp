<?php
if (! class_exists ( 'PostmanEmailLog' )) {
	class PostmanEmailLog {
		public $sender;
		public $recipients;
		public $subject;
		public $body;
		public $success;
		public $statusMessage;
		public $sessionTranscript;
		public $transportUri;
		public $replyTo;
		public $originalTo;
		public $originalSubject;
		public $originalMessage;
		public $originalHeaders;
	}
}

if (! class_exists ( 'PostmanEmailLogService' )) {
	
	/**
	 * This class creates the Custom Post Type for Email Logs and handles writing these posts.
	 *
	 * @author jasonhendriks
	 */
	class PostmanEmailLogService {
		
		// constants
		const POSTMAN_CUSTOM_POST_TYPE_SLUG = 'postman_sent_mail';
		
		/*
		 * Private content is published only for your eyes, or the eyes of only those with authorization
		 * permission levels to see private content. Normal users and visitors will not be aware of
		 * private content. It will not appear in the article lists. If a visitor were to guess the URL
		 * for your private post, they would still not be able to see your content. You will only see
		 * the private content when you are logged into your WordPress blog.
		 */
		const POSTMAN_CUSTOM_POST_STATUS_PRIVATE = 'private';
		
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
		 * Behavior to run on the WordPress 'init' action
		 */
		public function init() {
			$this->create_post_type ();
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
							'name' => _x ( 'Sent Emails', 'The group of Emails that have been delivered', 'postman-smtp' ),
							'singular_name' => _x ( 'Sent Email', 'An Email that has been delivered', 'postman-smtp' ) 
					),
					'capability_type' => '',
					'capabilities' => array () 
			) );
			$this->logger->trace ( 'Created post type: ' . self::POSTMAN_CUSTOM_POST_TYPE_SLUG );
		}
		
		/**
		 * Logs successful email attempts
		 *
		 * @param PostmanMessage $message        	
		 * @param unknown $transcript        	
		 * @param PostmanTransport $transport        	
		 */
		public function writeSuccessLog(PostmanMessage $message, $transcript, PostmanTransport $transport) {
			if (PostmanOptions::getInstance ()->isMailLoggingEnabled ()) {
				$log = $this->createLog ( $message, $transcript, '', true, $transport );
				$this->writeToEmailLog ( $log );
			}
		}
		
		/**
		 * Logs failed email attempts, requires more metadata so the email can be resent in the future
		 *
		 * @param PostmanMessage $message        	
		 * @param unknown $transcript        	
		 * @param PostmanTransport $transport        	
		 * @param unknown $statusMessage        	
		 * @param unknown $originalTo        	
		 * @param unknown $originalSubject        	
		 * @param unknown $originalMessage        	
		 * @param unknown $originalHeaders        	
		 */
		public function writeFailureLog(PostmanMessage $message = null, $transcript, PostmanTransport $transport, $statusMessage, $originalTo, $originalSubject, $originalMessage, $originalHeaders) {
			if (PostmanOptions::getInstance ()->isMailLoggingEnabled ()) {
				$log = $this->createLog ( $message, $transcript, $statusMessage, false, $transport );
				$log->originalTo = $originalTo;
				$log->originalSubject = $originalSubject;
				$log->originalMessage = $originalMessage;
				$log->originalHeaders = $originalHeaders;
				$this->writeToEmailLog ( $log );
			}
		}
		
		/**
		 * Writes an email sending attempt to the Email Log
		 *
		 * From http://wordpress.stackexchange.com/questions/8569/wp-insert-post-php-function-and-custom-fields
		 */
		private function writeToEmailLog(PostmanEmailLog $log) {
			// nothing here is sanitized as WordPress should take care of
			// making database writes safe
			$my_post = array (
					'post_type' => self::POSTMAN_CUSTOM_POST_TYPE_SLUG,
					'post_title' => $log->subject,
					'post_content' => $log->body,
					'post_excerpt' => $log->statusMessage,
					'post_status' => PostmanEmailLogService::POSTMAN_CUSTOM_POST_STATUS_PRIVATE 
			);
			
			// Insert the post into the database (WordPress gives us the Post ID)
			$post_id = wp_insert_post ( $my_post );
			$this->logger->debug ( sprintf ( 'Saved message #%s to the database', $post_id ) );
			$this->logger->trace ( $log );
			
			// Write the meta data related to the email
			update_post_meta ( $post_id, 'success', $log->success );
			update_post_meta ( $post_id, 'from_header', $log->sender );
			update_post_meta ( $post_id, 'to_header', $log->recipients );
			update_post_meta ( $post_id, 'reply_to_header', $log->replyTo );
			update_post_meta ( $post_id, 'transport_uri', $log->transportUri );
			
			if (! $log->success) {
				// if the message failed to send, add meta data so we can re-send it
				update_post_meta ( $post_id, 'original_to', $log->originalTo );
				update_post_meta ( $post_id, 'original_subject', $log->originalSubject );
				update_post_meta ( $post_id, 'original_message', $log->originalMessage );
				update_post_meta ( $post_id, 'original_headers', $log->originalHeaders );
			}
			
			// we do not sanitize the session transcript - let the reader decide how to handle the data
			update_post_meta ( $post_id, 'session_transcript', $log->sessionTranscript );
			
			// truncate the log (remove older entries)
			$purger = new PostmanEmailLogPurger ();
			$purger->truncateLogItems ( PostmanOptions::getInstance ()->getMailLoggingMaxEntries () );
		}
		
		/**
		 * Creates a Log object for use by writeToEmailLog()
		 *
		 * @param PostmanMessage $message        	
		 * @param unknown $transcript        	
		 * @param unknown $statusMessage        	
		 * @param unknown $success        	
		 * @param PostmanTransport $transport        	
		 * @return PostmanEmailLog
		 */
		private function createLog(PostmanMessage $message = null, $transcript, $statusMessage, $success, PostmanTransport $transport) {
			$log = new PostmanEmailLog ();
			if ($message) {
				$log->sender = $message->getFromAddress ()->format ();
				$log->recipients = $this->flattenEmails ( $message->getToRecipients () );
				$log->subject = $message->getSubject ();
				$log->body = $message->getBody ();
				if (null !== $message->getReplyTo ()) {
					$log->replyTo = $message->getReplyTo ()->format ();
				}
			}
			$log->success = $success;
			$log->statusMessage = $statusMessage;
			$log->transportUri = PostmanTransportRegistry::getInstance ()->getPublicTransportUri ( $transport );
			$log->sessionTranscript = $transcript;
			return $log;
		}
		
		/**
		 * Creates a readable "TO" entry based on the recipient header
		 * 
		 * @param array $addresses
		 * @return string
		 */
		private static function flattenEmails(array $addresses) {
			$flat = '';
			$count = 0;
			foreach ( $addresses as $address ) {
				if ($count >= 3) {
					$flat .= sprintf ( __ ( '.. +%d more', 'postman-smtp' ), sizeof ( $addresses ) - $count );
					break;
				}
				if ($count > 0) {
					$flat .= ', ';
				}
				$flat .= $address->format ();
				$count ++;
			}
			return $flat;
		}
	}
}
