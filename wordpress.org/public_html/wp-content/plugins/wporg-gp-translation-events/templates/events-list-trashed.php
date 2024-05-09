<?php

namespace Wporg\TranslationEvents;

use Wporg\TranslationEvents\Event\Events_Query_Result;

/** @var Events_Query_Result $trashed_events_query */

gp_title( __( 'Deleted Translation Events', 'gp-translation-events' ) );
gp_breadcrumb_translation_events();
gp_tmpl_header();
$event_page_title = __( 'Deleted Translation Events', 'gp-translation-events' );
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>

<div class="event-page-wrapper">
	<div class="event-left-col">
		<?php if ( empty( $trashed_events_query->events ) ) : ?>
			<?php esc_html_e( 'No deleted events found.', 'gp-translation-events' ); ?>
		<?php else : ?>
			<ul class="event-list">
				<?php foreach ( $trashed_events_query->events as $event ) : ?>
					<li class="event-list-item">
						<a href="<?php echo esc_url( Urls::event_edit( $event->id() ) ); ?>"><?php echo esc_html( $event->title() ); ?></a>
						<?php if ( current_user_can( 'trash_translation_event', $event->id() ) ) : ?>
							<a href="<?php echo esc_url( Urls::event_trash( $event->id() ) ); ?>" class="button is-small">Restore</a>
						<?php endif; ?>
						<?php if ( current_user_can( 'delete_translation_event', $event->id() ) ) : ?>
							<a href="<?php echo esc_url( Urls::event_delete( $event->id() ) ); ?>" class="button is-small is-destructive">Delete Permanently</a>
						<?php endif; ?>
						<?php if ( $event->is_past() ) : ?>
							<span class="event-list-date">ended <?php $event->end()->print_relative_time_html(); ?></time></span>
						<?php else : ?>
							<span class="event-list-date">ends <?php $event->end()->print_relative_time_html(); ?></time></span>
						<?php endif; ?>
						<?php echo esc_html( get_the_excerpt( $event->id() ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'     => $trashed_events_query->page_count,
						'current'   => $trashed_events_query->current_page,
						'format'    => '?page=%#%',
						'prev_text' => '&laquo; Previous',
						'next_text' => 'Next &raquo;',
					)
				) ?? ''
			);
			?>
		<?php endif; ?>
	</div>
</div>

<div class="clear"></div>
<?php gp_tmpl_footer(); ?>
