<?php
namespace Wporg\TranslationEvents\Theme_2024;

$event_ids = $attributes['event_ids'] ?? array();
$data      = array(
	'event_ids' => $event_ids,
	'show_flag' => true,
);
?>
<!-- wp:wporg-translate-events-2024/event-list <?php echo wp_json_encode( $data ); ?> /-->
