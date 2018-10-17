<?php

namespace WordCamp\Budgets\Privacy;

use WP_Query, WP_Post;
use WordCamp\Budgets\Reimbursement_Requests;
use WCP_Payment_Request;

defined( 'WPINC' ) || die();


add_filter( 'the_posts',                          __NAMESPACE__ . '\hide_others_payment_files', 10, 2 );
add_filter( 'wp_privacy_personal_data_exporters', __NAMESPACE__ . '\register_personal_data_exporters' );
add_filter( 'wp_privacy_personal_data_erasers',   __NAMESPACE__ . '\register_personal_data_erasers'   );


/**
 * Prevent non-admins from viewing payment files uploaded by other users.
 *
 * The files sometimes have sensitive information, like account numbers etc. `the_posts` was chosen over
 * `ajax_query_attachments_args`, `pre_get_posts`, and other techniques, because it is the most comprehensive
 * and flexible solution. It will remove things from the Media Library, but also REST API endpoints, XML-RPC,
 * RSS, etc. It also allows the chance the to only apply the conditions to certain post types, whereas setting
 * query vars is much more limited.
 *
 * SECURITY WARNING: When querying attachments `get_posts()`, make sure you pass `suppress_filters => false`,
 * otherwise this will not run.
 *
 * @param WP_Post[] $attachments
 * @param WP_Query  $wp_query
 *
 * @return array
 */
function hide_others_payment_files( $attachments, $wp_query ) {
	$user = wp_get_current_user();

	if ( 'attachment' !== $wp_query->get( 'post_type' ) || in_array( 'administrator', $user->roles, true ) ) {
		return $attachments;
	}

	$payment_posts_ids = get_payment_file_parent_ids( $attachments );

	foreach ( $attachments as $index => $attachment ) {
		if ( ! in_array( $attachment->post_parent, $payment_posts_ids, true ) ) {
			continue;
		}

		if ( $attachment->post_author === $user->ID ) {
			continue;
		}

		/*
		 * The post is already cached from the request in `get_payment_file_parent_ids()`, so this doesn't create
		 * a new database query, it's just a way to access the individual post directly instead of iterating through
		 * `$payment_posts_with_attachments`.
		 */
		$parent_author = (int) get_post( $attachment->post_parent )->post_author;

		if ( $parent_author === $user->ID ) {
			continue;
		}

		unset( $attachments[ $index ] );
	}

	// Re-index the array, because WP_Query functions will assume there are no gaps.
	return array_values( $attachments );
}

/**
 * Get the Reimbursement/Vendor Payment posts that are attached to the given media items.
 *
 * @param WP_Post[] $attachments
 *
 * @return int[]
 */
function get_payment_file_parent_ids( $attachments ) {
	$parent_ids     = array_unique( wp_list_pluck( $attachments, 'post_parent' ) );
	$orphaned_index = array_search( 0, $parent_ids, true );

	// All payment files should be attached to a post, so unattached files can be removed.
	if ( false !== $orphaned_index ) {
		unset( $parent_ids[ $orphaned_index ] );
	}

	$payment_posts_with_attachments = get_posts( array(
		'post__in'    => $parent_ids,
		'post_status' => 'any',
		'numberposts' => 1000,
		'post_type'   => array(
			Reimbursement_Requests\POST_TYPE,
			WCP_Payment_Request::POST_TYPE,
		),
	) );

	return wp_list_pluck( $payment_posts_with_attachments, 'ID' );
}

/**
 * Registers the personal data eraser for each WordCamp post type
 *
 * @param array $erasers
 *
 * @return array
 */
function register_personal_data_erasers( $erasers ) {
	/**
	 * This is an empty stub, we are not adding an eraser for now, because it contains data which can be used for
	 * accounting or reference purpose.
	 */
	return $erasers;
}

/**
 * Registers the personal data exporter for each WordCamp post type.
 *
 * @param array $exporters
 *
 * @return array
 */
function register_personal_data_exporters( $exporters ) {
	$exporters['wcb-reimbursements'] = array(
		'exporter_friendly_name' => __( 'WordCamp Reimbursement Requests', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\reimbursements_exporter',
	);

	$exporters['wcb-vendor-payments'] = array(
		'exporter_friendly_name' => __( 'WordCamp Vendor Payment Requests', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\vendor_payment_exporter',
	);

	return $exporters;
}

/**
 * Finds and exports personal data associated with an email address in a vendor payment request
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function vendor_payment_exporter( $email_address, $page ) {
	$results = array(
		'data' => array(),
		'done' => true,
	);

	$vendor_payment_requests = get_post_wp_query( WCP_Payment_Request::POST_TYPE, $page, $email_address );

	if ( empty( $vendor_payment_requests ) ) {
		return $results;
	}

	$data_to_export = array();

	foreach ( $vendor_payment_requests->posts as $post ) {
		$vendor_payment_exp_data = array();
		$meta                    = get_post_meta( $post->ID );

		$vendor_payment_exp_data[] = [
			'name'  => __( 'Title', 'wordcamporg' ),
			'value' => $post->post_title,
		];
		$vendor_payment_exp_data[] = [
			'name'  => __( 'Date', 'wordcamporg' ),
			'value' => $post->post_date,
		];

		$vendor_payment_exp_data = array_merge(
			$vendor_payment_exp_data, get_meta_details( $meta, WCP_Payment_Request::POST_TYPE )
		);

		if ( ! empty( $vendor_payment_exp_data ) ) {
			$data_to_export[] = array(
				'group_id'    => WCP_Payment_Request::POST_TYPE,
				'group_label' => __( 'WordCamp Vendor Payments', 'wordcamporg' ),
				'item_id'     => WCP_Payment_Request::POST_TYPE . "-{$post->ID}",
				'data'        => $vendor_payment_exp_data,
			);
		}
	}

	$results['done'] = $vendor_payment_requests->max_num_pages <= $page;
	$results['data'] = $data_to_export;

	return $results;
}

/**
 * Finds and exports personal data associated with an email address in a Reimbursement Request.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function reimbursements_exporter( $email_address, $page ) {
	$results = array(
		'data' => array(),
		'done' => true,
	);

	$reimbursements = get_post_wp_query( Reimbursement_Requests\POST_TYPE, $page, $email_address );

	if ( empty( $reimbursements ) ) {
		return $results;
	}

	$data_to_export = array();

	foreach ( $reimbursements->posts as $post ) {
		$reimbursement_data_to_export = array();
		$meta                         = get_post_meta( $post->ID );

		$reimbursement_data_to_export[] = [
			'name'  => __( 'Title', 'wordcamporg' ),
			'value' => $post->post_title,
		];
		$reimbursement_data_to_export[] = [
			'name'  => __( 'Date', 'wordcamporg' ),
			'value' => $post->post_date,
		];

		// Meta fields.
		$reimbursement_data_to_export = array_merge(
			$reimbursement_data_to_export, get_meta_details( $meta, Reimbursement_Requests\POST_TYPE )
		);

		if ( ! empty( $reimbursement_data_to_export ) ) {
			$data_to_export[] = array(
				'group_id'    => Reimbursement_Requests\POST_TYPE,
				'group_label' => __( 'WordCamp Reimbursement Request', 'wordcamporg' ),
				'item_id'     => Reimbursement_Requests\POST_TYPE . "-{$post->ID}",
				'data'        => $reimbursement_data_to_export,
			);
		}
	}

	$results['done'] = $reimbursements->max_num_pages <= $page;
	$results['data'] = $data_to_export;

	return $results;
}

/**
 * Helper function, to build and return WP_Query object for fetching posts that should be considered for exporting data
 *
 * We use `_camppayments_vendor_email_address` as the key for `payment_request`, instead of author email,
 * because the vendor contact details could be of an individual (instead of a business), and thus is a potential PII
 *
 * @param string  $query_type
 * @param integer $page
 * @param string  $email_address Email address of the entity making the request.
 *
 * @return null|WP_Query
 */
function get_post_wp_query( $query_type, $page, $email_address ) {
	$query_args = array(
		'post_type'      => $query_type,
		'post_status'    => 'any',
		'number_posts'   => - 1,
		'posts_per_page' => 20,
		'paged'          => $page,
	);

	switch ( $query_type ) {
		case Reimbursement_Requests\POST_TYPE:
			$user = get_user_by( 'email', $email_address );

			if ( empty( $user ) ) {
				return null;
			}

			$query_args = array_merge( $query_args, array( 'post_author' => $user->ID ) );
			break;

		case WCP_Payment_Request::POST_TYPE:
			$query_args['meta_query'] = [
				'relation' => 'AND',
			];

			$query_args['meta_query'][] = [
				'key'   => '_camppayments_vendor_email_address',
				'value' => $email_address,
			];
			break;

		default:
			return null;
	}

	return new WP_Query( $query_args );
}

/**
 *
 * @param array  $meta      Meta object of post, as retrieved by `get_post_meta( $post->ID )`.
 * @param string $post_type Post type. Could be one of `wcb_reimbursement` or `wcp_payment_request`.
 *
 * @return array Details of the reimbursement request
 */
function get_meta_details( $meta, $post_type ) {
	$meta_details = array();

	foreach ( get_meta_fields_mapping( $post_type ) as $meta_field => $meta_field_name ) {
		$data = isset( $meta[ $meta_field ] ) ? $meta[ $meta_field ] : null;

		if ( ! empty( $data ) && is_array( $data ) && ! empty( $data[0] ) ) {
			$meta_details[] = [
				'name'  => $meta_field_name,
				'value' => $meta [ $meta_field ][0],
			];
		}
	}

	return $meta_details;
}

/**
 * Returns array of meta fields and their titles that we want to allow export for.
 *
 * @param string $post_type
 *
 * @return array
 */
function get_meta_fields_mapping( $post_type ) {
	$mapping_fields = array();

	if ( Reimbursement_Requests\POST_TYPE === $post_type ) {
		$prefix         = '_wcbrr_';
		$mapping_fields = array_merge(
			$mapping_fields,
			array(
				$prefix . 'name_of_payer'               => __( 'Payer Name', 'wordcamporg' ),
				$prefix . 'currency'                    => __( 'Currency', 'wordcamporg' ),
				$prefix . 'payment_method'              => __( 'Payment Method', 'wordcamporg' ),

				// Payment Method - Direct Deposit.
				$prefix . 'ach_bank_name'               => __( 'Bank Name', 'wordcamporg' ),
				$prefix . 'ach_account_type'            => __( 'Account Type', 'wordcamporg' ),
				$prefix . 'ach_routing_number'          => __( 'Routing Number', 'wordcamporg' ),
				$prefix . 'ach_account_number'          => __( 'Account Number', 'wordcamporg' ),
				$prefix . 'ach_account_holder_name'     => __( 'Account Holder Name', 'wordcamporg' ),

				// Payment Method - Check.
				$prefix . 'payable_to'                  => __( 'Payable To', 'wordcamporg' ),
				$prefix . 'check_street_address'        => __( 'Street Address', 'wordcamporg' ),
				$prefix . 'check_city'                  => __( 'City', 'wordcamporg' ),
				$prefix . 'check_state'                 => __( 'State / Province', 'wordcamporg' ),
				$prefix . 'check_zip_code'              => __( 'ZIP / Postal Code', 'wordcamporg' ),
				$prefix . 'check_country'               => __( 'Country', 'wordcamporg' ),

				// Payment Method - Wire.
				$prefix . 'bank_name'                   => __( 'Beneficiary’s Bank Name', 'wordcamporg' ),
				$prefix . 'bank_street_address'         => __( 'Beneficiary’s Bank Street Address', 'wordcamporg' ),
				$prefix . 'bank_city'                   => __( 'Beneficiary’s Bank City', 'wordcamporg' ),
				$prefix . 'bank_state'                  => __( 'Beneficiary’s Bank State / Province', 'wordcamporg' ),
				$prefix . 'bank_zip_code'               => __( 'Beneficiary’s Bank ZIP / Postal Code', 'wordcamporg' ),
				$prefix . 'bank_country_iso3166'        => __( 'Beneficiary’s Bank Country', 'wordcamporg' ),
				$prefix . 'bank_bic'                    => __( 'Beneficiary’s Bank SWIFT BIC', 'wordcamporg' ),
				$prefix . 'beneficiary_account_number'  => __( 'Beneficiary’s Account Number or IBAN', 'wordcamporg' ),

				// Intermediary bank details.
				$prefix . 'interm_bank_name'            => __( 'Intermediary Bank Name', 'wordcamporg' ),
				$prefix . 'interm_bank_street_address'  => __( 'Intermediary Bank Street Address', 'wordcamporg' ),
				$prefix . 'interm_bank_city'            => __( 'Intermediary Bank City', 'wordcamporg' ),
				$prefix . 'interm_bank_state'           => __( 'Intermediary Bank State / Province', 'wordcamporg' ),
				$prefix . 'interm_bank_zip_code'        => __( 'Intermediary Bank ZIP / Postal Code', 'wordcamporg' ),
				$prefix . 'interm_bank_country_iso3166' => __( 'Intermediary Bank Country', 'wordcamporg' ),
				$prefix . 'interm_bank_swift'           => __( 'Intermediary Bank SWIFT BIC', 'wordcamporg' ),
				$prefix . 'interm_bank_account'         => __( 'Intermediary Bank Account', 'wordcamporg' ),

				$prefix . 'beneficiary_name'            => __( 'Beneficiary’s Name', 'wordcamporg' ),
				$prefix . 'beneficiary_street_address'  => __( 'Beneficiary’s Street Address', 'wordcamporg' ),
				$prefix . 'beneficiary_city'            => __( 'Beneficiary’s City', 'wordcamporg' ),
				$prefix . 'beneficiary_state'           => __( 'Beneficiary’s State / Province', 'wordcamporg' ),
				$prefix . 'beneficiary_zip_code'        => __( 'Beneficiary’s ZIP / Postal Code', 'wordcamporg' ),
				$prefix . 'beneficiary_country_iso3166' => __( 'Beneficiary’s Country', 'wordcamporg' ),
			)
		);
	} elseif ( WCP_Payment_Request::POST_TYPE === $post_type ) {
		$prefix         = '_camppayments_';
		$mapping_fields = array_merge(
			$mapping_fields,
			array(
				// Vendor payment fields.
				$prefix . 'description'            => __( 'Description', 'wordcamporg' ),
				$prefix . 'general_notes'          => __( 'Notes', 'wordcamporg' ),
				$prefix . 'vendor_name'            => __( 'Name', 'wordcamporg' ),
				$prefix . 'vendor_email_address'   => __( 'Email Address', 'wordcamporg' ),
				$prefix . 'vendor_contact_person'  => __( 'Contact Person', 'wordcamporg' ),
				$prefix . 'vendor_street_address'  => __( 'Street Address', 'wordcamporg' ),
				$prefix . 'vendor_city'            => __( 'City', 'wordcamporg' ),
				$prefix . 'vendor_state'           => __( 'State / Province', 'wordcamporg' ),
				$prefix . 'vendor_zip_code'        => __( 'ZIP / Postal Code', 'wordcamporg' ),
				$prefix . 'vendor_country_iso3166' => __( 'Country', 'wordcamporg' ),
			)
		);
	}

	return $mapping_fields;
}
