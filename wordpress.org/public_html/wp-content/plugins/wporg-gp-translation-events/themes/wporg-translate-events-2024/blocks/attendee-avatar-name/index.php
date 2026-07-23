<?php namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/attendee-avatar-name',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['user_id'] ) ) {
				return '';
			}
			$user_id = $attributes['user_id'];
			$is_new_contributor = ! empty( $attributes['is_new_contributor'] );
			ob_start();
			?>
			<!-- wp:group -->
				<div class="wp-block-group">
				<a href="<?php echo esc_url( get_author_posts_url( $user_id ) ); ?>" class="attendee-avatar"><?php echo get_avatar( $user_id, 48 ); ?></a>
				<a href="<?php echo esc_url( get_author_posts_url( $user_id ) ); ?>" class="attendee-name"><?php echo esc_html( get_the_author_meta( 'display_name', $user_id ) ); ?></a>
				<?php if ( $is_new_contributor ) : ?>
					<span class="first-time-contributor-tada" title="<?php esc_html_e( 'New Translation Contributor', 'wporg-translate-events-2024' ); ?>"></span>
				<?php endif; ?>
			</div>
			<!-- /wp:group -->
			<?php
			return ob_get_clean();
		},
	)
);
