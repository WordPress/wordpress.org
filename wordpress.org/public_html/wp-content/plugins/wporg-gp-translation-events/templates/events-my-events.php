<?php
/**
 * Template for My Events.
 */
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Event\Events_Query_Result;
use Wporg\TranslationEvents\Templates;

/** @var Events_Query_Result $events_i_created_query */
/** @var Events_Query_Result $events_i_host_query */
/** @var Events_Query_Result $events_i_attended_query */

Templates::header(
	array(
		'html_title'  => esc_html__( 'Translation Events', 'gp-translation-events' ) . ' - ' . esc_html__( 'My Events', 'gp-translation-events' ),
		'page_title'  => __( 'My Events', 'gp-translation-events' ),
		'breadcrumbs' => array( esc_html__( 'My Events', 'gp-translation-events' ) ),
	),
);
?>
<div class="events-links-to-anchors">
	<ul>
		<?php if ( ! empty( $events_i_am_or_will_attend_query->events ) ) : ?>
			<li><a href="#events-i-am-or-will-attend"><?php esc_html_e( 'Events I am or will be attending', 'gp-translation-events' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ! empty( $events_i_host_query->events ) ) : ?>
			<li><a href="#events-i-host"><?php esc_html_e( 'Events I host', 'gp-translation-events' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ! empty( $events_i_created_query->events ) ) : ?>
			<li><a href="#events-i-created"><?php esc_html_e( 'Events I have created', 'gp-translation-events' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ! empty( $events_i_attended_query->events ) ) : ?>
			<li><a href="#events-i-attended"><?php esc_html_e( 'Events I attended', 'gp-translation-events' ); ?></a></li>
		<?php endif; ?>
		</ul>
</div>
<div class="event-page-wrapper">
	<?php
	if ( empty( $events_i_am_or_will_attend_query->events ) && empty( $events_i_created_query->events ) && empty( $events_i_host_query->events ) && empty( $events_i_attended_query->post_count ) ) :
		esc_html_e( 'No events found.', 'gp-translation-events' );
	endif;
	?>

	<?php if ( ! empty( $events_i_am_or_will_attend_query->events ) ) : ?>
		<h2 id="events-i-am-or-will-attend"><?php esc_html_e( 'Events I am or will be attending', 'gp-translation-events' ); ?> </h2>
		<?php
		Templates::partial(
			'event-list',
			array(
				'query'                  => $events_i_am_or_will_attend_query,
				'pagination_query_param' => 'events_i_am_or_will_attend_paged',
				'show_start'             => true,
				'show_end'               => true,
				'relative_time'          => false,
			),
		);
	endif;
	?>

	<?php if ( ! empty( $events_i_host_query->events ) ) : ?>
		<h2 id="events-i-host"><?php esc_html_e( 'Events I host', 'gp-translation-events' ); ?> </h2>
		<?php
		Templates::partial(
			'event-list',
			array(
				'query'                  => $events_i_host_query,
				'pagination_query_param' => 'events_i_hosted_paged',
				'show_start'             => true,
				'show_end'               => true,
				'relative_time'          => false,
			),
		);
	endif;
	?>

	<?php if ( ! empty( $events_i_created_query->events ) ) : ?>
		<h2 id="events-i-created"><?php esc_html_e( 'Events I have created', 'gp-translation-events' ); ?> </h2>
		<?php
		Templates::partial(
			'event-list',
			array(
				'query'                  => $events_i_created_query,
				'pagination_query_param' => 'events_i_created_paged',
				'show_start'             => true,
				'show_end'               => true,
				'relative_time'          => false,
			),
		);
	endif;
	?>

	<?php if ( ! empty( $events_i_attended_query->events ) ) : ?>
		<h2 id="events-i-attended"><?php esc_html_e( 'Events I attended', 'gp-translation-events' ); ?> </h2>
		<?php
		Templates::partial(
			'event-list',
			array(
				'query'                  => $events_i_attended_query,
				'pagination_query_param' => 'events_i_attended_paged',
				'show_start'             => true,
				'show_end'               => true,
				'relative_time'          => false,
			),
		);
	endif;
	?>
</div>

<?php Templates::footer(); ?>
