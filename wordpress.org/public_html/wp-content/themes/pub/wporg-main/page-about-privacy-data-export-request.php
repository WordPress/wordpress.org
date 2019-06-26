<?php
/**
 * Template Name: About -> Privacy -> Data Export Request
 *
 * Page template for displaying the Data Export Request page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/privacy'                      => esc_html_x( 'Privacy Policy', 'Page title', 'wporg' ),
	'about/privacy/cookies'              => esc_html_x( 'Cookie Policy', 'Page title', 'wporg' ),
	'about/privacy/data-export-request'  => esc_html_x( 'Data Export Request', 'Page title', 'wporg' ),
	'about/privacy/data-erasure-request' => esc_html_x( 'Data Erasure Request', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// Pretend we're a direct child of the About page for styling purposes.
add_filter( 'body_class', function( $classes ) {
	$classes[] = 'page-parent-about';

	return $classes;
} );

nocache_headers();

reCAPTCHA\enqueue_script( 'export-request-form' );

$result = privacy_process_request( 'export' );

$email         = $result['email'];
$error_message = $result['error_message'];
$success       = $result['success'];
$nonce_action  = $result['nonce_action'];

if ( ! $email && is_user_logged_in() ) {
	$email = wp_get_current_user()->user_email;
}

/* See inc/page-meta-descriptions.php for the meta description for this page. */

add_action( 'wp_head', function() {
	// TODO: Move to Theme once styled.
	echo '<style>
		form.request-form label {
			display: block;
			color: #555;
			font-size: 0.8em;
		}
		form.request-form input[type="email"] {
			width: 100%;
		}
	</style>';
} );

get_header( 'child-page' );
the_post();
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php esc_html_e( 'WordPress.org respects your privacy and intends to remain transparent about any personal data we store about individuals. Under the General Data Protection Regulation (GDPR), EU citizens and residents are entitled to receive a copy of any personal data we might hold about you.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The following form will allow you to request an export of any data linked to your email address. You will be required to authenticate ownership of that address, and may be asked to provide additional identification or information necessary to verify the request and search our records.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'This export will contain relevant personal or private data stored on WordPress.org, WordPress.net, WordCamp.org, BuddyPress.org, bbPress.org, and other related domains and sites.', 'wporg' ); ?></p>

					<?php if ( $error_message ) : ?>
						<div class="notice notice-error notice-alt">
							<p><?php echo wp_kses_post( $error_message ); ?></p>
						</div>
					<?php elseif ( $success ) : ?>
					<div class="notice notice-success notice-alt">
						<p><?php esc_html_e( 'Please check your email for a confirmation link, and follow the instructions to authenticate your request.', 'wporg' ); ?></p>
					</div>
					<?php endif; ?>

					<form id="export-request-form" class="request-form" method="POST" action="#">
						<label for="email"><?php esc_html_e( 'Email Address', 'wporg' ); ?></label>
						<?php
						printf( '<input type="email" name="email" id="email" placeholder="%1$s" required value="%2$s" />',
							/* translators: Example placeholder email address */
							esc_attr__( 'you@example.com', 'wporg' ),
							esc_attr( $email )
						);
						?>
						<p><?php esc_html_e( 'By submitting this form, you declare that you are the individual owner of the specified email address and its associated accounts; and that all submitted information including any supplemental details necessary to verify your identity are true.', 'wporg' ); ?></p>
						<?php
						reCAPTCHA\display_submit_button( __( 'Accept Declaration and Request Export', 'wporg' ) );
						if ( is_user_logged_in() ) :
							wp_nonce_field( $nonce_action );
						endif;
						?>
					</form>
					<p><?php esc_html_e( 'Please Note: Before we can begin processing your request, we&#8217;ll require that you verify ownership of the email address. If the email address is associated with an account, we&#8217;ll also require you to log in to that account first.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
