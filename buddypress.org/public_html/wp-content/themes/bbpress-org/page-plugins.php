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
							printf( __( 'Viewing %1$s to %2$s (%3$s)', 'bbporg' ),
								number_format_i18n( $from_num ),
								number_format_i18n( $to_num ),
								number_format_i18n( $plugins->info['results'] )
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

							echo $pag_links;
						?>

					</div>
				</div>

				<?php foreach ( (array) $plugins->plugins as $plugin ) : ?>

				<div class="single-plugin">
					<h3 class="plugin-title"><a href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $plugin->slug ); ?>/"><?php echo esc_html( $plugin->name ); ?></a></h3>

					<div class="plugin-meta">
						<?php if ( ! empty( $plugin->version ) ) : ?>
							<div><?php printf( __( 'Version: %s', 'bbporg' ), esc_html( $plugin->version ) ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $plugin->requires ) ) : ?>
							<div><?php printf( __( 'Requires: %s', 'bbporg' ), esc_html( $plugin->requires ) ); ?></div>
						<?php endif; ?>
						<?php if ( ! empty( $plugin->tested ) ) : ?>
							<div><?php printf( __( 'Compatible up to: %s', 'bbporg' ), esc_html( $plugin->tested ) ); ?></div>
						<?php endif; ?>
						<div><?php printf( __( 'Rating: %s', 'bbporg' ), $plugin->rating_html ); // raw html - do not escape ?></div>
					</div>

					<p class="plugin-description" style="font-size: 12px">
						<?php echo substr( strip_tags( $plugin->description ), 0, 300 ); ?>&hellip;
					</p>

				</div>

				<?php endforeach; ?>

				<div class="bbp-pagination">
					<div class="bbp-pagination-count">

						<?php
							/* translators: 1: starting number of plugins, 2: ending number, 3: total number */
							printf( __( 'Viewing %1$s to %2$s (%3$s)', 'bbporg' ),
								number_format_i18n( $from_num ),
								number_format_i18n( $to_num ),
								number_format_i18n( $plugins->info['results'] )
							);
						?>

					</div>

					<div class="bbp-pagination-links">

						<?php echo $pag_links; ?>

					</div>
				</div>

<?php endwhile; ?>
				<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>
