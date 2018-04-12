<?php

namespace WP15\Meetup_Events;
defined( 'WPINC' ) || die();

/** @var array $events */

?>

<form id="wp15-events-filter">
	<label>
		<span><?php esc_html_e( 'Search events:', 'wp15' ); ?></span>
		<?php // translators: Change this to a city in your locale that has a WP15 event planned. If none do, then choose a recognizable city in your locale. ?>
		<input id="wp15-events-query" type="text" value="" placeholder="<?php echo esc_attr_x( 'Seattle', 'Event query placeholder', 'wp15' ); ?>" />
	</label>
</form>

<ul class="wp15-events-list">
	<?php foreach ( $events as $event ) : ?>
		<li data-location="<?php echo esc_attr( $event['location'] ); ?>">
			<h3 class="wp15-event-group">
				<?php echo esc_html( $event['group'] ); ?>
			</h3>

			<p class="wp15-event-title">
				<a href="<?php echo esc_url( $event['event_url'] ); ?>">
					<?php echo esc_html( $event['name'] ); ?>
				</a>
			</p>

			<p class="wp15-event-date-time">
				<?php echo esc_html( $event['time'] ); ?>
			</p>
		</li>
	<?php endforeach; ?>
</ul>

<p class="wp15-organize-event">
	<?php printf(
		wp_kses_data( __( 'Don’t see your city? Get in touch with <a href="%s">your local group</a>, or <a href="%s">organize a group in your town</a>.', 'wp15' ) ),
		'https://www.meetup.com/pro/wordpress/',
		'https://make.wordpress.org/community/handbook/meetup-organizer/welcome/'
	); ?>
</p>
