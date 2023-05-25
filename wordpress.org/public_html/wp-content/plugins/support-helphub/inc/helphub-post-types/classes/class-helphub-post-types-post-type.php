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
 * Helphub Post Types, Post Type Class
 *
 * All functionality pertaining to post types in Helphub Post Types.
 *
 * @package WordPress
 * @subpackage HelpHub_Post_Types
 * @category Plugin
 * @author Jon Ang
 * @since 1.0.0
 */
class HelpHub_Post_Types_Post_Type {
	/**
	 * The post type token.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $post_type;

	/**
	 * The slug used in URLs for the post type items.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $single_slug;

	/**
	 * The slug used in URLs for the archive of the post type.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $archive_slug;

	/**
	 * The post type singular label.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $singular;

	/**
	 * The post type plural label.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $plural;

	/**
	 * The post type args.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    array
	 */
	public $args;

	/**
	 * The taxonomies for this post type.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    array
	 */
	public $taxonomies;

	/**
	 * Constructor function.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type id/handle.
	 * @param string $singular The singular pronunciation of the post type name.
	 * @param string $plural The plural pronunciation of the post type name.
	 * @param array $args The typical arguments allowed to register a post type.
	 * @param string $single_slug The 'singular' slug for the post type.
	 * @param string $archive_slug The 'archive' slug for the post type.
	 * @param array $taxonomies The list of taxonomies that the post type is associated with.
	 */
	public function __construct( $post_type = 'thing', $singular = '', $plural = '', $args = array(), $taxonomies = array(), $single_slug = false, $archive_slug = false ) {
		$this->post_type  = $post_type;
		$this->singular   = $singular;
		$this->plural     = $plural;
		$this->args       = $args;
		$this->taxonomies = $taxonomies;

		if ( ! $single_slug ) {
			$single_slug = sanitize_title_with_dashes( $this->singular );
		}
		$this->single_slug  = apply_filters( 'helphub_single_slug', $single_slug );

		if ( ! $archive_slug ) {
			$archive_slug = sanitize_title_with_dashes( $this->plural );
		}
		$this->archive_slug = apply_filters( 'helphub_archive_slug', $archive_slug );

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		if ( is_admin() ) {
			global $pagenow;

			add_action( 'admin_menu', array( $this, 'meta_box_setup' ), 20 );
			add_action( 'save_post', array( $this, 'meta_box_save' ), 50 );
			add_filter( 'enter_title_here', array( $this, 'enter_title_here' ) );
			add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );

			if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) { // WPCS: input var ok; CSRF ok.
				add_filter(
					'manage_edit-' . $this->post_type . '_columns', array(
						$this,
						'register_custom_column_headings',
					), 10, 1
				);
				add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
			}
		}
		add_action( 'admin_init', array( $this, 'add_menu_order' ) );
		add_action( 'after_setup_theme', array( $this, 'ensure_post_thumbnails_support' ) );
	} // End __construct()

	/**
	 * Register the post type.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_type() {

		if ( post_type_exists( $this->post_type ) ) {
			return;
		}

		$labels = array(
			'name'               => $this->plural,
			'singular_name'      => $this->singular,
			'add_new'            => _x( 'Add New', 'add new helphub post', 'wporg-forums' ),
			/* translators: %s: Post type name. */
			'add_new_item'       => sprintf( __( 'Add New %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'edit_item'          => sprintf( __( 'Edit %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Post type name. */
			'new_item'           => sprintf( __( 'New %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Plural post type name. */
			'all_items'          => sprintf( __( 'All %s', 'wporg-forums' ), $this->plural ),
			/* translators: %s: Post type name. */
			'view_item'          => sprintf( __( 'View %s', 'wporg-forums' ), $this->singular ),
			/* translators: %s: Plural post type name. */
			'search_items'       => sprintf( __( 'Search %s', 'wporg-forums' ), $this->plural ),
			/* translators: %s: Plural post type name. */
			'not_found'          => sprintf( __( 'No %s Found', 'wporg-forums' ), $this->plural ),
			/* translators: %s: Plural post type name. */
			'not_found_in_trash' => sprintf( __( 'No %s Found In Trash', 'wporg-forums' ), $this->plural ),
			'parent_item_colon'  => '',
			'menu_name'          => $this->plural,
		);

		$defaults = array(
			'labels'                => $labels,
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'query_var'             => true,
			'rewrite'               => array(
				'slug' => $this->single_slug,
			),
			'capability_type'       => 'post',
			'has_archive'           => $this->archive_slug,
			'hierarchical'          => false,
			'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions' ),
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-smiley',
			'show_in_rest'          => true,
			'rest_base'             => $this->archive_slug,
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		$args = wp_parse_args( $this->args, $defaults );

		register_post_type( $this->post_type, $args );
	} // End register_post_type()

	/**
	 * Register the post-type taxonomy.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function register_taxonomy() {
		foreach ( $this->taxonomies as $taxonomy ) {
			$taxonomy = new HelpHub_Post_Types_Taxonomy( esc_attr( $this->post_type ), $taxonomy, '', '', array() ); // Leave arguments empty, to use the default arguments.
			$taxonomy->register();
		}
	} // End register_taxonomy()

	/**
	 * Add custom columns for the "manage" screen of this post type.
	 *
	 * @access public
	 *
	 * @param string $column_name The name of the column.
	 * @param int $id The ID.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_custom_columns( $column_name, $id ) {
		switch ( $column_name ) {
			case 'image':
				// Displays img tag.
				echo $this->get_image( $id, 40 );
				/* @codingStandardsIgnoreLine */
				break;
			default:
				break;
		}
	} // End register_custom_columns()

	/**
	 * Add custom column headings for the "manage" screen of this post type.
	 *
	 * @access public
	 *
	 * @param array $defaults The default value.
	 *
	 * @since  1.0.0
	 * @return array $defaults
	 */
	public function register_custom_column_headings( $defaults ) {
		$new_columns = array();

		$last_item = array();

		if ( isset( $defaults['date'] ) ) {
			unset( $defaults['date'] );
		}

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, - 1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( is_array( $last_item ) && 0 < count( $last_item ) ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[ $k ] = $v;
				break;
			}
		}

		return $defaults;
	} // End register_custom_column_headings()

	/**
	 * Update messages for the post type admin.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $messages Array of messages for all post types.
	 *
	 * @return array           Modified array.
	 */
	public function updated_messages( $messages ) {
		global $post, $post_ID; /* @codingStandardsIgnoreLine */

		$permalink = get_permalink( $post_ID ); /* @codingStandardsIgnoreLine */

		$messages[ $this->post_type ] = array(
			0  => '',
			// Unused. Messages start at index 1.
			/* translators: %1$s: Post link tag. %2$s: Close post link tag. %3$s: Post type name. %4$s: Lowercase post type name. */
			1  => sprintf( __( '%3$s updated. %1$sView %4$s%2$s', 'wporg-forums' ), '<a href="' . esc_url( $permalink ) . '">', '</a>', $this->singular, strtolower( $this->singular ) ),
			2  => __( 'Custom field updated.', 'wporg-forums' ),
			3  => __( 'Custom field deleted.', 'wporg-forums' ),
			/* translators: %s: Post type name. */
			4  => sprintf( __( '%s updated.', 'wporg-forums' ), $this->singular ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( '%1$s restored to revision from %2$s', 'wporg-forums' ), $this->singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			// WPCS: CSRF ok; input var ok.
			/* translators: %1$s Post type name. %2$s: Lowercase post type name. %3$s: Post link tag. %4$s: Close post link tag. */
			6  => sprintf( __( '%1$s published. %3$sView %2$s%4$s', 'wporg-forums' ), $this->singular, strtolower( $this->singular ), '<a href="' . esc_url( $permalink ) . '">', '</a>' ),
			/* translators: %s: Post type name. */
			7  => sprintf( __( '%s saved.', 'wporg-forums' ), $this->singular ),
			/* translators: %1$s: Post type name. %2$s: Lowercase post type name. %3$s: Post link tag. %4$s: Close post link tag. */
			8  => sprintf( __( '%1$s submitted. %2$sPreview %3$s%4$s', 'wporg-forums' ), $this->singular, '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', $permalink ) ) . '">', strtolower( $this->singular ), '</a>' ),
			/* translators: %1$s: Post type name. %2$s: Lowercase post type name. %3$s: Date. %4$s: Post link tag. %5$s: Close post link tag. */
			9  => sprintf( __( '%1$s scheduled for: %3$s. %4$sPreview %2$s%5$s', 'wporg-forums' ), $this->singular, strtolower( $this->singular ), '<strong>' . date_i18n( __( 'M j, Y @ G:i', 'wporg-forums' ), strtotime( $post->post_date ) ) . '</strong>', '<a target="_blank" href="' . esc_url( $permalink ) . '">', '</a>' ),
			/* translators: %1$s: Post type name. %2$s: Lowercase post type name. %3$s: Post link tag. %4$s: Close post link tag. */
			10 => sprintf( __( '%1$s draft updated. %3$sPreview %2$s%4$s', 'wporg-forums' ), $this->singular, strtolower( $this->singular ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', $permalink ) ) . '">', '</a>' ),
		);

		return $messages;
	} // End updated_messages()

	/**
	 * Setup the meta box.
	 * You can use separate conditions here to add different meta boxes for different post types
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_setup() {
		if ( 'post' === $this->post_type ) {
			add_meta_box(
				$this->post_type . '-display', __( 'Display Settings', 'wporg-forums' ), array(
					$this,
					'meta_box_content',
				), $this->post_type, 'normal', 'high'
			);
		} elseif ( 'helphub_version' === $this->post_type ) {
			add_meta_box(
				$this->post_type . '-version-meta', __( 'Display Settings', 'wporg-forums' ), array(
					$this,
					'meta_box_version_content',
				), $this->post_type, 'normal', 'high'
			);
		}
	} // End meta_box_setup()

	/**
	 * The contents of our post meta box.
	 * Duplicate this function for more callbacks
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_content() {
		$field_data = $this->get_custom_fields_post_display_settings();
		$this->meta_box_content_render( $field_data );
	}

	/**
	 * The contents of our post meta box.
	 * Duplicate this function for more callbacks
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_version_content() {
		$field_data = $this->get_custom_fields_version_display_settings();
		$this->meta_box_content_render( $field_data );
	}

	/**
	 * The rendering of fields in meta boxes
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param array $field_data The field data to populate the rendering function.
	 *
	 * @return void
	 */
	public function meta_box_content_render( $field_data ) {
		global $post_id;
		$fields = get_post_custom( $post_id );

		$html = '';

		$html .= '<input type="hidden" name="helphub_' . $this->post_type . '_noonce" id="helphub_' . $this->post_type . '_noonce" value="' . wp_create_nonce( plugin_basename( dirname( HelpHub_Post_Types()->plugin_path ) ) ) . '" />';

		if ( 0 < count( $field_data ) ) {
			$html .= '<table class="form-table">' . "\n";
			$html .= '<tbody>' . "\n";

			foreach ( $field_data as $k => $v ) {
				$data = $v['default'];
				if ( isset( $fields[ '_' . $k ] ) && isset( $fields[ '_' . $k ][0] ) ) {
					$data = $fields[ '_' . $k ][0];
				}

				switch ( $v['type'] ) {
					case 'hidden':
						$field = '<input name="' . esc_attr( $k ) . '" type="hidden" id="' . esc_attr( $k ) . '" value="' . esc_attr( $data ) . '" />';
						$html .= '<tr valign="top">' . $field . "\n";
						$html .= '</tr>' . "\n";
						break;
					case 'text':
					case 'url':
						$field = '<input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text" value="' . esc_attr( $data ) . '" />';
						$html .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'textarea':
						$field = '<textarea name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" class="large-text">' . esc_attr( $data ) . '</textarea>';
						$html .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'editor':
						ob_start();
						wp_editor(
							$data, $k, array(
								'media_buttons' => false,
								'textarea_rows' => 10,
							)
						);
						$field = ob_get_contents();
						ob_end_clean();
						$html .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'upload':
						$data_atts = '';
						if ( isset( $v['media-frame']['title'] ) ) {
							$data_atts .= sprintf( 'data-title="%s" ', esc_attr( $v['media-frame']['title'] ) );
						}
						if ( isset( $v['media-frame']['button'] ) ) {
							$data_atts .= sprintf( 'data-button="%s" ', esc_attr( $v['media-frame']['button'] ) );
						}
						if ( isset( $v['media-frame']['library'] ) ) {
							$data_atts .= sprintf( 'data-library="%s" ', esc_attr( $v['media-frame']['library'] ) );
						}

						$field  = '<input name="' . esc_attr( $k ) . '" type="file" id="' . esc_attr( $k ) . '" class="regular-text helphub-upload-field" />';
						$field .= '<button id="' . esc_attr( $k ) . '" class="helphub-upload button" ' . $data_atts . '>' . $v['label'] . '</button>';
						$html  .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'radio':
						$field = '';
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<p><label for="' . esc_attr( $k . '-' . $val ) . '"><input id="' . esc_attr( $k . '-' . $val ) . '" type="radio" name="' . esc_attr( $k ) . '" value="' . esc_attr( $val ) . '" ' . checked( $val, $data, false ) . ' />' . $option . '</label></p>' . "\n";
							}
						}
						$html .= '<tr valign="top"><th><label>' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'checkbox':
						$field = '<p><input id="' . esc_attr( $v['name'] ) . '" type="checkbox" name="' . esc_attr( $k ) . '" value="1" ' . checked( 'yes', $data, false ) . ' / ></p>' . "\n";
						if ( isset( $v['description'] ) ) {
							$field .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '<tr valign="top"><th><label for="' . esc_attr( $v['name'] ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						$html .= '</td></tr>' . "\n";
						break;
					case 'multicheck':
						$field = '';
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<p><label for="' . esc_attr( $k . '-' . $val ) . '"><input id="' . esc_attr( $k . '-' . $val ) . '" type="checkbox" name="' . esc_attr( $k ) . '[]" value="' . esc_attr( $val ) . '" ' . checked( 1, in_array( $val, (array) $data, true ), false ) . ' />' . $option . '</label></p>' . "\n";
							}
						}
						$html .= '<tr valign="top"><th><label>' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'select':
						$field = '<select name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" >' . "\n";
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<option value="' . esc_attr( $val ) . '" ' . selected( $val, $data, false ) . '>' . $option . '</option>' . "\n";
							}
						}
						$field .= '</select>' . "\n";
						$html  .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'date':
						if ( ! intval( $data ) ) {
							$data = time();
						}
						$field = '<input name="' . esc_attr( $k ) . '" type="date" id="' . esc_attr( $k ) . '" class="helphub-meta-date" value="' . esc_attr( date_i18n( 'F d, Y', $data ) ) . '" />';
						$html .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					default:
						$field = apply_filters( 'helphub_data_field_type_' . $v['type'], null, $k, $data, $v );
						if ( $field ) {
							$html .= '<tr valign="top"><th><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
							if ( isset( $v['description'] ) ) {
								$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
							}
							$html .= '</td></tr>' . "\n";
						}
						break;
				} // End switch().
			} // End foreach().

			$html .= '</tbody>' . "\n";
			$html .= '</table>' . "\n";
		} // End if().

		echo $html;
		/* @codingStandardsIgnoreLine */
	} // End meta_box_content()

	/**
	 * Save meta box fields.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int $post_id
	 */
	public function meta_box_save( $post_id ) {
		// Verify.
		$plugin_basename = plugin_basename( dirname( HelpHub_Post_Types()->plugin_path ) );
		$nonce_key       = 'helphub_' . $this->post_type . '_noonce';
		/* @codingStandardsIgnoreLine */
		if ( empty( $_POST[ $nonce_key ] ) || ( get_post_type() != $this->post_type ) || ! wp_verify_nonce( $_POST[ $nonce_key ], $plugin_basename ) ) {
			return $post_id;
		}

		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			/* @codingStandardsIgnoreLine */
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}

		$field_data = $this->get_custom_fields_settings();
		$fields     = array_keys( $field_data );

		foreach ( $fields as $f ) {

			switch ( $field_data[ $f ]['type'] ) {
				case 'url':
					${$f} = isset( $_POST[ $f ] ) ? esc_url( $_POST[ $f ] ) : '';
					/* @codingStandardsIgnoreLine */
					break;
				case 'textarea':
				case 'editor':
					${$f} = isset( $_POST[ $f ] ) ? wp_kses_post( trim( $_POST[ $f ] ) ) : '';
					/* @codingStandardsIgnoreLine */
					break;
				case 'checkbox':
					${$f} = isset( $_POST[ $f ] ) ? 'yes' : 'no';
					/* @codingStandardsIgnoreLine */
					break;
				case 'multicheck':
					// Ensure checkbox is array and whitelist accepted values against options.
					${$f} = isset( $_POST[ $f ] ) && is_array( $field_data[ $f ]['options'] ) ? (array) array_intersect( (array) $_POST[ $f ], array_flip( $field_data[ $f ]['options'] ) ) : '';
					/* @codingStandardsIgnoreLine */
					break;
				case 'radio':
				case 'select':
					// Whitelist accepted value against options.
					$values = array();
					if ( is_array( $field_data[ $f ]['options'] ) ) {
						$values = array_keys( $field_data[ $f ]['options'] );
					}
					${$f} = isset( $_POST[ $f ] ) && in_array( $_POST[ $f ], $values ) ? $_POST[ $f ] : '';
					/* @codingStandardsIgnoreLine */
					break;
				case 'date':
					${$f} = isset( $_POST[ $f ] ) ? strtotime( wp_strip_all_tags( $_POST[ $f ] ) ) : '';
					/* @codingStandardsIgnoreLine */
					break;
				default:
					${$f} = isset( $_POST[ $f ] ) ? strip_tags( trim( $_POST[ $f ] ) ) : '';
					/* @codingStandardsIgnoreLine */
					break;
			}

			// Save it.
			if ( 'read_time' !== $f ) {
				update_post_meta( $post_id, '_' . $f, ${$f} );
			}
		} // End foreach().

		// Save the project gallery image IDs.
		if ( isset( $_POST['helphub_image_gallery'] ) ) : /* @codingStandardsIgnoreLine */
			$attachment_ids = array_filter( explode( ',', sanitize_text_field( $_POST['helphub_image_gallery'] ) ) );
			/* @codingStandardsIgnoreLine */
			update_post_meta( $post_id, '_helphub_image_gallery', implode( ',', $attachment_ids ) );
		endif;

		return $post_id;
	} // End meta_box_save()

	/**
	 * Customise the "Enter title here" text.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param string $title The title.
	 *
	 * @return string $title
	 */
	public function enter_title_here( $title ) {
		if ( get_post_type() === $this->post_type ) {
			if ( 'post' === get_post_type() ) {
				$title = __( 'Enter the article title here', 'wporg-forums' );
			}
		}

		return $title;
	} // End enter_title_here()

	/**
	 * Get the settings for the custom fields.
	 * Use array merge to get a unified fields array
	 * eg. $fields = array_merge( $this->get_custom_fields_post_display_settings(), $this->get_custom_fields_post_advertisement_settings(), $this->get_custom_fields_post_spacer_settings() );
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_settings() {

		$fields = array();
		if ( 'post' === get_post_type() ) {
			$fields = $this->get_custom_fields_post_display_settings();
		} elseif ( 'helphub_version' === get_post_type() ) {
			$fields = $this->get_custom_fields_version_display_settings();
		}

		return $fields;

	} // End get_custom_fields_settings()

	/**
	 * Get the settings for the post display custom fields.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_post_display_settings() {
		$fields = array();

		$fields['read_time'] = array(
			'name'        => __( 'Article Read Time', 'wporg-forums' ),
			'description' => __( 'Leave this empty, calculation is automatic', 'wporg-forums' ),
			'type'        => 'text',
			'default'     => '',
			'section'     => 'info',
		);

		$fields['custom_read_time'] = array(
			'name'        => __( 'Custom Read Time', 'wporg-forums' ),
			'description' => __( 'Only fill up this field if the automated calculation is incorrect', 'wporg-forums' ),
			'type'        => 'text',
			'default'     => '',
			'section'     => 'info',
		);

		return $fields;
	}

	/**
	 * Get the settings for the post display custom fields.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_version_display_settings() {
		$fields = array();

		$fields['version_date'] = array(
			'name'        => __( 'Date Released', 'wporg-forums' ),
			'description' => __( 'Date this WordPress Version was released', 'wporg-forums' ),
			'type'        => 'date',
			'default'     => '',
			'section'     => 'info',
		);

		$fields['musician_codename'] = array(
			'name'        => __( 'Musician', 'wporg-forums' ),
			'description' => __( 'The Jazz Musician this release was named after', 'wporg-forums' ),
			'type'        => 'text',
			'default'     => '',
			'section'     => 'info',
		);

		return $fields;
	}


	/**
	 * Get the image for the given ID.
	 *
	 * @param  int $id The post ID.
	 * @param  mixed $size Image dimension. (default: "thing-thumbnail").
	 *
	 * @since  1.0.0
	 * @return string <img> tag.
	 */
	protected function get_image( $id, $size = 'thing-thumbnail' ) {
		$response = '';

		if ( has_post_thumbnail( $id ) ) {
			// If not a string or an array, and not an integer, default to 150x9999.
			if ( ( is_int( $size ) || ( 0 < intval( $size ) ) ) && ! is_array( $size ) ) {
				$size = array( intval( $size ), intval( $size ) );
			} elseif ( ! is_string( $size ) && ! is_array( $size ) ) {
				$size = array( 150, 9999 );
			}
			$response = get_the_post_thumbnail( intval( $id ), $size );
		}

		return $response;
	} // End get_image()

	/**
	 * Run on activation.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function activation() {
		$this->flush_rewrite_rules();
	} // End activation()

	/**
	 * Flush the rewrite rules
	 *
	 * @access public
	 * @since 1.0.0
	 */
	private function flush_rewrite_rules() {
		$this->register_post_type();
		flush_rewrite_rules();
	} // End flush_rewrite_rules()

	/**
	 * Ensure that "post-thumbnails" support is available for those themes that don't register it.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function ensure_post_thumbnails_support() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails' );
		}
	} // End ensure_post_thumbnails_support()

	/**
	 * Add menu order
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function add_menu_order() {
		add_post_type_support( 'post', 'page-attributes' );
	} // End ens

} // End Class
