<?php
/**
 * Events list page.
 */

namespace Wporg\TranslationEvents;

use DateTime;
use WP_Query;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Events_Query_Result;

/** @var Events_Query_Result $current_events_query */
/** @var Events_Query_Result $upcoming_events_query */
/** @var Events_Query_Result $past_events_query */
/** @var Events_Query_Result $user_attending_events_query */

gp_title( __( 'Translation Events', 'gp-translation-events' ) );
gp_breadcrumb_translation_events();
gp_tmpl_header();
$event_page_title = __( 'Translation Events', 'gp-translation-events' );
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>

<div class="event-page-wrapper">
<div class="event-left-col">
<?php
if ( ! empty( $current_events_query->events ) ) :
	?>
	<h2><?php esc_html_e( 'Current events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		foreach ( $current_events_query->events as $event ) :
			$event_url = gp_url( wp_make_link_relative( get_the_permalink( $event->id() ) ) );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->title() ); ?></a>
				<span class="event-list-date">ends <?php $event->end()->print_relative_time_html(); ?></time></span>
				<?php echo esc_html( get_the_excerpt( $event->id() ) ); ?>
			</li>
			<?php
		endforeach;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $current_events_query->page_count,
				'current'   => $current_events_query->current_page,
				'format'    => '?current_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;

if ( ! empty( $upcoming_events_query->events ) ) :
	?>
	<h2><?php esc_html_e( 'Upcoming events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		foreach ( $upcoming_events_query->events as $event ) :
			$event_url = gp_url( wp_make_link_relative( get_the_permalink( $event->id() ) ) );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->title() ); ?></a>
				<span class="event-list-date">starts <?php $event->start()->print_relative_time_html(); ?></span>
				<?php echo esc_html( get_the_excerpt( $event->id() ) ); ?>
			</li>
			<?php
		endforeach;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $upcoming_events_query->page_count,
				'current'   => $upcoming_events_query->current_page,
				'format'    => '?upcoming_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;
if ( ! empty( $past_events_query->events ) ) :
	?>
	<h2><?php esc_html_e( 'Past events', 'gp-translation-events' ); ?></h2>
	<ul class="event-list">
		<?php
		foreach ( $past_events_query->events as $event ) :
			$event_url = gp_url( wp_make_link_relative( get_the_permalink( $event->id() ) ) );
			?>
			<li class="event-list-item">
				<a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->title() ); ?></a>
				<span class="event-list-date">ended <?php $event->end()->print_relative_time_html( 'F j, Y H:i T' ); ?></span>
				<?php esc_html( get_the_excerpt( $event->id() ) ); ?>
			</li>
			<?php
		endforeach;
		?>
	</ul>

	<?php
	echo wp_kses_post(
		paginate_links(
			array(
				'total'     => $past_events_query->page_count,
				'current'   => $past_events_query->current_page,
				'format'    => '?past_events_paged=%#%',
				'prev_text' => '&laquo; Previous',
				'next_text' => 'Next &raquo;',
			)
		) ?? ''
	);

	wp_reset_postdata();
endif;

if ( empty( $current_events_query->events ) && empty( $upcoming_events_query->events ) && empty( $past_events_query->post_count ) ) :
	esc_html_e( 'No events found.', 'gp-translation-events' );
endif;
?>
</div>
<?php if ( is_user_logged_in() ) : ?>
	<div class="event-right-col">
		<h2>Events I'm Attending</h2>
		<?php if ( empty( $user_attending_events_query->events ) ) : ?>
			<p>You don't have any events to attend.</p>
		<?php else : ?>
			<ul class="event-attending-list">
				<?php
				foreach ( $user_attending_events_query->events as $event ) :
					$event_url = gp_url( wp_make_link_relative( get_the_permalink( $event->id() ) ) );
					?>
					<li class="event-list-item">
						<a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->title() ); ?></a>
						<?php if ( $event->start() === $event->end() ) : ?>
							<span class="event-list-date events-i-am-attending"><?php $event->start()->print_time_html( 'F j, Y H:i T' ); ?></span>
						<?php else : ?>
							<span class="event-list-date events-i-am-attending"><?php $event->start()->print_time_html( 'F j, Y H:i T' ); ?> - <?php $event->end()->print_time_html( 'F j, Y H:i T' ); ?></span>
						<?php endif; ?>
					</li>
					<?php
				endforeach;
				?>
			</ul>
			<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => $user_attending_events_query->page_count,
							'current'   => $user_attending_events_query->current_page,
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
