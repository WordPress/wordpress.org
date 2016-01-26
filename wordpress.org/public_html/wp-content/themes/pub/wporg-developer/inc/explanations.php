<?php
/**
 * Explanations functionality
 *
 * @package wporg-developer
 */

/**
 * Class to handle creating, editing, managing, and retrieving explanations
 * for various Code Reference post types.
 */
class WPORG_Explanations {

	/**
	 * List of Code Reference post types.
	 *
	 * @access public
	 * @var array
	 */
	public $post_types = array();

	/**
	 * Explanations post type slug.
	 *
	 * @access public
	 * @var string
	 */
	public $exp_post_type = 'wporg_explanations';

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->post_types = DevHub\get_parsed_post_types();

		// Setup.
		add_action( 'init',                    array( $this, 'register_post_type'     ), 0   );
		add_action( 'init',                    array( $this, 'remove_editor_support'  ), 100 );
		add_action( 'edit_form_after_title',   array( $this, 'post_to_expl_controls'  )      );
		add_action( 'edit_form_top',           array( $this, 'expl_to_post_controls'  )      );
		add_action( 'admin_bar_menu',          array( $this, 'toolbar_edit_link'      ), 100 );

		// Script and styles.
		add_action( 'admin_enqueue_scripts',   array( $this, 'admin_enqueue_scripts'  )      );

		// AJAX.
		add_action( 'wp_ajax_new_explanation', array( $this, 'new_explanation'        )      );
		add_action( 'wp_ajax_un_publish',      array( $this, 'un_publish_explanation' )      );

		// Content tweaks.
		add_filter( 'syntaxhighlighter_precode', 'html_entity_decode' );
	}

	/**
	 * Register the Explanations post type.
	 *
	 * @access public
	 */
	public function register_post_type() {
		register_post_type( $this->exp_post_type, array(
			'labels'            => array(
				'name'                => __( 'Explanations', 'wporg' ),
				'singular_name'       => __( 'Explanation', 'wporg' ),
				'all_items'           => __( 'Explanations', 'wporg' ),
				'edit_item'           => __( 'Edit Explanation', 'wporg' ),
				'view_item'           => __( 'View Explanation', 'wporg' ),
				'search_items'        => __( 'Search Explanations', 'wporg' ),
				'not_found'           => __( 'No Explanations found', 'wporg' ),
				'not_found_in_trash'  => __( 'No Explanations found in trash', 'wporg' ),
			),
			'public'            => false,
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_nav_menus' => false,
			'supports'          => array( 'editor', 'revisions' ),
			'rewrite'           => false,
			'query_var'         => false,
		) );
	}

	/**
	 * Remove 'editor' support for the function, hook, class, and method post types.
	 *
	 * @access public
	 */
	public function remove_editor_support() {
		foreach ( $this->post_types as $type ) {
			remove_post_type_support( $type, 'editor' );
		}
	}

	/**
	 * Output the Post-to-Explanation controls in the post editor for functions,
	 * hooks, classes, and methods.
	 *
	 * @access public
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function post_to_expl_controls( $post ) {
		if ( ! in_array( $post->post_type, $this->post_types ) ) {
			return;
		}

		$explanation = DevHub\get_explanation( $post );
		$date_format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
		?>
		<div class="postbox-container" style="margin-top:20px;">
			<div class="postbox">
				<h3 class="hndle"><?php _e( 'Explanation', 'wporg' ); ?></h3>
				<div class="inside">
					<table class="form-table explanation-meta">
						<tbody>
						<tr valign="top">
							<th scope="row">
								<label for="explanation-status"><?php _e( 'Status:', 'wporg' ); ?></label>
							</th>
							<td class="explanation-status" name="explanation-status">
								<div class="status-links">
									<?php $this->status_controls( $post ); ?>
								</div><!-- .status-links -->
							</td><!-- .explanation-status -->
						</tr>
						<?php if ( $explanation ) : ?>
							<tr valign="top">
								<th scope="row">
									<label for="expl-modified"><?php _e( 'Last Modified:', 'wporg' ); ?></label>
								</th>
								<td name="expl-modified">
									<p><?php echo get_post_modified_time( $date_format, false, $post->ID ); ?></p>
								</td>
							</tr>
						<?php endif; // $has_explanation ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the Explanation-to-Post controls in the Explanation post editor.
	 *
	 * @access public
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function expl_to_post_controls( $post ) {
		if ( $this->exp_post_type !== $post->post_type ) {
			return;
		}

		$parent = is_a( $post, 'WP_Post' ) ? $post->post_parent : get_post( $post )->post_parent;

		if ( 0 !== $parent ) :
			$prefix = '<strong>' . __( 'Associated with: ', 'wporg' ) . '</strong>';
			?>
			<div class="postbox-container" style="margin-top:20px;width:100%;">
				<div class="postbox">
					<div class="inside" style="padding-bottom:0;">
						<?php edit_post_link( get_the_title( $parent ), $prefix, '', $parent ); ?>
					</div>
				</div>
			</div>
		<?php
		endif;
	}

	/**
	 * Adds an 'Edit Explanation' link to the Toolbar on parsed post type single pages.
	 *
	 * @access public
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function toolbar_edit_link( $wp_admin_bar ) {
		global $wp_the_query;

		$screen = $wp_the_query->get_queried_object();

		if ( is_admin() || empty( $screen->post_type ) || ! is_singular( $this->post_types ) ) {
			return;
		}

		if ( ! empty( $screen->post_type ) ) {
			// Proceed only if there's an explanation for the current reference post type.
			if ( $explanation = \DevHub\get_explanation( $screen ) ) {
				$post_type = get_post_type_object( $this->exp_post_type );

				$wp_admin_bar->add_menu( array(
					'id'    => 'edit-explanation',
					'title' => $post_type->labels->edit_item,
					'href'  => get_edit_post_link( $explanation )
				) );
			}
		}
	}

	/**
	 * Output the Explanation status controls.
	 *
	 * @access public
	 *
	 * @param int|WP_Post Post ID or WP_Post object.
	 */
	public function status_controls( $post ) {
		$explanation = DevHub\get_explanation( $post );

		if ( $explanation ) :
			echo $this->get_status_label( $explanation->ID );
			?>
			<span id="expl-row-actions" class="expl-row-actions">
				<a id="edit-expl" href="<?php echo get_edit_post_link( $explanation->ID ); ?>">
					<?php _e( 'Edit Content', 'wporg' ); ?>
				</a>
				<?php if ( 'publish' == get_post_status( $explanation ) ) : ?>
					<a href="#unpublish" id="unpublish-expl" data-nonce="<?php echo wp_create_nonce( 'unpublish-expl' ); ?>" data-id="<?php the_ID(); ?>">
						<?php _e( 'Un-publish', 'wporg' ); ?>
					</a>
				<?php endif; ?>
			</span><!-- .expl-row-actions -->
		<?php else : ?>
			<p class="status" id="status-label"><?php _e( 'None', 'wporg' ); ?></p>
			<span id="expl-row-actions" class="expl-row-actions">
				<a id="create-expl" href="" data-nonce="<?php echo wp_create_nonce( 'create-expl' ); ?>" data-id="<?php the_ID(); ?>">
					<?php _e( 'Add Explanation', 'wporg' ); ?>
				</a><!-- #create-explanation -->
			</span><!-- expl-row-actions -->
		<?php
		endif;
	}

	/**
	 * Retrieve status label for the given post.
	 *
	 * @access public
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return string
	 */
	public function get_status_label( $post ) {
		if ( ! $post = get_post( $post ) ) {
			return '';
		}

		switch( $status = $post->post_status ) {
			case 'draft' :
				$label = __( 'Drafted', 'wporg' );
				break;
			case 'pending' :
				$label = __( 'Pending Review', 'wporg' );
				break;
			case 'publish' :
				$label = __( 'Published', 'wporg' );
				break;
			default :
				$status = '';
				$label = __( 'None', 'wporg' );
				break;
		}

		return '<p class="status ' . $status . '" id="status-label">' . $label . '</p>';
	}

	/**
	 * Enqueue JS and CSS for all parsed post types and explanation pages.
	 *
	 * @access public
	 */
	public function admin_enqueue_scripts() {

		if ( in_array( get_current_screen()->id, array_merge(
				DevHub\get_parsed_post_types(),
				array( 'wporg_explanations', 'edit-wporg_explanations' )
		) ) ) {
			wp_enqueue_style( 'wporg-admin', get_template_directory_uri() . '/stylesheets/admin.css', array(), '20141218' );
			wp_enqueue_script( 'wporg-explanations', get_template_directory_uri() . '/js/explanations.js', array( 'jquery', 'wp-util' ), '20141218', true );

			wp_localize_script( 'wporg-explanations', 'wporg', array(
				'editContentLabel' => __( 'Edit Content', 'wporg' ),
				'statusLabel'      => array(
					'draft'        => __( 'Drafted', 'wporg' ),
					'pending'      => __( 'Pending Review', 'wporg' ),
					'publish'      => __( 'Published', 'wporg' ),
				),
			) );
		}
	}

	/**
	 * AJAX handler for creating and associating a new explanation.
	 *
	 * @access public
	 */
	public function new_explanation() {
		check_ajax_referer( 'create-expl', 'nonce' );

		$post_id = empty( $_REQUEST['post_id'] ) ? 0 : absint( $_REQUEST['post_id'] );

		if ( DevHub\get_explanation( $post_id ) ) {
			wp_send_json_error( new WP_Error( 'post_exists', __( 'Explanation already exists.', 'wporg' ) ) );
		} else {
			$title = get_post_field( 'post_title', $post_id );

			$explanation = wp_insert_post( array(
				'post_type'   => 'wporg_explanations',
				'post_title'  => "Explanation: $title",
				'ping_status' => false,
				'post_parent' => $post_id,
			) );

			if ( ! is_wp_error( $explanation ) && 0 !== $explanation ) {
				wp_send_json_success( array(
					'post_id' => $explanation
				) );
			} else {
				wp_send_json_error(
					new WP_Error( 'post_error', __( 'Explanation could not be created.', 'wporg' ) )
				);
			}

		}
	}

	/**
	 * AJAX handler for un-publishing an explanation.
	 *
	 * @access public
	 */
	public function un_publish_explanation() {
		check_ajax_referer( 'unpublish-expl', 'nonce' );

		$post_id = empty( $_REQUEST['post_id'] ) ? 0 : absint( $_REQUEST['post_id'] );

		if ( $explanation = \DevHub\get_explanation( $post_id ) ) {
			$update = wp_update_post( array(
				'ID'          => $explanation->ID,
				'post_status' => 'draft'
			) );

			if ( ! is_wp_error( $update ) && 0 !== $update ) {
				wp_send_json_success( array( 'post_id' => $update ) );
			} else {
				wp_send_json_error(
					new WP_Error( 'unpublish_error', __( 'Explanation could not be un-published.', 'wporg' ) )
				);
			}
		}
	}
}

$explanations = new WPORG_Explanations();
