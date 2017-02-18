<?php

namespace WordPressdotorg\Forums;

class Theme_Directory_Compat extends Directory_Compat {

	const COMPAT = 'theme';

	var $slug  = false;
	var $theme = null;

	function compat() {
		return self::COMPAT;
	}

	function compat_title() {
		return __( 'Theme Support', 'wporg-forums' );
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return ! empty( $this->theme ) ? $this->theme->post_title : '';
	}

	function forum_id() {
		return Plugin::THEMES_FORUM_ID;
	}

	function query_var() {
		return 'wporg_' . self::COMPAT;
	}

	function taxonomy() {
		return 'topic-' . self::COMPAT;
	}

	public function __construct() {
		$this->init();
	}

	/**
	 * Add views if the theme query_var is present.
	 */
	public function parse_query() {
		$slug = get_query_var( 'wporg_theme' );
		if ( ! $slug ) {
			return;
		}

		$theme = $this->get_object( $slug );
		if ( ! $theme ) {
			return;
		} else {
			$this->slug         = $slug;
			$this->theme        = $theme;
			$this->authors      = $this->get_authors( $slug );
			$this->contributors = $this->get_contributors( $slug );
		}
	}

	public function do_view_sidebar() {

		$this->do_topic_sidebar();

		?>
		<div>
			<h3><?php _e( 'Browse Themes', 'wporg-forums' ); ?></h3>

			<ul class="theme-submenu">
				<li class="view"><a href="//wordpress.org/themes/"><?php _e( 'Featured', 'wporg-forums' ); ?></a></li>
				<li class="view"><a href="//wordpress.org/themes/browse/popular/"><?php _e( 'Most Popular', 'wporg-forums' ); ?></a></li>
				<li class="view"><a href="//wordpress.org/themes/browse/new/"><?php _e( 'Latest', 'wporg-forums' ); ?></a></li>
				<li class="view"><a href="/themes/getting-started/"><?php _e( 'Theme Authors', 'wporg-forums' ); ?></a></li>
				<li class="view"><a href="/themes/commercial/"><?php _e( 'Commercial', 'wporg-forums' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h3><?php _e( 'Search Themes', 'wporg-forums' ); ?></h3>

			<form id="side-search" method="get" action="//wordpress.org/themes/search.php">
			<div>
				<input type="text" class="text" name="q" value="" />
				<input type="submit" class="button" value="<?php _e( 'Search', 'wporg-forums' ); ?>" />
			</div>
			</form>
		</div>
		<?php
	}

	public function do_topic_sidebar() {
		$theme   = sprintf( '<a href="//wordpress.org/themes/%s/">%s</a>', esc_attr( $this->slug() ), esc_html( $this->theme->post_title ) );
		$support = sprintf( '<a href="//wordpress.org/support/theme/%s/">%s</a>', esc_attr( $this->slug() ), __( 'Support Threads', 'wporg-forums' ) );
		$active  = sprintf( '<a href="//wordpress.org/support/theme/%s/active">%s</a>', esc_attr( $this->slug() ), __( 'Active Topics', 'wporg-forums' ) );
		$reviews = sprintf( '<a href="//wordpress.org/support/theme/%s/reviews/">%s</a>', esc_attr( $this->slug() ), __( 'Reviews', 'wporg-forums' ) );
		$create  = '';

		$create_label = '';
		if ( isset( $this->ratings ) && $this->ratings->is_rating_view() && bbp_current_user_can_access_create_reply_form() ) {
			$create_label = \WPORG_Ratings::get_user_rating( 'theme', $this->slug(), get_current_user_id() ) ?
				__( 'Edit Review', 'wporg-forums' ) :
				__( 'Add Review', 'wporg-forums' );
		} elseif ( bbp_is_single_forum() && bbp_current_user_can_access_create_topic_form() ) {
			$create_label = __( 'Create Topic', 'wporg-forums' );
		}
		if ( $create_label ) {
			$create = sprintf( '<a href="#new-post">%s</a>', $create_label );
		}
		?>
		<div>
			<h3><?php _e( 'About this Theme', 'wporg-forums' ); ?></h3>
			<ul>
				<li><?php echo $theme; ?></li>
				<li><?php echo $support; ?></li>
				<li><?php echo $active; ?></li>
				<li><?php echo $reviews; ?></li>
				<?php if ( $create ) : ?>
				<li class="create-topic"><?php echo $create; ?></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
	}

	public function do_view_header() {
	}
}
