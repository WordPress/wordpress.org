<?php
/**
 * Template Name: Download -> Releases
 *
 * Template for displaying a release archive page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'download/releases'     => _x( 'Releases', 'Page title', 'wporg' ),
	'download/beta-nightly' => _x( 'Beta/Nightly', 'Page title', 'wporg' ),
	'download/counter'      => _x( 'Counter', 'Page title', 'wporg' ),
	'download/source'       => _x( 'Source Code', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK ) {
	$releases = $GLOBALS['rosetta']->rosetta->get_releases_breakdown();
} else {
	$releases = \WordPressdotorg\Releases\get_breakdown();
}

the_post();
get_header( 'child-page' );
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> role="main">
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title col-8">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php esc_html_e( 'This is an archive of every release we’ve done that we have a record of.', 'wporg' ); ?><br />
						<?php
						printf(
							/* translators: Stable branch */
							wp_kses_post( __( 'None of these are safe to use, except the <strong>latest</strong> in the %s series, which is actively maintained.', 'wporg' ) ),
							esc_html( WP_CORE_STABLE_BRANCH )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: History page URL */
							wp_kses_post( __( 'Curious about which jazzers we highlighted for each release? <a href="%s">It’s on the History page</a>.', 'wporg' ) ),
							esc_url( home_url( '/about/history/' ) )
						);
						?>
					</p>

					<?php
					if ( ! empty( $releases ) ) :
						if ( isset( $releases['latest'] ) ) :
							?>
							<h3 id="latest"><?php esc_html_e( 'Latest release', 'wporg' ); ?></h3>
							<table class="releases latest">
								<?php release_cols(); ?>
								<?php release_row( $releases['latest'] ); ?>
							</table>
							<?php
						endif;

						if ( ! empty( $releases['branches'] ) ) :
							echo '<a name="older" id="older"></a>';

							foreach ( $releases['branches'] as $branch => $branch_release ) :
								?>
								<h3>
									<?php
									printf(
										/* translators: Version number. */
										esc_html__( '%s Branch', 'wporg' ),
										esc_html( $branch )
									);
									?>
								</h3>
								<table class="releases">
									<?php release_cols(); ?>
									<?php
									foreach ( $branch_release as $release ) :
										release_row( $release );
									endforeach;
									?>
								</table>
								<?php
							endforeach;
						endif; // Any branches.

						if ( ! empty( $releases['betas'] ) ) :
							?>
							<h3 id="betas"><?php esc_html_e( 'Beta &amp; RC releases', 'wporg' ); ?></h3>
							<p><?php esc_html_e( 'These were testing releases and are only available here for archival purposes.', 'wporg' ); ?></p>
							<table id="beta" class="releases">
								<?php release_cols(); ?>
								<?php
								foreach ( $releases['betas'] as $release ) :
									release_row( $release );
								endforeach;
								?>
							</table>

							<?php
						endif; // Any betas.

						if ( ! empty( $releases['mu'] ) ) :
							?>
							<h3 id="mu"><?php esc_html_e( 'MU releases', 'wporg' ); ?></h3>
							<p><?php esc_html_e( 'WordPress MU releases made prior to MU being merged into WordPress 3.0', 'wporg' ); ?></p>
							<table class="releases">
								<?php release_cols(); ?>
								<?php
								foreach ( $releases['mu'] as $release ) :
									release_row( $release );
								endforeach;
								?>
							</table>

							<?php
						endif; // Any MUs.
					else : // No releases.
						echo '<p>' . esc_html__( 'There are no releases, yet.', 'wporg' ) . '</p>';
					endif; // if releases.
					?>
				</section>
			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->
	</main><!-- #main -->

<?php
get_footer();
