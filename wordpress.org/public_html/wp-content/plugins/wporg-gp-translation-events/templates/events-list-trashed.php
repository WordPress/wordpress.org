<?php
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Event\Events_Query_Result;
use Wporg\TranslationEvents\Templates;

/** @var Events_Query_Result $trashed_events_query */

Templates::header(
	array(
		'html_title' => __( 'Deleted Translation Events', 'gp-translation-events' ),
		'page_title' => __( 'Deleted Translation Events', 'gp-translation-events' ),
	),
);
?>

<div class="event-page-wrapper">
	<div class="event-left-col">
		<?php if ( empty( $trashed_events_query->events ) ) : ?>
			<?php esc_html_e( 'No deleted events found.', 'gp-translation-events' ); ?>
		<?php else : ?>
			<?php
			Templates::partial(
				'event-list',
				array(
					'query'                  => $trashed_events_query,
					'pagination_query_param' => 'page',
					'show_start'             => true,
					'show_end'               => true,
					'relative_time'          => false,
				),
			);
			?>
		<?php endif; ?>
	</div>
</div>

<?php Templates::footer(); ?>
