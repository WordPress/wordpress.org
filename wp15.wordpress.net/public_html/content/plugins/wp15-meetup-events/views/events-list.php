<?php

namespace WP15\Meetup_Events;

/** @var array $events */

?>

<ul class="wp15-events-list">
	<?php foreach ( $events as $event ) : ?>
		<li>
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
