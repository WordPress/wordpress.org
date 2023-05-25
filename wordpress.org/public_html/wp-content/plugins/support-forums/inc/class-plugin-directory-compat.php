<?php

namespace WordPressdotorg\Forums;

class Plugin_Directory_Compat extends Directory_Compat {

	const COMPAT = 'plugin';

	var $slug   = false;
	var $plugin = null;

	function compat() {
		return self::COMPAT;
	}

	function compat_views() {
		return array( self::COMPAT, 'reviews', 'active', 'unresolved' );
	}

	function compat_title() {
		/* translators: %s: plugin title */
		return sprintf( _x( '[%s] Support', 'plugin', 'wporg-forums' ), $this->title() );
	}

	function reviews_title() {
		/* translators: %s: plugin title */
		return sprintf( _x( '[%s] Reviews', 'plugin', 'wporg-forums' ), $this->title() );
	}

	function active_title() {
		/* translators: %s: plugin title */
		return sprintf( _x( '[%s] Recent Activity', 'plugin', 'wporg-forums' ), $this->title() );
	}

	function unresolved_title() {
		/* translators: %s: plugin title */
		return sprintf( _x( '[%s] Unresolved Topics', 'plugin', 'wporg-forums' ), $this->title() );
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return $this->plugin->post_title ?? '';
	}

	function status() {
		return $this->plugin->post_status ?? '';
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

	function name() {
		return ucfirst( self::COMPAT );
	}

	public function __construct() {
		$this->init();
	}

	/**
	 * Add views if the plugin query_var is present.
	 */
	public function parse_query() {
		global $wp;

		$slug = get_query_var( 'wporg_plugin' );
		if ( ! $slug ) {
			// bbPress feeds are bad and don't actually fill in globals.
			if ( isset( $wp->query_vars['feed'] ) && ! empty( $wp->query_vars['wporg_plugin'] ) ) {
				$slug = $wp->query_vars['wporg_plugin'];
			} else {
				return;
			}
		}

		if ( '_redirect_' == $slug ) {
			wp_safe_redirect( 'https://wordpress.org/plugins/', 301 );
			exit;
		}

		if ( ! $this->for_slug( $slug ) ) {
			status_header( 404 );
		}
	}

	/**
	 * Set the directory instance to the slugs data.
	 *
	 * @param string $slug The plugin slug.
	 */
	public function for_slug( $slug ) {
		$plugin = $this->get_object( $slug );
		if ( ! $plugin ) {
			return false;
		}

		$this->slug         = $plugin->post_name;
		$this->plugin       = $plugin;
		$this->authors      = $this->get_authors( $slug );
		$this->contributors = $this->get_contributors( $slug );
		$this->support_reps = $this->get_support_reps( $slug );

		return true;
	}

	public function do_view_sidebar() {

		$this->do_topic_sidebar();

	}

	public function do_topic_sidebar() {
		if ( ! $this->plugin ) {
			return;
		}

		if ( file_exists( WPORGPATH . 'wp-content/plugins/plugin-directory/class-template.php' ) ) {
			include_once WPORGPATH . 'wp-content/plugins/plugin-directory/class-template.php';
		}

		$plugin_repo_url = get_home_url( WPORG_PLUGIN_DIRECTORY_BLOGID, '/' . $this->slug() . '/' );

		$icon       = '';
		$plugin     = sprintf( '<a href="%s">%s</a>', esc_url( $plugin_repo_url ), esc_html( $this->plugin->post_title ) );
		$faq        = sprintf( '<a href="%s">%s</a>', esc_url( $plugin_repo_url . '#faq' ), __( 'Frequently Asked Questions', 'wporg-forums' ) );
		$support    = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/' ), __( 'Support Threads', 'wporg-forums' ) );
		$active     = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/active/' ), __( 'Active Topics', 'wporg-forums' ) );
		$unresolved = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/unresolved/' ), __( 'Unresolved Topics', 'wporg-forums' ) );
		$reviews    = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/reviews/' ), __( 'Reviews', 'wporg-forums' ) );
		$create     = '';

		if ( class_exists( '\WordPressdotorg\Plugin_Directory\Template' ) ) {
			switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
			$icon = \WordPressdotorg\Plugin_Directory\Template::get_plugin_icon( $this->plugin, 'html' );
			restore_current_blog();
		}

		$create_label = '';
		if ( isset( $this->ratings ) && $this->ratings->is_rating_view() && bbp_current_user_can_access_create_topic_form() ) {
			$create_label = $this->ratings->review_exists() ?
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
			<ul>
				<?php if ( $icon ) : ?>
				<li class="plugin-meta-icon"><?php echo $icon; ?></li>
				<?php endif; ?>
				<li><?php echo $plugin; ?></li>
				<?php if ( ! empty( $this->plugin->post_content ) && false !== strpos( $this->plugin->post_content, '<!--section=faq-->' ) ) : ?>
				<li><?php echo $faq; ?></li>
				<?php endif; ?>
				<li><?php echo $support; ?></li>
				<li><?php echo $active; ?></li>
				<li><?php echo $unresolved; ?></li>
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
