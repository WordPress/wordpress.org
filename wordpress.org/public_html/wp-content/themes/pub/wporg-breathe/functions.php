<?php
namespace WordPressdotorg\Make\Breathe;

/**
 * Sets up theme defaults.
 */
function after_setup_theme() {
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'title-tag' );

	remove_theme_support( 'custom-header' );
	remove_theme_support( 'custom-background' );

	remove_action( 'customize_register', 'breathe_customize_register' );
	remove_action( 'customize_preview_init', 'breathe_customize_preview_js' );
	remove_filter( 'wp_head', 'breathe_color_styles' );
	add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );

	add_filter( 'o2_filtered_content', __NAMESPACE__ . '\append_last_updated', 10, 2 );

	// Customize Code Syntax Block syntax highlighting theme to use styles from theme.
	// Based on the plugin's docs, this should be default behavior but isn't.
	add_filter( 'mkaz_prism_css_path', function() {
		return '/assets/prism/prism.css';
	} );

	// Use the front-end style.css as the editor styles, not perfect, but looks better than without.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\after_setup_theme', 11 );

/**
 * Add postMessage support for site title and description in the customizer.
 *
 * @param WP_Customize_Manager $wp_customize The customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial( 'blogname', [
			'selector'            => '.site-title a',
			'container_inclusive' => false,
			'render_callback'     => __NAMESPACE__ . '\customize_partial_blogname',
		] );
	}
}

/**
 * noindex certain archives.
 */
function no_robots( $noindex ) {
	if ( is_tax( 'mentions' ) ) {
		$noindex = true;
	}

	if ( get_query_var( 'o2_recent_comments' ) ) {
		$noindex = true;
	}


	// This is used by https://github.com/WordPress/phpunit-test-reporter/blob/master/src/class-display.php on the test reporter page
	if ( isset( $_GET['rpage'] ) ) {
		$noindex = true;
	}

	return $noindex;
}
add_filter( 'wporg_noindex_request', __NAMESPACE__ . '\no_robots' );

/**
 * Renders the site title for the selective refresh partial.
 */
function customize_partial_blogname() {
	bloginfo( 'name' );
}

function styles() {
	wp_dequeue_style( 'breathe-style' );
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );

	wp_enqueue_style( 'wporg-breathe', get_stylesheet_uri(), array( 'p2-breathe' ), filemtime( __DIR__ . '/style.css' ) );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\styles', 11 );

function scripts() {
	wp_enqueue_script( 'wporg-breathe-chapters', get_stylesheet_directory_uri() . '/js/chapters.js', array( 'jquery' ), '20200127' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts', 11 );

function inline_scripts() {
	$current_site = get_site();
	?>
	<script type="text/javascript">
		var el = document.getElementById( 'make-welcome-toggle' );
		if ( el ) {
			el.addEventListener( 'click', function( e ) {
				var $welcome = jQuery( '.make-welcome' ),
					$toggle  = $welcome.find( '#make-welcome-toggle'),
					$content = $welcome.find( '#make-welcome-content'),
					isHide   = ! $content.is( ':hidden' );

				// Toggle it
				$toggle.text( $toggle.data( isHide ? 'show' : 'hide' ) );
				$welcome.get( 0 ).classList.toggle( 'collapsed', isHide );
				$content.slideToggle();
				$welcome.find('.post-edit-link' ).toggle( ! isHide );

				// Remember it
				document.cookie = $content.data( 'cookie' ) + '=' +
					( isHide ? $content.data( 'hash' ) : '' ) +
					'; expires=Fri, 31 Dec 9999 23:59:59 GMT' +
					'; domain=<?php echo esc_js( $current_site->domain ); ?>' +
					'; path=<?php echo esc_js( $current_site->path ); ?>';
			} );
		}
	</script>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\inline_scripts' );

function welcome_box() {
	$welcome      = get_page_by_path( 'welcome' );
	$cookie       = 'welcome-' . get_current_blog_id();
	$hash         = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';
	$content_hash = $welcome ? md5( $welcome->post_content ) : '';

	if ( ! $welcome ) {
		return;
	}

	$columns = preg_split( '|<hr\s*/?>|', $welcome->post_content );
	if ( count( $columns ) === 2 ) {
		$welcome->post_content = "<div class='content-area'>\n\n{$columns[0]}</div><div class='widget-area'>\n\n{$columns[1]}</div>";
	}

	setup_postdata( $welcome );
	$GLOBALS['post'] = $welcome; // setup_postdata() doesn't do this for us.

	// Disable Jetpack sharing buttons
	add_filter( 'sharing_show', '__return_false' );
	// Disable o2 showing the post inline
	add_filter( 'o2_post_fragment', '__return_empty_array' );
	?>
	<div class="make-welcome">
		<div class="entry-meta">
			<?php edit_post_link( __( 'Edit', 'wporg' ), '', '', $welcome->ID, 'post-edit-link make-welcome-edit-post-link' ); ?>
			<button
				type="button"
				id="make-welcome-toggle"
				data-show="<?php esc_attr_e( 'Show welcome box', 'wporg' ); ?>"
				data-hide="<?php esc_attr_e( 'Hide welcome box', 'wporg' ); ?>"
			><?php _e( 'Hide welcome box', 'wporg' ); ?></button>
		</div>
		<div class="entry-content clear" id="make-welcome-content" data-cookie="<?php echo $cookie; ?>" data-hash="<?php echo $content_hash; ?>">
			<script type="text/javascript">
				var elContent = document.getElementById( 'make-welcome-content' );
				if ( elContent ) {
					if ( -1 !== document.cookie.indexOf( elContent.dataset.cookie + '=' + elContent.dataset.hash ) ) {
						var elToggle = document.getElementById( 'make-welcome-toggle' ),
							elEditLink = document.getElementsByClassName( 'make-welcome-edit-post-link' ),
							elContainer = document.querySelector( '.make-welcome' );

						// It's hidden, hide it ASAP.
						elContent.className += " hidden";
						elToggle.innerText = elToggle.dataset.show;

						// Add class to welcome box container indicating collapsed state.
						elContainer && elContainer.classList.add( 'collapsed' );

						if ( elEditLink.length ) {
							elEditLink[0].className += " hidden";
						}
					}
				}
			</script>
			<?php the_content(); ?>
		</div>
	</div>
	<?php
	remove_filter( 'sharing_show', '__return_false' );
	remove_filter( 'o2_post_fragment', '__return_empty_array' );

	$GLOBALS['post'] = false; // wp_reset_postdata() may not do this.
	wp_reset_postdata();
}
add_action( 'wporg_breathe_after_header', __NAMESPACE__ . '\welcome_box' );

function javascript_notice() {
	?>
	<noscript class="js-disabled-notice">
		<?php _e( 'Please enable JavaScript to view this page properly.', 'wporg' ); ?>
	</noscript>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\javascript_notice' );

/**
 * Adds each site's slug to the body class, so that CSS rules can target specific sites.
 *
 * @param array $classes Array of CSS classes.
 * @return array Array of CSS classes.
 */
function add_site_slug_to_body_class( $classes ) {
	$current_site = get_site( get_current_blog_id() );

	$classes[] = 'wporg-make';
	if ( $current_site ) {
		$classes[] = 'make-' . trim( $current_site->path, '/' );
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\add_site_slug_to_body_class' );

/**
 * Defines `link_before` and `link_after` to make icon items accessible for screen readers.
 *
 * @param object  $args  An object of wp_nav_menu() arguments.
 * @param WP_Post $item  Menu item data object.
 * @return object An object of wp_nav_menu() arguments.
 */
function add_screen_reader_text_for_icon_menu_items( $args, $item ) {
	if ( in_array( 'icon', $item->classes, true ) ) {
		$args->link_before = '<span class="screen-reader-text">';
		$args->link_after  = '</span>';
	}

	return $args;
}
add_filter( 'nav_menu_item_args', __NAMESPACE__ . '\add_screen_reader_text_for_icon_menu_items', 10, 2 );

/**
 * Disables Jetpack Mentions on any handbook page or comment.
 *
 * More precisely, this prevents the linked mentions from being shown. A more
 * involved approach (to include clearing meta-cached data) would be needed to
 * more efficiently prevent mentions from being looked for in the first place.
 *
 * @param string $linked  The linked mention.
 * @param string $mention The term being mentioned.
 * @return string
 */
function disable_mentions_for_handbook( $linked, $mention ) {
	if ( function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() && ! is_single( 'credits' ) ) {
		return '@' . $mention;
	}

	return $linked;
}
add_filter( 'jetpack_mentions_linked_mention', __NAMESPACE__ . '\disable_mentions_for_handbook', 10, 2 );

/**
 * More contextual link title for post authors.
 *
 * @param array    $bootstrap_model O2 user model.
 * @param \WP_User $user_data       User data.
 *
 * @return array
 */
function user_model( $bootstrap_model, $user_data ) {
	/* translators: 1: User display_name; 2: User nice_name */
	$bootstrap_model['urlTitle'] = sprintf( __( 'Profile of %1$s (%2$s)', 'wporg' ), $user_data->display_name, '@' . $user_data->user_nicename );

	return $bootstrap_model;
}
add_filter( 'o2_user_model', __NAMESPACE__ . '\user_model', 10, 2 );

/**
 * Fixes bug in (or at least in using) SyntaxHighlighter code shortcodes that
 * causes double-encoding of `>` and '&' characters.
 *
 * @param string $content The text being handled as code.
 * @return string
 */
function fix_code_entity_encoding( $content ) {
	return str_replace( [ '&amp;gt;', '&amp;amp;' ], [ '&gt;', '&amp;' ], $content );
}
add_filter( 'syntaxhighlighter_htmlresult', __NAMESPACE__ . '\fix_code_entity_encoding', 20 );

/**
 * Appends a 'Last updated' to handbook pages.
 *
 * @param string $content Content of the current post.
 * @return Content of the current post.
 */
function append_last_updated( $content, $post ) {
	if ( ! function_exists( 'wporg_is_handbook' ) || ! wporg_is_handbook( $post->post_type ) ) {
		return $content;
	}

	$content .= sprintf(
		/* translators: %s: Date of last update. */
		'<p class="handbook-last-updated">' . __( 'Last updated: %s', 'wporg' ) . '</p>',
		sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		)
	);

	return $content;
}

/**
 * Noindex some requests:
 *  - all o2 taxonomy pages, rather than the default of only noindexing archives with less than 3 posts
 *  - Posts/pages/etc with less than 100char.
 */
function maybe_noindex( $noindex ) {
	// Noindex all o2 taxonomy pages.
	if ( is_tax() || is_tag() || is_category() ) {
		$noindex = true;
	}

	// Noindex empty/short pages
	if ( is_singular() && strlen( get_the_content() ) < 100 ) {
		$noindex = true;
	}

	return $noindex;
}
add_filter( 'wporg_noindex_request', __NAMESPACE__ . '\maybe_noindex' );

/**
 * Outputs team icons represented via SVG images using the `svg` tag (as opposed to via CSS).
 *
 * While the SVG could easily, and more cleanly, be added via CSS, doing so would not allow the SVGs
 * to otherwise inherit the link colors (such as on :hover). If the theme changes to move the team
 * icon outside of the link, or if matching the link color is no longer required, then the SVG
 * definitions can be moved to CSS.
 *
 * Currently handles the following teams:
 * - Core Performance
 * - Openverse
 *
 * Note: Defining a team's icon in this way also requires adjusting the site's styles to not expect
 * a ::before content of a dashicon font character. (Search style.css for: Adjustments for teams with SVG icons.)
 */
function add_svg_icon_to_site_name() {
	$site = get_site();

	if ( ! $site ) {
		return;
	}

	$svg = [];

	if ( '/openverse/' === $site->path ) :
		$svg = [
			'viewbox' => '0 16 200 200',
			'paths'   => [
				'M142.044 93.023c16.159 0 29.259-13.213 29.259-29.512 0-16.298-13.1-29.511-29.259-29.511s-29.259 13.213-29.259 29.511c0 16.299 13.1 29.512 29.259 29.512ZM28 63.511c0 16.24 12.994 29.512 29.074 29.512V34C40.994 34 28 47.19 28 63.511ZM70.392 63.511c0 16.24 12.994 29.512 29.074 29.512V34c-15.998 0-29.074 13.19-29.074 29.511ZM142.044 165.975c16.159 0 29.259-13.213 29.259-29.512 0-16.298-13.1-29.511-29.259-29.511s-29.259 13.213-29.259 29.511c0 16.299 13.1 29.512 29.259 29.512ZM70.392 136.414c0 16.257 12.994 29.544 29.074 29.544v-59.006c-15.999 0-29.074 13.204-29.074 29.462ZM28 136.414c0 16.34 12.994 29.544 29.074 29.544v-59.006c-16.08 0-29.074 13.204-29.074 29.462Z',
			],
		];

	elseif ( '/performance/' === $site->path ) :
		$svg = [
			'viewbox' => '0 8 94 94',
			'paths'   => [
				'M39.21 20.85h-11.69c-1.38 0-2.5 1.12-2.5 2.5v11.69c0 1.38 1.12 2.5 2.5 2.5h11.69c1.38 0 2.5-1.12 2.5-2.5v-11.69c0-1.38-1.12-2.5-2.5-2.5z',
				'M41.71,58.96v11.69c0,.66-.26,1.3-.73,1.77-.47,.47-1.11,.73-1.77,.73h-11.69c-.66,0-1.3-.26-1.77-.73-.47-.47-.73-1.11-.73-1.77v-21.37c0-.4,.1-.79,.28-1.14,.03-.06,.07-.12,.1-.18,.21-.33,.49-.61,.83-.82l11.67-7.04c.44-.27,.95-.39,1.47-.36,.51,.03,1,.23,1.4,.55,.26,.21,.47,.46,.63,.75,.16,.29,.26,.61,.29,.94,.02,.11,.02,.22,.02,.34v5.38s0,.07,0,.11v11.08s0,.04,0,.07Z',
				'M68.98,30.23v16.84c0,.33-.06,.65-.19,.96-.13,.3-.31,.58-.54,.81l-6.88,6.88c-.23,.23-.51,.42-.81,.54-.3,.13-.63,.19-.96,.19h-13.15c-.66,0-1.3-.26-1.77-.73-.47-.47-.73-1.11-.73-1.77v-11.69c0-.66,.26-1.3,.73-1.77,.47-.47,1.11-.73,1.77-.73h13.08s1.11,0,1.11-1.11-1.11-1.11-1.11-1.11h-13.08c-.66,0-1.3-.26-1.77-.73s-.73-1.11-.73-1.77v-11.69c0-.66,.26-1.3,.73-1.77,.47-.47,1.11-.73,1.77-.73h13.15c.33,0,.65,.06,.96,.19,.3,.13,.58,.31,.81,.54l6.88,6.88c.23,.23,.42,.51,.54,.81,.13,.3,.19,.63,.19,.96Z',
			],
		];

	endif;

	if ( empty( $svg['viewbox'] ) || empty( $svg['paths'] ) ) {
		return;
	}

	printf( '<svg aria-hidden="true" role="img" viewBox="%s" xmlns="http://www.w3.org/2000/svg">' . "\n", esc_attr( $svg['viewbox'] ) );

	foreach ( $svg['paths'] as $path ) {
		printf( "\t" . '<path d="%s" stroke="currentColor" fill="currentColor"/>' . "\n", esc_attr( $path ) );
	}

	echo "</svg>\n";
}
add_action( 'wporg_breathe_before_name', __NAMESPACE__ . '\add_svg_icon_to_site_name' );

/**
 * Register translations for plugins without their own GlotPress project.
 */
// wp-content/plugins/wporg-o2-posting-access/wporg-o2-posting-access.php
/* translators: %s: Post title */
__( 'Pending Review: %s', 'wporg' );
__( 'Submit for review', 'wporg' );
_n_noop( '%s post awaiting review', '%s posts awaiting review', 'wporg' );
