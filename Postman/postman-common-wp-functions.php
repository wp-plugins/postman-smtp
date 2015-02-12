<?php

define ( 'POSTMAN_HOME_PAGE_RELATIVE_URL', 'options-general.php?page=postman' );
define ( 'POSTMAN_HOME_PAGE_ABSOLUTE_URL', admin_url ( POSTMAN_HOME_PAGE_RELATIVE_URL ) );

if (! function_exists ( 'postmanRedirect' )) {
	/**
	 * A fa�ade function that handles redirects.
	 * Inside WordPress we can use wp_redirect(). Outside WordPress, not so much. **Load it before postman-core.php**
	 *
	 * @param unknown $url        	
	 */
	function postmanRedirect($url) {
		$logger = new PostmanLogger ( 'postman.php' );
		$logger->debug ( sprintf ( "Redirecting to '%s'", $url ) );
		wp_redirect ( $url );
		exit ();
	}
}

if (! function_exists ( 'postmanHttpTransport' )) {
	/**
	 * Makes the outgoing HTTP requests
	 *
	 * @param unknown $url        	
	 * @param unknown $args        	
	 */
	function postmanHttpTransport($url, $parameters) {
		$args = array (
				'timeout' => PostmanOptions::getInstance()->getConnectionTimeout(),
				'body' => $parameters 
		);
		$logger = new PostmanLogger ( 'PostmanHttpTransport' );
		$logger->debug ( sprintf ( 'Posting to %s', $url) );
		$response = wp_remote_post ( $url, $args );
		
		// pre-process the response
		if (is_wp_error ( $response )) {
			$logger->error ( $response->get_error_message () );
			throw new Exception ( 'Error executing wp_remote_post: ' . $response->get_error_message () );
		} else {
			$theBody = wp_remote_retrieve_body ( $response );
			return $theBody;
		}
	}
}

require_once 'postman-common-functions.php';
?>