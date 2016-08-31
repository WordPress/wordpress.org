<?php

namespace WordPressdotorg\Forums;

class Plugin_Directory_Compat extends Directory_Compat {

	const COMPAT = 'plugin';

	var $slug   = false;
	var $plugin = null;

	function compat() {
		return self::COMPAT;
	}

	function compat_title() {
		return __( 'Plugin Support', 'wporg-forums' );
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return $this->plugin->post_title;
	}

	function forum_id() {
		return Plugin::PLUGINS_FORUM_ID;
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
	 * Add views if the plugin query_var is present.
	 */
	public function parse_query() {
		$slug = get_query_var( 'wporg_plugin' );
		if ( ! $slug ) {
			return;
		}

		$plugin = $this->get_object( $slug );
		if ( ! $plugin ) {
			return;
		} else {
			$this->slug         = $slug;
			$this->plugin       = $plugin;
			$this->authors      = $this->get_authors( $slug );
			$this->contributors = $this->get_contributors( $slug );
		}
	}

	public function do_view_sidebar() {
		?>
		<div>
			<h3><?php _e( 'Browse Plugins', 'wporg-forums' ); ?></h3>

			<ul class="plugin-submenu">
				<li class="view"><a href='//wordpress.org/plugins/'><?php _e( 'Featured', 'wporg' ); ?></a></li>
				<li class="view"><a href='//wordpress.org/plugins/browse/popular/'><?php _e( 'Most Popular', 'wporg' ); ?></a></li>
				<li class="view"><a href='//wordpress.org/plugins/browse/favorites/'><?php _e( 'Favorites', 'wporg' ); ?></a></li>
				<li class="view"><a href='//wordpress.org/plugins/browse/beta/'><?php _e( 'Beta Testing', 'wporg' ); ?></a></li>
				<li class="view"><a href='/plugins/about/'><?php _e( 'Developers', 'wporg' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h3><?php _e( 'Search Plugins', 'wporg-forums' ); ?></h3>

			<form id="side-search" method="get" action="//wordpress.org/plugins/search.php">
			<div>
				<input type="text" class="text" name="q" value="" />
				<input type="submit" class="button" value="<?php _e( 'Search', 'wporg-forums' ); ?>" />
			</div>
			</form>
		</div>
		<?php
	}

	public function do_topic_sidebar() {
		include_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
		$plugin  = sprintf( '<a href="//wordpress.org/plugins/%s/">%s</a>', esc_attr( $this->slug() ), esc_html( $this->plugin->post_title ) );
		$faq     = sprintf( '<a href="//wordpress.org/plugins/%s/faq/">Frequently Asked Questions</a>', esc_attr( $this->slug() ) );
		$support = sprintf( '<a href="//wordpress.org/support/plugin/%s/">Support Threads</a>', esc_attr( $this->slug() ) );
		$reviews = sprintf( '<a href="//wordpress.org/support/plugin/%s/reviews/">Reviews</a>', esc_attr( $this->slug() ) );
		?>
		<div>
			<h3>About this Plugin</h3>
			<ul>
				<li><?php echo wporg_get_plugin_icon( $this->slug, 128 ); ?></li>
				<li style="clear:both;"><?php echo $plugin; ?></li>
				<?php if ( ! empty( $this->plugin->post_content ) && false !== strpos( $this->plugin->post_content, '<!--section=faq-->' ) ) : ?>
				<li><?php echo $faq; ?></li>
				<?php endif; ?>
				<li><?php echo $support; ?></li>
				<li><?php echo $reviews; ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Return a custom view header string so that get_breadcrumbs will display it.
	 */
	public function get_view_header() {
		$slug        = esc_attr( $this->slug );
		$description = esc_html__( 'Description', 'wporg-forums' );
		$support     = esc_html__( 'Support', 'wporg-forums' );
		$reviews     = esc_html__( 'Reviews', 'wporg-forums' );

		$header = <<<EOT
		<ul id="sections">
			<li class="section-description">
				<a href="//wordpress.org/plugins/{$slug}/">{$description}</a>
			</li>
			<li class="section-support">
				<a href="//wordpress.org/support/plugin/{$slug}/">{$support}</a>
			<li>
			<li class="section-reviews">
				<a href="//wordpress.org/support/plugin/{$slug}/reviews/">{$reviews}</a>
			</li>
			</ul>
EOT;
		return $header;
	}
}
