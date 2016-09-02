<?php
/**
 * WPBBP functions and definitions
 *
 * @package WPBBP
 */


/**
 * Use the ‘Lead Topic’ uses the single topic part
 * allowing styling the lead topic separately from the main reply loop.
 */
add_filter( 'bbp_show_lead_topic', '__return_true' );

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
		'20160701'
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

/**
 * Register these bbPress views:
 *  View: All topics
 *  View: Tagged modlook
 *
 * @uses bbp_register_view() To register the view
 */
function wporg_support_custom_views() {
	bbp_register_view( 'all-topics', __( 'All topics', 'wporg-forums' ), array( 'order' => 'DESC' ), false );
	if ( get_current_user_id() && current_user_can( 'moderate' ) ) {
		bbp_register_view( 'taggedmodlook', __( 'Tagged modlook', 'wporg-forums' ), array( 'topic-tag' => 'modlook' ) );
	}
}
add_action( 'bbp_register_views', 'wporg_support_custom_views' );

/**
 * Display an ordered list of bbPress views
 */
function wporg_support_get_views() {
	$all = bbp_get_views();
	$ordered = array(
		'all-topics',
		'no-replies',
		'support-forum-no',
		'taggedmodlook',
	);
	$found = array();
	foreach ( $ordered as $view ) {
		if ( array_key_exists( $view, $all ) ) {
			$found[] = $view;
		}
	}
	$view_iterator = 0;
	$view_count    = count( $found );

	foreach ( $found as $view ) : $view_iterator++; ?>

		<li class="view"><a href="<?php bbp_view_url( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

		<?php if ( $view_iterator < $view_count ) : ?>|<?php endif; ?>

	<?php endforeach;

	// Unset variables
	unset( $view_count, $view_iterator, $view, $found, $all, $ordered );
}

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

function bb_base_search_form() {
?>

	<form role="search" method="get" id="searchform" action="https://wordpress.org/search/do-search.php">
		<div>
			<h3><?php _e( 'Forum Search', 'wporg-forums' ); ?></h3>
			<label class="screen-reader-text hidden" for="search"><?php _e( 'Search for:', 'wporg-forums' ); ?></label>
			<input name="search" class="text" id="forumsearchbox" value type="text" />
			<input name="go" class="button" type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'wporg-forums' ); ?>" />
			<input value="1" name="forums" type="hidden">
		</div>
	</form>

<?php
}

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

	// WP version
	$wp_version = '';
	if ( function_exists( 'WordPressdotorg\Forums\Version_Dropdown\get_topic_version' ) ) {
		$wp_version = WordPressdotorg\Forums\Version_Dropdown\get_topic_version( $topic_id );
	}

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
	<?php endif; ?>
	<?php if ( ! empty( $wp_version ) ) : ?>
		<li class="wp-version"><?php echo esc_html( $wp_version ); ?></li>
	<?php endif; ?>
	<?php if ( function_exists( 'WordPressdotorg\Forums\Topic_Resolution\get_topic_resolution_form' ) ) : ?>
		<?php if ( WordPressdotorg\Forums\Topic_Resolution\Plugin::get_instance()->is_enabled_on_forum() ) : ?>
			<li class="topic-resolved"><?php WordPressdotorg\Forums\Topic_Resolution\get_topic_resolution_form( $topic_id ); ?></li>
		<?php endif; ?>
	<?php endif; ?>

	<?php
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

function bb_base_before_topics_loop() {
	do_action( 'bbp_template_notices' );

	if ( ! is_tax( 'topic-tag' ) ) {
		return;
	}

	$term_subscription = '';
	if ( function_exists( 'WordPressdotorg\Forums\Term_Subscription\get_subscription_link' ) ) {
		$term_subscription = WordPressdotorg\Forums\Term_Subscription\get_subscription_link( get_queried_object()->term_id );
	}
	?>
	<div id="topic-tag" class="bbp-topic-tag">
	<h1 class="entry-title"><?php printf( esc_html__( 'Topic Tag: %s', 'bbpress' ), '<span>' . bbp_get_topic_tag_name() . '</span>' ); ?></h1>
	<?php if ( ! empty( $term_subscription ) ) : ?><h2><?php echo $term_subscription; ?></h2><?php endif; ?>
		<div class="entry-content">
	<?php
}
add_action( 'bbp_template_before_topics_loop', 'bb_base_before_topics_loop' );

function bb_base_after_topics_loop() {
	if ( ! is_tax( 'topic-tag' ) ) {
		return;
	}
	?>
		</div>
	</div><!-- #topic-tag -->
	<?php
}
add_action( 'bbp_template_after_topics_loop', 'bb_base_after_topics_loop' );

function bb_is_intl_forum() {
	return get_locale() != 'en_US';
}

