<?php
/**
 * Reference Template: Related Functionality
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( show_usage_info() ) :

	$has_uses    = ( post_type_has_uses_info()  && ( $uses    = get_uses()    ) && $uses->have_posts()    );
	$has_used_by = ( post_type_has_usage_info() && ( $used_by = get_used_by() ) && $used_by->have_posts() );

	$uses_to_show    = 5;
	$used_by_to_show = 5;

	if ( $has_uses || $has_used_by ) :
	?>
	<hr />
	<section class="related">
		<h2><?php _e( 'Related', 'wporg' ); ?></h2>

		<?php if ( $has_uses ) : ?>
			<article class="uses">
				<h3><?php _e( 'Uses', 'wporg' ); ?></h3>
				<table id="uses-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Uses', 'wporg' ); ?></caption>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Uses', 'wporg' ); ?></th>
							<th class="related-desc"><?php esc_html_e( 'Description', 'wporg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php while ( $uses->have_posts() ) : $uses->the_post(); ?>
						<tr>
							<td>
								<span><?php echo esc_attr( get_source_file() ); ?>:</span>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?><?php if ( ! in_array( get_post_type(), array( 'wp-parser-class', 'wp-parser-hook' ), true ) ) : ?>()<?php endif; ?></a>
							</td>
							<td class="related-desc">
								<?php echo get_summary(); ?>
							</td>
						</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					<tbody>
				</table>

				<?php if ( $uses->post_count > $uses_to_show ) : ?>
				<a href="#" class="show-more"><?php
					/* translators: %d: remaining 'uses' count */
					printf( _n( 'Show %d more use', 'Show %d more uses', $uses->post_count - $uses_to_show, 'wporg' ),
						number_format_i18n( $uses->post_count - $uses_to_show )
					);
					?></a>
				<a href="#" class="hide-more"><?php esc_html_e( 'Hide more uses', 'wporg' ); ?></a>
				<?php endif; ?>
			</article>
		<?php endif; ?>

		<?php if ( $has_used_by ) : ?>
			<?php if ( $has_uses && $uses->post_count > $uses_to_show ) : ?><hr /><?php endif; ?>

			<article class="used-by">
				<h3><?php esc_html_e( 'Used By', 'wporg' ); ?></h3>
				<table id="used-by-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Used By', 'wporg' ); ?></caption>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Used By', 'wporg' ); ?></th>
							<th class="related-desc"><?php esc_html_e( 'Description', 'wporg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php while ( $used_by->have_posts() ) : $used_by->the_post(); ?>
						<tr>
							<td>
								<span><?php echo esc_attr( get_source_file() ); ?>:</span>
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?><?php if ( ! in_array( get_post_type(), array( 'wp-parser-class', 'wp-parser-hook' ), true ) ) : ?>()<?php endif; ?></a>
							</td>
							<td class="related-desc">
								<?php echo get_summary(); ?>
							</td>
						</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					<tbody>
				</table>

				<?php if ( $used_by->post_count > $used_by_to_show ) : ?>
				<a href="#" class="show-more"><?php
					/* translators: %d: remaining 'used by' count */
					printf( _n( 'Show %d more used by', 'Show %d more used by', $used_by->post_count - $used_by_to_show, 'wporg' ),
						number_format_i18n( $used_by->post_count - $used_by_to_show )
					);
					?></a>
				<a href="#" class="hide-more"><?php esc_html_e( 'Hide more used by', 'wporg' ); ?></a>
				<?php endif; ?>
			</article>
		<?php endif; ?>
	</section>
	<?php endif; ?>
<?php endif; ?>
