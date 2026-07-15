<?php

namespace WordPressdotorg\Forums;

class Theme_Directory_Compat extends Directory_Compat {

	const COMPAT = 'theme';

	var $slug  = false;
	var $theme = null;

	function compat() {
		return self::COMPAT;
	}

	function compat_views() {
		return array( self::COMPAT, 'reviews', 'active', 'unresolved' );
	}

	function compat_title() {
		/* translators: %s: theme title */
		return sprintf( _x( '[%s] Support', 'theme', 'wporg-forums' ), $this->title() );
	}

	function reviews_title() {
		/* translators: %s: theme title */
		return sprintf( _x( '[%s] Reviews', 'theme', 'wporg-forums' ), $this->title() );
	}

	function active_title() {
		/* translators: %s: theme title */
		return sprintf( _x( '[%s] Recent Activity', 'theme', 'wporg-forums' ), $this->title() );
	}

	function unresolved_title() {
		/* translators: %s: theme title */
		return sprintf( _x( '[%s] Unresolved Topics', 'theme', 'wporg-forums' ), $this->title() );
	}

	function slug() {
		return $this->slug;
	}

	function title() {
		return ! empty( $this->theme ) ? $this->theme->post_title : '';
	}

	function status() {
		return ! empty( $this->theme ) ? $this->theme->post_status : '';
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

	function name() {
		return ucfirst( self::COMPAT );
	}

	public function __construct() {
		$this->init();
	}

	/**
	 * Add views if the theme query_var is present.
	 */
	public function parse_query() {
		global $wp;

		$slug = get_query_var( 'wporg_theme' );
		if ( ! $slug ) {
			// bbPress feeds are bad and don't actually fill in globals.
			if ( isset( $wp->query_vars['feed'] ) && ! empty( $wp->query_vars['wporg_theme'] ) ) {
				$slug = $wp->query_vars['wporg_theme'];
			} else {
				return;
			}
		}

		if ( '_redirect_' == $slug ) {
			wp_safe_redirect( 'https://wordpress.org/themes/', 301 );
			exit;
		}

		if ( ! $this->for_slug( $slug ) ) {
			status_header( 404 );
		}
	}

	/**
	 * Set the directory instance to the slugs data.
	 *
	 * @param string $slug The theme slug.
	 */
	public function for_slug( $slug ) {
		$theme = $this->get_object( $slug );
		if ( ! $theme ) {
			return false;
		}

		$this->slug         = $theme->post_name;
		$this->theme        = $theme;
		$this->authors      = $this->get_authors( $slug );
		$this->contributors = $this->get_contributors( $slug );
		$this->support_reps = $this->get_support_reps( $slug );

		$this->initialize_term();

		return true;
	}

	public function do_view_sidebar() {

		$this->do_topic_sidebar();

	}

	public function do_topic_sidebar() {
		if ( ! $this->theme ) {
			return;
		}

		$icon       = ''; 
		$theme      = sprintf( '<a href="//wordpress.org/themes/%s/">%s</a>', esc_attr( $this->slug() ), esc_html( $this->theme->post_title ) );
		$support    = sprintf( '<a href="%s">%s</a>', home_url( '/theme/' . esc_attr( $this->slug() ) . '/' ), __( 'Support Threads', 'wporg-forums' ) );
		$active     = sprintf( '<a href="%s">%s</a>', home_url( '/theme/' . esc_attr( $this->slug() ) . '/active/' ), __( 'Active Topics', 'wporg-forums' ) );
		$unresolved = sprintf( '<a href="%s">%s</a>', home_url( '/theme/' . esc_attr( $this->slug() ) . '/unresolved/' ), __( 'Unresolved Topics', 'wporg-forums' ) );
		$reviews    = sprintf( '<a href="%s">%s</a>', home_url( '/theme/' . esc_attr( $this->slug() ) . '/reviews/' ), __( 'Reviews', 'wporg-forums' ) );
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

		if ( file_exists( WPORGPATH . 'wp-content/plugins/theme-directory/class-wporg-themes-repo-package.php' ) ) {
			include_once WPORGPATH . 'wp-content/plugins/theme-directory/class-wporg-themes-repo-package.php';
		
			if ( class_exists( 'WPORG_Themes_Repo_Package' ) ) {
				switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID );
				$repo_package = new \WPORG_Themes_Repo_Package( $this->theme->ID );
				$icon = $repo_package->screenshot_url();
				restore_current_blog();
			}
		}

		?>
		<div>
			<ul>
				<?php if ( ! empty( $icon ) ) : ?>
					<li class="theme-meta-icon"><img src="<?php echo esc_url( $icon ); ?>"></li> 
				<?php endif; ?>
				<li><?php echo $theme; ?></li>
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
