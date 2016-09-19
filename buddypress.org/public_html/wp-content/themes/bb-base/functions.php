<?php

/**
 * Set up the content width value based on the theme's design.
 */
if ( ! isset( $content_width ) )
	$content_width = 700;

// Hide breadcrumbs
add_filter( 'bbp_no_breadcrumb',   '__return_true' );
add_filter( 'bbp_show_lead_topic', '__return_true' );

// Always show admin bar
show_admin_bar( true );

/**
 * Are we looking at a codex site
 *
 * @return bool
 */
function bb_base_is_codex() {
	return (bool) strstr( $_SERVER['HTTP_HOST'], 'codex' );
}

/**
 * Are we looking at a buddypress.org site?
 *
 * @return bool
 */
function bb_base_is_buddypress() {
	$retval = (bool) strstr( $_SERVER['HTTP_HOST'], 'buddypress' );
	return (bool) apply_filters( 'bb_base_is_buddypress', $retval );
}

/**
 * Are we looking at a bbpress.org site?
 *
 * @return bool
 */
function bb_base_is_bbpress() {
	$retval = (bool) strstr( $_SERVER['HTTP_HOST'], 'bbpress' );
	return (bool) apply_filters( 'bb_base_is_bbpress', $retval );
}


// Include in Codex code on codex sites
if ( bb_base_is_codex() ) {
	include( get_template_directory() . '/functions-codex.php' );
}

/**
 * Enqueue parent theme CSS
 */
function bb_base_register_stylesheets() {

	// Version of CSS
	$version = '20160919';

	// Base theme styling
	wp_enqueue_style( 'bb-base',   get_template_directory_uri()   . '/style.css', false,                         $version, 'screen' );

	// Handle root styling for buddypress/bbpress
	if ( bb_base_is_bbpress() ) {
		$root =	'style-bbpress.css';
	} elseif ( bb_base_is_buddypress() ) {
		$root =	'style-buddypress.css';
	}
	wp_enqueue_style( 'bb-root',   get_template_directory_uri()   . '/' . $root,  array( 'bb-base' ),            $version, 'screen' );

	// Any additional styling from the currently active theme
	wp_enqueue_style( 'bb-child',  get_stylesheet_directory_uri() . '/style.css', array( 'bb-base', 'bb-root' ), $version, 'screen' );
}
add_action( 'wp_enqueue_scripts', 'bb_base_register_stylesheets' );

function bb_base_topic_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Forum Search', 'bbporg'); ?></h3>
			<label class="screen-reader-text hidden" for="ts"><?php _e( 'Search for:', 'bbporg' ); ?></label>
			<input type="text" value="<?php echo bb_base_topic_search_query(); ?>" name="ts" id="ts" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'bbporg' ); ?>" />
		</div>
	</form>

<?php
}

function bb_base_reply_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Reply Search', 'bbporg'); ?></h3>
			<label class="screen-reader-text hidden" for="rs"><?php _e( 'Search for:', 'bbporg' ); ?></label>
			<input type="text" value="<?php echo bb_base_reply_search_query(); ?>" name="rs" id="rs" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'bbporg' ); ?>" />
		</div>
	</form>

<?php
}

function bb_base_plugin_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Plugin Search', 'bbporg'); ?></h3>
			<label class="screen-reader-text hidden" for="ps"><?php _e( 'Search for:', 'bbporg' ); ?></label>
			<input type="text" value="<?php echo bb_base_plugin_search_query(); ?>" name="ps" id="ts" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'bbporg' ); ?>" />
		</div>
	</form>

<?php
}

function bb_base_topic_search_query( $escaped = true ) {

	if ( empty( $_GET['ts'] ) ) {
		return false;
	}

	$query = apply_filters( 'bb_base_topic_search_query', $_GET['ts'] );
	if ( true === $escaped ) {
		$query = stripslashes( esc_attr( $query ) );
	}

	return $query;
}

function bb_base_reply_search_query( $escaped = true ) {

	if ( empty( $_GET['rs'] ) ) {
		return false;
	}

	$query = apply_filters( 'bb_base_reply_search_query', $_GET['rs'] );
	if ( true === $escaped ) {
		$query = stripslashes( esc_attr( $query ) );
	}

	return $query;
}

function bb_base_plugin_search_query( $escaped = true ) {

	if ( empty( $_GET['ps'] ) ) {
		return false;
	}

	$query = apply_filters( 'bb_base_plugin_search_query', $_GET['ps'] );
	if ( true === $escaped ) {
		$query = stripslashes( esc_attr( $query ) );
	}

	return $query;
}

function bb_base_single_topic_description() {

	// Validate topic_id
	$topic_id = bbp_get_topic_id();

	// Unhook the 'view all' query var adder
	remove_filter( 'bbp_get_topic_permalink', 'bbp_add_view_all' );

	// Build the topic description
	$voice_count = bbp_get_topic_voice_count   ( $topic_id, true );
	$reply_count = bbp_get_topic_replies_link  ( $topic_id );
	$time_since  = bbp_get_topic_freshness_link( $topic_id );

	// Singular/Plural
	$voice_count = sprintf( _n( '%s participant', '%s participants', $voice_count, 'bbpress' ), bbp_number_format( $voice_count ) );
	$last_reply  = bbp_get_topic_last_active_id( $topic_id );

	?>

	<li class="topic-forum">In: <a href="<?php bbp_forum_permalink( bbp_get_topic_forum_id() ); ?>"><?php bbp_topic_forum_title(); ?></a></li>
	<?php if ( !empty( $reply_count ) ) : ?><li class="reply-count"><?php echo $reply_count; ?></li><?php endif; ?>
	<?php if ( !empty( $voice_count ) ) : ?><li class="voice-count"><?php echo $voice_count; ?></li><?php endif; ?>
	<?php if ( !empty( $last_reply  ) ) : ?>
		<li class="topic-freshness-author"><?php printf( __( 'Last reply from: %s', 'bbporg' ), bbp_get_author_link( array( 'type' => 'name', 'post_id' => $last_reply, 'size' => '15' ) ) ); ?></li>
	<?php endif; ?>
	<?php if ( !empty( $time_since  ) ) : ?><li class="topic-freshness-time"><?php printf( __( 'Last activity: %s', 'bbporg' ), $time_since ); ?></li><?php endif; ?>
	<?php if ( is_user_logged_in() ) : ?>
		<?php $_topic_id = bbp_is_reply_edit() ? bbp_get_reply_topic_id() : $topic_id; ?>
		<li class="topic-subscribe"><?php bbp_topic_subscription_link( array( 'before' => '', 'topic_id' => $_topic_id ) ); ?></li>
		<li class="topic-favorite"><?php bbp_topic_favorite_link( array( 'topic_id' => $_topic_id ) ); ?></li>
	<?php endif;
}

function bb_base_single_forum_description() {

	// Validate forum_id
	$forum_id = bbp_get_forum_id();

	// Unhook the 'view all' query var adder
	remove_filter( 'bbp_get_forum_permalink', 'bbp_add_view_all' );

	// Get some forum data
	$topic_count = bbp_get_forum_topic_count( $forum_id, true, true );
	$reply_count = bbp_get_forum_reply_count( $forum_id, true, true );
	$last_active = bbp_get_forum_last_active_id( $forum_id );

	// Has replies
	if ( !empty( $reply_count ) ) {
		$reply_text = sprintf( _n( '%s reply', '%s replies', $reply_count, 'bbpress' ), bbp_number_format( $reply_count ) );
	}

	// Forum has active data
	if ( !empty( $last_active ) ) {
		$topic_text      = bbp_get_forum_topics_link( $forum_id );
		$time_since      = bbp_get_forum_freshness_link( $forum_id );

	// Forum has no last active data
	} else {
		$topic_text      = sprintf( _n( '%s topic', '%s topics', $topic_count, 'bbpress' ), bbp_number_format( $topic_count ) );
	}
	?>

	<?php if ( bbp_get_forum_parent_id() ) : ?><li class="topic-parent">In: <a href="<?php bbp_forum_permalink( bbp_get_forum_parent_id() ); ?>"><?php bbp_forum_title( bbp_get_forum_parent_id() ); ?></a></li><?php endif; ?>
	<?php if ( !empty( $topic_count ) ) : ?><li class="topic-count"><?php echo $topic_text; ?></li><?php endif; ?>
	<?php if ( !empty( $reply_count ) ) : ?><li class="reply-count"><?php echo $reply_text; ?></li><?php endif; ?>
	<?php if ( !empty( $last_active  ) ) : ?>
		<li class="forum-freshness-author"><?php printf( __( 'Last post by: %s', 'bbporg' ), bbp_get_author_link( array( 'type' => 'name', 'post_id' => $last_active ) ) ); ?></li>
	<?php endif; ?>
	<?php if ( !empty( $time_since  ) ) : ?><li class="forum-freshness-time"><?php printf( __( 'Last activity: %s', 'bbporg' ), $time_since ); ?></li><?php endif; ?>
	<?php if ( is_user_logged_in() ) : ?>
		<li class="forum-subscribe"><?php bbp_forum_subscription_link( array( 'forum_id' => $forum_id ) ); ?></li>
	<?php endif;
}

/** Plugins *******************************************************************/

function bb_base_get_plugins( $page = 1, $search = false, $tag = 'bbpress' ) {
	$args    = array( 'tag' => $tag, 'page' => (int) $page, 'per_page' => 10, 'search' => $search );
	$plugins = bb_base_plugins_api('query_plugins', $args);

	foreach( $plugins->plugins as $plugin_key => $plugin_value ) {
		if ( $plugins->plugins[$plugin_key]->slug == 'mingle' ) {
			unset( $plugins->plugins[$plugin_key] );
			$plugins->info['results']--;
			continue;
		}

		$plugins->plugins[$plugin_key]->rating_html = bb_base_get_plugin_rating_html( $plugins->plugins[$plugin_key]->rating, $plugins->plugins[$plugin_key]->num_ratings );
	}

	return $plugins;
}

/**
 * See /wp-admin/includes/plugins-install.php
 */
function bb_base_plugins_api( $action, $args = null ) {

	if ( is_array($args) ) {
		$args = (object)$args;
	}

	if ( !isset( $args->per_page ) ) {
		$args->per_page = 19;
	}

	$args = apply_filters( 'plugins_api_args', $args, $action        );
	$res  = apply_filters( 'plugins_api',      false, $action, $args );

	// Cache individual plugin requests and query requests

	if ( empty( $res ) ) {
		$request = wp_remote_post( 'https://api.wordpress.org/plugins/info/1.0/', array( 'body' => array( 'action' => $action, 'request' => serialize( $args ) ) ) );

		if ( is_wp_error( $request ) ) {
			$res = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $request->get_error_message() );
		} else {
			$res = unserialize( $request['body'] );
			if ( empty( $res ) ) {
				$res = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $request['body'] );
			}
		}
	} elseif ( !is_wp_error( $res ) ) {
		$res->external = true;
	}

	return apply_filters( 'plugins_api_result', $res, $action, $args );
}

function bb_base_get_plugin_rating_html( $rating = false, $num_ratings = 0 ) {

	if ( empty( $rating ) ) {
		return false;
	}

	$bb_base_uri = get_template_directory_uri();
	$rating_html = '
	<span class="star-holder" title="' . esc_attr( sprintf( _n( '(based on %s rating)', '(based on %s ratings)', $num_ratings ), number_format_i18n( $num_ratings ) ) ) . '">';

	$star1 = '<img src="' . $bb_base_uri . '/images/star_off.png' . '" alt="' . __('1 stars') . '" />';
	$star2 = '<img src="' . $bb_base_uri . '/images/star_off.png' . '" alt="' . __('2 stars') . '" />';
	$star3 = '<img src="' . $bb_base_uri . '/images/star_off.png' . '" alt="' . __('3 stars') . '" />';
	$star4 = '<img src="' . $bb_base_uri . '/images/star_off.png' . '" alt="' . __('4 stars') . '" />';
	$star5 = '<img src="' . $bb_base_uri . '/images/star_off.png' . '" alt="' . __('5 stars') . '" />';

	if ( $rating <= 7 )
		$star1 = '<img src="' . $bb_base_uri . '/images/star_half.png' . '" alt="' . __('1 stars') . '" />';
	else if ( $rating >= 20 )
		$star1 = '<img src="' . $bb_base_uri . '/images/star.png' . '" alt="' . __('1 stars') . '" />';

	if ( $rating > 20 && $rating <= 27 )
		$star2 = '<img src="' . $bb_base_uri . '/images/star_half.png' . '" alt="' . __('2 stars') . '" />';
	else if ( $rating >= 40 )
		$star2 = '<img src="' . $bb_base_uri . '/images/star.png' . '" alt="' . __('2 stars') . '" />';

	if ( $rating > 40 && $rating <= 47 )
		$star3 = '<img src="' . $bb_base_uri . '/images/star_half.png' . '" alt="' . __('3 stars') . '" />';
	else if ( $rating >= 60 )
		$star3 = '<img src="' . $bb_base_uri . '/images/star.png' . '" alt="' . __('3 stars') . '" />';

	if ( $rating > 60 && $rating <= 67 )
		$star4 = '<img src="' . $bb_base_uri . '/images/star_half.png' . '" alt="' . __('4 stars') . '" />';
	else if ( $rating >= 80 )
		$star4 = '<img src="' . $bb_base_uri . '/images/star.png' . '" alt="' . __('4 stars') . '" />';

	if ( $rating > 80 && $rating <= 87 )
		$star5 = '<img src="' . $bb_base_uri . '/images/star_half.png' . '" alt="' . __('5 stars') . '" />';
	else if ( $rating >= 93 )
		$star5 = '<img src="' . $bb_base_uri . '/images/star.png' . '" alt="' . __('5 stars') . '" />';

	$rating_html .= '<span class="star star1">' . $star1 . '</span>';
	$rating_html .= '<span class="star star2">' . $star2 . '</span>';
	$rating_html .= '<span class="star star3">' . $star3 . '</span>';
	$rating_html .= '<span class="star star4">' . $star4 . '</span>';
	$rating_html .= '<span class="star star5">' . $star5 . '</span>';

	return $rating_html;
}

/** Caching *******************************************************************/

/**
 * Output front page topics
 *
 * @author johnjamesjacoby
 * @uses bb_base_get_homepage_topics()
 * @param mixed $args
 * @return void
 */
function bb_base_homepage_topics( $args = false ) {
	echo bb_base_get_homepage_topics( $args );
}

/**
 * Get front page topics output and stash it for an hour
 *
 * @author johnjamesjacoby
 * @param mixed $args
 * @return HTML
 */
function bb_base_get_homepage_topics( $args = false ) {

	// Transient settings
	$expiration    = HOUR_IN_SECONDS;
	$transient_key = 'bb_base_homepage_topics' . ( is_ssl() ? '_ssl' : '' );
	$output        = get_transient( $transient_key );

	// No transient found, so query for topics again
	if ( false === $output ) {

		// Setup some default topics query args
		$r = wp_parse_args( $args, array(
			's'              => '',
			'posts_per_page' => 5,
			'max_num_pages'  => 1,
			'paged'          => 1,
			'show_stickies'  => false
		) );

		// Look for topics
		if ( bbp_has_topics( $r ) ) {
			$output = bbp_buffer_template_part( 'loop',     'topics',    false );
		} else {
			$output = bbp_buffer_template_part( 'feedback', 'no-topics', false );
		}

		// Set the transient
		set_transient( $transient_key, $output, $expiration );
	}

	// Return the output
	return $output;
}

/**
 * Purge the homepage topics cache when bbPress's post cache is cleaned.
 *
 * This allows the homepage topics fragment cache to be updated when new topics
 * and replies are created in the support forums.
 *
 * @author johnjamesjacoby
 * @return void
 */
function bb_base_purge_homepage_topics() {
	delete_transient( 'bb_base_homepage_topics' );
}
add_action( 'bbp_clean_post_cache', 'bb_base_purge_homepage_topics' );

/**
 * Output first page of support topics
 *
 * @author johnjamesjacoby
 * @uses bb_base_get_homepage_topics()
 * @param mixed $args
 * @return void
 */
function bb_base_support_topics() {
	echo bb_base_get_support_topics();
}

/**
 * Get first page of support topics output and stash it for an our
 *
 * @author johnjamesjacoby
 * @param mixed $args
 * @return HTML
 */
function bb_base_get_support_topics() {
	// Transient settings
	$expiration    = HOUR_IN_SECONDS;
	$transient_key = 'bb_base_support_topics' . ( is_ssl() ? '_ssl' : '' );
	$output        = get_transient( $transient_key );

	// No transient found, so query for topics again
	if ( false === $output ) {

		// Look for topics
		$output = bbp_buffer_template_part( 'content', 'archive-topic', false );

		// Set the transient
		set_transient( $transient_key, $output, $expiration );
	}

	// Return the output
	return $output;
}

/**
 * Purge first page of topics cache when bbPress's post cache is cleaned.
 *
 * This allows the support topics fragment cache to be updated when new topics
 * and replies are created in the support forums.
 *
 * @author johnjamesjacoby
 * @return void
 */
function bb_base_purge_support_topics() {
	delete_transient( 'bb_base_support_topics' );
}
add_action( 'bbp_clean_post_cache', 'bb_base_purge_support_topics' );

/**
 * Hack to refresh topic and forum data when bug prevents last active times from
 * updating (splits/merges/trash/spam/etc...)
 *
 * @author johnjamesjacoby
 * @since 1.0.1
 * @return If not refreshing
 */
function bb_base_recount_current_thing() {

	// Bail if no refresh
	if ( empty( $_GET['refresh'] ) || ( 'true' != $_GET['refresh'] ) ) {
		return;
	}

	// Refresh topic data
	if ( bbp_is_single_topic() ) {

		// Bail if not capable
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		// Get the topic ID
		$topic_id = bbp_get_topic_id();

		bbp_update_topic_voice_count( $topic_id );
		bbp_update_topic_last_reply_id( $topic_id );
		bbp_update_topic_last_active_id( $topic_id );
		bbp_update_topic_last_active_time( $topic_id );

		bb_base_purge_support_topics();
		bb_base_purge_homepage_topics();

		// Redirect without _GET
		wp_safe_redirect( bbp_get_topic_permalink() );
		die;

	// Refresh forum data
	} elseif ( bbp_is_single_forum() ) {

		// Bail if not capable
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		bbp_update_forum_last_reply_id();
		bbp_update_forum_last_topic_id();
		bbp_update_forum_last_active_id();
		bbp_update_forum_last_active_time();

		bb_base_purge_support_topics();
		bb_base_purge_homepage_topics();

		// Redirect without _GET
		wp_safe_redirect( bbp_get_forum_permalink() );
		die;
	}
}
add_action( 'bbp_template_redirect', 'bb_base_recount_current_thing' );

function bb_base_register_menus() {
	register_nav_menu( 'header-nav-menu', 'Main nav bar' );
}
add_action( 'init', 'bb_base_register_menus' );
