<?php
include_once (ABSPATH . 'wp-admin/includes/plugin.php');
if (! class_exists ( 'PostmanHelp' )) {
	class PostmanHelp {
		
		// member variables
		private $logger;
		
		/**
		 * Constructor
		 */
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'init', array (
					$this,
					'init' 
			) );
		}
		public function init() {
			$this->createHelpMenu ();
		}
		
		/**
		 * From http://www.smashingmagazine.com/2012/11/08/complete-guide-custom-post-types/
		 * @return string
		 */
		public function createHelpMenu() {
			function my_contextual_help($contextual_help, $screen_id, $screen) {
				if ('settings_page_postman' == $screen->id) {
					
					$contextual_help = '<h2>Products</h2>
    <p>Products show the details of the items that we sell on the website. You can see a list of them on this page in reverse chronological order - the latest one we added is first.</p>
    <p>You can view/edit the details of each product by clicking on its name, or you can perform bulk actions using the dropdown menu and selecting multiple items.</p>';
				} elseif ('edit-product' == $screen->id) {
					
					$contextual_help = '<h2>Editing products</h2>
    <p>This page allows you to view/modify product details. Please make sure to fill out the available boxes with the appropriate details (product image, price, brand) and <strong>not</strong> add these details to the product description.</p>';
				}
				return $contextual_help;
			}
			add_action ( 'contextual_help', 'my_contextual_help', 10, 3 );
		}
	}
}