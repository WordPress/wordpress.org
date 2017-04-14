<?php
/**
 * Set up functionality for the Planning Checklist tool.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks;
defined( 'WPINC' ) || die();

use WordCamp\Mentors;

/**
 * Initialize the Tasks functionality
 */
function init() {
	register_cpt();
	register_tax();
	register_status();

	// Admin notices.
	if ( isset( $_GET['page'] ) && Mentors\PREFIX . '-planning-checklist' === $_GET['page'] ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
	}
}

add_action( 'init', __NAMESPACE__ . '\init', 0 );

/**
 * Register custom post types.
 *
 * @since 1.0.0
 */
function register_cpt() {
	$labels = array(
		'name'                  => _x( 'Tasks', 'Post Type General Name', 'wordcamporg' ),
		'singular_name'         => _x( 'Task', 'Post Type Singular Name', 'wordcamporg' ),
		'menu_name'             => __( 'Tasks', 'wordcamporg' ),
		'name_admin_bar'        => __( 'Tasks', 'wordcamporg' ),
		'archives'              => __( 'Task Archives', 'wordcamporg' ),
		'attributes'            => __( 'Task Attributes', 'wordcamporg' ),
		'parent_item_colon'     => __( 'Parent Task:', 'wordcamporg' ),
		'all_items'             => __( 'All Tasks', 'wordcamporg' ),
		'add_new_item'          => __( 'Add New Task', 'wordcamporg' ),
		'add_new'               => __( 'Add New', 'wordcamporg' ),
		'new_item'              => __( 'New Task', 'wordcamporg' ),
		'edit_item'             => __( 'Edit Task', 'wordcamporg' ),
		'update_item'           => __( 'Update Task', 'wordcamporg' ),
		'view_item'             => __( 'View Task', 'wordcamporg' ),
		'view_items'            => __( 'View Tasks', 'wordcamporg' ),
		'search_items'          => __( 'Search Task', 'wordcamporg' ),
		'not_found'             => __( 'Not found', 'wordcamporg' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'wordcamporg' ),
		'featured_image'        => __( 'Featured Image', 'wordcamporg' ),
		'set_featured_image'    => __( 'Set featured image', 'wordcamporg' ),
		'remove_featured_image' => __( 'Remove featured image', 'wordcamporg' ),
		'use_featured_image'    => __( 'Use as featured image', 'wordcamporg' ),
		'insert_into_item'      => __( 'Insert into task', 'wordcamporg' ),
		'uploaded_to_this_item' => __( 'Uploaded to this task', 'wordcamporg' ),
		'items_list'            => __( 'Tasks list', 'wordcamporg' ),
		'items_list_navigation' => __( 'Tasks list navigation', 'wordcamporg' ),
		'filter_items_list'     => __( 'Filter tasks list', 'wordcamporg' ),
	);

	$args = array(
		'label'                 => __( 'Task', 'wordcamporg' ),
		'description'           => __( 'Planning Checklist tasks', 'wordcamporg' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'excerpt', 'page-attributes', 'custom-fields' ),
		'taxonomies'            => array( Mentors\PREFIX . '_task_category' ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => false,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'show_in_admin_bar'     => false,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'rewrite'               => false,
		'capability_type'       => 'task',
		'show_in_rest'          => true,
		'rest_controller_class' => __NAMESPACE__ . '\Controller',
	);

	register_post_type( Mentors\PREFIX . '_task', $args );

	add_filter( 'map_meta_cap', __NAMESPACE__ . '\map_task_caps', 10, 2 );
}

/**
 * Map CPT capabilities.
 *
 * @since 1.0.0
 *
 * @param array  $caps The user's actual capabilities.
 * @param string $cap  Capability name.
 *
 * @return array
 */
function map_task_caps( $caps, $cap ) {
	switch ( $cap ) {
		case 'edit_task' :
		case 'edit_tasks' :
		case 'edit_others_tasks' :
		case 'read_task' :
			$caps[] = Mentors\ORGANIZER_CAP;
			break;

		case 'read_private_tasks' :
		case 'publish_tasks' :
		case 'delete_task' :
			$caps[] = Mentors\MENTOR_CAP;
			break;
	}

	return $caps;
}

/**
 * Register custom taxonomies.
 *
 * @since 1.0.0
 */
function register_tax() {
	$labels = array(
		'name'                       => _x( 'Task Categories', 'Taxonomy General Name', 'wordcamporg' ),
		'singular_name'              => _x( 'Task Category', 'Taxonomy Singular Name', 'wordcamporg' ),
		'menu_name'                  => __( 'Category', 'wordcamporg' ),
		'all_items'                  => __( 'All Categories', 'wordcamporg' ),
		'parent_item'                => __( 'Parent Category', 'wordcamporg' ),
		'parent_item_colon'          => __( 'Parent Category:', 'wordcamporg' ),
		'new_item_name'              => __( 'New Category Name', 'wordcamporg' ),
		'add_new_item'               => __( 'Add New Category', 'wordcamporg' ),
		'edit_item'                  => __( 'Edit Category', 'wordcamporg' ),
		'update_item'                => __( 'Update Category', 'wordcamporg' ),
		'view_item'                  => __( 'View Category', 'wordcamporg' ),
		'separate_items_with_commas' => __( 'Separate categories with commas', 'wordcamporg' ),
		'add_or_remove_items'        => __( 'Add or remove categories', 'wordcamporg' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'wordcamporg' ),
		'popular_items'              => __( 'Popular Categories', 'wordcamporg' ),
		'search_items'               => __( 'Search Categories', 'wordcamporg' ),
		'not_found'                  => __( 'Not Found', 'wordcamporg' ),
		'no_terms'                   => __( 'No categories', 'wordcamporg' ),
		'items_list'                 => __( 'Categories list', 'wordcamporg' ),
		'items_list_navigation'      => __( 'Categories list navigation', 'wordcamporg' ),
	);

	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => false,
		'show_ui'                    => false,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => false,
		'show_tagcloud'              => false,
		'rewrite'                    => false,
		'show_in_rest'               => true,
	);

	register_taxonomy( Mentors\PREFIX . '_task_category', array( Mentors\PREFIX . '_task' ), $args );
}

/**
 * Register custom post statuses.
 *
 * @since 1.0.0
 */
function register_status() {
	$stati = array(
		Mentors\PREFIX . '_task_pending'  => esc_html__( 'Pending',  'wordcamporg' ),
		Mentors\PREFIX . '_task_complete' => esc_html__( 'Complete', 'wordcamporg' ),
		Mentors\PREFIX . '_task_skipped'  => esc_html__( 'Skipped',  'wordcamporg' ),
	);

	foreach ( $stati as $id => $label ) {
		register_post_status(
			$id,
			array(
				'label'       => $label,
				'public'                    => false,
				// Custom parameter to flag its use with the Task CPT.
				Mentors\PREFIX . '_task' => true,
			)
		);
	}
}

/**
 * Register fields to include in REST responses.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_rest_fields() {
	register_rest_field(
		Mentors\PREFIX . '_task',
		'lastModifier',
		array(
			'get_callback' => function( $object ) {
				$object = (object) $object;

				return get_post_meta( $object->id, Mentors\PREFIX . '-last-modifier', true );
			},
			'schema'       => array(
				'description' => __( 'Username of the last user to modify the task post.', 'wordcamporg' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'sanitize_callback' => 'sanitize_user',
				),
			),
		)
	);

	register_rest_field(
		Mentors\PREFIX . '_task',
		'helpLink',
		array(
			'schema'       => array(
				'description' => __( 'A help link for more information about a task.', 'wordcamporg' ),
				'type'        => 'object',
				'context'     => array( 'view' ),
				'properties'  => array(
					'text' => array(
						'description' => __( 'The help link text.', 'wordcamporg' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					),
					'url'  => array(
						'description' => __( 'The help link URL.', 'wordcamporg' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					),
				),
			),
		)
	);
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_fields' );

/**
 * Get the array of Task-specific status objects.
 *
 * @since 1.0.0
 *
 * @return array
 */
function get_task_statuses() {
	return get_post_stati(
		array(
			Mentors\PREFIX . '_task' => true,
		),
		false
	);
}

/**
 * Add a page to the Dashboard menu.
 *
 * @since 1.0.0
 *
 * @return void
 */
function add_tasks_page() {
	\add_submenu_page(
		'index.php',
		__( 'Planning Checklist', 'wordcamporg' ),
		__( 'Planning', 'wordcamporg' ),
		Mentors\ORGANIZER_CAP,
		Mentors\PREFIX . '-planning-checklist',
		__NAMESPACE__ . '\render_tasks_page'
	);
}

add_action( 'admin_menu', __NAMESPACE__ . '\add_tasks_page' );

/**
 * Render callback for the page.
 *
 * @since 1.0.0
 *
 * return void
 */
function render_tasks_page() {
	$list_table = new List_Table();
	$list_table->prepare_items();

	include Mentors\get_views_dir_path() . 'tasks.php';
}

/**
 * Enqueue JavaScript and CSS assets for the Tasks Dashboard page.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page.
 *
 * @return void
 */
function enqueue_page_assets( $hook_suffix ) {
	if ( 'dashboard_page_' . Mentors\PREFIX . '-planning-checklist' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		Mentors\PREFIX . '-planning-checklist',
		Mentors\get_css_url() . 'tasks/dashboard.css',
		array(),
		Mentors\CSS_VERSION
	);

	$script_dependencies = array(
		'wp-api',
		'wp-util',
		'utils',
	);

	wp_enqueue_script(
		Mentors\PREFIX . '-planning-checklist',
		Mentors\get_js_url() . 'tasks/dashboard.js',
		$script_dependencies,
		Mentors\JS_VERSION,
		true
	);

	wp_localize_script(
		Mentors\PREFIX . '-planning-checklist',
		'WordCampMentors',
		array(
			'prefix'  => Mentors\PREFIX,
			'l10n'    => array(
				'confirmReset' => esc_html__( 'Are you sure you want to reset the task data? This action cannot be undone.', 'wordcamporg' ),
			),
			'stati'   => get_task_statuses(),
		)
	);
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_page_assets', 20 );

/**
 * Render JS templates.
 *
 * @since 1.0.0
 *
 * @return void
 */
function print_templates() {
	$js_list_table = new List_Table( array(
		'js' => true,
	) );
	$js_list_table->prepare_items();

	$columns = $js_list_table->get_column_count();
	?>
	<script id="tmpl-<?php echo esc_attr( Mentors\PREFIX ); ?>-task" type="text/template">
		<?php $js_list_table->single_row_columns( $js_list_table->items[0] ); ?>
	</script>
	<script id="tmpl-<?php echo esc_attr( Mentors\PREFIX ); ?>-more" type="text/template">
		<?php include Mentors\get_views_dir_path() . 'task-more.php'; ?>
	</script>
	<?php
	// Initial data.
	$request_url = add_query_arg( array(
		'per_page' => 300,
		'orderby'  => 'menu_order',
		'order'    => 'asc',
	), get_rest_url( null, 'wp/v2/' . Mentors\PREFIX . '_task' ) );

	$initial_tasks = rest_do_request( \WP_REST_Request::from_url( $request_url ) );

	$request_url = add_query_arg( array(
		'per_page' => 100,
	), get_rest_url( null, 'wp/v2/' . Mentors\PREFIX . '_task_category' ) );

	$initial_task_categories = rest_do_request( \WP_REST_Request::from_url( $request_url ) );
	?>
	<script type="text/javascript">
		/* <![CDATA[ */
		var WordCampMentorsTaskData = <?php echo wp_json_encode( $initial_tasks->data ); ?>;
		var WordCampMentorsTaskCategoryData = <?php echo wp_json_encode( $initial_task_categories->data ); ?>;
		/* ]]> */
	</script>
	<?php
}

add_action( 'admin_print_footer_scripts-dashboard_page_' . Mentors\PREFIX . '-planning-checklist', __NAMESPACE__ . '\print_templates' );

/**
 * Display admin notices at the top of the Planning Checklist page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function admin_notices() {
	global $pagenow;

	if ( 'index.php' !== $pagenow ||
	     ! isset( $_GET['page'], $_GET['status'] ) ||
	     Mentors\PREFIX . '-planning-checklist' !== $_GET['page'] ) {
		return;
	}

	$type = 'error';
	$message = '';

	switch ( $_GET['status'] ) {
		case 'invalid-nonce' :
			$message = __( 'Invalid nonce.', 'wordcamporg' );
			break;

		case 'insufficient-permissions' :
			$message = __( 'Insufficient permissions to reset task data.', 'wordcamporg' );
			break;

		case 'reset-errors' :
			$message = __( 'Checklist data reset with errors.', 'wordcamporg' );
			break;

		case 'reset-success' :
			$type = 'success';
			$message = __( 'Checklist data successfully reset.', 'wordcamporg' );
			break;
	}

	if ( $message ) : ?>
	<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
		<?php echo wpautop( esc_html( $message ) ); ?>
	</div>
	<?php endif;
}
