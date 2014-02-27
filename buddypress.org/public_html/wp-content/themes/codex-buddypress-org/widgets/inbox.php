<?php
class BPOrg_Inbox_Widget extends WP_Widget {
	function bporg_inbox_widget() {
		parent::WP_Widget( false, $name = __( "Inbox", 'bporg' ) );
	}

	function widget( $args, $instance ) {
		global $bp;

	    extract( $args );
		echo $before_widget;
		echo $before_title . __( 'Inbox', 'bp-follow' ) . ' &middot <a href="' . esc_url( $bp->loggedin_user->domain . 'messages/inbox/' ) . '">View All</a>' . $after_title; ?>

		<?php if ( bp_has_message_threads( 'per_page=5&max=5' ) ) : ?>

			<ul id="message-threads">
				<?php while ( bp_message_threads() ) : bp_message_thread(); ?>

					<li>
						<?php bp_message_thread_avatar() ?>
						<div class="details">
							<a class="title" href="<?php bp_message_thread_view_link() ?>" title="<?php esc_attr_e( "View Message", "buddypress" ); ?>"><?php bp_message_thread_subject() ?></a>
							<div class="meta"><?php _e( 'From:', 'buddypress' ); ?> <?php bp_message_thread_from() ?> &middot; <?php bp_message_thread_last_post_date() ?></div>
						</div>
					</li>

				<?php endwhile; ?>
			</ul><!-- #message-threads -->

		<?php else: ?>
			<div id="message">
				<p>You have no messages in your inbox.</p>
			</div>
		<?php endif;?>

		<?php echo $after_widget; ?>

	<?php
	}
}
add_action( 'widgets_init', create_function( '', 'return register_widget("BPOrg_Inbox_Widget");' ) );
