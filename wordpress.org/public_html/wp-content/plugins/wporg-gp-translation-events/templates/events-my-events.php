<?php
/**
 * Template for My Events.
 */
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Event\Events_Query_Result;
use Wporg\TranslationEvents\Templates;

/** @var Events_Query_Result $events */
/** @var ?Attendee[] $current_user_attendee_per_event Associative array with event id as key, and boolean as value. */

Templates::header(
	array(
		'html_title'  => esc_html__( 'Translation Events', 'gp-translation-events' ) . ' - ' . esc_html__( 'My Events', 'gp-translation-events' ),
		'page_title'  => __( 'My Events', 'gp-translation-events' ),
		'breadcrumbs' => array( esc_html__( 'My Events', 'gp-translation-events' ) ),
	),
);

?>

<div class="event-page-wrapper">
	<?php
	if ( empty( $events->events ) ) :
		esc_html_e( 'No events found.', 'gp-translation-events' );
	else :
		?>
		<?php
		Templates::partial(
			'event-list',
			array(
				'query'                           => $events,
				'pagination_query_param'          => 'page',
				'show_start'                      => true,
				'show_end'                        => true,
				'relative_time'                   => false,
				'current_user_attendee_per_event' => $current_user_attendee_per_event,
			),
		);
	endif;
	?>
</div>

<?php Templates::footer(); ?>
