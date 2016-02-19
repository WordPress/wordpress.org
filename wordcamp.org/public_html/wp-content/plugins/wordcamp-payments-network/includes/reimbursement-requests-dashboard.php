<?php

namespace WordCamp\Budgets_Dashboard\Reimbursement_Requests;

defined( 'WPINC' ) or die();

const LATEST_DATABASE_VERSION = 1;

if ( is_network_admin() ) {
	add_action( 'network_admin_menu',    __NAMESPACE__ . '\register_submenu_page' );
	add_action( 'init',                  __NAMESPACE__ . '\upgrade_database'      );

} elseif ( is_admin() ) {
	add_action( 'save_post',    __NAMESPACE__ . '\update_index_row', 11, 2 );   // See note in callback about priority
	add_action( 'trashed_post', __NAMESPACE__ . '\delete_index_row'        );
	add_action( 'delete_post',  __NAMESPACE__ . '\delete_index_row'        );
}

/**
 * Register the admin page
 */
function register_submenu_page() {
	add_submenu_page(
		'wordcamp-budgets-dashboard',
		'WordCamp Reimbursement Requests',
		'Reimbursements',
		'manage_network',
		'reimbursement-requests-dashboard',
		__NAMESPACE__ . '\render_submenu_page'
	);
}

/**
 * Render the admin page
 */
function render_submenu_page() {
	require_once( __DIR__ . '/reimbursement-requests-list-table.php' );

	$list_table = new Reimbursement_Requests_List_Table();
	$sections   = get_submenu_page_sections();

	$list_table->prepare_items();

	switch ( get_current_section() ) {
		case 'submitted':
			$section_explanation = 'These requests have been completed by the organizer, and need to be reviewed by a deputy.';
			break;

		case 'info_requested':
			$section_explanation = 'These requests have been reviewed by a deputy, and sent back to the organizer because they lacked some required information.';
			break;

		case 'rejected':
			$section_explanation = 'These requests have been reviewed by a deputy and rejected.';
			break;

		case 'in_process':
			$section_explanation = "These requests have been reviewed by a deputy and payment is pending.";
			break;

		case 'paid':
			$section_explanation = 'These requests have been paid and closed.';
			break;
	}

	require_once( dirname( __DIR__ ) . '/views/reimbursement-requests/page-reimbursement-requests.php' );
}

/**
 * Get the current section
 *
 * @return string
 */
function get_current_section() {
	$sections        = get_section_slugs();
	$current_section = 'submitted';

	if ( isset( $_GET['section'] ) && in_array( $_GET['section'], $sections, true ) ) {
		$current_section = $_GET['section'];
	}

	return $current_section;
}

/**
 * Get the slugs for each page section
 *
 * @return array
 */
function get_section_slugs() {
	$slugs = array_keys( \WordCamp\Budgets\Reimbursement_Requests\get_custom_statuses() );

	foreach( $slugs as & $slug ) {
		$slug = str_replace( 'wcbrr_', '', $slug );
	}

	return $slugs;
}

/**
 * Get all the data needed to render the section tabs
 *
 * @return array
 */
function get_submenu_page_sections() {
	$statuses        = \WordCamp\Budgets\Reimbursement_Requests\get_custom_statuses();
	$sections        = array();
	$current_section = get_current_section();

	foreach ( $statuses as $status_slug => $status_name ) {
		$status_slug = str_replace( 'wcbrr_', '', $status_slug );    // make the URL easier to read

		$classes = 'nav-tab';
		if ( $status_slug === $current_section ) {
			$classes .= ' nav-tab-active';
		}

		$href = add_query_arg(
			array(
				'page'    => 'reimbursement-requests-dashboard',
				'section' => $status_slug,
			),
			network_admin_url( 'admin.php' )
		);

		$sections[ $status_slug ] = array(
			'classes' => $classes,
			'href'    => $href,
			'text'    => $status_name,
		);
	}

	return $sections;
}

/**
 * Create or update the database tables
 */
function upgrade_database() {
	global $wpdb;

	$current_database_version = get_site_option( 'wcbdrr_database_version', 0 );

	if ( version_compare( $current_database_version, LATEST_DATABASE_VERSION, '>=' ) ) {
		return;
	}

	$table_name = get_index_table_name();
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$schema = "
		CREATE TABLE $table_name (
			blog_id        int( 11 )        unsigned NOT NULL default '0',
			request_id     int( 11 )        unsigned NOT NULL default '0',
			date_requested int( 11 )        unsigned NOT NULL default '0',
			request_title  varchar( 75 )             NOT NULL default '',
			status         varchar( 30 )             NOT NULL default '',
			wordcamp_name  varchar( 75 )             NOT NULL default '',
			currency       varchar( 3  )             NOT NULL default '',
			amount         numeric( 10, 2 ) unsigned NOT NULL default '0',

			PRIMARY KEY (blog_id, request_id),
			KEY status (status)
		)
		DEFAULT CHARACTER SET {$wpdb->charset}
		COLLATE {$wpdb->collate};
	";

	dbDelta( $schema );

	update_site_option( 'wcbdrr_database_version', LATEST_DATABASE_VERSION );
}

/**
 * Returns the name of the custom table.
 */
function get_index_table_name() {
	global $wpdb;

	return $wpdb->get_blog_prefix( 0 ) . 'wcbd_reimbursement_requests_index';
}

/**
 * Add or update a row in the index
 *
 * NOTE: This must run after \WordCamp\Budgets\Reimbursement_Requests\save_request(), because otherwise the
 * get_post_meta() calls would be fetching the old data, rather than the latest from the current process.
 *
 * @param int      $request_id
 * @param \WP_Post $request
 */
function update_index_row( $request_id, $request ) {
	global $wpdb;

	if ( \WordCamp\Budgets\Reimbursement_Requests\POST_TYPE !== $request->post_type ) {
		return;
	}

	// Drafts, etc aren't displayed in the list table, so there's no reason to index them
	$ignored_statuses = array( 'auto-draft', 'draft', 'trash' );
	// todo also `inherit`. should switch to whitelist instead of blacklist

	if ( in_array( $request->post_status, $ignored_statuses, true ) ) {
		return;
	}

	$index_row = array(
		'blog_id'        => get_current_blog_id(),
		'request_id'     => $request_id,
		'date_requested' => strtotime( $request->post_date_gmt ),
		'request_title'  => $request->post_title,
		'status'         => $request->post_status,
		'wordcamp_name'  => get_wordcamp_name(),
		'currency'       => get_post_meta( $request_id, '_wcbrr_currency', true ),
		'amount'         => get_amount( $request_id ),
	);

	/*
	 * Some fields have default values like 'null-select-one', 'null-separator1', etc, but $wpdb->process_fields()
	 * will choke on those, so we need to set them to empty strings instead.
	 */
	foreach ( $index_row as & $column ) {
		if ( 'null' === substr( $column, 0, 4 ) ) {
			$column = '';
		}
	}

	$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f' );

	$wpdb->replace( get_index_table_name(), $index_row, $formats );
}

/**
 * Calculate the total expense amount for a given request
 *
 * @param int $request_id
 *
 * @return float
 */
function get_amount( $request_id ) {
	$amount   = 0.0;
	$expenses = get_post_meta( $request_id, '_wcbrr_expenses', true );

	if ( is_array( $expenses ) ) {
		foreach ( $expenses as $expense ) {
			$amount += (float) $expense['_wcbrr_amount'];
		}
	}

	return $amount;
}

/**
 * Delete a row from the index
 *
 * @param int $request_id
 */
function delete_index_row( $request_id ) {
	global $wpdb;

	/*
	 * Normally we would check if $request_id is from the kind of post type we want, but that's not necessary in
	 * this case, because only requests are added to the index to begin with. If $request_id is from some other
	 * post type, then $wpdb->delete() will return false with no negative consequences. That's quicker than having
	 * to query for the $request post in order to check the post type.
	 */

	$wpdb->delete(
		get_index_table_name(),
		array(
			'blog_id'    => get_current_blog_id(),
			'request_id' => $request_id,
		)
	);
}
