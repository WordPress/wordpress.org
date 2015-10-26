<?php
/**
 * WPBBP functions and definitions
 *
 * @package WPBBP
 */

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function wporg_support_setup() {
	load_theme_textdomain( 'wporg-forums' );
}
add_action( 'after_setup_theme', 'wporg_support_setup' );

/**
 * Enqueue scripts and styles.
 *
 * Enqueue existing wordpress.org/support stylesheets
 * @link http://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/style
 */
function wporg_support_scripts() {

	wp_register_style(
		'bb-base',
		'//bbpress.org/wp-content/themes/bb-base/style.css',
		array(),
		'20150216d'
	);

	wp_register_style(
		'forum-wp4-style',
		get_template_directory_uri() . '/style.css',
		array( 'bb-base' ),
		'20150704'
	);

	wp_register_style(
		'forum-wp4-style-rtl',
		get_template_directory_uri() . '/style-rtl.css',
		array( 'forum-wp4-style' ),
		'20151026'
	);

	wp_enqueue_style( 'forum-wp4-style' );

	if ( is_rtl() ) {
		wp_enqueue_style( 'forum-wp4-style-rtl' );
	}
}
add_action( 'wp_enqueue_scripts', 'wporg_support_scripts' );

/**
 * Customized breadcrumb arguments
 * Breadcrumb Root Text: "WordPress Support"
 * Custom seperator `«` and `»`
 *
 * @uses bbp_before_get_breadcrumb_parse_args() To parse the custom arguments
 */
function wporg_support_breadcrumb() {
	// HTML
	$args['before']          = '';
	$args['after']           = '';

	// Separator
	$args['sep']             = is_rtl() ? __( '&laquo;', 'wporg-forums' ) : __( '&raquo;', 'wporg-forums' );
	$args['pad_sep']         = 1;
	$args['sep_before']      = '<span class="bbp-breadcrumb-sep">' ;
	$args['sep_after']       = '</span>';

	// Crumbs
	$args['crumb_before']    = '';
	$args['crumb_after']     = '';

	// Home
	$args['include_home']    = false;

	// Forum root
	$args['include_root']    = true;
	$args['root_text']       = __( 'WordPress Support', 'wporg-forums' );

	// Current
	$args['include_current'] = true;
	$args['current_before']  = '<span class="bbp-breadcrumb-current">';
	$args['current_after']   = '</span>';

	return $args;
}
add_filter('bbp_before_get_breadcrumb_parse_args', 'wporg_support_breadcrumb' );

/**
 * Register these bbPress views:
 *  View: All Topics
 *  @ToDo View: Not Resolved
 *  @ToDo View: modlook
 *
 * @uses bbp_register_view() To register the view
 */
function wporg_support_custom_views() {
	bbp_register_view( 'all-topics', __( 'All Topics', 'wporg-forums' ), array( 'order' => 'DESC' ), false );
//	bbp_register_view( 'support-forum-no', __( 'Not Resolved', 'wporg-forums' ), array( 'post_status' => 'closed' ), false );
//	bbp_register_view( 'taggedmodlook', __( 'Tagged modlook', 'wporg-forums' ), array( 'topic-tag' => 'modlook' ) );
}
add_action( 'bbp_register_views', 'wporg_support_custom_views' );

/**
 * Custom Body Classes
 *
 * @uses get_body_class() To add the `wporg-support` class
 */
function wporg_support_body_class($classes) {
	$classes[] = 'wporg-responsive';
	$classes[] = 'wporg-support';
	return $classes;
}
add_filter( 'body_class', 'wporg_support_body_class' );

/**
 * The Header for our theme.
 *
 * @package WPBBP
 */
function wporg_get_global_header() {
	$GLOBALS['pagetitle'] = wp_title( '&laquo;', false, 'right' ) . ' ' . get_bloginfo( 'name' );
	require WPORGPATH . 'header.php';
}

/**
 * The Footer for our theme.
 *
 * @package WPBBP
 */
function wporg_get_global_footer() {
	require WPORGPATH . 'footer.php';
}

/**
 * Link user profiles to their global profiles.
 */
function wporg_support_profile_url( $user_id ) {
	$user = get_userdata( $user_id );

	return esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename );
}
add_filter( 'bbp_pre_get_user_profile_url', 'wporg_support_profile_url' );

/** bb Base *******************************************************************/

function bb_base_topic_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Forum Search', 'wporg-forums' ); ?></h3>
			<label class="screen-reader-text hidden" for="ts"><?php _e( 'Search for:', 'wporg-forums' ); ?></label>
			<input type="text" value="<?php echo bb_base_topic_search_query(); ?>" name="ts" id="ts" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'wporg-forums' ); ?>" />
		</div>
	</form>

<?php
}

function bb_base_reply_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Reply Search', 'wporg-forums' ); ?></h3>
			<label class="screen-reader-text hidden" for="rs"><?php _e( 'Search for:', 'wporg-forums' ); ?></label>
			<input type="text" value="<?php echo bb_base_reply_search_query(); ?>" name="rs" id="rs" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'wporg-forums' ); ?>" />
		</div>
	</form>

<?php
}

function bb_base_plugin_search_form() {
?>

	<form role="search" method="get" id="searchform" action="">
		<div>
			<h3><?php _e( 'Plugin Search', 'wporg-forums' ); ?></h3>
			<label class="screen-reader-text hidden" for="ps"><?php _e( 'Search for:', 'wporg-forums' ); ?></label>
			<input type="text" value="<?php echo bb_base_plugin_search_query(); ?>" name="ps" id="ts" />
			<input class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'wporg-forums' ); ?>" />
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
	$voice_count = sprintf( _n( '%s participant', '%s participants', $voice_count, 'wporg-forums' ), bbp_number_format( $voice_count ) );
	$last_reply  = bbp_get_topic_last_active_id( $topic_id );

	?>

	<li class="topic-forum">In: <a href="<?php bbp_forum_permalink( bbp_get_topic_forum_id() ); ?>"><?php bbp_topic_forum_title(); ?></a></li>
	<?php if ( !empty( $reply_count ) ) : ?><li class="reply-count"><?php echo $reply_count; ?></li><?php endif; ?>
	<?php if ( !empty( $voice_count ) ) : ?><li class="voice-count"><?php echo $voice_count; ?></li><?php endif; ?>
	<?php if ( !empty( $last_reply  ) ) : ?>
		<li class="topic-freshness-author"><?php printf( __( 'Last reply from: %s', 'wporg-forums' ), bbp_get_author_link( array( 'type' => 'name', 'post_id' => $last_reply, 'size' => '15' ) ) ); ?></li>
	<?php endif; ?>
	<?php if ( !empty( $time_since  ) ) : ?><li class="topic-freshness-time"><?php printf( __( 'Last activity: %s', 'wporg-forums' ), $time_since ); ?></li><?php endif; ?>
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
		$reply_text = sprintf( _n( '%s reply', '%s replies', $reply_count, 'wporg-forums' ), bbp_number_format( $reply_count ) );
	}

	// Forum has active data
	if ( !empty( $last_active ) ) {
		$topic_text      = bbp_get_forum_topics_link( $forum_id );
		$time_since      = bbp_get_forum_freshness_link( $forum_id );

	// Forum has no last active data
	} else {
		$topic_text      = sprintf( _n( '%s topic', '%s topics', $topic_count, 'wporg-forums' ), bbp_number_format( $topic_count ) );
	}
	?>

	<?php if ( bbp_get_forum_parent_id() ) : ?><li class="topic-parent">In: <a href="<?php bbp_forum_permalink( bbp_get_forum_parent_id() ); ?>"><?php bbp_forum_title( bbp_get_forum_parent_id() ); ?></a></li><?php endif; ?>
	<?php if ( !empty( $topic_count ) ) : ?><li class="topic-count"><?php echo $topic_text; ?></li><?php endif; ?>
	<?php if ( !empty( $reply_count ) ) : ?><li class="reply-count"><?php echo $reply_text; ?></li><?php endif; ?>
	<?php if ( !empty( $last_active  ) ) : ?>
		<li class="forum-freshness-author"><?php printf( __( 'Last post by: %s', 'wporg-forums' ), bbp_get_author_link( array( 'type' => 'name', 'post_id' => $last_active ) ) ); ?></li>
	<?php endif; ?>
	<?php if ( !empty( $time_since  ) ) : ?><li class="forum-freshness-time"><?php printf( __( 'Last activity: %s', 'wporg-forums' ), $time_since ); ?></li><?php endif; ?>
	<?php if ( is_user_logged_in() ) : ?>
		<li class="forum-subscribe"><?php bbp_forum_subscription_link( array( 'forum_id' => $forum_id ) ); ?></li>
	<?php endif;
}
