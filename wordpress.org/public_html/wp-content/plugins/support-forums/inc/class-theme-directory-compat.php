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
		$theme   = sprintf( '<a href="//wordpress.org/plugins/%s/">%s</a>', esc_attr( $this->slug() ), esc_html( $this->theme->post_title ) );
		$support = sprintf( '<a href="//wordpress.org/support/plugin/%s/">Support Threads</a>', esc_attr( $this->slug() ) );
		$reviews = sprintf( '<a href="//wordpress.org/support/plugin/%s/reviews/">Reviews</a>', esc_attr( $this->slug() ) );
		?>
		<div>
			<h3>About this Theme</h3>
			<ul>
				<li><?php echo $theme; ?></li>
				<li><?php echo $support; ?></li>
				<li><?php echo $reviews; ?></li>
			</ul>
		</div>
		<?php
	}

	public function do_view_header() {
		$slug        = esc_attr( $this->slug );
		$description = esc_html__( 'Description', 'wporg-forums' );
		$support     = esc_html__( 'Support', 'wporg-forums' );
		$reviews     = esc_html__( 'Reviews', 'wporg-forums' );
		?>
		<ul id="sections">
			<li class="section-description">
				<a href="//wordpress.org/themes/<?php echo $slug; ?>/"><?php echo $description; ?></a>
			</li>
			<li class="section-support">
				<a href="//wordpress.org/support/theme/<?php echo $slug; ?>/"><?php echo $support; ?></a>
			<li>
			<li class="section-reviews">
				<a href="//wordpress.org/support/theme/<?php echo $slug; ?>/reviews/"><?php echo $reviews; ?></a>
			</li>
		</ul>
		<?php
	}
}
