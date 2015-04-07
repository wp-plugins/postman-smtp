<?php
include_once (ABSPATH . 'wp-admin/includes/plugin.php');
if (! class_exists ( 'PostmanEmailLog' )) {
	class PostmanEmailLog {
		private $logger;
		function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'init', array (
					$this,
					'create_post_type' 
			) );
		}
		function create_post_type() {
			register_post_type ( 'postman_sent_mail', array (
					'labels' => array (
					),
					'show_in_nav_menus' => true,
					'show_ui' => true,
					'has_archive' => true 
			) );
			$this->logger->debug ( 'Created custom post type \'postman_email\'' );
		}
	}
	
	// WordPress.org
	
	// Custom Post Type Screens
	// When a custom post type is created like in the example above, it gets a new top-level administration menu to create and manage posts of that new post type. New administration screens will be accessible from that menu, such as post edit screen where you will have a full post editor and everything that comes along with it according to what features you set that your custom post type should support by the supports argument of the register_post_type() function. You can customize the screens with several action and filter hooks, see this Custom Post Type Snippets post by Yoast for an explanation and code examples on how to change a custom post type overview screen.
	
	// URLs
	// A custom post type will also get its own slug within the site URL structure. In the above example, a post of this product custom post type can be displayed at http://example.com/acme_product/%product_name% where acme_product is the slug of your custom post type and %product_name% is the slug of your particular product, so a permalink could be e.g. http://example.com/product/foobrozinator. You can see this permalink appear on the edit post screen for your custom post type, just like with default post types.
	
	// URLs of Namespaced Custom Post Types Identifiers
	
	// When you namespace a custom post type identifier and still want to use a clean URL structure, you need to set the rewrite argument of the register_post_type() function. For example, assuming the ACME Widgets example from above:
	
	// add_action( 'init', 'create_posttype' );
	// function create_posttype() {
	// register_post_type( 'acme_product',
	// array(
	// 'labels' => array(
	// 'name' => __( 'Products' ),
	// 'singular_name' => __( 'Product' )
	// ),
	// 'public' => true,
	// 'has_archive' => true,
	// 'rewrite' => array('slug' => 'products'),
	// )
	// );
	// }
	// The above will result in post URLs in the form http://example.com/products/%product_name%. Note that we used a plural word for the slug here which is a form that some people prefer because it implies a more logical URL for a page that embeds a list of products, i.e. http://example.com/products/.
	
	// Also note that using a generic slug like products here can potentially conflict with other plugins or themes that use the same slug, but most people would dislike longer and more obscure URLs like http://example.com/acme_products/foobrozinator and resolving the URL conflict between two plugins is easier simply because the URL structure is not stored persistently in each post's database record the same way custom post type identifiers are stored.
	
	// Custom Post Type Templates
	// The WordPress theme system supports custom templates for custom post types too. A custom template for a single display of posts belonging to a custom post type is supported since WordPress Version 3.0 and the support for a custom template for an archive display was added in Version 3.1.
	
	// Note: In some cases, the permalink structure must be updated in order for the new template files to be accessed when viewing posts of a custom post type. To do this, go to Administration Panels > Settings > Permalinks, change the permalink structure to a different structure, save the changes, and change it back to the desired structure.
	
	// Template Files
	
	// In the same way single posts and their archives can be displayed using the single.php and archive.php template files, respectively,
	
	// single posts of a custom post type will use single-{post_type}.php
	// and their archives will use archive-{post_type}.php
	// and if you don't have this post type archive page you can pass BLOG_URL?post_type={post_type}
	// where {post_type} is the $post_type argument of the register_post_type() function.
	
	// So for the above example, you could create single-acme_product.php and archive-acme_product.php template files for single product posts and their archives.
	
	// Alternatively, you can use the is_post_type_archive() function in any template file to check if the query shows an archive page of a given post types(s), and the post_type_archive_title() to display the post type title.
	
	// Querying by Post Type
	// In any template file of the WordPress theme system, you can also create new queries to display posts from a specific post type. This is done via the post_type argument of the WP_Query object.
	
	// Example:
	
	// $args = array( 'post_type' => 'product', 'posts_per_page' => 10 );
	// $loop = new WP_Query( $args );
	// while ( $loop->have_posts() ) : $loop->the_post();
	// the_title();
	// echo '<div class="entry-content">';
	// the_content();
	// echo '</div>';
	// endwhile;
	// This simply loops through the latest 10 product posts and displays the title and content of them one by one.
	
	// Custom Post Types in the Main Query
	// Registering a custom post type does not mean it gets added to the main query automatically.
	
	// If you want your custom post type posts to show up on standard archives or include them on your home page mixed up with other post types, use the pre_get_posts action hook.
	
	// // Show posts of 'post', 'page' and 'movie' post types on home page
	// add_action( 'pre_get_posts', 'add_my_post_types_to_query' );
	
	// function add_my_post_types_to_query( $query ) {
	// if ( is_home() && $query->is_main_query() )
	// $query->set( 'post_type', array( 'post', 'page', 'movie' ) );
	// return $query;
	// }
	// Function Reference
	// Post Types: register_post_type(), add_post_type_support(), remove_post_type_support(), post_type_supports(), post_type_exists(), set_post_type(), get_post_type(), get_post_types(), get_post_type_object(), get_post_type_capabilities(), get_post_type_labels(), is_post_type_hierarchical(), is_post_type_archive(), post_type_archive_title()
	
	// More Information
	// Custom post type standards
	// WordPress Post Type Generator
	// Showing custom post types on your home/blog page
	// Podcast Presentation: WordPress Custom Post Types Both Slides and Audio on Custom Post Types
	// Custom Post Types in WordPress 3.0
	// Extending Custom Post Types in WordPress 3.0
	// Change Order for Custom Post Types in WordPress 3.0 and up
	// Custom Post Type Example
	// Custom Post Type Snippets
	// Category:
	// Advanced Topics
	// Home Page
	// WordPress Lessons
	// Getting Started
	// Working with WordPress
	// Design and Layout
	// Advanced Topics
	// Troubleshooting
	// Developer Docs
	// About WordPress
	// Codex Resources
	// Community portal
	// Current events
	// Recent changes
	// Random page
	// Help
	// About
	// Blog
	// Hosting
	// Jobs
	// Support
	// Developers
	// Get Involved
	// Learn
	// Showcase
	// Plugins
	// Themes
	// Ideas
	// WordCamp
	// WordPress.TV
	// BuddyPress
	// bbPress
	// WordPress.com
	// Matt
	// Privacy
	// License / GPLv2
	
	// Code is Poetry
}