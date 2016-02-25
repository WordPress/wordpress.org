<?php

/*
 * Create Sponsor Invoice Post type
 */

namespace WordCamp\Budgets\Sponsor_Invoices;

defined( 'WPINC' ) or die();

const POST_TYPE = 'wcb_sponsor_invoice';

// Initialization
add_action( 'init',                  __NAMESPACE__ . '\register_post_type'        );
add_action( 'init',                  __NAMESPACE__ . '\register_post_statuses'    );
add_action( 'add_meta_boxes',        __NAMESPACE__ . '\init_meta_boxes'           );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets',        11 );

// Admin UI
add_filter( 'display_post_states',                        __NAMESPACE__ . '\display_post_states'       );
add_filter( 'manage_'. POST_TYPE .'_posts_columns',       __NAMESPACE__ . '\get_columns'               );
add_action( 'manage_'. POST_TYPE .'_posts_custom_column', __NAMESPACE__ . '\render_columns',     10, 2 );

// Saving posts
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\set_invoice_status',  10, 2 );
add_action( 'save_post',           __NAMESPACE__ . '\save_invoice',        10, 2 );
add_filter( 'map_meta_cap',        __NAMESPACE__ . '\modify_capabilities', 10, 4 );

/**
 * Register the custom post type
 *
 * @return object | \WP_Error
 */
function register_post_type() {
	$labels = array(
		'name'               => _x( 'Sponsor Invoices', 'general sponsor invoices', 'wordcamporg' ),
		'singular_name'      => _x( 'Sponsor Invoice',  'post type singular name',  'wordcamporg' ),
		'menu_name'          => _x( 'Sponsor Invoices', 'admin menu',               'wordcamporg' ),
		'name_admin_bar'     => _x( 'Sponsor Invoices', 'add new on admin bar',     'wordcamporg' ),
		'add_new'            => _x( 'Add New',          'invoice',                  'wordcamporg' ),

		'add_new_item'       => __( 'Add New Sponsor Invoice',    'wordcamporg' ),
		'new_item'           => __( 'New Invoice',                'wordcamporg' ),
		'edit_item'          => __( 'Edit Invoice',               'wordcamporg' ),
		'view_item'          => __( 'View Invoice',               'wordcamporg' ),
		'all_items'          => __( 'Sponsor Invoices',           'wordcamporg' ),
		'search_items'       => __( 'Search Invoices',            'wordcamporg' ),
		'not_found'          => __( 'No invoice found.',          'wordcamporg' ),
		'not_found_in_trash' => __( 'No invoice found in Trash.', 'wordcamporg' ),
	);

	$args = array(
		'labels'            => $labels,
		'description'       => 'WordCamp Sponsor Invoices',
		'public'            => false,
		'show_ui'           => defined( 'WPORG_PROXIED_REQUEST' ) && WPORG_PROXIED_REQUEST, // todo set to `true` during launch
		'show_in_menu'      => 'wordcamp-budget',
		'show_in_nav_menus' => true,
		'supports'          => array( 'title' ),
		'has_archive'       => true,
	);
	
	return \register_post_type( POST_TYPE, $args );
}

/**
 * Get the slugs and names for our custom post statuses
 *
 * @return array
 */
function get_custom_statuses() {
	return array(
		'wcbsi_submitted' => __( 'Submitted', 'wordcamporg' ),
		'wcbsi_approved'  => __( 'Sent',      'wordcamporg' ),
		'wcbsi_paid'      => __( 'Paid',      'wordcamporg' ),
	);
}

/**
 * Register our custom post statuses
 */
function register_post_statuses() {
	// todo use get_custom_statuses() for DRYness, but need to handle label_count

	register_post_status(
		'wcbsi_submitted',
		array(
			'label'              => _x( 'Submitted', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Submitted <span class="count">(%s)</span>', 'Submitted <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbsi_approved',
		array(
			'label'              => _x( 'Sent', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Sent <span class="count">(%s)</span>', 'Sent <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbsi_paid',
		array(
			'label'              => _x( 'Paid', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Paid <span class="count">(%s)</span>', 'Paid <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);
}

/**
 * Register meta boxes
 */
function init_meta_boxes() {
	// Replace Core's status box with a custom one
	remove_meta_box( 'submitdiv', POST_TYPE, 'side' );

	add_meta_box(
		'submitdiv',
		__( 'Status', 'wordcamporg' ),
		__NAMESPACE__ . '\render_status_metabox',
		POST_TYPE,
		'side',
		'high'
	);

	add_meta_box(
		'wcbsi_sponsor_invoice',
		__( 'Sponsor Invoice', 'wordcamporg' ),
		__NAMESPACE__ . '\render_sponsor_invoice_metabox',
		POST_TYPE,
		'normal',
		'high'
	);
}

/**
 * Enqueue scripts and stylesheets
 */
function enqueue_assets() {
	wp_register_script(
		'sponsor-invoices',
		plugins_url( 'javascript/sponsor-invoices.js', __DIR__ ),
		array( 'wordcamp-budgets', 'jquery', 'underscore', 'wp-util' ),
		1,
		true
	);

	$current_screen = get_current_screen();

	if ( POST_TYPE !== $current_screen->id ) {
		return;
	}

	wp_enqueue_script( 'sponsor-invoices' );
}

/**
 * Prepare sponsor data for displaying in the UI
 *
 * @param int $sponsor_id If passed, will return only data for that sponsor. Otherwise returns all sponsors.
 *
 * @return array
 */
function prepare_sponsor_data( $sponsor_id = null ) {
	$data = array();

	$field_names = array(
		'company_name',	'first_name', 'last_name', 'email_address', 'phone_number',
		'street_address1', 'street_address2', 'city', 'state', 'zip_code', 'country'
	);

	// These use dashes instead of underscores because the loop below converts to dashes
	$required_fields = array(
		'company-name',	'first-name', 'last-name', 'email-address', 'phone-number',
		'street-address1', 'city', 'state', 'zip-code', 'country'
	);

	if ( is_numeric( $sponsor_id ) ) {
		$sponsors = array( get_post( $sponsor_id ) );
	} else {
		$sponsors = get_posts( array(
			'post_type'      => 'wcb_sponsor',
			'posts_per_page' => 100,
			'post_status'    => array( 'draft', 'pending', 'publish' ),
		) );
	}

	foreach ( $sponsors as $sponsor ) {
		$meta_values = get_post_custom( $sponsor->ID );

		$data[ $sponsor->ID ] = array( 'name' => $sponsor->post_title );
		$data[ $sponsor->ID ]['data_attributes']['edit-url'] = admin_url( sprintf( 'post.php?post=%s&action=edit', $sponsor->ID ) );

		foreach ( $field_names as $name ) {
			$meta_key = "_wcpt_sponsor_$name";
			$data_key = str_replace( '_', '-', $name ); // for consistency with JavaScript conventions
			$value    = '';

			if ( isset( $meta_values[ $meta_key ][0] ) ) {
				 $value = $meta_values[ $meta_key ][0];
			}

			$data[ $sponsor->ID ]['data_attributes'][ $data_key ] = $value;
		}

		$complete = required_fields_complete( $data[ $sponsor->ID ]['data_attributes'], $required_fields );
		$data[ $sponsor->ID ]['data_attributes']['required-fields-complete'] = $complete ? 'true' : 'false';
	}

	return $data;
}

/**
 * Check if all of the required fields have values
 *
 * @param $submitted_values
 * @param $required_fields
 *
 * @return bool
 */
function required_fields_complete( $submitted_values, $required_fields ) {
	$complete = true;

	foreach ( $submitted_values as $key => $value ) {
		if ( in_array( $key, $required_fields, true ) ) {
			if ( empty( $value ) || 'null' === substr( $value, 0, 4 ) ) {
				$complete = false;
				break;
			}
		}
	}

	return $complete;
}

/**
 * Render the Status metabox
 *
 * @param \WP_Post $post
 */
function render_status_metabox( $post ) {
	wp_nonce_field( 'status', 'status_nonce' );

	$delete_text = EMPTY_TRASH_DAYS ? __( 'Move to Trash' ) : __( 'Delete Permanently' );

	/*
	 * We can't use current_user_can( 'edit_post', N ) in this case, because the restriction only applies when
	 * submitting the edit form, not when viewing the post. We also want to allow editing by plugins, but not
	 * always through the UI. So, instead, we simulate get the same result in a different way.
	 *
	 * Network admins can edit submitted invoices in order to correct them before they're sent to QuickBooks, but
	 * not even network admins can edit them once they've been created in QuickBooks, because then our copy of the
	 * invoice would no longer match QuickBooks.
	 *
	 * This intentionally only prevents editing through the UI; we still want plugins to be able to edit the
	 * invoice, so that the status can be updated to paid, etc.
	 */
	$allowed_edit_statuses = array( 'auto-draft', 'draft' );

	if ( current_user_can( 'manage_network' ) ) {
		$allowed_edit_statuses[] = 'wcbsi_submitted';
	}

	$current_user_can_edit_request = in_array( $post->post_status, $allowed_edit_statuses, true );

	require_once( dirname( __DIR__ ) . '/views/sponsor-invoice/metabox-status.php' );
}

/**
 * Render Sponsor Invoice Metabox
 *
 * @param \WP_Post $post
 *
 */
function render_sponsor_invoice_metabox( $post ) {
	wp_nonce_field( 'sponsor_invoice', 'sponsor_invoice_nonce' );

	$current_screen       = get_current_screen();
	$available_sponsors   = prepare_sponsor_data();
	$available_classes    = \WordCamp_QBO_Client::get_classes();
	$available_currencies = \WordCamp_Budgets::get_currencies();
	$selected_sponsor_id  = get_post_meta( $post->ID, '_wcbsi_sponsor_id',      true );
	$selected_class_id    = get_post_meta( $post->ID, '_wcbsi_qbo_class_id',    true );
	$selected_currency    = get_post_meta( $post->ID, '_wcbsi_currency',        true );
	$description          = get_post_meta( $post->ID, '_wcbsi_description',     true );
	$amount               = get_post_meta( $post->ID, '_wcbsi_amount',          true );

	if ( 'add' === $current_screen->action && isset( $_GET['sponsor_id'] ) ) {
		$selected_sponsor_id = absint( $_GET['sponsor_id'] );
	}

	require_once( dirname( __DIR__ ) . '/views/sponsor-invoice/metabox-general.php' );
}

/**
 * Display the status of a post after its title on the Vendor Payments page
 *
 * @param array $states
 *
 * @return array
 */
function display_post_states( $states ) {
	global $post;

	$custom_states = get_custom_statuses();

	foreach ( $custom_states as $slug => $name ) {
		if ( $slug == $post->post_status && $slug != get_query_var( 'post_status' ) ) {
			$states[ $slug ] = $name;
		}
	}

	return $states;
}

/**
 * Set the status when invoices are submitted.
 *
 * @param array $post_data
 * @param array $post_data_raw
 *
 * @return array
 */
function set_invoice_status( $post_data, $post_data_raw ) {
	if ( ! \WordCamp_Budgets::post_edit_is_actionable( $post_data, POST_TYPE ) ) {
		return $post_data;
	}

	$sponsor                 = prepare_sponsor_data( $post_data_raw['_wcbsi_sponsor_id'] );
	$sponsor                 = array_pop( $sponsor );
	$sponsor_fields_complete = 'true' === $sponsor['data_attributes']['required-fields-complete'];

	$required_invoice_fields = array(
		'_wcbsi_sponsor_id', '_wcbsi_description', '_wcbsi_currency', '_wcbsi_amount',
		'_wcbsi_qbo_class_id',
	);
	$invoice_fields_complete = required_fields_complete( $post_data_raw, $required_invoice_fields );

	if ( ! $sponsor_fields_complete || ! $invoice_fields_complete ) {
		// Set to draft if any required info isn't available
		$post_data['post_status'] = 'draft';

		// todo display message to user letting them know why this is happening
		// todo this should run after save, b/c sanitization/validation could empty out some fields

	} else if ( in_array( $post_data['post_status'], array( 'auto-draft', 'draft' ), true ) && isset( $_POST['send-invoice'] ) ) {
		/*
		 * Only set to submitted if the previous status was a draft, because a network admin can make changes
		 * after it's been submitted, and we don't want to revert the post status in those cases.
		 */

		$post_data['post_status'] = 'wcbsi_submitted';
	}

	return $post_data;
}

/**
 * Save the post's data
 *
 * @param int      $post_id
 * @param \WP_Post $post
 */
function save_invoice( $post_id, $post ) {
	if ( ! \WordCamp_Budgets::post_edit_is_actionable( $post, POST_TYPE ) ) {
		return;
	}

	// Verify nonces
	$nonces = array( 'status_nonce', 'sponsor_invoice_nonce' );

	foreach ( $nonces as $nonce ) {
		check_admin_referer( str_replace( '_nonce', '', $nonce ), $nonce );
	}

	// Sanitize and save the field values
	$fields = array( 'sponsor_id', 'qbo_class_id', 'currency', 'description', 'amount' );

	foreach( $fields as $field ) {
		$meta_key = "_wcbsi_$field";
		$value = sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );

		if ( 'amount' == $field ) {
			$value = \WordCamp_Budgets::validate_amount( $value );
		}

		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}

/**
 * Define columns for the Vendor Payments screen.
 *
 * @param array $_columns
 * @return array
 */
function get_columns( $_columns ) {
	$columns = array(
		'cb'             => $_columns['cb'],
		'author'         => __( 'Author' ),
		'title'          => $_columns['title'],
		'date'           => $_columns['date'],
		'sponsor_name'   => __( 'Sponsor',  'wordcamporg' ),
		'payment_amount' => __( 'Amount',   'wordcamporg' ),
	);

	return $columns;
}

/**
 * Render custom columns on the Vendor Payments screen.
 *
 * @param string $column
 * @param int $post_id
 */
function render_columns( $column, $post_id ) {
	switch ( $column ) {
		case 'sponsor_name':
			// todo could reuse get_sponsor_name() from dashboard if made some minor modifications

			$sponsor = get_post( get_post_meta( $post_id, '_wcbsi_sponsor_id', true ) );
			echo esc_html( $sponsor->post_title );
			break;

		case 'payment_amount':
			$currency = get_post_meta( $post_id, '_wcbsi_currency', true );
			if ( false === strpos( $currency, 'null' ) ) {
				echo esc_html( $currency ) . ' ';
			}

			echo esc_html( get_post_meta( $post_id, '_wcbsi_amount', true ) );
			break;
	}
}

/**
 * Modify the default capabilities
 *
 * @param array  $required_capabilities The primitive capabilities that are required to perform the requested meta capability
 * @param string $requested_capability  The requested meta capability
 * @param int    $user_id               The user ID.
 * @param array  $args                  Adds the context to the cap. Typically the object ID.
 */
function modify_capabilities( $required_capabilities, $requested_capability, $user_id, $args ) {
	// todo maybe centralize this, since almost identical to counterpart in payment-requests.php

	global $post;

	if ( is_a( $post, 'WP_Post' ) && POST_TYPE == $post->post_type ) {
		/*
		 * Only network admins can edit/delete requests once they've been submitted.
		 *
		 * The organizer can still open the request (in order to view the status and details), but won't be allowed to make any changes to it.
		 */
		if ( ! in_array( $post->post_status, array( 'auto-draft', 'draft' ), true ) ) {
			if ( 'edit_post' == $requested_capability ) {
				if ( isset( $_REQUEST['action'] ) && 'edit' != $_REQUEST['action'] ) {
					$required_capabilities[] = 'manage_network';
				}
			}

			if ( 'delete_post' == $requested_capability ) {
				$required_capabilities[] = 'manage_network';
			}
		}
	}

	return $required_capabilities;
}
