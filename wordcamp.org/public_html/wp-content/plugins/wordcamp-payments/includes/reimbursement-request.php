<?php

/*
 * Create Reimbursement Request Post type
 */

namespace WordCamp\Budgets\Reimbursement_Requests;

defined( 'WPINC' ) or die();

const POST_TYPE = 'wcb_reimbursement';

// Initialization
add_action( 'init',                  __NAMESPACE__ . '\register_post_type'        );
add_action( 'init',                  __NAMESPACE__ . '\register_post_statuses'    );
add_action( 'add_meta_boxes',        __NAMESPACE__ . '\init_meta_boxes'           );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets',        11 );

// Saving posts
add_filter( 'wp_insert_post_data',    __NAMESPACE__ . '\set_request_status',               10, 2 );
add_action( 'save_post',              __NAMESPACE__ . '\save_request',                     10, 2 );
add_action( 'transition_post_status', __NAMESPACE__ . '\notify_organizer_request_updated', 10, 3 );

// Miscellaneous
add_filter( 'map_meta_cap',        __NAMESPACE__ . '\modify_capabilities', 10, 4 );
add_filter( 'display_post_states', __NAMESPACE__ . '\display_post_states'        );

/**
 * Register the custom post type
 *
 * @return object | \WP_Error
 */
function register_post_type() {
	$labels = array(
		'name'               => _x( 'Reimbursement Requests', 'general reimbursement requests', 'wordcamporg' ),
		'singular_name'      => _x( 'Reimbursement Request',  'post type singular name',        'wordcamporg' ),
		'menu_name'          => _x( 'Reimbursement Requests', 'admin menu',                     'wordcamporg' ),
		'name_admin_bar'     => _x( 'Reimbursement Requests', 'add new on admin bar',           'wordcamporg' ),
		'add_new'            => _x( 'Add New',                'reimbursement request',          'wordcamporg' ),

		'add_new_item'       => __( 'Add New Reimbursement Request',             'wordcamporg' ),
		'new_item'           => __( 'New Reimbursement Request',                 'wordcamporg' ),
		'edit_item'          => __( 'Edit Reimbursement Request',                'wordcamporg' ),
		'view_item'          => __( 'View Reimbursement Request',                'wordcamporg' ),
		'all_items'          => __( 'Reimbursements',                            'wordcamporg' ),
		'search_items'       => __( 'Search Reimbursement Requests',             'wordcamporg' ),
		'not_found'          => __( 'No Reimbursement Requests found.',          'wordcamporg' ),
		'not_found_in_trash' => __( 'No Reimbursement Requests found in Trash.', 'wordcamporg' ),
	);

	$args = array(
		'labels'            => $labels,
		'description'       => 'WordCamp Reimbursement Requests',
		'public'            => false,
		'show_ui'           => true,
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
		'wcbrr_submitted'      => __( 'Submitted',             'wordcamporg' ),
		'wcbrr_info_requested' => __( 'Information Requested', 'wordcamporg' ),
		'wcbrr_rejected'       => __( 'Rejected',              'wordcamporg' ),
		'wcbrr_in_process'     => __( 'Payment in Process',    'wordcamporg' ),
		'wcbrr_paid'           => __( 'Paid',                  'wordcamporg' ),
	);
}

/**
 * Register our custom post statuses
 */
function register_post_statuses() {
	// todo use get_custom_statuses() for DRYness, but need to handle label_count

	register_post_status(
		'wcbrr_submitted',
		array(
			'label'              => _x( 'Submitted', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Submitted <span class="count">(%s)</span>', 'Submitted <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbrr_info_requested',
		array(
			'label'              => _x( 'Information Requested', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Information Requested <span class="count">(%s)</span>', 'Information Requested <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbrr_rejected',
		array(
			'label'              => _x( 'Rejected', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbrr_in_process',
		array(
			'label'              => _x( 'Payment in Process', 'post', 'wordcamporg' ),
			'label_count'        => _nx_noop( 'Payment in Process <span class="count">(%s)</span>', 'Payment in Process <span class="count">(%s)</span>', 'wordcamporg' ),
			'public'             => true,
			'publicly_queryable' => false,
		)
	);

	register_post_status(
		'wcbrr_paid',
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
	global $wcp_payment_request, $post;

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
		'wcbrr_notes',
		__( 'Notes', 'wordcamporg' ),
		__NAMESPACE__ . '\render_notes_metabox',
		POST_TYPE,
		'side',
		'high'
	);

	add_meta_box(
		'wcbrr_general_information',
		__( 'General Information', 'wordcamporg' ),
		__NAMESPACE__ . '\render_general_information_metabox',
		POST_TYPE,
		'normal',
		'high'
	);

	add_meta_box(
		'wcbrr_payment_information',
		__( 'Payment Information', 'wordcamporg' ),
		array( $wcp_payment_request, 'render_payment_metabox' ),    // todo centralize this instead of using directly from another module
		POST_TYPE,
		'normal',
		'high',
		array(
			'meta_key_prefix' => 'wcbrr',
			'fields_enabled'  => user_can_edit_request( $post ),
		)
	);

	add_meta_box(
		'wcbrr_expenses',
		__( 'Expenses', 'wordcamporg' ),
		__NAMESPACE__ . '\render_expenses_metabox',
		POST_TYPE,
		'normal',
		'high'
	);
}

/**
 * Enqueue scripts and stylesheets
 */
function enqueue_assets() {
	global $post;

	wp_register_script(
		'wordcamp-reimbursement-requests',
		plugins_url( 'javascript/reimbursement-requests.js', __DIR__ ),
		array( 'wordcamp-budgets', 'wcb-attached-files', 'jquery', 'underscore', 'wp-util' ),
		\WordCamp_Budgets::VERSION,
		true
	);

	$current_screen = get_current_screen();

	if ( POST_TYPE !== $current_screen->id ) {
		return;
	}

	wp_enqueue_script( 'wordcamp-reimbursement-requests' );

	if ( is_a( $post, 'WP_Post' ) ) {
		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_script( 'wcb-attached-files' );
	}
}

/**
 * Get the name of the requester
 *
 * @param int $post_author_id
 *
 * @return string
 */
function get_requester_name( $post_author_id ) {
	$requester_name = '';

	$author = get_user_by( 'id', $post_author_id );

	if ( is_a( $author, 'WP_User' ) ) {
		$requester_name = $author->get( 'display_name' );
	}

	return $requester_name;
}

/**
 * Determine if the current user can submit changes to the given Reimbursement Request
 *
 * This is used instead of current_user_can( 'edit_post', N ), because Core uses 'edit_post' both for accessing
 * the Edit screen, and for submitting changes to the post. We always want organizers to be able to view their
 * requests and to submit notes, but they should only be able to change the form fields if the post hasn't been
 * submitted yet, or if we've asked for more information.
 *
 * @param \WP_Post $post
 *
 * @return bool
 */
function user_can_edit_request( $post ) {
	$editable_status = in_array( $post->post_status, array( 'auto-draft', 'draft', 'wcbrr_info_requested' ), true );
	return current_user_can( 'manage_network' ) || $editable_status;
}

/**
 * Render the Status metabox
 *
 * @param \WP_Post $post
 */
function render_status_metabox( $post ) {
	wp_nonce_field( 'status', 'status_nonce' );

	$show_draft_button  = current_user_can( 'draft_post', $post->ID ) && ! current_user_can( 'manage_network' ); // Network admins can save as draft via the status dropdown, so the button is unnecessary UI clutter
	$show_submit_button = user_can_edit_request( $post );
	$available_statuses = array_merge( array( 'draft' => __( 'Draft' ) ), get_custom_statuses() );
	$status_name        = get_status_name( $post->post_status );
	$request_id         = get_current_blog_id() . '-' . $post->ID;
	$requested_by       = get_requester_name( $post->post_author );
	$delete_text        = EMPTY_TRASH_DAYS ? __( 'Move to Trash' ) : __( 'Delete Permanently' );
	$update_text        = current_user_can( 'manage_network' ) ? __( 'Update Request', 'wordcamporg' ) : __( 'Send Request', 'wordcamporg' );

	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/metabox-status.php' );
}

/**
 * Get the name for the given status slug
 *
 * @param string $status_slug
 *
 * @return string
 */
function get_status_name( $status_slug ) {
	$status_name = '';

	switch ( $status_slug ) {
		case 'auto-draft':
		case 'draft':
			$status_name = __( 'Draft' );
			break;

		default:
			$custom_statuses = get_custom_statuses();
			foreach ( $custom_statuses as $custom_slug => $custom_name ) {
				if ( $custom_slug === $status_slug ) {
					$status_name = $custom_name;
				}
			}
	}

	return $status_name;
}

/**
 * Render the Notes metabox
 *
 * @param \WP_Post $post
 */
function render_notes_metabox( $post ) {
	wp_nonce_field( 'notes', 'notes_nonce' );

	$existing_notes = get_post_meta( $post->ID, '_wcbrr_notes', true );

	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/metabox-notes.php' );
}

/**
 * Render General Information Metabox
 *
 * @param \WP_Post $post
 *
 */
function render_general_information_metabox( $post ) {
	wp_nonce_field( 'general_information', 'general_information_nonce' );

	$available_currencies = \WordCamp_Budgets::get_currencies();
	$available_reasons    = get_reimbursement_reasons();
	$files                = \WordCamp_Budgets::get_attached_files( $post );

	$name_of_payer     = get_post_meta( $post->ID, '_wcbrr_name_of_payer',  true );
	$selected_currency = get_post_meta( $post->ID, '_wcbrr_currency',       true );
	$selected_reason   = get_post_meta( $post->ID, '_wcbrr_reason',         true );
	$other_reason      = get_post_meta( $post->ID, '_wcbrr_reason_other',   true );

	if ( empty ( $name_of_payer ) ) {
		$name_of_payer = get_requester_name( $post->post_author );
	}

	wp_localize_script( 'wcb-attached-files', 'wcbAttachedFiles', $files );

	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/metabox-general-information.php' );
}

/**
 * Get the reasons for reimbursement
 *
 * @return array
 */
function get_reimbursement_reasons() {
	return array(
		'last-minute-purchase'   => __( 'Last-minute purchase',                   'wordcamporg' ),
		'vendor-required-cash'   => __( 'Vendor required cash payment',           'wordcamporg' ),
		'payment-on-delivery'    => __( 'Vendor required payment at delivery',    'wordcamporg' ),
		'convenience'            => __( 'Organizer convenience',                  'wordcamporg' ),
		'central-missed-payment' => __( "Payment by Central didn't come through", 'wordcamporg' ),
		'other'                  => __( 'Other (describe in next field)',         'wordcamporg' ),
	);
}

/**
 * Render Expenses Metabox
 *
 * @param \WP_Post $post
 *
 */
function render_expenses_metabox( $post ) {
	wp_nonce_field( 'expenses', 'expenses_nonce' );

	$expenses = get_post_meta( $post->ID, '_wcbrr_expenses', true );
	if ( ! $expenses ) {
		$expenses = array( array( 'id' => 1 ) );
	}

	wp_localize_script( 'wordcamp-reimbursement-requests', 'wcbPaymentCategories', \WordCamp_Budgets::get_payment_categories() );

	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/metabox-expenses.php' );
	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/template-expense.php' );
}

/**
 * Display the status of a post after its title on the Payment Requests page
 *
 * @todo centralize this, since it's the same in other modules
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
 * Set the status when reimbursements are submitted.
 *
 * @param array $post_data
 * @param array $post_data_raw
 *
 * @return array
 */
function set_request_status( $post_data, $post_data_raw ) {
	if ( ! \WordCamp_Budgets::post_edit_is_actionable( $post_data, POST_TYPE ) ) {
		return $post_data;
	}

	// Requesting to save draft
	if ( isset( $post_data_raw['wcbsi-save-draft'] ) ) {
		if ( current_user_can( 'draft_post', $post_data['ID'] ) ) {
			$post_data['post_status'] = 'draft';
		}
	}

	// Requesting to submit/update the post
	elseif ( isset( $post_data_raw['send-reimbursement-request'] ) ) {
		if ( current_user_can( 'manage_network' ) ) {
			$post_data['post_status'] = $_POST['post_status'];
		} else {
			$post_data['post_status'] = 'wcbrr_submitted';
		}
	}

	return $post_data;
}

/**
 * Save the post's data
 *
 * @param int      $post_id
 * @param \WP_Post $post
 */
function save_request( $post_id, $post ) {
	if ( ! \WordCamp_Budgets::post_edit_is_actionable( $post, POST_TYPE ) ) {
		return;
	}

	verify_metabox_nonces();
	validate_and_save_notes( $post, $_POST['wcbrr_new_note'] );

	/*
	 * We need to determine if the user is allowed to modify the request -- in terms of this plugin's post_status
	 * restrictions, not in terms of current_user_can( 'edit_post', N ) -- but at this point in the execution
	 * the status has already changed from the original one to the new one, so user_can_edit_request() would often
	 * return an incorrect result, because it would be evaluating the new status, when it should use the old one.
	 * That would result in all the meta fields the user entered being ignored when going from `draft` to
	 * `submitted`, `info_requested` to `submitted`, etc.
	 *
	 * To avoid that, we create a stub WP_Post with the original post status, and give that to
	 * user_can_edit_request() instead.
	 */
	$original_post = new \WP_Post( (object) array( 'post_status' => $_POST['original_post_status'] ) );

	if ( user_can_edit_request( $original_post ) ) {
		$text_fields = array( 'name_of_payer', 'currency', 'reason' );
		validate_and_save_text_fields( $post_id, $text_fields, $_POST );

		\WordCamp_Budgets::validate_save_payment_method_fields( $post_id, 'wcbrr' );

		validate_and_save_expenses( $post_id, $_POST['wcbrr-expenses-data'] );

		// Attach existing files
		remove_action( 'save_post', __NAMESPACE__ . '\save_request', 10 ); // avoid infinite recursion
		\WordCamp_Budgets::attach_existing_files( $post_id, $_POST );
		add_action( 'save_post', __NAMESPACE__ . '\save_request', 10, 2 );
	}
}

/**
 * Verify that each metabox has a valid nonce
 */
function verify_metabox_nonces() {
	$nonces = array(
		'status_nonce',
		'notes_nonce',
		'general_information_nonce',
		'payment_details_nonce',
		'expenses_nonce'
	);

	foreach ( $nonces as $nonce ) {
		check_admin_referer( str_replace( '_nonce', '', $nonce ), $nonce );
	}
}

/**
 * Validate and save text fields
 *
 * @param int   $post_id
 * @param array $field_names
 * @param array $data
 */
function validate_and_save_text_fields( $post_id, $field_names, $data ) {
	foreach ( $field_names as $field ) {
		$meta_key = "_wcbrr_$field";
		$value = sanitize_text_field( wp_unslash( $data[ $meta_key ] ) );

		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}

/**
 * Validate and save expense data
 *
 * @param int   $post_id
 * @param array $expenses
 */
function validate_and_save_expenses( $post_id, $expenses ) {
	$expenses = json_decode( wp_unslash( $expenses ) );

	if ( empty( $expenses ) ) {
		delete_post_meta( $post_id, '_wcbrr_expenses' );
		return;
	}

	$defaults = array(
		'_wcbrr_category'        => '',
		'_wcbrr_category_other'  => '',
		'_wcbrr_vendor_name'     => '',
		'_wcbrr_description'     => '',
		'_wcbrr_date'            => '',
		'_wcbrr_amount'          => 0,
		'_wcbrr_vendor_location' => '',
	);

	foreach ( $expenses as & $expense ) {
		$expense = shortcode_atts( $defaults, $expense );   // 'id' is intentionally removed because it's just a temporary client-side value

		$expense['_wcbrr_category']        = sanitize_text_field( $expense['_wcbrr_category']    );
		$expense['_wcbrr_category_other']  = sanitize_text_field( $expense['_wcbrr_category_other'] );
		$expense['_wcbrr_vendor_name']     = sanitize_text_field( $expense['_wcbrr_vendor_name'] );
		$expense['_wcbrr_description']     = sanitize_text_field( $expense['_wcbrr_description'] );
		$expense['_wcbrr_date']            = empty( $expense['_wcbrr_date'] ) ? '' : date( 'Y-m-d', strtotime( $expense['_wcbrr_date'] ) );
		$expense['_wcbrr_amount']          = \WordCamp_Budgets::validate_amount( $expense['_wcbrr_amount'] );
		$expense['_wcbrr_vendor_location'] = in_array( $expense['_wcbrr_vendor_location'], array( 'local', 'online' ) ) ? $expense['_wcbrr_vendor_location'] : '';
	}

	update_post_meta( $post_id, '_wcbrr_expenses', $expenses );
}

/**
 * Validate and save expense data
 *
 * @param \WP_Post $post
 * @param array    $expenses
 */
function validate_and_save_notes( $post, $new_note_message ) {
	$new_note_message = sanitize_text_field( wp_unslash( $new_note_message ) );

	if ( empty( $new_note_message ) ) {
		return;
	}

	$notes = get_post_meta( $post->ID, '_wcbrr_notes', true );

	$new_note = array(
		'timestamp' => time(),
		'author_id' => get_current_user_id(),
		'message'   => $new_note_message
	);

	$notes[] = $new_note;

	update_post_meta( $post->ID, '_wcbrr_notes', $notes );
	notify_parties_of_new_note( $post, $new_note );
}

/**
 * Notify WordCamp Central or the request author when new notes are added
 *
 * @param \WP_Post $request
 * @param array    $note
 */
function notify_parties_of_new_note( $request, $note ) {
	$note_author = get_user_by( 'id', $note['author_id'] );

	if ( $note_author->has_cap( 'manage_network' ) ) {
		$to = \WordCamp_Budgets::get_requester_formatted_email( $request->post_author );
	} else {
		$to = 'support@wordcamp.org';
	}

	if ( ! $to ) {
		return;
	}

	$subject          = 'New note on ' . sanitize_text_field( $request->post_title );
	$note_author_name = get_requester_name( $note['author_id'] );
	$request_url      = admin_url( sprintf( 'post.php?post=%s&action=edit', $request->ID ) );
	$headers          = array( 'Reply-To: support@wordcamp.org' );

	$message = sprintf( "
		%s has added the following note on the reimbursement request for %s:

		%s

		You can view the request and respond to their note at:

		%s",
		sanitize_text_field( $note_author_name ),
		sanitize_text_field( $request->post_title ),
		sanitize_text_field( $note['message'] ),
		esc_url_raw( $request_url )
	);
	$message = str_replace( "\t", '', $message );

	wp_mail( $to, $subject, $message, $headers );
}

/**
 * Notify the organizer when the status of their invoice changes or when notes are added
 *
 * @param string   $new_status
 * @param string   $old_status
 * @param \WP_Post $request
 */
function notify_organizer_request_updated( $new_status, $old_status, $request ) {
	if ( $new_status === $old_status ) {
		return;
	}

	$to                = \WordCamp_Budgets::get_requester_formatted_email( $request->post_author );
	$relevant_statuses = array( 'wcbrr_info_requested', 'wcbrr_rejected', 'wcbrr_in_process', 'wcbrr_paid' );

	if ( ! $to || ! in_array( $request->post_status, $relevant_statuses, true ) ) {
		return;
	}

	$subject     = 'Status update for ' . sanitize_text_field( $request->post_title );
	$status_name = get_status_name( $request->post_status );
	$request_url = admin_url( sprintf( 'post.php?post=%s&action=edit', $request->ID ) );
	$headers     = array( 'Reply-To: support@wordcamp.org' );

	$message = sprintf( "
		The status of your reimbursement request for %s has been updated to %s.

		You can view the request and add notes at:

		%s",
		sanitize_text_field( $request->post_title ),
		sanitize_text_field( $status_name ),
		esc_url_raw( $request_url )
	);
	$message = str_replace( "\t", '', $message );

	wp_mail( $to, $subject, $message, $headers );
}

/**
 * Modify the default capabilities
 *
 * @todo maybe centralize this, since similar functionality in payment-requests.php and sponsor-invoice.php
 *
 * @param array  $required_capabilities The primitive capabilities that are required to perform the requested meta capability
 * @param string $requested_capability  The requested meta capability
 * @param int    $user_id               The user ID.
 * @param array  $args                  Adds the context to the cap. Typically the object ID.
 */
function modify_capabilities( $required_capabilities, $requested_capability, $user_id, $args ) {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) || POST_TYPE !== $post->post_type ) {
		return $required_capabilities;
	}

	$drafted_status             = in_array( $post->post_status, array( 'auto-draft', 'draft' ), true );
	$draft_or_incomplete_status = $drafted_status || 'wcbrr_info_requested' === $post->post_status;

	switch( $requested_capability ) {
		case 'draft_post':
			if ( $draft_or_incomplete_status ) {
				$required_capabilities = array( 'edit_posts' );
			} else {
				$required_capabilities[] = 'manage_network';
			}
			break;

		case 'delete_post':
			if ( ! $drafted_status ) {
				$required_capabilities[] = 'manage_network';
			}
			break;
	}

	return $required_capabilities;
}
