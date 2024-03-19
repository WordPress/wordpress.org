<?php
/**
 * Template part for displaying a message that posts cannot be found.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

if ( is_tax( 'plugin_section', 'favorites' ) ) :
	if ( is_user_logged_in() ) :
		$current_user = wp_get_current_user();
		?>

		<p><?php esc_html_e( 'No favorites have been added, yet.', 'wporg-plugins' ); ?></p>

		<?php if ( get_query_var( 'favorites_user' ) === $current_user->user_nicename ) : ?>
			<p><?php esc_html_e( 'Find a plugin and mark it as a favorite to see it here.', 'wporg-plugins' ); ?></p>
			<p>
				<?php
				/* translators: Link to user profile. */
				printf( wp_kses_post( __( 'Your favorite plugins are also shared on <a href="%s">your profile</a>.', 'wporg-plugins' ) ), esc_url( 'https://profiles.wordpress.org/' . $current_user->user_nicename . '/#content-favorites' ) );
				?>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<p>
			<?php
			/* translators: URL to login scren. */
			printf( wp_kses_post( __( '<a href="%s">Login to WordPress.org</a> to mark plugins as favorites.', 'wporg-plugins' ) ), esc_url( wp_login_url( 'https://wordpress.org/plugins/browse/favorites/' ) ) );
			?>
		</p>

	<?php endif; // is_user_logged_in.
else :
	?><p><?php esc_html_e( 'Sorry, but nothing matched your query.', 'wporg-plugins' ); ?></p><?php

endif;
