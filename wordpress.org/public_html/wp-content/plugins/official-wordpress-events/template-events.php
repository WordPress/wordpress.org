<div id="ofe_events">
	<ul>
		<?php foreach ( $events as $event ) : ?>

			<li>
				<a href="<?php echo esc_attr( esc_url( $event->url ) ); ?>">
					<?php echo esc_html( $event->title ); ?>
				</a><br />

				<?php echo esc_html( date( 'l, F jS | g:i a', (int) $event->start_timestamp ) ); ?><br />

				<?php echo esc_html( $event->location ); ?>
			</li>

		<?php endforeach; ?>
	</ul>
</div> <!-- end #ofe_events -->
