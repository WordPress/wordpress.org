<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

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
		<script>
			jQuery( '.plugin-favorite-heart' )
				.on( 'click touchstart animationend', function () {
					jQuery( this ).toggleClass( 'is-animating' );
				} )
				.on( 'click', function () {
					jQuery( this ).toggleClass( 'favorited' );
				} );
		</script>
	</div>
	<?php
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
			$tested_up_to             = (string) get_post_meta( get_post( $post )->ID, 'tested', true );
			$version_to_check_against = (string) ( get_current_major_wp_version() - 0.2 );
			if ( version_compare( $version_to_check_against, $tested_up_to, '>' ) ) {
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
			$message = current_user_can( 'plugin_approve' )
				? __( 'This plugin is disabled (closed, but actively serving updates).', 'wporg-plugins' )
				: __( 'This plugin has been closed for new installs.', 'wporg-plugins' );

			$message = sprintf( $error_notice, $message );
			break;

		case 'closed':
			$closed_date = get_post_meta( get_the_ID(), 'plugin_closed_date', true );
			if ( ! empty( $closed_date ) ) {
				/* translators: Closing date. */
				$message = sprintf( __( 'This plugin was closed on %s and is no longer available for download.', 'wporg-plugins' ), mysql2date( get_option( 'date_format' ), $closed_date ) );
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

	echo '<h5>' . esc_html__( 'Previous Versions', 'wporg-plugins' ) . '</h5>';
	echo '<div class="plugin-notice notice notice-info notice-alt"><p>' . esc_html__( 'Previous versions of this plugin may not be secure or stable and are available for testing purposes only.', 'wporg-plugins' ) . '</p></div>';

	echo '<select class="previous-versions" onchange="getElementById(\'download-previous-link\').href=this.value;">';
	foreach ( $tags as $version ) {
		$text = ( 'trunk' === $version ? esc_html__( 'Development Version', 'wporg-plugins' ) : $version );
		printf( '<option value="%s">%s</option>', esc_attr( Template::download_link( $post, $version ) ), esc_html( $text ) );
	}
	echo '</select> ';

	printf(
		'<a href="%s" id="download-previous-link" class="button">%s</a>',
		esc_url( Template::download_link( $post, reset( $tags ) ) ),
		esc_html__( 'Download', 'wporg-plugins' )
	);
}
