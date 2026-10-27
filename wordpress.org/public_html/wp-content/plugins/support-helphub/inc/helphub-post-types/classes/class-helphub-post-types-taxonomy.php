<?php
/**
 * This file is part of the Helphub Post Types plugin
 *
 * @package WordPress
 * @author Jon Ang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helphub Post Types Taxonomy Class
 *
 * Re-usable class for registering post type taxonomies.
 *
 * @package WordPress
 * @subpackage HelpHub_Post_Types
 * @category Plugin
 * @author Jon Ang
 * @since 1.0.0
 */
class HelpHub_Post_Types_Taxonomy {
	/**
	 * The post type to register the taxonomy for.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @var     array
	 */
	private $post_type;

	/**
	 * The key of the taxonomy.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @var     string
	 */
	private $token;

	/**
	 * The singular name for the taxonomy.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @var     string
	 */
	private $singular;

	/**
	 * The plural name for the taxonomy.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @var     string
	 */
	private $plural;

	/**
	 * The arguments to use when registering the taxonomy.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @var     string
	 */
	private $args;

	/**
	 * Class constructor.
	 *
	 * @access  public
	 * @since   1.3.0
	 * @param   array  $post_type The post type key.
	 * @param   string $token     The taxonomy key.
	 * @param   string $singular  Singular name.
	 * @param   string $plural    Plural name.
	 * @param   array  $args      Array of argument overrides.
	 */
	public function __construct( $post_type = array(), $token = 'thing-category', $singular = '', $plural = '', $args = array() ) {
		$this->post_type = $post_type;
		$this->token     = esc_attr( $token );
		$this->singular  = esc_html( $singular );
		$this->plural    = esc_html( $plural );

		if ( '' === $this->singular ) {
			$this->singular = __( 'Category', 'wporg-forums' );
		}
		if ( '' === $this->plural ) {
			$this->plural = __( 'Categories', 'wporg-forums' );
		}

		$this->args = wp_parse_args( $args, $this->_get_default_args() );

		add_action( 'init', array( $this, 'register' ) );

		if ( 'category' === $this->token ) {
			add_action(
				'load-edit-tags.php',
				function() {
					$screen_id = 'edit-' . $this->token;
					add_filter( 'manage_' . $screen_id . '_columns', array( $this, 'register_custom_column_headings' ) );
					add_filter( 'manage_' . $screen_id . '_sortable_columns', array( $this, 'register_custom_column_sorts' ) );
					add_filter( 'manage_' . $this->token . '_custom_column', array( $this, 'register_custom_columns' ), 10, 3 );

					add_action( 'pre_get_terms', array( $this, 'list_table_do_custom_sort' ) );
					add_action(
						'admin_head',
						function() {
							echo '<style>table .column-sort_order{width: 5rem;}</style>';
						}
					);
				}
			);
		}
	} // End __construct()

	/**
	 * Return an array of default arguments.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @return  array Default arguments.
	 */
	private function _get_default_args() {
		return array(
			'labels'            => $this->_get_default_labels(),
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'rewrite'           => array(
				'slug' => str_replace( 'helphub_', '', esc_attr( $this->token ) ),
			),
		);
	} // End _get_default_args()

	/**
	 * Return an array of default labels.
	 *
	 * @access  private
	 * @since   1.3.0
	 * @return  array Default labels.
	 */
	private function _get_default_labels() {
		return array(
			'name'                => sprintf( _x( '%s', 'taxonomy general name', 'wporg-forums' ), $this->plural ), /* @codingStandardsIgnoreLine */
			'singular_name'       => sprintf( _x( '%s', 'taxonomy singular name', 'wporg-forums' ), $this->singular ), /* @codingStandardsIgnoreLine */
			/* translators: %s: Plural of the search label. */
			'search_items'      => sprintf( __( 'Search %s', 'wporg-forums' ), $this->plural ),
			/* translators: %s: Plural name of the post type. */
			'all_items'         => sprintf( __( 'All %s', 'wporg-forums' ), $this->plural ),
			/* translators: %s: Post type name. */
			'parent_item'       => sprintf( __( 'Parent %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'edit_item'         => sprintf( __( 'Edit %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'update_item'       => sprintf( __( 'Update %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'add_new_item'      => sprintf( __( 'Add New %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'new_item_name'     => sprintf( __( 'New %s Name', 'wporg-forums' ), $this->singular ),
			'menu_name'         => $this->plural,
		);
	} // End _get_default_labels()

	/**
	 * Register the taxonomy.
	 *
	 * @access  public
	 * @since   1.3.0
	 * @return  void
	 */
	public function register() {
		register_taxonomy( esc_attr( $this->token ), (array) $this->post_type, (array) $this->args );
	} // End register()

	/**
	 * Add custom column headings for the "manage" screen of this post type.
	 *
	 * @param array $columns The default value.
	 *
	 * @return array
	 */
	public function register_custom_column_headings( $columns ) {
		$new_columns = array(
			'sort_order' => __( 'Order', 'wporg-forums' ),
		);
		$columns = array_merge( array_slice( $columns, 0, 1 ), $new_columns, array_slice( $columns, 1 ) );
		return $columns;
	}

	/**
	 * Enable sorting on columns.
	 *
	 * @param array $cols An array of sortable columns.
	 * @return array Updated list of columns.
	 */
	public function register_custom_column_sorts( $cols ) {
		$cols['sort_order'] = array(
			'sort_order',
			true,
			__( 'Order', 'wporg-forums' ),
			__( 'Table ordered by order.', 'wporg-forums' ),
		);

		return $cols;
	} // End register_custom_column_sorts()

	/**
	 * Add custom columns for the "manage" screen of this post type.
	 *
	 * @access public
	 *
	 * @param string $value Filter content (value of column).
	 * @param string $column_name The name of the column.
	 * @param int    $id The ID.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_custom_columns( $value, $column_name, $id ) {
		switch ( $column_name ) {
			case 'sort_order':
				// phpcs:ignore
				echo get_term_meta( $id, 'sort_order', true );
				break;
			default:
				break;
		}
	} // End register_custom_columns()

	/**
	 * Filter the term query if our custom sort is used.
	 *
	 * @param WP_Term_Query $term_query Current instance of WP_Term_Query (passed by reference).
	 */
	public function list_table_do_custom_sort( $term_query ) {
		if ( isset( $term_query->query_vars['orderby'] ) && 'sort_order' === $term_query->query_vars['orderby'] ) {
			$term_query->query_vars['orderby'] = 'meta_value_num';
			$term_query->query_vars['meta_key'] = 'sort_order';
		}
	}
} // End Class
