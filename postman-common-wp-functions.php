<?php
if (! function_exists ( 'postmanRedirect' )) {
	/**
	 * A faade function that handles redirects.
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

require_once 'postman-common-functions.php';
?>