<div id="ofe-events" class="ofe-events">
	<?php foreach ( $events as $date => $day_events ) : ?>

		<h3>
			<?php echo date( 'F j', strtotime( $date ) ); ?>
			<span class="owe-day-of-week"><?php echo date( '(l)', strtotime( $date ) ); ?></span>
		</h3>

		<ul class="ofe-event-list">
			<?php foreach ( $day_events as $event ) : ?>
				<li>
					<?php if ( $event->start_timestamp ) : ?>
						<?php echo date( 'g:i a', $event->start_timestamp ); ?>
						<br>
					<?php endif; ?>

					<a href="<?php echo esc_url( $event->url ); ?>">
						<?php echo esc_html( $event->title ); ?>
						<br>
					</a>
					
					<?php if ( $event->type ) : ?>
						<?php echo esc_html( 'wordcamp' === $event->type ? 'WordCamp' : mb_convert_case( $event->type, MB_CASE_TITLE, 'UTF-8' ) ); ?>
						<span class="owe-separator"></span>
					<?php endif; ?>

					<?php if ( $event->location ) : ?>
						<?php echo esc_html( 'online' === $event->location ? 'Online' : $event->location ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endforeach; ?>
</div> <!-- end #ofe-events -->
