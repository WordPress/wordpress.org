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
 * @return array
 */
function get_post_statuses() {
	return array(
		'draft',
		'wcb-incomplete',
		'wcb-pending-approval',
		'wcb-approved',
		'wcb-pending-payment',
		'wcb-paid',
		'wcb-failed',
		'wcb-cancelled',
	);
}

/**
 * Register our custom post statuses
 */
function register_post_statuses() {
	// These are legacy statuses. Real statuses in wordcamp-budgets.php.

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

	$introduction_message = sprintf(
		'<p>%s</p> <p>%s</p>',
		__( 'This is where you can give us information on how we can reimburse you for approved expenses that you paid out-of-pocket.', 'wordcamporg' ),
		__( 'Each wire transfer and check costs us processing fees, so if you have multiple out-of-pocket expenses, please try to group them into one reimbursement request.', 'wordcamporg' )
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
			'introduction_message' => $introduction_message,
			'show_vendor_requested_payment_method' => false,
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
		2,
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
	$editable_status = in_array( $post->post_status, array( 'auto-draft', 'draft', 'wcb-incomplete' ), true );
	return current_user_can( 'manage_network' ) || $editable_status;
}

/**
 * Render the Status metabox
 *
 * @param \WP_Post $post
 */
function render_status_metabox( $post ) {
	wp_nonce_field( 'status', 'status_nonce' );

	$back_compat_statuses = array(
		'wcbrr_submitted'      => 'wcb-pending-approval',
		'wcbrr_info_requested' => 'wcb-incomplete',
		'wcbrr_rejected'       => 'wcb-failed',
		'wcbrr_in_process'     => 'wcb-pending-payment',
		'wcbrr_paid'           => 'wcb-paid',
	);

	// Map old statuses to new statuses.
	if ( array_key_exists( $post->post_status, $back_compat_statuses ) ) {
		$post->post_status = $back_compat_statuses[ $post->post_status ];
	}

	$editable_statuses = array( 'auto-draft', 'draft', 'wcb-incomplete' );
	$current_user_can_edit_request = false;
	$submit_text = _x( 'Update', 'payment request', 'wordcamporg' );
	$submit_note = '';

	if ( current_user_can( 'manage_network' ) ) {
		$current_user_can_edit_request = true;
	} elseif ( in_array( $post->post_status, $editable_statuses ) ) {
		$submit_text = __( 'Submit for Review', 'wordcamporg' );
		$submit_note = __( 'Once submitted for review, this request can not be edited.', 'wordcamporg' );
		$current_user_can_edit_request = true;
	}

	$incomplete_notes = get_post_meta( $post->ID, '_wcp_incomplete_notes', true );
	$incomplete_readonly = ! current_user_can( 'manage_network' ) ? 'readonly' : '';

	$request_id         = get_current_blog_id() . '-' . $post->ID;
	$requested_by       = \WordCamp_Budgets::get_requester_name( $post->post_author );
	$update_text        = current_user_can( 'manage_network' ) ? __( 'Update Request', 'wordcamporg' ) : __( 'Send Request', 'wordcamporg' );

	require_once( dirname( __DIR__ ) . '/views/reimbursement-request/metabox-status.php' );
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
	$date_paid         = get_post_meta( $post->ID, '_wcbrr_date_paid',      true );

	if ( empty ( $name_of_payer ) ) {
		$name_of_payer = \WordCamp_Budgets::get_requester_name( $post->post_author );
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
 * Display the status of a post after its title on the Vendor Payments page
 *
 * @todo centralize this, since it's the same in other modules
 *
 * @param array $states
 *
 * @return array
 */
function display_post_states( $states ) {
	global $post;

	if ( $post->post_type != POST_TYPE )
		return $states;

	$status = get_post_status_object( $post->post_status );
	if ( get_query_var( 'post_status' ) != $post->post_status ) {
		$states[ $status->name ] = $status->label;
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
	if ( isset( $post_data_raw['wcb-save-draft'] ) ) {
		if ( current_user_can( 'draft_post', $post_data['ID'] ) ) {
			$post_data['post_status'] = 'draft';
		}
	}

	// Requesting to submit/update the post
	if ( ! current_user_can( 'manage_network' ) ) {
		$editable_statuses = array( 'auto-draft', 'draft', 'wcb-incomplete' );
		if ( ! empty( $post_data_raw['wcb-update'] ) && in_array( $post_data['post_status'], $editable_statuses ) ) {
			$post_data['post_status'] = 'wcb-pending-approval';
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
		$text_fields = array( 'name_of_payer', 'currency', 'reason', 'reason_other' );
		validate_and_save_text_fields( $post_id, $text_fields, $_POST );

		// Save payment date
		if ( isset( $_POST['_wcbrr_date_paid'] ) && current_user_can( 'manage_network' ) ) {
			$date_paid = sanitize_text_field( $_POST['_wcbrr_date_paid'] );
			$date_paid = absint( strtotime( $date_paid ) );
			update_post_meta( $post->ID, '_wcbrr_date_paid', $date_paid );
		}

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

	// Save incomplete message.
	if ( isset( $_POST['wcp_mark_incomplete_notes'] ) ) {
		$safe_value = '';
		if ( $post->post_status == 'wcb-incomplete' ) {
			$safe_value = wp_kses( $_POST['wcp_mark_incomplete_notes'], wp_kses_allowed_html( 'strip' ) );
		}

		update_post_meta( $post->ID, '_wcp_incomplete_notes', $safe_value );
	}

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
		$subject_prefix = sprintf( '[%s] ', get_wordcamp_name() );
	} else {
		$to = 'support@wordcamp.org';
		$subject_prefix = '';
	}

	if ( ! $to ) {
		return;
	}

	$subject          = sprintf( '%sNew note on `%s`', $subject_prefix, sanitize_text_field( $request->post_title ) );
	$note_author_name = \WordCamp_Budgets::get_requester_name( $note['author_id'] );
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
 * Notify the organizer when the status of their reimbursement changes or when notes are added
 *
 * @param string   $new_status
 * @param string   $old_status
 * @param \WP_Post $request
 */
function notify_organizer_request_updated( $new_status, $old_status, $request ) {
	if ( $request->post_type !== POST_TYPE ) {
		return;
	}

	if ( $new_status === $old_status ) {
		return;
	}

	$to                = \WordCamp_Budgets::get_requester_formatted_email( $request->post_author );
	$relevant_statuses = array( 'wcb-incomplete', 'wcb-approved', 'wcb-pending-payment', 'wcb-paid', 'wcb-failed', 'wcb-cancelled' );

	if ( ! $to || ! in_array( $request->post_status, $relevant_statuses, true ) ) {
		return;
	}

	$subject     = 'Status update for ' . sanitize_text_field( $request->post_title );
	$status      = get_post_status_object( $request->post_status );
	$status_name = $status->label;
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
	$draft_or_incomplete_status = $drafted_status || 'wcb-incomplete' === $post->post_status;

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

/**
 * Regular CSV Export
 *
 * @param $args array
 *
 * @return string
 */
function _generate_payment_report_default( $args ) {
	$column_headings = array(
		'WordCamp', 'ID', 'Title', 'Status', 'Paid', 'Requested', 'Amount',
		'Reason', 'Categories', 'Payment Method', 'Name',
		'Check Payable To', 'URL',
	);

	ob_start();
	$report = fopen( 'php://output', 'w' );

	fputcsv( $report, $column_headings );

	foreach( $args['data'] as $entry ) {
		switch_to_blog( $entry->blog_id );

		$post = get_post( $entry->request_id );

		$back_compat_statuses = array(
			'wcbrr_submitted'      => 'wcb-pending-approval',
			'wcbrr_info_requested' => 'wcb-incomplete',
			'wcbrr_rejected'       => 'wcb-failed',
			'wcbrr_in_process'     => 'wcb-pending-payment',
			'wcbrr_paid'           => 'wcb-paid',
		);

		// Map old statuses to new statuses.
		if ( array_key_exists( $post->post_status, $back_compat_statuses ) ) {
			$post->post_status = $back_compat_statuses[ $post->post_status ];
		}

		if ( $args['status'] && $post->post_status != $args['status'] ) {
			restore_current_blog();
			continue;
		} elseif ( $post->post_type != POST_TYPE ) {
			restore_current_blog();
			continue;
		}

		$currency = get_post_meta( $post->ID, '_wcbrr_currency', true );
		$reason = get_post_meta( $post->ID, '_wcbrr_reason', true );
		$expenses = get_post_meta( $post->ID, '_wcbrr_expenses', true );

		$amount = 0;
		$categories = array();

		if ( strpos( $currency, 'null' ) === 0 ) {
			$currency = '';
		}

		if ( strpos( $reason, 'null' ) === 0 ) {
			$reason = '';
		}

		foreach ( $expenses as $expense ) {
			if ( ! empty( $expense['_wcbrr_amount'] ) ) {
				$amount += floatval( $expense['_wcbrr_amount'] );
			}

			if ( ! empty( $expense['_wcbrr_category'] ) ) {
				$categories[] = $expense['_wcbrr_category'];
			}
		}

		$amount = number_format( $amount, 2, '.', '' );
		$status = get_post_status_object( $post->post_status );

		$row = array(
			get_wordcamp_name(),
			sprintf( '%d-%d', $entry->blog_id, $entry->request_id ),
			html_entity_decode( $post->post_title ),
			$status->label,
			date( 'Y-m-d', absint( get_post_meta( $post->ID, '_wcbrr_date_paid', true ) ) ),
			date( 'Y-m-d', strtotime( $post->post_date_gmt ) ),
			$amount,
			$currency,
			implode( ',', $categories ),
			get_post_meta( $post->ID, '_wcbrr_payment_method', true ),
			get_post_meta( $post->ID, '_wcbrr_name_of_payer', true ),
			\WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_wcbrr_payable_to', true ) ),
			get_edit_post_link( $post->ID ),
		);

		restore_current_blog();

		if ( ! empty( $row ) ) {
			fputcsv( $report, $row );
		}
	}

	fclose( $report );
	return ob_get_clean();
}

/**
 * Quick Checks via JP Morgan
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_jpm_checks( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	$options = apply_filters( 'wcb_payment_req_check_options', array(
		'pws_customer_id' => '',
		'account_number'  => '',
		'contact_email'   => '',
		'contact_phone'   => '',
	) );

	$report = fopen( 'php://output', 'w' );
	ob_start();

	// File Header
	fputcsv( $report, array( 'FILHDR', 'PWS', $options['pws_customer_id'], date( 'm/d/Y' ), date( 'Hi' ) ), ',', '|' );

	$total = 0;
	$count = 0;

	if ( false !== get_site_transient( '_wcb_jpm_checks_counter_lock' ) ) {
		wp_die( 'JPM Checks Export is locked. Please try again later or contact support.' );
	}

	// Avoid at least *some* race conditions.
	set_site_transient( '_wcb_jpm_checks_counter_lock', 1, 30 );
	$start = absint( get_site_option( '_wcb_jpm_checks_counter', 0 ) );

	foreach ( $args['data'] as $entry ) {
		switch_to_blog( $entry->blog_id );
		$post = get_post( $entry->request_id );

		if ( $args['status'] && $post->post_status != $args['status'] ) {
			restore_current_blog();
			continue;
		} elseif ( $args['post_type'] != POST_TYPE ) {
			restore_current_blog();
			continue;
		} elseif ( get_post_meta( $post->ID, '_wcbrr_payment_method', true ) != 'Check' ) {
			restore_current_blog();
			continue;
		}

		$count++;
		$amount = 0;
		$description = array();

		$expenses = get_post_meta( $post->ID, '_wcbrr_expenses', true );
		foreach ( $expenses as $expense ) {
			if ( ! empty( $expense['_wcbrr_amount'] ) ) {
				$amount += floatval( $expense['_wcbrr_amount'] );
			}

			if ( ! empty( $expense['_wcbrr_description'] ) ) {
				$description[] = sanitize_text_field( $expense['_wcbrr_description'] );
			}
		}

		$amount = round( $amount, 2 );
		$total += $amount;

		$payable_to = \WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_wcbrr_payable_to', true ) );
		$payable_to = html_entity_decode( $payable_to ); // J&amp;J to J&J
		$countries = \WordCamp_Budgets::get_valid_countries_iso3166();

		$vendor_country_code = get_post_meta( $post->ID, '_wcbrr_check_country', true );
		if ( ! empty( $countries[ $vendor_country_code ] ) ) {
			$vendor_country_code = $countries[ $vendor_country_code ]['alpha3'];
		}

		$description = implode( ', ', $description );
		$description = html_entity_decode( $description );

		// Payment Header
		fputcsv( $report, array(
			'PMTHDR',
			'USPS',
			'QKCHECKS',
			date( 'm/d/Y' ),
			number_format( $amount, 2, '.', '' ),
			$options['account_number'],
			$start + $count, // must be globally unique?
			$options['contact_email'],
			$options['contact_phone'],
		), ',', '|' );

		// Payee Name Record
		fputcsv( $report, array(
			'PAYENM',
			substr( $payable_to, 0, 35 ),
			'',
			sprintf( '%d-%d', $entry->blog_id, $entry->request_id ),
		), ',', '|' );

		// Payee Address Record
		fputcsv( $report, array(
			'PYEADD',
			substr( get_post_meta( $post->ID, '_wcbrr_check_street_address', true ), 0, 35 ),
			'',
		), ',', '|' );

		// Additional Payee Address Record
		fputcsv( $report, array( 'ADDPYE', '', '' ), ',', '|' );

		// Payee Postal Record
		fputcsv( $report, array(
			'PYEPOS',
			substr( get_post_meta( $post->ID, '_wcbrr_check_city', true ), 0, 35 ),
			substr( get_post_meta( $post->ID, '_wcbrr_check_state', true ), 0, 35 ),
			substr( get_post_meta( $post->ID, '_wcbrr_check_zip_code', true ), 0, 10 ),
			substr( $vendor_country_code, 0, 3 ),
		), ',', '|' );

		// Payment Description
		fputcsv( $report, array(
			'PYTDES',
			substr( $description, 0, 122 ),
		), ',', '|' );

		restore_current_blog();
	}

	// File Trailer
	fputcsv( $report, array( 'FILTRL', $count * 6 + 2 ), ',', '|' );

	// Update counter and unlock
	$start = absint( get_site_option( '_wcb_jpm_checks_counter', 0 ) );
	update_site_option( '_wcb_jpm_checks_counter', $start + $count );
	delete_site_transient( '_wcb_jpm_checks_counter_lock' );

	fclose( $report );
	return ob_get_clean();
}

/**
 * NACHA via JP Morgan
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_jpm_ach( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	$ach_options = apply_filters( 'wcb_payment_req_ach_options', array(
		'bank-routing-number' => '', // Immediate Destination (bank routing number)
		'company-id'          => '', // Company ID
		'financial-inst'      => '', // Originating Financial Institution
	) );

	ob_start();

	// File Header Record

	echo '1'; // Record Type Code
	echo '01'; // Priority Code
	echo ' ' . str_pad( substr( $ach_options['bank-routing-number'], 0, 9 ), 9, '0', STR_PAD_LEFT );
	echo str_pad( substr( $ach_options['company-id'], 0, 10 ), 10, '0', STR_PAD_LEFT ); // Immediate Origin (TIN)
	echo date( 'ymd' ); // Transmission Date
	echo date( 'Hi' ); // Transmission Time
	echo 'A'; // File ID Modifier
	echo '094'; // Record Size
	echo '10'; // Blocking Factor
	echo '1'; // Format Code
	echo str_pad( 'JPMORGANCHASE', 23 ); // Destination
	echo str_pad( 'WCEXPORT', 23 ); // Origin
	echo str_pad( '', 8 ); // Reference Code (optional)
	echo PHP_EOL;

	// Batch Header Record

	echo '5'; // Record Type Code
	echo '200'; // Service Type Code
	echo 'WordCamp Communi'; // Company Name
	echo str_pad( '', 20 ); // Blanks
	echo str_pad( substr( $ach_options['company-id'], 0, 10 ), 10 ); // Company Identification

	// Get the first one in the set.
	// @todo Split batches by account type.
	foreach ( $args['data'] as $entry ) {
		switch_to_blog( $entry->blog_id );
		$post = get_post( $entry->request_id );

		if ( $args['status'] && $post->post_status != $args['status'] ) {
			restore_current_blog();
			continue;
		} elseif ( $post->post_type != POST_TYPE ) {
			restore_current_blog();
			continue;
		} elseif ( get_post_meta( $post->ID, '_wcbrr_payment_method', true ) != 'Direct Deposit' ) {
			restore_current_blog();
			continue;
		}

		$account_type = get_post_meta( $post->ID, '_wcbrr_ach_account_type', true );
		restore_current_blog();
		break;
	}

	$entry_class = $account_type == 'Personal' ? 'PPD' : 'CCD';
	echo $entry_class; // Standard Entry Class

	echo 'Reimbursem'; // Entry Description
	echo date( 'ymd', \WordCamp\Budgets_Dashboard\_next_business_day_timestamp() ); // Company Description Date
	echo date( 'ymd', \WordCamp\Budgets_Dashboard\_next_business_day_timestamp() ); // Effective Entry Date
	echo str_pad( '', 3 ); // Blanks
	echo '1'; // Originator Status Code
	echo str_pad( substr( $ach_options['financial-inst'], 0, 8 ), 8 ); // Originating Financial Institution
	echo '0000001'; // Batch Number
	echo PHP_EOL;

	$count = 0;
	$total = 0;
	$hash = 0;

	foreach ( $args['data'] as $entry ) {
		switch_to_blog( $entry->blog_id );
		$post = get_post( $entry->request_id );

		if ( $args['status'] && $post->post_status != $args['status'] ) {
			restore_current_blog();
			continue;
		} elseif ( $post->post_type != POST_TYPE ) {
			restore_current_blog();
			continue;
		} elseif ( get_post_meta( $post->ID, '_wcbrr_payment_method', true ) != 'Direct Deposit' ) {
			restore_current_blog();
			continue;
		}

		$count++;

		// Entry Detail Record

		echo '6'; // Record Type Code

		$transaction_code = $account_type == 'Personal' ? '27' : '22';
		echo $transaction_code; // Transaction Code

		// Transit/Routing Number of Destination Bank + Check digit
		$routing_number = get_post_meta( $post->ID, '_wcbrr_ach_routing_number', true );
		$routing_number = \WCP_Encryption::maybe_decrypt( $routing_number );
		$routing_number = substr( $routing_number, 0, 8 + 1 );
		$routing_number = str_pad( $routing_number, 8 + 1 );
		$hash += absint( substr( $routing_number, 0, 8 ) );
		echo $routing_number;

		// Bank Account Number
		$account_number = get_post_meta( $post->ID, '_wcbrr_ach_account_number', true );
		$account_number = \WCP_Encryption::maybe_decrypt( $account_number );
		$account_number = substr( $account_number, 0, 17 );
		$account_number = str_pad( $account_number, 17 );
		echo $account_number;

		// Amount
		$amount = 0;
		$expenses = get_post_meta( $post->ID, '_wcbrr_expenses', true );
		foreach ( $expenses as $expense ) {
			if ( ! empty( $expense['_wcbrr_amount'] ) ) {
				$amount += floatval( $expense['_wcbrr_amount'] );
			}
		}

		$amount = round( $amount, 2 );
		$total += $amount;
		$amount = str_pad( number_format( $amount, 2, '', '' ), 10, '0', STR_PAD_LEFT );
		echo $amount;

		// Individual Identification Number
		echo str_pad( sprintf( '%d-%d', $entry->blog_id, $entry->request_id ), 15 );

		// Individual Name
		$name = get_post_meta( $post->ID, '_wcbrr_ach_account_holder_name', true );
		$name = \WCP_Encryption::maybe_decrypt( $name );
		$name = substr( $name, 0, 22 );
		$name = str_pad( $name, 22 );
		echo $name;

		echo '  '; // User Defined Data
		echo '0'; // Addenda Record Indicator

		// Trace Number
		echo str_pad( substr( $ach_options['bank-routing-number'], 0, 8 ), 8, '0', STR_PAD_LEFT ); // routing number
		echo str_pad( $count, 7, '0', STR_PAD_LEFT ); // sequence number
		echo PHP_EOL;
	}

	// Batch Trailer Record

	echo '8'; // Record Type Code
	echo '200'; // Service Class Code
	echo str_pad( $count, 6, '0', STR_PAD_LEFT ); // Entry/Addenda Count
	echo str_pad( substr( $hash, -10 ), 10, '0', STR_PAD_LEFT ); // Entry Hash
	echo str_pad( number_format( $total, 2, '', '' ), 12, '0', STR_PAD_LEFT ); // Total Debit Entry Dollar Amount
	echo str_pad( 0, 12, '0', STR_PAD_LEFT ); // Total Credit Entry Dollar Amount
	echo str_pad( substr( $ach_options['company-id'], 0, 10 ), 10 ); // Company ID
	echo str_pad( '', 25 ); // Blanks
	echo str_pad( substr( $ach_options['financial-inst'], 0, 8 ), 8 ); // Originating Financial Institution
	echo '0000001'; // Batch Number
	echo PHP_EOL;


	// File Trailer Record

	echo '9'; // Record Type Code
	echo '000001'; // Batch Count
	echo str_pad( ceil( $count / 10 ), 6, '0', STR_PAD_LEFT ); // Block Count
	echo str_pad( $count, 8, '0', STR_PAD_LEFT ); // Entry/Addenda Count
	echo str_pad( substr( $hash, -10 ), 10, '0', STR_PAD_LEFT ); // Entry Hash
	echo str_pad( number_format( $total, 2, '', '' ), 12, '0', STR_PAD_LEFT ); // Total Debit Entry Dollar Amount
	echo str_pad( 0, 12, '0', STR_PAD_LEFT ); // Total Credit Entry Dollar Amount
	echo str_pad( '', 39 ); // Blanks
	echo PHP_EOL;

	// The file must have a number of lines that is a multiple of 10 (e.g. 10, 20, 30).
	echo str_repeat( PHP_EOL, 10 - ( ( 4 + $count ) % 10 ) - 1 );
	return ob_get_clean();
}