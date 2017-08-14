<?php
/**
 * Plugin Name:        WordCamp.org Network Plugin Control
 * Plugin Description: Monitor the plugins on WordCamp.org and show a notification if any plugin doesn't have the
 *                     intended network activation state. Also remove links from the Network Plugins UI that would
 *                     switch a plugin to an unintended state.
 *
 * @package WordCamp\Plugins\Network
 */

namespace WordCamp\Plugins\Network;
defined( 'WPINC' ) || die();

/**
 * The two arrays here depict the intended network activation state of each
 * plugin on WordCamp.org. When plugins are added, removed, or their state is
 * permanently changed, this function should also be updated.
 *
 * @access private
 *
 * @param string $state The state to retrieve a list of plugins for.
 *                      Possible values are 'activated' or 'deactivated'.
 *
 * @return array The list of plugins for the specified state.
 */
function _get_network_plugin_state_list( $state ) {
	$network_plugin_state = array(
		'activated'   => array(
			'akismet/akismet.php',
			'bbpress-network-templates/bbpress-network-templates.php',
			'camptix-admin-flags/camptix-admin-flags.php',
			'camptix-attendance/camptix-attendance.php',
			'camptix-badge-generator/bootstrap.php',
			'camptix/camptix.php',
			'camptix-extras/camptix-extras.php',
			'camptix-network-tools/camptix-network-tools.php',
			'custom-content-width/custom-content-width.php',
			'email-post-changes/email-post-changes.php',
			'email-post-changes-specific-post/email-post-changes-specific-post.php',
			'jetpack/jetpack.php',
			'jquery-ui-css/jquery-ui-css.php',
			'tagregator/bootstrap.php',
			'wordcamp-payments/bootstrap.php',
			'wordcamp-payments-network/bootstrap.php',
			'wordcamp-coming-soon-page/bootstrap.php',
			'wordcamp-dashboard-widgets/wordcamp-dashboard-widgets.php',
			'wordcamp-docs/wordcamp-docs.php',
			'wordcamp-forms-to-drafts/wordcamp-forms-to-drafts.php',
			'wordcamp-participation-notifier/wordcamp-participation-notifier.php',
			'wordcamp-remote-css/bootstrap.php',
			'wordcamp-site-cloner/wordcamp-site-cloner.php',
			'wc-canonical-years/wc-canonical-years.php',
			'wc-fonts/wc-fonts.php',
			'wc-post-types/wc-post-types.php',
			'wordcamp-qbo-client/wordcamp-qbo-client.php',
			'wordcamp-shortcodes/wc-shortcodes.php',
			'wordpress-importer/wordpress-importer.php',
			'wporg-profiles-wp-activity-notifier/wporg-profiles-wp-activity-notifier.php',
			'json-rest-api/plugin.php',
			'wp-super-cache/wp-cache.php',
		),
		'deactivated' => array(
			'bbpress/bbpress.php',
			'campt-indian-payment-gateway/campt-indian-payment-gateway.php',
			'camptix-kdcpay-gateway/camptix-kdcpay.php',
			'camptix-mailchimp/camptix-mailchimp.php',
			'camptix-mercadopago/camptix-mercadopago.php',
			'camptix-pagseguro/camptix-pagseguro.php',
			'camptix-payfast-gateway/camptix-payfast.php',
			'camptix-trustcard/camptix-trustcard.php',
			'camptix-trustpay/camptix-trustpay.php',
			'liveblog/liveblog.php',
			'multi-event-sponsors/bootstrap.php',
			'polldaddy/polldaddy.php',
			'supportflow/supportflow.php',
			'wordcamp-api/bootstrap.php',
			'wordcamp-organizer-reminders/bootstrap.php',
			'wcpt/wcpt-loader.php',
			'wordcamp-wiki/bootstrap.php',
			'wordcamp-qbo/wordcamp-qbo.php',
		),
	);

	return $network_plugin_state[ $state ];
}

/**
 * Prevent casual or unintended changes to the network activation state of
 * plugins on WordCamp.org.
 *
 * This simply removes the "Network Deactivate" link from plugins that should be
 * network activated, and the "Network Activate" link from plugins that should not.
 *
 * @param array  $actions     The network actions available for the current plugin.
 * @param string $plugin_file The plugin filename relative to the plugins directory.
 *
 * @return array The updated list of available actions for the current plugin.
 */
function network_plugin_actions( $actions, $plugin_file ) {
	$do_not_network_deactivate = _get_network_plugin_state_list( 'activated' );

	if ( in_array( $plugin_file, $do_not_network_deactivate, true ) && array_key_exists( 'deactivate', $actions ) ) {
		unset( $actions['deactivate'] );
	}

	$do_not_network_activate = _get_network_plugin_state_list( 'deactivated' );

	if ( in_array( $plugin_file, $do_not_network_activate, true ) && array_key_exists( 'activate', $actions ) ) {
		unset( $actions['activate'] );
	}

	return $actions;
}

add_filter( 'network_admin_plugin_action_links', __NAMESPACE__ . '\network_plugin_actions', 10, 2 );

/**
 * Display a network admin notice if there are plugins that don't have their intended
 * activation state.
 */
function network_plugin_notifier() {
	if ( ! is_super_admin() ) {
		return;
	}

	$state_activated   = _get_network_plugin_state_list( 'activated' );
	$state_deactivated = _get_network_plugin_state_list( 'deactivated' );

	$all_plugins             = array_keys( get_plugins() );
	$missing_plugins         = array_diff( array_merge( $state_activated, $state_deactivated ), $all_plugins );

	$active_plugins          = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
	$wrong_state_deactivated = array_diff( $state_activated, $active_plugins, $missing_plugins );
	$wrong_state_activated   = array_intersect( $state_deactivated, $active_plugins );

	if ( ! empty( $missing_plugins ) || ! empty( $wrong_state_deactivated ) || ! empty( $wrong_state_activated ) ) {
		?>
		<div class="notice notice-error">
			<?php if ( ! empty( $missing_plugins ) ) : ?>
				<p>The following plugins are missing:</p>
				<ul class="ul-disc">
					<li><?php echo implode( '</li><li>', array_map( 'esc_html', $missing_plugins ) ); ?></li>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $wrong_state_deactivated ) ) : ?>
				<p>The following plugins should be network-activated, but are not:</p>
				<ul class="ul-disc">
					<li><?php echo implode( '</li><li>', array_map( 'esc_html', $wrong_state_deactivated ) ); ?></li>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $wrong_state_activated ) ) : ?>
				<p>The following plugins should <strong>not</strong> be network-activated, but are:</p>
				<ul class="ul-disc">
					<li><?php echo implode( '</li><li>', array_map( 'esc_html', $wrong_state_activated ) ); ?></li>
				</ul>
			<?php endif; ?>

			<p>Please let a WordCamp.org developer know about this message.</p>
		</div>
		<?php
	}
}

add_action( 'network_admin_notices', __NAMESPACE__ . '\network_plugin_notifier' );
