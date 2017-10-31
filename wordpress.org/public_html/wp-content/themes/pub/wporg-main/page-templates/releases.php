<?php
/**
 * Template Name: Releases
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;
$releases = $GLOBALS['rosetta']->rosetta->get_releases_breakdown();

the_post();
get_header( 'page' ); ?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'col-12' ); ?> role="main">
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header><!-- .entry-header -->

		<div class="entry-content">
			<?php
			if ( ! empty( $releases ) ) :
				if ( isset( $releases['latest'] ) ) :
					rosetta_release_row( null, null, null, true );
					?>
					<h3 id="latest"><?php _e( 'Latest release', 'wporg' ); ?></h3>
					<table class="releases latest">
						<?php echo rosetta_release_row( $releases['latest'], 'alt' ); ?>
					</table>
					<?php
				endif;

				if ( ! empty( $releases['branches'] ) ) :
					echo '<a name="older" id="older"></a>';

					foreach ( $releases['branches'] as $branch => $branch_release ):
						rosetta_release_row( null, null, null, true );
						?>
						<h3><?php printf( __( '%s Branch', 'wporg' ), $branch );?></h3>
						<table class="releases">
							<?php
							foreach ( $branch_release as $release ) :
								rosetta_release_row( $release, 'alt' );
							endforeach;
							?>
						</table>
						<?php
					endforeach;
				endif; # any branches

				if ( ! empty( $releases['betas'] ) ) :
					?>
					<h3 id="betas"><?php _e( 'Beta &amp; RC releases', 'wporg' ); ?></h3>
					<table id="beta" class="releases">
						<?php
						rosetta_release_row( null, null, null, true );
						foreach ( $releases['betas'] as $release ):
							rosetta_release_row( $release, 'alt', 'beta-first' );
						endforeach;
						?>
					</table>

					<?php
				endif; # any betas
			else: # no releases
				echo '<p>' . __( 'There are no releases, yet.', 'wporg' ) . '</p>';
			endif; # if releases
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
