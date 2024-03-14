<?php
namespace Wporg\TranslationEvents;

use GP;
?>

<div class="event-list-top-bar">
	<ul class="event-list-nav">
		<?php if ( is_user_logged_in() ) : ?>
			<li><a href="<?php echo esc_url( gp_url( '/events/my-events/' ) ); ?>">My Events</a></li>
			<?php
			/**
			 * Filter the ability to create, edit, or delete an event.
			 *
			 * @param bool $can_crud_event Whether the user can create, edit, or delete an event.
			 */
			$can_crud_event = apply_filters( 'gp_translation_events_can_crud_event', GP::$permission->current_user_can( 'admin' ) );
			if ( $can_crud_event ) :
				?>
				<li><a class="button is-primary" href="<?php echo esc_url( gp_url( '/events/new/' ) ); ?>">Create Event</a></li>
			<?php endif; ?>
		<?php endif; ?>
	</ul>
</div>
