<?php
/**
 * The header for our theme.
 *
 * @package wporg-plugins
 */

$GLOBALS['pagetitle'] = __( 'Plugin Directory &mdash; Free WordPress Plugins', 'wporg-plugins' );

require WPORGPATH . 'header.php';
?>

<div id="headline">
	<div class="wrapper">
		<h2><a href="<?php echo home_url('/'); ?>"><?php _e( 'Plugin Directory', 'wporg-plugins' ); ?></a></h2>
		<?php
		$items = array();
		if ( is_user_logged_in() ) {
			$items[] = sprintf(
				__( 'Welcome, %s', 'wporg-plugins' ),
				sprintf(
					'<a href="https://profiles.wordpress.org/%s">%s</a>',
					wp_get_current_user()->user_nicename,
					wp_get_current_user()->display_name
				)
			);
			if ( true /* user_has_plugins */ ) {
				$items[] = '<a href="' . admin_url( 'edit.php?post_type=plugin' ) . '">' . __( 'Manage My Plugins', 'wporg-plugins' ) . '</a>';
			}
			$items[] = '<a href="https://login.wordpress.org/logout">' . __( 'Log Out', 'wporg-plugins' ) . '</a>';
		} else {
			$items[] = '<a href="https://login.wordpress.org/?redirect_to=' . urlencode( wporg_plugins_self_link() ) . '">' . __( 'Log In', 'wporg-plugins' ) . '</a>';
		}
		echo '<p class="login">' . implode( ' | ', $items ) . '</p>';
		?>
	</div>
</div>

<div id="pagebody">