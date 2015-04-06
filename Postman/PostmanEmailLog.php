<?php
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(!class_exists('PostmanEmailLog')) {
	class PostmanEmailLog {
		
	}


// 	WordPress.org
	
// 	Showcase
// 	Themes
// 	Plugins
// 	Mobile
// 	Support
// 	Get Involved
// 	About
// 	Blog
// 	Hosting
// 	Download WordPress
// 	Codex
// 	Codex tools: Log in
// 	Attention Interested in functions, hooks, classes, or methods? Check out the new WordPress Code Reference!
// 	Post Types
	
// 	Languages: English • 日本語 • Português do Brasil • Nederlands • Slovenčina • 中文(简体) • (Add your language)
	
// 	WordPress can hold and display many different types of content. A single item of such a content is generally called a post, although post is also a specific post type. Internally, all the post types are stored in the same place, in the wp_posts database table, but are differentiated by a column called post_type.
	
// 	WordPress 3.0 gives you the capability to add your own custom post types and to use them in different ways.
	
// 	Contents
	
// 	1 Default Post Types
// 	1.1 Post
// 	1.2 Page
// 	1.3 Attachment
// 	1.4 Revision
// 	1.5 Navigation Menu
// 	2 Custom Post Types
// 	2.1 A word about custom post types as a plugin
// 	2.2 Naming Best Practices
// 	2.3 Reserved Post Type Identifiers
// 	2.4 Custom Post Type Screens
// 	2.5 URLs
// 	2.5.1 URLs of Namespaced Custom Post Types Identifiers
// 	2.6 Custom Post Type Templates
// 	2.6.1 Template Files
// 	2.7 Querying by Post Type
// 	2.8 Custom Post Types in the Main Query
// 	2.9 Function Reference
// 	3 More Information
// 	Default Post Types
// 	There are five post types that are readily available to users or internally used by the WordPress installation by default :
	
// 		Post (Post Type: 'post')
// 		Page (Post Type: 'page')
// 		Attachment (Post Type: 'attachment')
// 		Revision (Post Type: 'revision')
// 		Navigation menu (Post Type: 'nav_menu_item')
// 		Post
// 		Post in WordPress is a post type that is typical for and most used by blogs. Posts are normally displayed in a blog in reverse sequential order by time (newest posts first). Posts are also used for creating the feeds.
	
// 		Page
// 		Page in WordPress is like post, but it lives outside the normal time-based listings of posts. Pages can use different page templates to display them. Pages can also be organized in a hierarchical structure, with pages being parents to other pages, but they normally cannot be assigned categories and tags. If permalinks are enabled, the permalink of a page is always composed solely of the main site URL and the user-friendly and URL-valid names (also referred to as slug) of the page and its parents if they exist. See the Pages article for more information about the differences.
	
// 		Attachment
// 		Attachment is a special post that holds information about a file uploaded through the WordPress media upload system, such as its description and name. For images, this is also linked to metadata information, stored in the wp_postmeta table, about the size of the images, the thumbnails generated from the images, the location of the image files, the HTML alt text, and even information obtained from EXIF data embedded in the images.
	
// 		Revision
// 		Revision is used to hold a draft post as well as any past revisions of a published post. Revisions are basically identical to the published post which they belong to, but have that post set as their parent using the post_parent column of the wp_posts table.
	
// 		Navigation Menu
// 		Navigation Menu is a type that holds information about a single item in the WordPress navigation menu system. These are the first examples of entries in the wp_posts table to be used for something other than an otherwise displayable content on the blog.
	
// 		Custom Post Types
// 		Custom post types are new post types you can create. A custom post type can be added to WordPress via the register_post_type() function. This function allows you to define a new post type by its labels, supported features, availability and other specifics.
	
// 		Note that you must call register_post_type() before the admin_menu and after the after_setup_theme action hooks. A good hook to use is the init hook.
	
// 		Here's a basic example of adding a custom post type:
	
// add_action( 'init', 'create_post_type' );
// function create_post_type() {
//   register_post_type( 'acme_product',
//     array(
//       'labels' => array(
//         'name' => __( 'Products' ),
//         'singular_name' => __( 'Product' )
//       ),
//       'public' => true,
//       'has_archive' => true,
//     )
//   );
// }
// This creates a post type named Product identified as acme_product. The register_post_type() function receives two major arguments. The first one is labels which define the name of the post type in both plural and singular forms. The second one is public which is a predefined flag to show the post type on the administration screens and to make it show up in the site content itself, if it's queried for.
	
// 	There are many more arguments you can pass to the register_post_type() function, to do things like set up hierarchy (to behave like pages), show the new post type in searches, change the URLs of the new posts, and hide or show meta boxes in the post edit screen. These parameters are optional, and you can use them to configure your post type on a detailed level.
	
// 	A word about custom post types as a plugin
// 	In order to avoid breaking a site on theme switching, try to define custom post types as a plugin, or, better as a Must Use Plugins. This way you won't force users into using a certain theme.
	
// Naming Best Practices
// While it is convenient to use a simple custom post type identifier like product which is consistent with the identifiers of the default post types (post, page, revision, attachment and nav_menu_item), it is better if you prefix your identifier with a short namespace that identifies your plugin, theme or website that implements the custom post type.
	
// For example:
	
// acme_product or aw_product for products post type used by a hypothetical ACMEWidgets.com website.
// eightfold_product or eft_product for products post type provided by a hypothetical EightFold theme.
// ai1m_product for products post type provided by a hypothetical All-in-One Merchant plugin.
// Without namespacing your custom post type identifier, other post types in your website will more likely conflict with custom post types defined in a theme you fall in love with later or a plugin you realize that you absolutely need to use. Or if you are developing custom post types or themes there is a much greater chance your plugin or theme will conflict with custom post types defined in other plugins or themes and/or custom post types defined in your prospective user's website. Namespacing your custom post type identifier will not guarantee against conflicts but will certainly minimize their likelihood.
	
// 	Do pay close attention to not having your custom post type identifier exceed 20 characters though, as the post_type column in the database is currently a VARCHAR field of that length.
	
// 	Reserved Post Type Identifiers
// 	Although the core development team has yet to make a final decision on this, it has been proposed on the wp-hackers mailing list that future core post type identifiers will be namespaced with wp_, i.e. if the core team decides to add an event post type then according to this suggestion they would use the wp_event identifier. Even though this has not been finalized, it will be a good idea to avoid any custom post types whose identifier begins with wp_.
	
// 	Custom Post Type Screens
// 	When a custom post type is created like in the example above, it gets a new top-level administration menu to create and manage posts of that new post type. New administration screens will be accessible from that menu, such as post edit screen where you will have a full post editor and everything that comes along with it according to what features you set that your custom post type should support by the supports argument of the register_post_type() function. You can customize the screens with several action and filter hooks, see this Custom Post Type Snippets post by Yoast for an explanation and code examples on how to change a custom post type overview screen.
	
// 	URLs
// 	A custom post type will also get its own slug within the site URL structure. In the above example, a post of this product custom post type can be displayed at http://example.com/acme_product/%product_name% where acme_product is the slug of your custom post type and %product_name% is the slug of your particular product, so a permalink could be e.g. http://example.com/product/foobrozinator. You can see this permalink appear on the edit post screen for your custom post type, just like with default post types.
	
// 	URLs of Namespaced Custom Post Types Identifiers
	
// 	When you namespace a custom post type identifier and still want to use a clean URL structure, you need to set the rewrite argument of the register_post_type() function. For example, assuming the ACME Widgets example from above:
	
// 	add_action( 'init', 'create_posttype' );
// 	function create_posttype() {
// 		register_post_type( 'acme_product',
// 		array(
// 		'labels' => array(
// 		'name' => __( 'Products' ),
// 		'singular_name' => __( 'Product' )
// 		),
// 		'public' => true,
// 		'has_archive' => true,
// 		'rewrite' => array('slug' => 'products'),
// 		)
// 		);
// 	}
// 	The above will result in post URLs in the form http://example.com/products/%product_name%. Note that we used a plural word for the slug here which is a form that some people prefer because it implies a more logical URL for a page that embeds a list of products, i.e. http://example.com/products/.
	
// 	Also note that using a generic slug like products here can potentially conflict with other plugins or themes that use the same slug, but most people would dislike longer and more obscure URLs like http://example.com/acme_products/foobrozinator and resolving the URL conflict between two plugins is easier simply because the URL structure is not stored persistently in each post's database record the same way custom post type identifiers are stored.
	
// 	Custom Post Type Templates
// 	The WordPress theme system supports custom templates for custom post types too. A custom template for a single display of posts belonging to a custom post type is supported since WordPress Version 3.0 and the support for a custom template for an archive display was added in Version 3.1.
	
// 	Note: In some cases, the permalink structure must be updated in order for the new template files to be accessed when viewing posts of a custom post type. To do this, go to Administration Panels > Settings > Permalinks, change the permalink structure to a different structure, save the changes, and change it back to the desired structure.
	
// 	Template Files
	
// 	In the same way single posts and their archives can be displayed using the single.php and archive.php template files, respectively,
	
// 	single posts of a custom post type will use single-{post_type}.php
// 	and their archives will use archive-{post_type}.php
// 	and if you don't have this post type archive page you can pass BLOG_URL?post_type={post_type}
// where {post_type} is the $post_type argument of the register_post_type() function.
	
// So for the above example, you could create single-acme_product.php and archive-acme_product.php template files for single product posts and their archives.
	
// Alternatively, you can use the is_post_type_archive() function in any template file to check if the query shows an archive page of a given post types(s), and the post_type_archive_title() to display the post type title.
	
// Querying by Post Type
// In any template file of the WordPress theme system, you can also create new queries to display posts from a specific post type. This is done via the post_type argument of the WP_Query object.
	
// Example:
	
// $args = array( 'post_type' => 'product', 'posts_per_page' => 10 );
// $loop = new WP_Query( $args );
// while ( $loop->have_posts() ) : $loop->the_post();
//   the_title();
//   echo '<div class="entry-content">';
//   the_content();
//   echo '</div>';
// endwhile;
// This simply loops through the latest 10 product posts and displays the title and content of them one by one.
	
// Custom Post Types in the Main Query
// Registering a custom post type does not mean it gets added to the main query automatically.
	
// If you want your custom post type posts to show up on standard archives or include them on your home page mixed up with other post types, use the pre_get_posts action hook.
	
// // Show posts of 'post', 'page' and 'movie' post types on home page
// add_action( 'pre_get_posts', 'add_my_post_types_to_query' );
	
// function add_my_post_types_to_query( $query ) {
//   if ( is_home() && $query->is_main_query() )
//     $query->set( 'post_type', array( 'post', 'page', 'movie' ) );
// 	return $query;
// 	}
// 	Function Reference
// 	Post Types: register_post_type(), add_post_type_support(), remove_post_type_support(), post_type_supports(), post_type_exists(), set_post_type(), get_post_type(), get_post_types(), get_post_type_object(), get_post_type_capabilities(), get_post_type_labels(), is_post_type_hierarchical(), is_post_type_archive(), post_type_archive_title()
	
// 	More Information
// 	Custom post type standards
// 	WordPress Post Type Generator
// 	Showing custom post types on your home/blog page
// 	Podcast Presentation: WordPress Custom Post Types Both Slides and Audio on Custom Post Types
// 	Custom Post Types in WordPress 3.0
// 	Extending Custom Post Types in WordPress 3.0
// 	Change Order for Custom Post Types in WordPress 3.0 and up
// 	Custom Post Type Example
// 	Custom Post Type Snippets
// 	Category:
// 	Advanced Topics
// 	Home Page
// 	WordPress Lessons
// 	Getting Started
// 	Working with WordPress
// 	Design and Layout
// 	Advanced Topics
// 	Troubleshooting
// 	Developer Docs
// 	About WordPress
// 	Codex Resources
// 	Community portal
// 	Current events
// 	Recent changes
// 	Random page
// 	Help
// 	About
// 	Blog
// 	Hosting
// 	Jobs
// 	Support
// 	Developers
// 	Get Involved
// 	Learn
// 	Showcase
// 	Plugins
// 	Themes
// 	Ideas
// 	WordCamp
// 	WordPress.TV
// 	BuddyPress
// 	bbPress
// 	WordPress.com
// 	Matt
// 	Privacy
// 	License / GPLv2
	
	
	
// 	Code is Poetry
	
	
}