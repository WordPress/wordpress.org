<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Returns a list of authors.
 *
 * @return string
 */
function wporg_plugins_template_authors() {
	$contributors = get_post_meta( get_the_id(), 'contributors', true );

	$authors = array();
	foreach ( $contributors as $contributor ) {
		$user = get_user_by( 'login', $contributor );
		if ( $user ) {
			$authors[] = $user;
		}
	}

	if ( ! $authors ) {
		$authors[] = new \WP_User( get_post()->post_author );
	}

	$author_links = array();
	$and_more     = false;
	foreach ( $authors as $user ) {
		$author_links[] = sprintf( '<a href="%s">%s</a>', 'https://profiles.wordpress.org/' . $user->user_nicename . '/', $user->display_name );
		if ( count( $author_links ) > 5 ) {
			$and_more = true;
			break;
		}
	}

	if ( $and_more ) {
		return sprintf( '<cite> By: %s, and others.</cite>', implode( ', ', $author_links ) );
	} else {
		return sprintf( '<cite> By: %s</cite>', implode( ', ', $author_links ) );
	}
}

/**
 * Displays a plugin banner.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_plugin_banner( $post = null ) {
	echo Template::get_plugin_banner( $post, 'html' ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
}

/**
 * Displays a button to favorite or unfavorite a plugin.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_plugin_favorite_button( $post = null ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$is_favorited = Tools::favorited_plugin( get_post( $post ) );
	?>
	<div class="plugin-favorite">
		<a href="<?php echo esc_url( Template::get_favorite_link() ); ?>" class="plugin-favorite-heart<?php echo $is_favorited ? ' favorited' : ''; ?>">
		<span class="screen-reader-text">
			<?php
			if ( $is_favorited ) {
				/* translators: %s: plugin name */
				printf( esc_html__( 'Unfavorite %s', 'wporg-plugins' ), get_the_title() );
			} else {
				/* translators: %s: plugin name */
				printf( esc_html__( 'Favorite %s', 'wporg-plugins' ), get_the_title() );
			}
			?>
		</span>
		</a>
	</div>
	<?php
		wp_add_inline_script(
			'wporg-plugins-faq',
			"jQuery( '.plugin-favorite-heart' )
				.on( 'click touchstart animationend', function () {
					jQuery( this ).toggleClass( 'is-animating' );
				} )
				.on( 'click', function () {
					jQuery( this ).toggleClass( 'favorited' );
				} );"
		);
}

/**
 * Displays the byline for a plugin author.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_author_byline( $post = null ) {
	$post = get_post( $post );

	$url    = get_post_meta( $post->ID, 'header_author_uri', true );
	$author = strip_tags( get_post_meta( $post->ID, 'header_author', true ) ) ?: get_the_author();
	$author = $url ? '<a class="url fn n" rel="nofollow" href="' . esc_url( $url ) . '">' . $author . '</a>' : $author;

	/* translators: post author. */
	printf( esc_html_x( 'By %s', 'post author', 'wporg-plugins' ), '<span class="author vcard">' . wp_kses_post( $author ) . '</span>' );
}

/**
 * Displays a descriptive status notice for active plugins.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_active_plugin_notice( $post = null ) {
	if ( ! in_array( get_post_status( $post ), [ 'rejected', 'closed' ], true ) ) {
		echo wp_kses_post( get_plugin_status_notice( $post ) );
	};
}

/**
 * Displays a descriptive status notice for inactive plugins.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_closed_plugin_notice( $post = null ) {
	echo wp_kses_post( get_closed_plugin_notice( $post ) );
}

/**
 * Returns a descriptive status notice for inactive plugins.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return string Message markup.
 */
function get_closed_plugin_notice( $post = null ) {
	$post   = get_post( $post );
	$notice = '';

	if ( in_array( get_post_status( $post ), [ 'rejected', 'closed' ], true ) ) {
		$notice = get_plugin_status_notice( $post );

		if ( get_current_user_id() === (int) $post->post_author ) {
			$info_notice = '<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div><!-- .plugin-notice -->';
			$message     = sprintf(
				/* translators: 1: plugins@wordpress.org */
				__( 'If you did not request this change, please contact <a href="mailto:%1$s">%1$s</a> for a status. All developers with commit access are contacted when a plugin is closed, with the reasons why, so check your spam email too.', 'wporgplugins' ),
				'plugins@wordpress.org'
			);

			$notice .= sprintf( $info_notice, $message );
		}
	};

	return $notice;
}

/**
 * Return a descriptive status notice based on the plugin's current post_status.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return string Message markup.
 */
function get_plugin_status_notice( $post = null ) {
	$post_status    = get_post_status( $post );
	$info_notice    = '<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div><!-- .plugin-notice -->';
	$error_notice   = '<div class="plugin-notice notice notice-error notice-alt"><p>%s</p></div><!-- .plugin-notice -->';
	$warning_notice = '<div class="plugin-notice notice notice-warning notice-alt"><p>%s</p></div><!-- .plugin-notice -->';

	$message = '';

	switch ( $post_status ) {
		case 'publish':
			if ( Template::is_plugin_outdated( $post ) ) {
				$message = sprintf(
					$warning_notice,
					__( 'This plugin <strong>hasn&#146;t been tested with the latest 3 major releases of WordPress</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-plugins' )
				);
			}
			break;

		case 'draft':
		case 'pending':
			$message = sprintf(
				$info_notice,
				__( 'This plugin is requested and not visible to the public yet. Please be patient as your plugin gets reviewed.', 'wporg-plugins' )
			);
			break;

		case 'approved':
			$message = sprintf(
				$info_notice,
				__( 'This plugin is approved and awaiting data upload but not visible to the public yet. Once you make your first commit, the plugin will become public.', 'wporg-plugins' )
			);
			break;

		case 'rejected':
			$message = sprintf(
				$error_notice,
				__( 'This plugin has been rejected and is not visible to the public.', 'wporg-plugins' )
			);
			break;

		case 'disabled':
		case 'closed':
			$closed_date  = get_post_meta( get_the_ID(), 'plugin_closed_date', true );
			$close_reason = Template::get_close_reason( $post );

			if ( $closed_date ) {
				if ( 'disabled' === $post_status && current_user_can( 'plugin_approve' ) ) {
					/* translators: %s: plugin closing date */
					$message = sprintf( __( 'This plugin has been disabled as of %s -- this means it is closed, but actively serving updates.', 'wporg-plugins' ), mysql2date( get_option( 'date_format' ), $closed_date ) );
				} else {
					/* translators: %s: plugin closing date */
					$message = sprintf( __( 'This plugin has been closed as of %s and is not available for download.', 'wporg-plugins' ), mysql2date( get_option( 'date_format' ), $closed_date ) );
				}

				// Determine permanence of closure.
				$committers = Tools::get_plugin_committers( $post->post_name );
				$permanent  = ( __( 'Author Request', 'wporg-plugins' ) === $close_reason || ! $committers );

				$days_passed = (int) ( ( current_time( 'timestamp' ) - mysql2date( 'U', $closed_date ) ) / DAY_IN_SECONDS );

				// If we're closed, it may be permanent.
				if ( $permanent ) {
					$message .= ' ' . __( 'This closure is permanent.', 'wporg-plugins' );
				} elseif ( $days_passed < 60 ) {
					$message .= ' ' . __( 'This closure is temporary, pending a full review.', 'wporg-plugins' );
				}

				// Display close reason if more than 60 days have passed.
				if ( $days_passed >= 60 ) {
					/* translators: %s: plugin close/disable reason */
					$message .= ' ' . sprintf( __( 'Reason: %s.', 'wporg-plugins' ), $close_reason );
				}
			} else {
				$message = __( 'This plugin has been closed and is no longer available for download.', 'wporg-plugins' );
			}

			$message = sprintf( $error_notice, $message );
			break;

		// Fall through.
		default:
			$message = sprintf(
				$error_notice,
				__( 'This plugin has been closed and is no longer available for download.', 'wporg-plugins' )
			);
			break;
	}

	return $message;
}

function the_unconfirmed_releases_notice() {
	$plugin = get_post();

	if ( ! $plugin->release_confirmation || ! current_user_can( 'plugin_admin_edit', $plugin ) ) {
		return;
	}

	$confirmations_required = $plugin->release_confirmation;
	$releases               = Plugin_Directory::get_releases( $plugin ) ?: [];
	$unconfirmed_releases   = wp_list_filter( $confirmed_releases, [ 'confirmed' => false ] );

	if ( ! $unconfirmed_releases ) {
		return;
	}

	printf(
		'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
		sprintf(
			__( 'This plugin has <a href="%s">a pending release that requires confirmation</a>.', 'wporg-plugins' ),
			home_url( '/developers/releases/' ) // TODO: Hardcoded URL.
		)
	);
}

/**
 * Display the ADVANCED Zone.
 */
function the_plugin_advanced_zone() {
	$post = get_post();

	// If the post is closed, this all goes away.
	if ( 'publish' !== $post->post_status ) {
		return;
	}

	echo '<hr>';

	echo '<h2>' . esc_html__( 'Advanced Options', 'wporg-plugins' ) . '</h2>';

	echo '<p>' . esc_html__( 'This section is intended for advanced users and developers only. They are presented here for testing and educational purposes.', 'wporg-plugins' ) . '</p>';

	// Output previous version download.
	the_previous_version_download();

}

/**
 * Displays a select element with links to previous plugin version to download.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_previous_version_download( $post = null ) {
	$post = get_post( $post );

	if ( 'publish' !== $post->post_status ) {
		return;
	}

	$tags = (array) get_post_meta( $post->ID, 'tagged_versions', true );
	// Sort the versions by version.
	usort( $tags, 'version_compare' );
	// We'll want to add a Development Version if it exists.
	$tags[] = 'trunk';
	// Remove the current version, this may be trunk.
	$tags = array_diff( $tags, array( get_post_meta( $post->ID, 'stable_tag', true ) ) );

	if ( empty( $tags ) ) {
		return;
	}

	// List Trunk, followed by the most recent non-stable release.
	$tags = array_reverse( $tags );

	echo '<h4>' . esc_html__( 'Previous Versions', 'wporg-plugins' ) . '</h4>';

	echo '<div class="plugin-notice notice notice-info notice-alt"><p>' . esc_html__( 'Previous versions of plugins may not be secure or stable. They are not recommended for use on production websites.', 'wporg-plugins' ) . '</p></div>';

	echo '<p>' . esc_html__( 'Please select a specific version to download.', 'wporg-plugins' ) . '</p>';

	echo '<select class="previous-versions" onchange="getElementById(\'download-previous-link\').href=this.value;">';
	foreach ( $tags as $version ) {
		$text = ( 'trunk' === $version ? __( 'Development Version', 'wporg-plugins' ) : $version );
		printf( '<option value="%s">%s</option>', esc_attr( Template::download_link( $post, $version ) ), esc_html( $text ) );
	}
	echo '</select> ';

	printf(
		'<a href="%s" id="download-previous-link" class="button">%s</a>',
		esc_url( Template::download_link( $post, reset( $tags ) ) ),
		esc_html__( 'Download', 'wporg-plugins' )
	);
}

/**
 * Display the Danger Zone.
 */
function the_plugin_danger_zone() {
	$post = get_post();

	if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
		return;
	}

	echo '<hr>';

	echo '<h2>' . esc_html__( 'The Danger Zone', 'wporg-plugins' ) . '</h2>';

	echo '<p>' . esc_html__( 'The following features are restricted to plugin committers only. They exist to allow plugin developers more control over their work.', 'wporg-plugins' ) . '</p>';

	echo '<div class="plugin-notice notice notice-error notice-alt"><p>' . esc_html__( 'These features often cannot be undone without intervention. Please do not attempt to use them unless you are absolutely certain. When in doubt, contact the plugins team for assistance.', 'wporg-plugins' ) . '</p></div>';

	// Output the Release Confirmation form.
	the_plugin_release_confirmation_form();

	// Output the transfer form.
	the_plugin_self_transfer_form();

	if ( 'publish' != $post->post_status ) {
		// A reminder of the closed status.
		the_active_plugin_notice();
	} else {
		// Output the self close button.
		the_plugin_self_close_button();
	}

}

/**
 * Displays a form for plugin committers to self-close a plugin. Permanently.
 * It is disabled for plugins with 20,000+ users.
 */
function the_plugin_self_close_button() {
	$post            = get_post();
	$active_installs = (int) get_post_meta( $post->ID, 'active_installs', true );
	$close_link      = false;

	if ( ! current_user_can( 'plugin_admin_edit', $post ) || 'publish' != $post->post_status ) {
		return;
	}

	echo '<h4>' . esc_html__( 'Close This Plugin', 'wporg-plugins' ) . '</h4>';
	echo '<p>' . esc_html__( 'This plugin is currently open. All developers have the ability to close their own plugins at any time.', 'wporg-plugins' ) . '</p>';

	echo '<div class="plugin-notice notice notice-warning notice-alt"><p>';
	if ( $active_installs >= 20000 ) {
		// Translators: %s is the plugin team email address.
		printf( __( '<strong>Notice:</strong> Due to the high volume of users for this plugin it cannot be closed without speaking directly to the plugins team. Please contact <a href="mailto:%1$s">%1$s</a> with a link to the plugin and explanation as to why it should be closed.', 'wporg-plugins' ), 'plugins@wordpress.org' );
	} else {
		$close_link = Template::get_self_close_link( $post );
		_e( '<strong>Warning:</strong> Closing a plugin is intended to be a <em>permanent</em> action. There is no way to reopen a plugin without contacting the plugins team.', 'wporg-plugins' );
	}
	echo '</p></div>';

	if ( $close_link ) {
		echo '<form method="POST" action="' . esc_url( $close_link ) . '" onsubmit="return confirm( jQuery(this).prev(\'.notice\').text() );">';
		// Translators: %s is the plugin name, as defined by the plugin itself.
		echo '<p><input class="button" type="submit" value="' . esc_attr( sprintf( __( 'I understand, please close %s.', 'wporg-plugins' ), get_the_title() ) ) . '" /></p>';
		echo '</form>';
	}
}

/**
 * Display a form to allow a plugin owner to transfer the ownership of a plugin to someone else.
 * This does NOT remove their commit ability.
 */
function the_plugin_self_transfer_form() {
	$post = get_post();

	if (
		! current_user_can( 'plugin_admin_edit', $post ) ||
		'publish' != $post->post_status
	) {
		return;
	}

	echo '<h4>' . esc_html__( 'Transfer This Plugin', 'wporg-plugins' ) . '</h4>';

	if ( get_current_user_id() != $post->post_author ) {
		$owner = get_user_by( 'id', $post->post_author );
		/* translators: %s: Name of plugin owner */
		echo '<p>' . esc_html( sprintf(
			__( 'This plugin is currently owned by %s, they can choose to transfer ownership rights of the plugin to you.', 'wporg-plugins' ),
			$owner->display_name
		) ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'You are the current owner of this plugin. You may transfer those rights to another person at any time, provided they have commit access to this plugin.', 'wporg-plugins' ) . '</p>';

	echo '<div class="plugin-notice notice notice-warning notice-alt"><p>' . __( '<strong>Warning:</strong> Transferring a plugin is intended to be <em>permanent</em>. There is no way to get plugin ownership back without contacting the plugin team.', 'wporg-plugins' ) . '</p></div>';

	$users = [];
	foreach ( Tools::get_plugin_committers( $post->post_name ) as $user_login ) {
		$user = get_user_by( 'login', $user_login );
		if ( $user->ID != get_current_user_id() ) {
			$users[] = $user;
		}
	}
	if ( ! $users ) {
		echo '<div class="plugin-notice notice notice-error notice-alt"><p>' . __( 'To transfer a plugin, you must first add the new owner as a committer.', 'wporg-plugins' ) . '</p></div>';
		return;
	}

	echo '<form method="POST" action="' . esc_url( Template::get_self_transfer_link() ) . '" onsubmit="return ( 0 != document.getElementById(\'transfer-new-owner\').value ) && confirm( jQuery(this).prev(\'.notice\').text() );">';
	echo '<p><label for="new_owner">' . esc_html__( 'New Owner', 'wporg-plugins' ) . '</label><br>';
	echo '<select id="transfer-new-owner" name="new_owner">';
	echo '<option value="0">---</option>';
	foreach ( $users as $user ) {
		printf(
			'<option value="%d">%s</option>' . "\n",
			esc_attr( $user->ID ),
			esc_html( $user->display_name . ' (' . $user->user_login . ')' )
		);
	}
	echo '</select></p>';
	// Translators: %s is the plugin name, as defined by the plugin itself.
	echo '<p><input class="button" type="submit" value="' . esc_attr( sprintf( __( 'Please transfer %s.', 'wporg-plugins' ), get_the_title() ) ) . '" /></p>';
	echo '</form>';

}

function the_plugin_release_confirmation_form() {
	$post = get_post();

	// Temporary: Plugin Reviewers only.
	if ( ! current_user_can( 'edit_post', $post ) ) {
		return;
	}

	if (
		! current_user_can( 'plugin_admin_edit', $post ) ||
		'publish' != $post->post_status
	) {
		return;
	}

	$confirmations_required = $post->release_confirmation;

	echo '<h4>' . esc_html__( 'Release Confirmation', 'wporg-plugins' ) . '</h4>';
	if ( $confirmations_required ) {
		echo '<p>' . __( 'Release confirmations for this plugin are <strong>enabled</strong>.', 'wporg-plugins' ) . '</p>';
	} else {
		echo '<p>' . __( 'Release confirmations for this plugin are <strong>disabled</strong>', 'wporg-plugins' ) . '</p>';
	}
	echo '<p>' . esc_html__( 'All future releases will require email confirmation before being made available. This increases security and ensures that plugin releases are only made when intended.', 'wporg-plugins' ) . '</p>';

	if ( ! $confirmations_required && 'trunk' === $post->stable_tag ) {
		echo '<div class="plugin-notice notice notice-warning notice-alt"><p>';
			_e( "Release confirmations currently require tagged releases, as you're releasing from trunk they cannot be enabled.", 'wporg-plugins' );
		echo '</p></div>';

	} else if ( ! $confirmations_required ) {
		echo '<div class="plugin-notice notice notice-warning notice-alt"><p>';
			_e( '<strong>Warning:</strong> Enabling release confirmations is intended to be a <em>permanent</em> action. There is no way to disable this without contacting the plugins team.', 'wporg-plugins' );
		echo '</p></div>';

		echo '<form method="POST" action="' . esc_url( Template::get_enable_release_confirmation_link() ) . '" onsubmit="return confirm( jQuery(this).prev(\'.notice\').text() );">';
		echo '<p><input class="button" type="submit" value="' . esc_attr__( 'I understand, please enable release confirmations.', 'wporg-plugins' ) . '" /></p>';
		echo '</form>';

	} else {
		/* translators: 1: plugins@wordpress.org */
		echo '<p>' . sprintf( __( 'To disable release confirmations, please contact the plugins team by emailing %s.', 'wporg-plugins' ), 'plugins@wordpress.org' ) . '</p>';
	}
}
