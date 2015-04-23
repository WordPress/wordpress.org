<div id="ofe-events">
	<?php foreach ( $events as $date => $day_events ) : ?>

		<h3>
			<?php echo date( 'F j', strtotime( $date ) ); ?>
			<span class="owe-day-of-week"><?php echo date( '(l)', strtotime( $date ) ); ?></span>
		</h3>

		<ul class="ofe-event-list">
			<?php foreach ( $day_events as $event ) : ?>
				<li>
					<?php if ( $event->location ) : ?>
						<?php echo esc_html( $event->location ); ?>
						<span class="owe-separator"></span>
					<?php endif; ?>

					<a href="<?php echo esc_attr( esc_url( $event->url ) ); ?>">
						<?php echo esc_html( $event->title ); ?>
					</a>

					<?php // WordCamp.org doesn't collect start times, so only display those for meetups ?>
					<?php if ( 'wordcamp' != $event->type ) : ?>
						<span class="owe-separator"></span>
						<?php echo date( 'g:i a', $event->start_timestamp ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endforeach; ?>
</div> <!-- end #ofe-events -->
