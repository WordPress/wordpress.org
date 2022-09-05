<?php
/**
 * The template for displaying the Dashicons resource page
 *
 * Template Name: Dashicons Resource
 *
 * @package wporg-developer
 */

require_once __DIR__ . '/inc/dashicons.php';

wp_enqueue_style(
	'dashicons-page',
	get_template_directory_uri() . '/stylesheets/page-dashicons.css',
	array(),
	filemtime( __DIR__ . '/stylesheets/page-dashicons.css' )
);
wp_enqueue_script(
	'dashicons-page',
	get_template_directory_uri() . '/js/page-dashicons.js',
	array( 'jquery', 'wp-util' ),
	filemtime( __DIR__ . '/js/page-dashicons.js' )
);

get_header(); ?>

	<div id="content-area" <?php body_class( 'dashicons-page' ); ?>>
		<?php while ( have_posts() ) :
			the_post(); ?>
		<main id="main" <?php post_class( 'site-main' ); ?> role="main">

			<div class="details clear">
				<div id="glyph"></div>

				<div class="entry-content">
					<?php the_content(); ?>
				</div><!-- .entry-content -->

				<div class="icon-filter">
					<input placeholder="<?php esc_attr_e( 'Filter&hellip;', 'wporg' ); ?>" name="search" id="search" type="text" value="" maxlength="150">
				</div>

			</div>

			<div id="icons">
				<div id="iconlist">

				<?php
				foreach ( DevHub_Dashicons::get_dashicons() as $group => $group_info ) :
					printf(
						'<h4 id="%s">%s <a href="#%s" class="anchor"><span aria-hidden="true">#</span><span class="screen-reader-text">%s</span></a></h4>' . "\n\n",
						esc_attr( 'icons-' . sanitize_title( $group ) ),
						$group_info['label'],
						esc_attr( 'icons-' . sanitize_title( $group ) ),
						$group_info['label']
					);

					echo "<!-- {$group} -->\n";

					echo "<ul>\n";
					foreach ( $group_info['icons'] as $name => $info ) {
						printf(
							'<li data-keywords="%s" data-code="%s" class="dashicons %s"><span>%s</span></li>' . "\n",
							esc_attr( $info['keywords'] ),
							esc_attr( $info['code'] ),
							esc_attr( $name ),
							$info['label']
						);
					}
					echo "</ul>\n";
				endforeach;
				?>

				</div>
			</div>

			<div id="instructions">

				<h3><?php _e( 'WordPress Usage', 'wporg' ); ?></h3>

				<p>
				<?php  printf(
					__( 'Admin menu items can be added with <code><a href="%1$s">register_post_type()</a></code> and <code><a href="%2$s">add_menu_page()</a></code>, which both have an option to set an icon. To show the current icon, you should pass in %3$s.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/register_post_type/',
					'https://developer.wordpress.org/reference/functions/add_menu_page/',
					'<code>\'dashicons-<span id="wp-class-example">{icon}</span>\'</code>'
				); ?></p>

				<h4><?php _e( 'Examples', 'wporg' ); ?></h4>

				<p>
				<?php printf(
					__( 'In <code><a href="%s">register_post_type()</a></code>, set <code>menu_icon</code> in the arguments array.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/register_post_type/'
				); ?></p>

<pre>&lt;?php
/**
 * Register the Product post type with a Dashicon.
 *
 * @see register_post_type()
 */
function wpdocs_create_post_type() {
	register_post_type( 'acme_product',
		array(
			'labels' => array(
				'name'          => __( 'Products', 'textdomain' ),
				'singular_name' => __( 'Product', 'textdomain' )
			),
			'public'      => true,
			'has_archive' => true,
			'menu_icon'   => 'dashicons-products',
		)
	);
}
add_action( 'init', 'wpdocs_create_post_type', 0 );
</pre>

				<p>
				<?php printf(
					__( 'The function <code><a href="%s">add_menu_page()</a></code> accepts a parameter after the callback function for an icon URL, which can also accept a dashicons class.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/add_menu_page/'
				); ?></p>

<pre>&lt;?php
/**
 * Register a menu page with a Dashicon.
 *
 * @see add_menu_page()
 */
function wpdocs_add_my_custom_menu() {
	// Add an item to the menu.
	add_menu_page(
		__( 'My Page', 'textdomain' ),
		__( 'My Title', 'textdomain' ),
		'manage_options',
		'my-page',
		'my_admin_page_function',
		'dashicons-admin-media'
	);
}</pre>

				<h3><?php _e( 'CSS/HTML Usage', 'wporg' ); ?></h3>

				<p><?php _e( "If you want to use dashicons in the admin outside of the menu, there are two helper classes you can use. These are <code>dashicons-before</code> and <code>dashicons</code>, and they can be thought of as setting up dashicons (since you still need your icon's class, too).", 'wporg' ); ?></p>

				<h4><?php _e( 'Examples', 'wporg' ); ?></h4>

				<p><?php _e( 'Adding an icon to a header, with the <code>dashicons-before</code> class. This can be added right to the element with text.', 'wporg' ); ?></p>

<pre>
&lt;h2 class="dashicons-before dashicons-smiley"&gt;<?php _e( 'A Cheerful Headline', 'wporg' ); ?>&lt;/h2&gt;
</pre>

				<p><?php _e( 'Adding an icon to a header, with the <code>dashicons</code> class. Note that here, you need extra markup specifically for the icon.', 'wporg' ); ?></p>

<pre>
&lt;h2&gt;&lt;span class="dashicons dashicons-smiley"&gt;&lt;/span&gt; <?php _e( 'A Cheerful Headline', 'wporg' ); ?>&lt;/h2&gt;
</pre>

				<h3><?php _e( 'Block Usage', 'wporg' ); ?></h3>

				<p><?php _e( 'The block editor supports use of dashicons as block icons and as its own component.', 'wporg' ); ?></p>

				<h4><?php _e( 'Examples', 'wporg' ); ?></h4>

				<p>
				<?php printf(
					/* translators: %s: URL to Block Editor Handbook for registering a block. */
					__( 'Adding an icon to a block. The <code>registerBlockType</code> function accepts a parameter "icon" which accepts the name of a dashicon. The provided example is truncated. See the <a href="%s">full example</a> in the Block Editor Handbook.', 'wporg' ),
					'https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/#registering-the-block'
				); ?></p>

<pre>
registerBlockType( 'gutenberg-examples/example-01-basic-esnext', {
    apiVersion: 2,
    title: 'Example: Basic (esnext)',
    icon: 'universal-access-alt',
    category: 'design',
    example: {},
    edit() {},
    save() {},
} );
</pre>
				<p>
				<?php printf(
					/* translators: %s: URL to handbook page for Dashicon component. */
					__( 'Using an icon as a component. A dedicated <code>Dashicon</code> component is available. See the <a href="%s">related documentation</a> in the Block Editor Handbook.', 'wporg' ),
					'https://developer.wordpress.org/block-editor/reference-guides/components/dashicon/'
				); ?></p>

<pre>
import { Dashicon } from '@wordpress/components';

const MyDashicon = () =&gt; (
    &lt;div&gt;
        &lt;Dashicon icon="admin-home" /&gt;
        &lt;Dashicon icon="products" /&gt;
        &lt;Dashicon icon="wordpress" /&gt;
    &lt;/div&gt;
);
</pre>

				<h3><?php _e( 'Photoshop Usage', 'wporg' ); ?></h3>

				<p><?php _e( 'Use the .OTF version of the font for Photoshop mockups, the web-font versions won\'t work. For most accurate results, pick the "Sharp" font smoothing.', 'wporg' ); ?></p>

			</div><!-- /#instructions -->

		</main><!-- #main -->

		<!-- Required for the Copy Glyph functionality -->
		<div id="temp" style="display:none;"></div>

		<script type="text/html" id="tmpl-glyphs">
			<div class="dashicons {{data.cssClass}}"></div>
			<div class="info">
				<span><strong>{{data.sectionName}}</strong></span>
				<span class="name"><code>{{data.cssClass}}</code></span>
				<span class="charCode"><code>{{data.charCode}}</code></span>
				<span class="link"><a href='javascript:dashicons.copy( "content: \"\\{{data.attr}}\";", "css" )'><?php _e( 'Copy CSS', 'wporg' ); ?></a></span>
				<span class="link"><a href="javascript:dashicons.copy( '{{data.html}}', 'html' )"><?php _e( 'Copy HTML', 'wporg' ); ?></a></span>
				<span class="link"><a href="javascript:dashicons.copy( '{{data.glyph}}' )"><?php _e( 'Copy Glyph', 'wporg' ); ?></a></span>
			</div>
		</script>

		<?php endwhile; // end of the loop. ?>

	</div><!-- #primary -->

<?php get_footer(); ?>
