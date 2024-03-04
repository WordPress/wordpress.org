<?php
/**
 * Events list page.
 */

namespace Wporg\TranslationEvents;

use DateTime;
use WP_Query;

/** @var WP_Query $current_events_query */
/** @var WP_Query $upcoming_events_query */
/** @var WP_Query $past_events_query */

gp_title( __( 'Translation Events', 'gp-translation-events' ) );
gp_breadcrumb_translation_events();
gp_tmpl_header();
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>

<div class="event-page-wrapper">
	<h1 class="event_page_title"><?php esc_html_e( 'Translation Events', 'gp-translation-events' ); ?></h1>
<div class="event-left-col">
<?php
if ( $current_events_query->have_posts() ) :
	?>
	<h2><?php esc_html_e( 'Current events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		while ( $current_events_query->have_posts() ) :
			$current_events_query->the_post();
			$event_end = Event::get_end_date_text( get_post_meta( get_the_ID(), '_event_end', true ) );
			$event_url = gp_url( wp_make_link_relative( get_the_permalink() ) );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( $event_url ); ?>"><?php the_title(); ?></a>
				<span class="event-list-date"><?php echo esc_html( $event_end ); ?></span>
				<?php the_excerpt(); ?>
			</li>
			<?php
		endwhile;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $current_events_query->max_num_pages,
				'current'   => max( 1, $current_events_query->query_vars['paged'] ),
				'format'    => '?current_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;
if ( $upcoming_events_query->have_posts() ) :
	?>
	<h2><?php esc_html_e( 'Upcoming events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		while ( $upcoming_events_query->have_posts() ) :
			$upcoming_events_query->the_post();
			$event_start = ( new DateTime( get_post_meta( get_the_ID(), '_event_start', true ) ) )->format( 'l, F j, Y' );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( gp_url( wp_make_link_relative( get_the_permalink() ) ) ); ?>"><?php the_title(); ?></a>
				<span class="event-list-date"><?php echo esc_html( $event_start ); ?></span>
				<?php the_excerpt(); ?>
			</li>
			<?php
		endwhile;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $upcoming_events_query->max_num_pages,
				'current'   => max( 1, $upcoming_events_query->query_vars['paged'] ),
				'format'    => '?upcoming_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;
if ( $past_events_query->have_posts() ) :
	?>
	<h2><?php esc_html_e( 'Past events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		while ( $past_events_query->have_posts() ) :
			$past_events_query->the_post();
			$event_start = ( new DateTime( get_post_meta( get_the_ID(), '_event_start', true ) ) )->format( 'M j, Y' );
			$event_end   = ( new DateTime( get_post_meta( get_the_ID(), '_event_end', true ) ) )->format( 'M j, Y' );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( gp_url( wp_make_link_relative( get_the_permalink() ) ) ); ?>"><?php the_title(); ?></a>
				<?php if ( $event_start === $event_end ) : ?>
					<span class="event-list-date"><?php echo esc_html( $event_start ); ?></span>
				<?php else : ?>
					<span class="event-list-date"><?php echo esc_html( $event_start ); ?> - <?php echo esc_html( $event_end ); ?></span>
				<?php endif; ?>
				<?php the_excerpt(); ?>
			</li>
			<?php
		endwhile;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $past_events_query->max_num_pages,
				'current'   => max( 1, $past_events_query->query_vars['paged'] ),
				'format'    => '?past_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;

if ( 0 === $current_events_query->post_count && 0 === $upcoming_events_query->post_count && 0 === $past_events_query->post_count ) :
	esc_html_e( 'No events found.', 'gp-translation-events' );
endif;
?>
</div>
<?php if ( is_user_logged_in() ) : ?>
	<div class="event-right-col">
		<h3 class="">Events I'm Attending</h3>
		<?php if ( ! $user_attending_events_query->have_posts() ) : ?>
			<p>You don't have any events to attend.</p>
		<?php else : ?>
			<ul class="event-attending-list">
				<?php
				while ( $user_attending_events_query->have_posts() ) :
					$user_attending_events_query->the_post();
					$event_start = ( new DateTime( get_post_meta( get_the_ID(), '_event_start', true ) ) )->format( 'M j, Y' );
					$event_end   = ( new DateTime( get_post_meta( get_the_ID(), '_event_end', true ) ) )->format( 'M j, Y' );
					?>
					<li class="event-list-item">
						<a href="<?php echo esc_url( gp_url( wp_make_link_relative( get_the_permalink() ) ) ); ?>"><?php the_title(); ?></a>
						<?php if ( $event_start === $event_end ) : ?>
							<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?></span>
						<?php else : ?>
							<span class="event-list-date events-i-am-attending"><?php echo esc_html( $event_start ); ?> - <?php echo esc_html( $event_end ); ?></span>
						<?php endif; ?>
					</li>
					<?php
				endwhile;
				?>
			</ul>
			<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => $user_attending_events_query->max_num_pages,
							'current'   => max( 1, $user_attending_events_query->query_vars['paged'] ),
							'format'    => '?user_attending_events_paged=%#%',
							'prev_text' => '&laquo; Previous',
							'next_text' => 'Next &raquo;',
						)
					) ?? ''
				);

				wp_reset_postdata();
		endif;
		?>
	</div>
<?php endif; ?>
</div>
<div class="clear"></div>
<?php gp_tmpl_footer(); ?>
