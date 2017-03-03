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

		$this->do_topic_sidebar();

	}

	public function do_topic_sidebar() {
		if ( file_exists( WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php' ) ) {
			include_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
		}

		$plugin     = sprintf( '<a href="//wordpress.org/plugins/%s/">%s</a>', esc_attr( $this->slug() ), esc_html( $this->plugin->post_title ) );
		$faq        = sprintf( '<a href="//wordpress.org/plugins/%s/faq/">%s</a>', esc_attr( $this->slug() ), __( 'Frequently Asked Questions', 'wporg-forums' ) );
		$support    = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/' ), __( 'Support Threads', 'wporg-forums' ) );
		$active     = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/active/' ), __( 'Active Topics', 'wporg-forums' ) );
		$unresolved = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/unresolved/' ), __( 'Unresolved Topics', 'wporg-forums' ) );
		$reviews    = sprintf( '<a href="%s">%s</a>', home_url( '/plugin/' . esc_attr( $this->slug() ) . '/reviews/' ), __( 'Reviews', 'wporg-forums' ) );
		$create     = '';

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
			<h3><?php _e( 'About this Plugin', 'wporg-forums' ); ?></h3>
			<ul>
				<?php if ( function_exists( 'wporg_get_plugin_icon' ) ) : ?>
				<li><?php echo wporg_get_plugin_icon( $this->slug, 128 ); ?></li>
				<?php endif; ?>
				<li style="clear:both;"><?php echo $plugin; ?></li>
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
