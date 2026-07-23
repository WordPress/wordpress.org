<?php
/**
 * Template for the events homepage, listing current, upcoming, and past events.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

$event_ids             = $attributes['event_ids'] ?? array();
$current_events_query  = $attributes['current_events_query'];
$upcoming_events_query = $attributes['upcoming_events_query'];
$past_events_query     = $attributes['past_events_query'];

$current_events_data = array(
	'event_ids' => $current_events_query['event_ids'] ?? array(),
	'filter_by' => 'current_events_paged',
	'next_page' => ( $current_events_query['page_count'] >= $current_events_query['current_page'] + 1 ) ? $current_events_query['current_page'] + 1 : 0,
);

$upcoming_events_data = array(
	'event_ids' => $upcoming_events_query['event_ids'] ?? array(),
	'filter_by' => 'upcoming_events_paged',
	'next_page' => ( $upcoming_events_query['page_count'] >= $upcoming_events_query['current_page'] + 1 ) ? $upcoming_events_query['current_page'] + 1 : 0,

);

$past_events_data = array(
	'event_ids' => $attributes['past_events_query']['event_ids'] ?? array(),
	'filter_by' => 'past_events_paged',
	'next_page' => ( $past_events_query['page_count'] >= $past_events_query['current_page'] + 1 ) ? $past_events_query['current_page'] + 1 : 0,

);

?>
<!-- wp:pattern {"slug":"wporg-translate-events-2024/front-cover"} /-->
<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
<h2 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);"><?php esc_html_e( 'Your next events', 'wporg-translate-events-2024' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:wporg-translate-events-2024/event-list <?php echo wp_json_encode( $current_events_data ); ?>  /-->
<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter","spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}} -->
<h2 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);"><?php esc_html_e( 'Upcoming events', 'wporg-translate-events-2024' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:wporg-translate-events-2024/event-list <?php echo wp_json_encode( $upcoming_events_data ); ?>  /-->
<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
<h2 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);"><?php esc_html_e( 'Past events', 'wporg-translate-events-2024' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:wporg-translate-events-2024/event-list <?php echo wp_json_encode( $past_events_data ); ?>  /-->
