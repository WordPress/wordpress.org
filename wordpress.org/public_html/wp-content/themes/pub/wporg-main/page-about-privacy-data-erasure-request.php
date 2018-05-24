<?php
/**
 * Template Name: About -> Privacy -> Data Erasure Request
 *
 * Page template for displaying the Data Erase Request page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/privacy'                      => _x( 'Privacy Policy',       'Page title', 'wporg' ),
	'about/privacy/data-export-request'  => _x( 'Data Export Request',  'Page title', 'wporg' ),
	'about/privacy/data-erasure-request' => _x( 'Data Erasure Request', 'Page title', 'wporg' ),
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

reCAPTCHA\enqueue_script( 'erase-request-form' );

$result = privacy_process_request( 'erase' );

$email         = $result['email'];
$error_message = $result['error_message'];
$success       = $result['success'];
$nonce_action  = $result['nonce_action'];

if ( ! $email && is_user_logged_in() ) {
	$email = wp_get_current_user()->user_email;
}

// See inc/page-meta-descriptions.php for the meta description for this page.

add_action( 'wp_head', function() {
	// TODO: Move to Theme once styled.
	echo '<style>
		p.error {
			border: 1px solid red;
			border-left: 4px solid red;
			padding: 6px;
		}
		p.success {
			border: 1px solid green;
			border-left: 4px solid green;
			padding: 6px;
		}
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
					<p><em>This page is under active development and is not currently enabled. All text is not final and will change.</em></p>

					<p><?php esc_html_e( 'WordPress.org respects your privacy and intends to remain transparent about any personal data we store about individuals. Under the General Data Protection Regulation (GDPR), EU citizens and residents may request deletion of personal data stored on our servers.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The following form will allow you to request deletion of your account and relevant personal and private data. You will be required to authenticate ownership of that address, and may be asked to provide additional identification or information necessary to verify the request.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'This will request permanent deletion of your WordPress.org account, and relevant personal or private data stored on WordPress.org, WordPress.net, WordCamp.org, BuddyPress.org, bbPress.org, and other related domains and sites.', 'wporg'); ?></p>

					<p><?php
						printf(
							/* translators: link to privacy policy. */
							esc_html__( 'Not all data can be erased, please review the %s for details.', 'wporg' ),
							'<a href="/about/privacy/">' . esc_html_x( 'Privacy Policy', 'Page title', 'wporg' ) . '</a>'
						);
					?></p>

					<?php if ( $error_message ) : ?>

						<p class="error">
							<strong><?php esc_html_e( 'An error occured with your request:', 'wporg' ); ?></strong><br>
							<?php echo $error_message; ?>
						</p>
					<?php elseif ( $success ) : ?>
						<p class="success"><strong><?php esc_html_e( 'Please check your email for a confirmation link, and follow the instructions to authenticate your request.', 'wporg' ); ?></strong></p>
					<?php endif; ?>

					<p class="error">
						<strong>This is currently disabled unless you have a 'special' WordPress.org account.</strong>
						<br>
						<span style="color: red">DO NOT REQUEST ERASURE UNLESS YOU WANT YOUR ACCOUNT DELETED.</span>
						</strong>
					</p>
					<?php if ( is_user_logged_in() && wporg_user_has_restricted_password() ) : ?>
						<p class="success">PS: You have a special account.</p>
					<?php endif; ?>

					<form id="erase-request-form" class="request-form" method="POST" action="#">
						<label for="email">
							<?php esc_html_e( 'Email Address', 'wporg' ); ?>
						</label>
						<input
							type="email"
							name="email" id="email"
							placeholder="<?php
								/* translators: Example placeholder email address */
								esc_attr_e( 'you@example.com', 'wporg' )
							?>"
							required
							value="<?php echo esc_attr( $email ); ?>"
						>
						<p><?php esc_html_e( 'By submitting this form, you declare that you are the individual owner of the specified email address and its associated accounts; and that all submitted information including any supplemental details necessary to verify your identity are true. You also declare that it is your intention for accounts associated with that email address to be permanently deleted.', 'wporg' ); ?></p>
						<?php reCAPTCHA\display_submit_button( __( 'Accept Declaration and Request Permanent Account Deletion', 'wporg' ) ); ?>
						<?php if ( is_user_logged_in() ) wp_nonce_field( $nonce_action ); ?>
					</form>

						<p><?php esc_html_e( "Please Note: Before we can begin processing your request, we'll require that you verify ownership of the email address. If the email address is associated with an account, we'll also require you to login to that account first.", 'wporg' ); ?></p>

				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
