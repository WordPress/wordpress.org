<?php
/**
 * Template Name: Releases
 *
 * Template for displaying a release archive page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK ) {
	$releases = $GLOBALS['rosetta']->rosetta->get_releases_breakdown();
} else {
	$releases = \WordPressdotorg\Releases\get_breakdown();
}

the_post();
get_header();
?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'col-12' ); ?> role="main">
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header><!-- .entry-header -->

		<div class="entry-content">
			<p>
				<strong><?php esc_html_e( 'This is an archive of every release we’ve done that we have a record of.', 'wporg' ); ?></strong><br />
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
					/* translators: Roadmap URL */
					wp_kses_post( __( 'Curious about which jazzers we highlighted for each release? <a href="%s">It’s on the roadmap</a>.', 'wporg' ) ),
					esc_url( home_url( '/about/roadmap/' ) )
				);
				?>
			</p>

			<?php
			if ( ! empty( $releases ) ) :
				if ( isset( $releases['latest'] ) ) :
					?>
					<h3 id="latest"><?php esc_html_e( 'Latest release', 'wporg' ); ?></h3>
					<table class="releases latest">
						<col width="15%" />
						<col width="25%" />
						<col width="15%" />
						<col width="15%" />
						<col width="15%" />
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
							<col width="15%" />
							<col width="25%" />
							<col width="15%" />
							<col width="15%" />
							<col width="15%" />
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
						<col width="15%" />
						<col width="25%" />
						<col width="15%" />
						<col width="15%" />
						<col width="15%" />
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
						<col width="15%" />
						<col width="30%" />
						<col width="15%" />
						<col width="15%" />
						<col width="15%" />
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

<?php
get_footer();
