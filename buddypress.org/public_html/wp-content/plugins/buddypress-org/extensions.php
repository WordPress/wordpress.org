<?php

/**
 * Unhook the single user filter, causing issues with theme compat.
 *
 * @todo research this later
 */
function bporg_unhook_single_user_filter() {
	remove_filter( 'bbp_is_single_user', 'bbp_filter_is_single_user', 10, 1 );
}
add_action( 'bp_init', 'bporg_unhook_single_user_filter', 11 );

/**
 * Activity requires diplay name be different than user name to prevent spam
 */
function bporg_activity_requires_display_name( &$activity_item ) {

	// Bail if no user ID
	if ( empty( $activity_item->user_id ) )
		return false;

	// Load user
	$user        = get_user_by( 'id', $activity_item->user_id );
	$username    = $user->user_login;
	$displayname = bp_core_get_user_displayname( $activity_item->user_id );

	// Unset the component if usernames are empty or the same
	if ( empty( $displayname ) || ( $username == $displayname ) ) {
		$activity_item->component = false;
	}
}
add_filter( 'bp_activity_before_save', 'bporg_activity_requires_display_name' );

/** User Search ***************************************************************/

function bporg_users_count_sql( $query, $search_terms ) {
	global $bp;

	return "SELECT DISTINCT count(user_id) {$bp->profile->table_name_data} WHERE value LIKE '%%$search_terms%%' ORDER BY value ASC";
}
add_filter( 'bp_core_search_users_count_sql', 'bporg_users_count_sql', 10, 2 );

function bporg_users_sql( $query, $search_terms, $pag_sql ) {
	global $bp;

	return "SELECT DISTINCT user_id FROM {$bp->profile->table_name_data} WHERE value LIKE '%%$search_terms%%' ORDER BY value ASC{$pag_sql}";
}
add_filter( 'bp_core_search_users_sql', 'bporg_users_sql', 10, 3 );

/** User Joins ****************************************************************/

function bporg_activity_comments_user_filter( $sql ) {
	return str_replace( 'minibb_users u,', '', str_replace( 'u.ID = a.user_id AND', '', str_replace( ', u.user_email, u.user_nicename, u.user_login, u.display_name', '', $sql ) ) );
}
add_filter( 'bp_activity_comments_user_join_filter', 'bporg_activity_comments_user_filter' );

function bporg_activity_user_filter( $sql ) {
	return str_replace( 'LEFT JOIN minibb_users u ON a.user_id = u.ID', '', str_replace( ', u.user_email, u.user_nicename, u.user_login, u.display_name', '', $sql ) );
}
add_filter( 'bp_activity_get_user_join_filter', 'bporg_activity_user_filter' );

function bporg_group_admin_filter( $sql ) {
	return str_replace( 'u.ID = m.user_id AND ', '', str_replace( 'minibb_users u, ', '', str_replace( 'u.ID as user_id, u.user_login, u.user_email, u.user_nicename, ', 'm.user_id, ', $sql ) ) );
}
add_filter( 'bp_group_admin_mods_user_join_filter', 'bporg_group_admin_filter' );

function bporg_group_members_filter( $sql ) {
	return str_replace( ', u.user_login, u.user_nicename, u.user_email', '', str_replace( 'minibb_users u, ', '', str_replace( 'u.ID = m.user_id AND u.ID = pd.user_id', 'm.user_id = pd.user_id', $sql ) ) );
}
add_filter( 'bp_group_members_user_join_filter', 'bporg_group_members_filter' );

function bporg_group_members_count_filter( $sql ) {
	return str_replace( 'u.ID = m.user_id AND ', '', str_replace( 'minibb_users u, ', '', str_replace( 'u.ID as user_id, u.user_login, u.user_email, u.user_nicename, ', 'm.user_id, ', $sql ) ) );
}
add_filter( 'bp_group_members_count_user_join_filter', 'bporg_group_members_count_filter' );

/** Other Functions ***********************************************************/

/**
 * Function (hooked to 'init') to handle redirect logic from various rearranged
 * BuddyPress.org root site page locations.
 *
 * @author johnjamesjacoby
 * @return if not BuddyPress.org root site
 */
function bporg_redirect() {

	// Explode the request (could use parse_url() here too)
	$uri_chunks = explode( '/', $_SERVER['REQUEST_URI'] );

	// Redirect /forums/ to /support/
	if ( $uri_chunks[1] === 'forums' && empty( $uri_chunks[2] ) ) {
		bp_core_redirect( home_url( '/support/' ) );
	}

	// Redirect members directory to root to block heavy paginated user queries
	if ( ( $uri_chunks[1] === 'members' ) && empty( $uri_chunks[2] ) ) {
		bp_core_redirect( home_url( '/' ) );
	}

	// Redirect old members profile pages to
	if ( ( $uri_chunks[1] === 'community' ) && ( $uri_chunks[2] === 'members' ) && ! empty( $uri_chunks[3] ) ) {
		bp_core_redirect( home_url( '/members/' . $uri_chunks[3] . '/' ) );
	}

	// Redirect old plugin groups to deprecated plugin forums
	if ( ( $uri_chunks[1] === 'community' ) && ( $uri_chunks[2] === 'groups' ) ) {

		// Single group topic redirect
		if ( !empty( $uri_chunks[5] ) && ( $uri_chunks[5] === 'topic' ) ) {
			bp_core_redirect( home_url( '/support/topic/' . $uri_chunks[6] . '/' ) );

		// Single group forum redirect
		} elseif ( empty( $uri_chunks[4] ) || ( $uri_chunks[4] === 'forum' ) ) {

			// Use legacy group slug
			if ( ! in_array( $uri_chunks[3], array( 'gallery', 'how-to-and-troubleshooting' ) ) ) {
				bp_core_redirect( home_url( '/support/forum/plugin-forums/' . $uri_chunks[3] . '/' ) );

			// Root forums, maybe with new slug
			} else {

				// New BuddyPress project forums locations
				switch ( $uri_chunks[3] ) {
					case 'gallery' :
						$url = '/support/forum/your-buddypress/';
						break;
					case 'how-to-and-troubleshooting' :
						$url = '/support/forum/how-to/';
						break;
					case 'creating-and-extending' :
						$url = '/support/forum/extending/';
						break;
					case 'requests-and-feedback' :
						$url = '/support/forum/feedback/';
						break;
					case 'buddypress' :
						$url = '/support/forum/installing/';
						break;
					case 'third-party-plugins' :
						$url = '/support/forum/plugins/';
						break;
					default:
						$url = trailingslashit( 'http://buddypress.org/support/forum/' . $uri_chunks[3] );
						break;
				}
				bp_core_redirect( home_url( $url ) );
			}
		}
	}

	// Redirect /support/topics/ to /support/
	if ( $uri_chunks[1] === 'support' && ( !empty( $uri_chunks[2] ) && ( 'topics' === $uri_chunks[2] ) ) ) {
		bp_core_redirect( home_url( '/support/' ) );
	}
}
if ( (bool) strstr( $_SERVER['HTTP_HOST'], 'buddypress' ) && ! is_admin() && defined( 'WP_USE_THEMES' ) && WP_USE_THEMES ) {
	add_action( 'init', 'bporg_redirect', 1 ); // before bp_init
}

function wporg_profiles_redirect() {
	$uri_chunks = explode( '/', trim( $_SERVER['REQUEST_URI'], '/' ) );
	if ( 'users' == $uri_chunks[0] ) {
		if ( ! empty( $uri_chunks[1] ) ) {
			wp_redirect( 'http://profiles.wordpress.org/' . $uri_chunks[1] . '/', 301 );
		} else {
			wp_redirect( 'http://wordpress.org/' );
		}
		exit;
	}

	if ( get_user_by( 'slug', $uri_chunks[0] ) ) {
		return;
	}

	if ( $user = get_user_by( 'login', urldecode( $uri_chunks[0] ) ) ) {
		wp_redirect( 'http://profiles.wordpress.org/' . $user->user_nicename . '/', 301 );
		exit;
	} elseif ( $user = get_user_by( 'login', str_replace( ' ', '', urldecode( $uri_chunks[0] ) ) ) ) {
		wp_redirect( 'http://profiles.wordpress.org/' . $user->user_nicename . '/', 301 );
		exit;
	}

	// For strange reasons, BP uses 'wp' rather than template_redirect.
	add_action( 'wp', 'wporg_profiles_maybe_template_redirect', 0 );
}

function wporg_profiles_maybe_template_redirect() {
	if ( is_robots() || is_feed() || is_trackback() ) {
		return;
	}

	ob_start();
	wp_redirect( 'http://wordpress.org/' );
	exit;
}

if ( 'profiles.wordpress.org' == $_SERVER['HTTP_HOST'] && ! is_admin() && defined( 'WP_USE_THEMES' ) && WP_USE_THEMES ) {
	add_action( 'init', 'wporg_profiles_redirect', 9 ); // before bp_init
	add_filter( 'bp_do_redirect_canonical', '__return_false' ); // Overrides #BP1741
}

function bporg_insert_at_mention( $content, $activity_obj ) {
	global $bp;

	if ( bp_is_my_profile() || !$bp->displayed_user->id )
		return $content;

	if ( 'activity_update' != $activity_obj->type )
		return $content;

	return '<a href="' . $bp->displayed_user->domain . '">@' . bp_core_get_username( $bp->displayed_user->id ) . '</a> ' . $content;
}
add_filter( 'bp_activity_content_before_save', 'bporg_insert_at_mention', 10, 2 );

function bporg_activity_with_others_filter( $qs ) {
	global $bp;

	$user_id = ( bp_displayed_user_id() ) ? bp_displayed_user_id() : bp_loggedin_user_id();

	/* Only filter on directory pages (no action) and the following scope on activity object. */
	if ( ( 'dashboard' == $bp->current_action && strpos( $qs, 'personal' ) !== false ) || 'just-me' == $bp->current_action ) {
		if ( strpos( $qs, 'filter' ) === false )
			$qs .= '&search_terms=@' . bp_core_get_username( $user_id ) . '<';

		return $qs;
	} else {
		return $qs;
	}
}
//add_filter( 'bp_ajax_querystring', 'bporg_activity_with_others_filter', 11 );

function bporg_fix_activity_redirect( $redirect, $activity ) {
	global $bp;

	$redirect = false;
	/* Redirect based on the type of activity */
	if ( $activity->component == $bp->groups->id ) {
		if ( $activity->user_id ) {
			$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->name . '/' . $activity->id . '/';
		}
	} else
		$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->name . '/' . $activity->id;

	return $redirect;
}
add_filter( 'bp_activity_permalink_redirect_url', 'bporg_fix_activity_redirect', 10, 2 );

// Borrowed from bbPress
function bporg_encodeit( $matches ) {
	$text = trim($matches[2]);
	$text = htmlspecialchars($text, ENT_QUOTES);
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace("|\n\n\n+|", "\n\n", $text);
	$text = str_replace('&amp;amp;', '&amp;', $text);
	$text = str_replace('&amp;lt;', '&lt;', $text);
	$text = str_replace('&amp;gt;', '&gt;', $text);
	$text = "<code>$text</code>";
	if ( "`" != $matches[1] )
		$text = "<pre>$text</pre>";
	return $text;
}

// Borrowed from bbPress
function bporg_decodeit( $matches ) {
	$text = $matches[2];
	$trans_table = array_flip(get_html_translation_table(HTML_ENTITIES));
	$text = strtr($text, $trans_table);
	$text = str_replace('<br />', '<coded_br />', $text);
	$text = str_replace('<p>', '<coded_p>', $text);
	$text = str_replace('</p>', '</coded_p>', $text);
	$text = str_replace(array('&#38;','&amp;'), '&', $text);
	$text = str_replace('&#39;', "'", $text);
	if ( '<pre><code>' == $matches[1] )
		$text = "\n$text\n";
	return "`$text`";
}

// Borrowed from bbPress. Makes code in backticks work, both in forum posts and in activity updates.
function bporg_code_trick( $text ) {
	$text = str_replace(array("\r\n", "\r"), "\n", $text);
	$text = preg_replace_callback("|(`)(.*?)`|", 'bporg_encodeit', $text);
	$text = preg_replace_callback("!(^|\n)`(.*?)`!s", 'bporg_encodeit', $text);
	return $text;
}
add_filter( 'bp_get_activity_content_body', 'bporg_code_trick', 1 );

function bporg_redirect_to_search() {
	if ( bp_is_current_component( 'search' ) ) {
		$terms = isset( $_REQUEST['s'] ) ? stripslashes( $_REQUEST['s'] ) : '';
		bp_core_redirect( add_query_arg( array( 's' => $terms ), bp_get_root_domain() ) );
	}
}
add_action( 'bp_init', 'bporg_redirect_to_search', 3 );
