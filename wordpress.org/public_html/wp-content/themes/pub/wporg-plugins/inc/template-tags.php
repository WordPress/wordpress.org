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

// Returns an absolute url to the current url, no matter what that actually is.
function wporg_plugins_self_link() {
	$site_path = preg_replace( '!^' . preg_quote( parse_url( home_url(), PHP_URL_PATH ), '!' ) . '!', '', $_SERVER['REQUEST_URI'] );
	return home_url( $site_path );
}

function wporg_plugins_template_last_updated() {
	return '<span title="' . get_the_time('Y-m-d') . '">' . sprintf( _x( '%s ago', 'wporg-plugins' ), human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ) . '</span>';
}

function wporg_plugins_template_compatible_up_to() {
	$tested = get_post_meta( get_the_id(), 'tested', true ) ;
	if ( ! $tested ) {
		$tested = _x( 'unknown', 'unknown version', 'wporg-plugins' );
	}
	return esc_html( $tested );
}

function wporg_plugins_template_requires() {
	return esc_html( get_post_meta( get_the_id(), 'requires', true ) );
}

function wporg_plugins_the_version() {
	return esc_html( get_post_meta( get_the_id(), 'version', true ) );
}

function wporg_plugins_download_link() {
	return esc_url( Template::download_link( get_the_id() ) );
}

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
	$and_more = false;
	foreach ( $authors as $user ) {
		$author_links[] = sprintf( '<a href="%s">%s</a>', 'https://profiles.wordpress.org/' . $user->user_nicename . '/', $user->display_name );
		if ( count( $author_links ) > 5 ) {
			$and_more = true;
			break;
		}
	}

	if ( $and_more ) {
		return sprintf( '<cite> By: %s, and others.</cite>', implode(', ', $author_links ) );
	} else {
		return sprintf( '<cite> By: %s</cite>', implode(', ', $author_links ) );
	}
}


/**
 * Displays a plugin banner.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_plugin_banner( $post = null ) {
	echo Template::get_plugin_banner( $post, 'html' );
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
				printf( __( 'Unfavorite %s', 'wporg-plugins' ), get_the_title() );
			} else {
				/* translators: %s: plugin name */
				printf( __( 'Favorite %s', 'wporg-plugins' ), get_the_title() );
			}
			?>
		</span>
	</a>
	<script>
		jQuery( '.plugin-favorite-heart' )
			.on( 'click touchstart animationend', function() {
				jQuery( this ).toggleClass( 'is-animating' );
			} )
			.on( 'click', function() {
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
	$author = esc_html( Template::encode( $author ) );
	$author = $url ? '<a class="url fn n" rel="nofollow" href="' . esc_url( $url ) . '">' . $author . '</a>' : $author;

	printf( _x( 'By %s', 'post author', 'wporg-plugins' ), '<span class="author vcard">' . $author . '</span>' );
}

/**
 * Displays a descriptive status notice for active plugins.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_active_plugin_notice( $post = null ) {
	if ( ! in_array( get_post_status( $post ), ['rejected', 'closed'], true ) ) {
		echo get_plugin_status_notice( $post );
	};
}

/**
 * Displays a descriptive status notice for inactive plugins.
 *
 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 */
function the_closed_plugin_notice( $post = null ) {
	echo get_closed_plugin_notice( $post );
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

	if ( in_array( get_post_status( $post ), ['rejected', 'closed'], true ) ) {
		$notice = get_plugin_status_notice( $post );

		if ( get_current_user_id() == $post->post_author ) {
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
			if ( time() - get_post_modified_time() > 2 * YEAR_IN_SECONDS ) {
				$message = sprintf(
					$warning_notice,
					__( 'This plugin <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-plugins' )
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
