<?php
/**
 * Reference Template: Related Functionality
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( show_usage_info() ) :
	?>
	<hr id="related" />
	<section class="related">
		<h2><?php _e( 'Related', 'wporg' ); ?></h2>

		<?php if ( post_type_has_uses_info() ) : ?>
			<article class="uses">
				<h3><?php _e( 'Uses', 'wporg' ); ?></h3>
				<ul>
					<?php
					$uses = get_uses();
					$uses_to_show = 5;
					while ( $uses->have_posts() ) : $uses->the_post()
						?>
						<li>
							<span><?php echo esc_attr( get_source_file() ); ?>:</span>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?><?php if ( 'wp-parser-hook' !== get_post_type() ) : ?>()<?php endif; ?></a>
						</li>
					<?php endwhile; wp_reset_postdata(); ?>
					<?php if ( $uses->post_count > $uses_to_show ) : ?>
						<a href="#" class="show-more"><?php
							/* translators: %d: remaining 'uses' count */
							printf( _n( 'Show %d more use', 'Show %d more uses', $uses->post_count - $uses_to_show, 'wporg' ),
								number_format_i18n( $uses->post_count - $uses_to_show )
							);
							?></a>
						<a href="#" class="hide-more"><?php _e( 'Hide more uses', 'wporg' ); ?></a>
					<?php endif; ?>
				</ul>
			</article>
		<?php endif; ?>

		<hr />
		<article class="used-by">
			<h3><?php _e( 'Used By', 'wporg' ); ?></h3>
			<ul>
				<?php
				$used_by = get_used_by();
				$used_by_to_show = 5;
				while ( $used_by->have_posts() ) : $used_by->the_post();
					?>
					<li>
						<span><?php echo esc_attr( get_source_file() ); ?>:</span>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?><?php if ( 'wp-parser-hook' !== get_post_type() ) : ?>()<?php endif; ?></a>
					</li>
				<?php endwhile; wp_reset_postdata(); ?>
				<?php if ( $used_by->post_count > $used_by_to_show ) : ?>
					<a href="#" class="show-more"><?php
						/* translators: %d: remaining 'used by' count */
						printf( _n( 'Show %d more used by', 'Show %d more used by', $used_by->post_count - $used_by_to_show, 'wporg' ),
							number_format_i18n( $used_by->post_count - $used_by_to_show )
						);
						?></a>
					<a href="#" class="hide-more"><?php _e( 'Hide more used by', 'wporg' ); ?></a>
				<?php endif; ?>
			</ul>
		</article>
	</section>
<?php endif; ?>

