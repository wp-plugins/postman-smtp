<?php
if (! class_exists ( "PostmanWordpressUtil" )) {
	// global function specific to the WordPress implementation of Postman
	class PostmanWordpressUtil {

		function runTest() {
			test1 ();
			test2 ();
			test3 ();
			test4 ();
			test5 ();
		}
		
		/**
		 * from http://codex.wordpress.org/Function_Reference/wp_mail
		 */
		function test1() {
			wp_mail ( 'wordpress@wordpress.org', 'a subject', 'a message' );
		}
		
		/**
		 * from http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_from_name
		 *
		 * @return string
		 */
		function test2() {
			add_filter ( 'wp_mail_from_name', 'custom_wp_mail_from_name' );
			function custom_wp_mail_from_name($original_email_from) {
				return 'WordPress Email System';
			}
		}
		
		/**
		 * from http://codex.wordpress.org/Function_Reference/wp_mail
		 */
		function test3() {
			$attachments = array (
					WP_CONTENT_DIR . '/uploads/file_to_attach.zip' 
			);
			$headers = 'From: My Name <myname@example.com>' . "\r\n";
			wp_mail ( 'test@example.org', 'subject', 'message', $headers, $attachments );
		}
		
		/**
		 * from
		 */
		function test4() {
			$multiple_to_recipients = array (
					'recipient1@example.com',
					'recipient2@foo.example.com' 
			);
			
			add_filter ( 'wp_mail_content_type', 'set_html_content_type' );
			
			wp_mail ( $multiple_to_recipients, 'The subject', '<p>The <em>HTML</em> message</p>' );
			
			// Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
			remove_filter ( 'wp_mail_content_type', 'set_html_content_type' );
			function set_html_content_type() {
				return 'text/html';
			}
		}
		
		/**
		 */
		function test5() {
			// Example using the array form of $headers
			// assumes $to, $subject, $message have already been defined earlier...
			$headers [] = 'From: Me Myself <me@example.net>';
			$headers [] = 'Cc: John Q Codex <jqc@wordpress.org>';
			$headers [] = 'Cc: iluvwp@wordpress.org'; // note you can just use a simple email address
			
			wp_mail ( $to, $subject, $message, $headers );
		}
	}
}