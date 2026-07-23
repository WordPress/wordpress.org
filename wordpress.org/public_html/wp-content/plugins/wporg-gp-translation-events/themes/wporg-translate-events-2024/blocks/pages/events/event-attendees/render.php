<?php
/**
 * Template for the event attendees page, rendering the attendee list for an event.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;

$event_id = $attributes['event_id'] ?? array();
$event    = Translation_Events::get_event_repository()->get_event( $event_id );
if ( ! $event ) {
	return '';
}

?>
<!-- wp:wporg-translate-events-2024/attendee-list
<?php
echo wp_json_encode(
	array(
		'id'        => $event->id(),
		'view_type' => 'table',
	)
);
?>

/-->
