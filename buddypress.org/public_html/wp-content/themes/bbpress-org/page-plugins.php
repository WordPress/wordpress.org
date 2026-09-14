<?php get_header(); ?>
<?php while ( have_posts() ) : the_post(); ?>
				<h3 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h3>

				<?php
					$current_page = isset( $_GET['ppage'] ) ? absint( $_GET['ppage'] ) : 1;
					$search       = bb_base_plugin_search_query( false );
					$plugins      = bb_base_get_plugins( $current_page, $search, 'bbpress' );
					$from_num     = intval( ( (int) $plugins->info['page'] - 1 ) * 10 ) + 1;
					$to_num       = ( $from_num + 9 > (int) $plugins->info['results'] ) ? $plugins->info['results'] : $from_num + 9;
				?>

				<div class="bbp-pagination">
					<div class="bbp-pagination-count">

						<?php
							/* translators: 1: starting number of plugins, 2: ending number, 3: total number */
							printf(
								/* translators: 1: First plugin number, 2: Last plugin number, 3: Total number of plugins. */
								esc_html__( 'Viewing %1$s to %2$s (%3$s)', 'bbporg' ),
								esc_html( number_format_i18n( $from_num ) ),
								esc_html( number_format_i18n( $to_num ) ),
								esc_html( number_format_i18n( $plugins->info['results'] ) )
							);
						?>

					</div>

					<div class="bbp-pagination-links">

						<?php
							$pag_links = paginate_links( array(
								'base'      => add_query_arg( array( 'ppage' => '%#%' ) ),
								'format'    => '',
								'total'     => ceil( $plugins->info['results'] / 10 ),
								'current'   => $plugins->info['page'],
								'prev_text' => '&larr;',
								'next_text' => '&rarr;',
								'mid_size'  => 1
							) );

							echo wp_kses_post( $pag_links );
						?>

					</div>
				</div>

				<?php foreach ( (array) $plugins->plugins as $plugin ) : ?>

				<div class="single-plugin">
					<h3 class="plugin-title"><a href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $plugin->slug ); ?>/"><?php echo esc_html( $plugin->name ); ?></a></h3>

					<div class="plugin-meta">
						<?php if ( ! empty( $plugin->version ) ) : ?>
							<?php /* translators: %s: Plugin version. */ ?>
							<div><?php printf( esc_html__( 'Version: %s', 'bbporg' ), esc_html( $plugin->version ) ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $plugin->requires ) ) : ?>
							<?php /* translators: %s: Minimum WordPress version. */ ?>
							<div><?php printf( esc_html__( 'Requires: %s', 'bbporg' ), esc_html( $plugin->requires ) ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $plugin->tested ) ) : ?>
							<?php /* translators: %s: Highest tested WordPress version. */ ?>
							<div><?php printf( esc_html__( 'Compatible up to: %s', 'bbporg' ), esc_html( $plugin->tested ) ); ?></div>
						<?php endif; ?>
						<?php /* translators: %s: Star rating markup. */ ?>
						<div><?php printf( esc_html__( 'Rating: %s', 'bbporg' ), wp_kses_post( $plugin->rating_html ) ); ?></div>
					</div>

					<p class="plugin-description" style="font-size: 12px">
						<?php echo esc_html( mb_substr( wp_strip_all_tags( $plugin->description ), 0, 300, 'UTF-8' ) ); ?>&hellip;
					</p>

				</div>

				<?php endforeach; ?>

				<div class="bbp-pagination">
					<div class="bbp-pagination-count">

						<?php
							/* translators: 1: starting number of plugins, 2: ending number, 3: total number */
							printf(
								/* translators: 1: First plugin number, 2: Last plugin number, 3: Total number of plugins. */
								esc_html__( 'Viewing %1$s to %2$s (%3$s)', 'bbporg' ),
								esc_html( number_format_i18n( $from_num ) ),
								esc_html( number_format_i18n( $to_num ) ),
								esc_html( number_format_i18n( $plugins->info['results'] ) )
							);
						?>

					</div>

					<div class="bbp-pagination-links">

						<?php echo wp_kses_post( $pag_links ); ?>

					</div>
				</div>

<?php endwhile; ?>
				<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>
