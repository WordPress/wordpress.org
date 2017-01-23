<?php
namespace WordPressdotorg\Make\Breathe;

/**
 * Sets up theme defaults.
 */
function after_setup_theme() {
	remove_theme_support( 'custom-header' );
	remove_theme_support( 'custom-background' );

	remove_action( 'customize_register', 'breathe_customize_register' );
	remove_action( 'customize_preview_init', 'breathe_customize_preview_js' );
	remove_filter( 'wp_head', 'breathe_color_styles' );

	add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );
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
 * Renders the site title for the selective refresh partial.
 */
function customize_partial_blogname() {
	bloginfo( 'name' );
}

function styles() {
	wp_dequeue_style( 'breathe-style' );
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );

	// Cacheing hack
	wp_enqueue_style( 'wporg-breathe', get_stylesheet_uri(), array( 'p2-breathe' ), '20170123' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\styles', 11 );

function scripts() {
	wp_enqueue_script( 'wporg-breathe-chapters', get_stylesheet_directory_uri() . '/js/chapters.js', array( 'jquery' ), '20170113' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts', 11 );

function inline_scripts() {
	$current_site = get_site();
	?>
	<script type="text/javascript">
		var el = document.getElementById( 'make-welcome-hide' );
		if ( el ) {
			el.addEventListener( 'click', function( e ) {
				document.cookie = el.dataset.cookie + '=' + el.dataset.hash +
					'; expires=Fri, 31 Dec 9999 23:59:59 GMT' +
					'; domain=<?php echo esc_js( $current_site->domain ); ?>' +
					'; path=<?php echo esc_js( $current_site->path ); ?>';
				jQuery( '.make-welcome' ).slideUp();
			} );
		}
	</script>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\inline_scripts' );

function welcome_box() {
	$welcome = get_page_by_path( 'welcome' );
	$cookie  = 'welcome-' . get_current_blog_id();
	$hash    = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';
	$content_hash = $welcome ? md5( $welcome->post_content ) : '';

	if ( $welcome && ( empty( $hash ) || $content_hash !== $hash ) ) :
		$columns = preg_split( '|<hr\s*/?>|', $welcome->post_content );
		if ( count( $columns ) === 2 ) {
			$welcome->post_content = "<div class='content-area'>\n\n{$columns[0]}</div><div class='widget-area'>\n\n{$columns[1]}</div>";
		}
		setup_postdata( $welcome );

		// Disable Jetpack sharing buttons
		add_filter( 'sharing_show', '__return_false' );
	?>
	<div class="make-welcome">
		<div class="entry-meta">
			<?php edit_post_link( __( 'Edit', 'o2' ), '', '', $welcome->ID ); ?>
			<button type="button" id="make-welcome-hide" class="toggle dashicons dashicons-no" data-hash="<?php echo $content_hash; ?>" data-cookie="<?php echo $cookie; ?>" title="<?php esc_attr_e( 'Hide this message', 'p2-breathe' ); ?>"></button>
		</div>
		<div class="entry-content clear">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
		remove_filter( 'sharing_show', '__return_false' );
		wp_reset_postdata();
	endif;
}
add_action( 'wporg_breathe_after_header', __NAMESPACE__ . '\welcome_box' );

function javascript_notice() {
	?>
	<noscript class="js-disabled-notice">
		<?php _e( 'Please enable JavaScript to view this page properly.', 'o2' ); ?>
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
