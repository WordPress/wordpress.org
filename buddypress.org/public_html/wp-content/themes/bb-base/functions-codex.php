<?php

/**
 * Plugin Name: Codex
 * Description: Activate on codex.buddypress.org and codex.bbpress.org
 * Version:     1.0
 * Author:      jjj
 * Author URI:  http://jaco.by
 */

/** Functions *****************************************************************/

/**
 *
 * @global object $post
 */
function codex_get_breadcrumb() {
	global $post;

	$crumb = array();

	if ( ! is_front_page() && ! is_page('home') ) {

		$crumb[] = '&rarr; ' . get_the_title( $post->ID );

		if ( !empty( $post->ancestors ) ) {
			foreach ( $post->ancestors as $post_id ) {
				$crumb[] = '&rarr; <a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>';
			}
		}

		$crumb[] = '<a href="/">Codex Home</a>';

		krsort( $crumb );
		$crumb = implode( ' ', $crumb );
		echo $crumb;
	}
}

/** Classes *******************************************************************/

/**
 * Codex
 *
 * @author jjj
 * @since
 */
class Codex_Loader {

	public function __construct() {

		// Register the widget columns
		register_sidebars( 1,
			array(
				'name'          => 'codex-sidebar',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h3 class="widgettitle">',
				'after_title'   => '</h3>'
			)
		);

		// Actions
		add_action( 'init', array( $this, 'register_taxonomies'    ) );

		// Filters
		add_filter( 'the_content', array( $this, 'add_h2_ids' ) );
		add_filter( 'the_content', array( $this, 'add_h3_ids' ) );
		add_filter( 'the_content', array( $this, 'add_h4_ids' ) );
	}

	public function get_term_caps() {
		return array(
			'manage_terms' => 'manage_codex_tags',
			'edit_terms'   => 'edit_codex_tags',
			'delete_terms' => 'delete_codex_tags',
			'assign_terms' => 'assign_codex_tags'
		);
	}

	/**
	 * Register the topic tag taxonomy
	 *
	 * @uses register_taxonomy() To register the taxonomy
	 */
	public function register_taxonomies() {

		// version labels
		$tax['labels'] = array(
			'name'          => __( 'Versions'           ),
			'singular_name' => __( 'Version'            ),
			'search_items'  => __( 'Search Versions'    ),
			'popular_items' => __( 'Popular Versions'   ),
			'all_items'     => __( 'All Versions'       ),
			'edit_item'     => __( 'Edit Version'       ),
			'update_item'   => __( 'Update Version'     ),
			'add_new_item'  => __( 'Add New Version'    ),
			'new_item_name' => __( 'New Version Name'   ),
			'view_item'     => __( 'View Version'       )
		);

		// version rewrite
		$tax['rewrite'] = array(
			'slug'       => 'version',
			'with_front' => false
		);

		// version filter
		$new_tax = array(
			'labels'                => $tax['labels'],
			'rewrite'               => $tax['rewrite'],
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'capabilities'          => $this->get_term_caps()
		);

		// Register the version taxonomy
		register_taxonomy(
			'version', // The version ID
			'page',    // For pages
			$new_tax
		);

		// component labels
		$tax['labels'] = array(
			'name'          => __( 'Components'           ),
			'singular_name' => __( 'Component'            ),
			'search_items'  => __( 'Search Components'    ),
			'popular_items' => __( 'Popular Components'   ),
			'all_items'     => __( 'All Components'       ),
			'edit_item'     => __( 'Edit Component'       ),
			'update_item'   => __( 'Update Component'     ),
			'add_new_item'  => __( 'Add New Component'    ),
			'new_item_name' => __( 'New Component Name'   ),
			'view_item'     => __( 'View Component'       )
		);

		// component rewrite
		$tax['rewrite'] = array(
			'slug'       => 'component',
			'with_front' => false
		);

		// component filter
		$new_tax = array(
			'labels'                => $tax['labels'],
			'rewrite'               => $tax['rewrite'],
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'capabilities'          => $this->get_term_caps()
		);

		// Register the component taxonomy
		register_taxonomy(
			'component', // The component ID
			'page',      // For pages
			$new_tax
		);

		// type labels
		$tax['labels'] = array(
			'name'          => __( 'Types'           ),
			'singular_name' => __( 'Type'            ),
			'search_items'  => __( 'Search Types'    ),
			'popular_items' => __( 'Popular Types'   ),
			'all_items'     => __( 'All Types'       ),
			'edit_item'     => __( 'Edit Type'       ),
			'update_item'   => __( 'Update Type'     ),
			'add_new_item'  => __( 'Add New Type'    ),
			'new_item_name' => __( 'New Type Name'   ),
			'view_item'     => __( 'View Type'       )
		);

		// type rewrite
		$tax['rewrite'] = array(
			'slug'       => 'type',
			'with_front' => false
		);

		// type filter
		$new_tax = array(
			'labels'                => $tax['labels'],
			'rewrite'               => $tax['rewrite'],
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'capabilities'          => $this->get_term_caps()
		);

		// Register the type taxonomy
		register_taxonomy(
			'type',    // The type ID
			'page',    // For pages
			$new_tax
		);

		// context labels
		$tax['labels'] = array(
			'name'          => __( 'Contexts'           ),
			'singular_name' => __( 'Context'            ),
			'search_items'  => __( 'Search Contexts'    ),
			'popular_items' => __( 'Popular Contexts'   ),
			'all_items'     => __( 'All Contexts'       ),
			'edit_item'     => __( 'Edit Context'       ),
			'update_item'   => __( 'Update Context'     ),
			'add_new_item'  => __( 'Add New Context'    ),
			'new_item_name' => __( 'New Context Name'   ),
			'view_item'     => __( 'View Context'       )
		);

		// context rewrite
		$tax['rewrite'] = array(
			'slug'       => 'context',
			'with_front' => false
		);

		// context filter
		$new_tax = array(
			'labels'                => $tax['labels'],
			'rewrite'               => $tax['rewrite'],
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_tagcloud'         => true,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'capabilities'          => $this->get_term_caps()
		);

		// Register the context taxonomy
		register_taxonomy(
			'context',    // The context ID
			'page',       // For pages
			$new_tax
		);
	}

	public static function create_page_toc() {
		$toc     = '';
		$content = get_the_content();
		if ( empty( $content  ) ) {
			return $toc;
		}

		$h2s     = self::get_h2s( $content );
		$h3s     = self::get_h3s( $content );
		$h4s     = self::get_h4s( $content );
		$items   = $h2s + $h3s + $h4s;

		if ( $items ) {
			$toc = '<h3 class="widgettitle" id="t-o-c">Contents</h3><ul class="items" id="shortcuts">';
			foreach ( $items as $item ) {
				$toc .= '<li><a href="#' . sanitize_title_with_dashes( $item[2] )  . '">' . esc_html( $item[2] )  . '</a> <span class="toc-arrow">&rarr;</span></li>';
			}
			$toc .= '</ul>';
		}

		return $toc;
	}

	public static function get_h2s( $content = '' ) {
		preg_match_all( "/(<h2>)(.*)(<\/h2>)/", $content, $matches, PREG_SET_ORDER );

		return $matches;
	}

	public static function get_h3s( $content = '' ) {
		preg_match_all( "/(<h3>)(.*)(<\/h3>)/", $content, $matches, PREG_SET_ORDER );

		return $matches;
	}

	public static function get_h4s( $content = '' ) {
		preg_match_all( "/(<h4>)(.*)(<\/h4>)/", $content, $matches, PREG_SET_ORDER );

		return $matches;
	}

	public function add_h2_ids( $content = '' ) {

		if ( !is_page() ) {
			return $content;
		}

		$items = self::get_h2s( $content );

		foreach ( $items as $item ) {
			$matches[]      = $item[0];
			$replacement    = '<h2 id="' . sanitize_title_with_dashes( $item[2] ) . '"><a href="#' . sanitize_title_with_dashes( $item[2] ). '">' . esc_html( $item[2] ) . '</a></h2>';
			$replacements[] = $replacement;
		}

		$content = str_replace( $matches, $replacements, $content );

		return $content;
	}

	public function add_h3_ids( $content = '' ) {

		if ( !is_page() ) {
			return $content;
		}

		$items = self::get_h3s( $content );

		foreach ( $items as $item ) {
			$matches[]      = $item[0];
			$replacement    = '<h3 id="' . sanitize_title_with_dashes( $item[2] ) . '"><a href="#' . sanitize_title_with_dashes( $item[2] ). '">' . esc_html( $item[2] ) . '</a></h3>';
			$replacements[] = $replacement;
		}

		$content = str_replace( $matches, $replacements, $content );

		return $content;
	}

	public function add_h4_ids( $content = '' ) {

		if ( !is_page() ) {
			return $content;
		}

		$items = self::get_h4s( $content );

		foreach ( $items as $item ) {
			$matches[]      = $item[0];
			$replacement    = '<h4 id="' . sanitize_title_with_dashes( $item[2] ) . '"><a href="#' . sanitize_title_with_dashes( $item[2] ). '">' . esc_html( $item[2] ) . '</a></h4>';
			$replacements[] = $replacement;
		}

		$content = str_replace( $matches, $replacements, $content );

		return $content;
	}
}
new Codex_Loader;

/**
 * Codex specific hacks
 *
 * @author jjj
 * @since February 6, 2012
 */
class Codex_Hacks {

	/**
	 * Main constructor
	 *
	 * Sets variables and adds actions
	 *
	 * @author jjj
	 * @since February 6, 2012
	 */
	public function __construct() {

		// Add some acitons
		add_action( 'admin_head', array( $this, 'unset_menus' ), 11 );

		// Codex meta caps (to prevent deletion)
		add_filter( 'map_meta_cap', array( $this, 'map_meta_caps' ), 10, 3 );

		// Emergency override
		do_action_ref_array( 'codex_hacks_loaded', array( &$this ) );
	}

	/**
	 * When loading up a codex site, check the current users' role and make sure
	 * they are at least an editor.
	 *
	 * We run this on 'init' as we want to give all users the ability to edit pages
	 * and it's possible they may have an old role (or none) and not be given any
	 * option to edit pages or link to wp-admin at all.
	 *
	 * This method is not currently used. Codexes are manually moderated to prevent
	 * automated spam attacks.
	 *
	 * @author jjj
	 * @since February 6, 2012
	 * @deprecated November 29, 2014
	 * @return Bail under certain conditions
	 */
	public function autorole() {

		// Should we bail
		if ( ! is_user_logged_in() || is_super_admin() ) {
			return;
		}

		global $current_user, $wp_roles;

		// Sanity check on current_user
		if ( empty( $current_user ) ) {
			return;
		}

		// Set some defaults
		$user_role    = '';
		$default_role = 'editor';

		// Get current user role
		if ( !empty( $current_user->roles ) ) {
			$user_roles = $current_user->roles;
			$user_role  = array_shift( $user_roles );
		}

		// Bail if role is already 'editor'
		if ( $user_role === $default_role ) {
			return;
		}

		// Get editable roles
		$editable_roles = apply_filters( 'editable_roles', $wp_roles->roles );

		// Remove a users role if it's an editable one
		foreach ( array_keys( $editable_roles ) as $role ) {
			$current_user->remove_role( $role );
		}

		// Add the editor role to the user
		$current_user->add_role( $default_role );
	}

	/**
	 * Codex specific capabilities
	 *
	 * @author jjj
	 * @param array $caps
	 * @param string $cap
	 * @param int $user_id
	 * @return array
	 */
	public function map_meta_caps( $caps = array(), $cap = '', $user_id = 0 ) {

		// What cap are we switching
		switch ( $cap ) {

			// Prevent content deletion
			case 'delete_post' :
			case 'delete_page' :
				$caps = array( 'do_not_allow' );
				if ( ! $this->is_user_trusted() ) {
					$caps = array( $cap );
				}
				break;

			// Allow codex management
			case 'delete_codex_tags' :
				$caps = array( 'do_not_allow' );
				if ( $this->is_user_trusted() ) {
					$caps = array( 'manage_categories' );
				}
				break;

			case 'edit_codex_tags'   :
			case 'assign_codex_tags' :
			case 'manage_codex_tags' :
				$caps = array( 'manage_categories' );
				break;
		}

		return $caps;
	}

	/**
	 * Unsets some menus in the codexes.
	 *
	 * For visual clean-up only; does not manage any capabilities or restrict access
	 * to menus.
	 *
	 * @author jjj
	 * @since February 6, 2012
	 * @global array $menu
	 * @return Bail under certain conditions
	 */
	public function unset_menus() {

		// Should we bail
		if ( $this->is_user_trusted() ) {
			return;
		}

		global $menu;

		/**
		* Unset menu items so average users cannot navigate to them
		* BuddyPress and bbPress codexs are handled differently because BuddyPress
		* has a Codex team forum and bbPress 2 adds new menus
		*/
		switch ( get_current_blog_id() ) {

			// codex.buddypress.org
			case 15 :
				unset( $menu[2]  ); // Separator
				unset( $menu[4]  ); // Separator
				unset( $menu[5]  ); // Posts
				unset( $menu[10] ); // Media
				unset( $menu[11] ); // Profile
				unset( $menu[25] ); // Comments
				unset( $menu[59] ); // Separator
				unset( $menu[70] ); // Profile
				unset( $menu[75] ); // Tools
				break;

			// codex.bbpress.org
			case 53 :
				unset( $menu[5]  ); // Posts
				unset( $menu[10] ); // Media
				unset( $menu[15] ); // Links
				unset( $menu[25] ); // Comments
				unset( $menu[59] ); // Topics
				unset( $menu[70] ); // Replies
				unset( $menu[75] ); // bbPress Separator
				break;
		}
	}

	/** Private Methods *******************************************************/

	/**
	 * Checks if user is trusted in the codex
	 *
	 * @author jjj
	 * @since February 6, 2012
	 * @return boolean True if should bail, false if should proceed
	 */
	private function is_user_trusted() {

		// Bail if user is an admin
		if ( in_array( 'administrator', wp_get_current_user()->roles ) ) {
			return true;
		}

		// Bail if user is super admin
		if ( is_super_admin() ) {
			return true;
		}

		return false;
	}
}
new Codex_Hacks();
