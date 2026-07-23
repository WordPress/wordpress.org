<?php
use Wporg\TranslationEvents\Urls;

?>
<!-- wp:table -->
<figure class="wp-block-table">
					<table class="event-stats">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Remote', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Host', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Action', 'wporg-translate-events-2024' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $attendees_not_contributing as $attendee ) : ?>
							<tr>
								<td>
									<!-- wp:wporg-translate-events-2024/attendee-avatar-name 
										<?php
										echo wp_json_encode(
											array(
												'user_id' => $attendee->user_id(),
												'is_new_contributor' => $attendee->is_new_contributor(),
											)
										);
										?>
									/-->
							</td>
							<td>
								<?php if ( $attendee->is_remote() ) : ?>
									<span><?php esc_html_e( 'Yes', 'wporg-translate-events-2024' ); ?></span>
									<?php endif; ?>
							</td>
							<td>
							<?php if ( $attendee->is_host() ) : ?>
							<span><?php esc_html_e( 'Yes', 'wporg-translate-events-2024' ); ?></span>
							<?php endif; ?>
					</td>
					<td>
					<form class="add-remove-user-as-host" method="post" action="<?php echo esc_url( Urls::event_toggle_host( $event->id(), $attendee->user_id() ) ); ?>">
							<?php wp_nonce_field( "toggle_translation_event_host_{$event->id()}_{$attendee->user_id()}" ); ?>
					<div class="wp-block-buttons wporg-theme-actions is-layout-flex wp-block-buttons-is-layout-flex">
							<?php if ( $attendee->is_host() ) : ?>
							<input type="submit" class="wp-block-button__link remove-as-host" value="<?php echo esc_attr__( 'Remove as host', 'wporg-translate-events-2024' ); ?>"/>
							<?php else : ?>
									<input type="submit" class="wp-block-button__link convert-to-host" value="<?php echo esc_attr__( 'Make co-host', 'wporg-translate-events-2024' ); ?>"/>
							<?php endif; ?>
							<?php if ( $event->is_hybrid() ) : ?>
								<div class="wp-block-button is-style-outline"><a href="<?php echo esc_url( Urls::event_toggle_attendance_mode( $event->id(), $attendee->user_id() ) ); ?>" class="wp-block-button__link wp-element-button set-attendance-mode" id="wporg-theme-button-preview"><?php $attendee->is_remote() ? esc_html_e( 'Set as on-site', 'wporg-translate-events-2024' ) : esc_html_e( 'Set as remote', 'wporg-translate-events-2024' ); ?></a></div>
							<?php endif; ?>
							<?php if ( ! $attendee->is_host() ) : ?>
								<div class="wp-block-button is-style-outline"><a href="<?php echo esc_url( Urls::event_remove_attendee( $event->id(), $attendee->user_id() ) ); ?>" class="wp-block-button__link wp-element-button remove-attendee" id="wporg-theme-button-preview"><?php esc_html_e( 'Remove', 'wporg-translate-events-2024' ); ?></a></div>
							<?php endif; ?>
					</div>
						</form>
					</td>
							</tr>
							<?php endforeach; ?>
	
						</tbody>
					</table>
				</figure>
			<!-- /wp:table -->
