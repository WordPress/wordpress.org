<?php
/**
 * Plugin name: Camptix Invoices for WordCamp.org
 * Description: Generate invoices for tickets.
 * Version: 1.0.1
 * Author: Willy Bahuaud, Simon Janin, Antonio Villegas, Mathieu Sarrasin
 * Author URI: https://central.wordcamp.org/
 *
 * @package Camptix_Invoices
 */

defined( 'ABSPATH' ) || exit;

define( 'CTX_INV_VER', '1.0.1' );
define( 'CTX_INV_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'CTX_INV_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'CTX_INV_ADMIN_URL', CTX_INV_URL . '/admin' );

/**
 * Loads WordCamp Docs PDF Generator.
 */
function ctx_load_docs_pdf_generator() {
	if ( ! defined( 'WORDCAMP_DOCS__PLUGIN_DIR' ) ) {
		return;
	}//end if
	require_once WORDCAMP_DOCS__PLUGIN_DIR . 'classes/class-wordcamp-docs-pdf-generator.php';
}
add_action( 'init', 'ctx_load_docs_pdf_generator' );

/**
 * Load invoice addon.
 */
function load_camptix_invoices() {
	require plugin_dir_path( __FILE__ ) . 'includes/class-camptix-addon-invoices.php';
	camptix_register_addon( 'CampTix_Addon_Invoices' );
	add_action( 'init', 'register_tix_invoice' );
}
add_action( 'camptix_load_addons', 'load_camptix_invoices' );

/**
 * Register invoice CPT and custom statuses.
 */
function register_tix_invoice() {
	register_post_type(
		'tix_invoice',
		array(
			'label'        => __( 'Invoices', 'wordcamporg' ),
			'labels'       => array(
				'name'           => __( 'Invoices', 'wordcamporg' ),
				'singular_name'  => _x( 'Invoice', 'Post Type Singular Name', 'wordcamporg' ),
				'menu_name'      => __( 'Invoices', 'wordcamporg' ),
				'name_admin_bar' => __( 'Invoice', 'wordcamporg' ),
				'archives'       => __( 'Invoice Archives', 'wordcamporg' ),
				'attributes'     => __( 'Invoice Attributes', 'wordcamporg' ),
				'add_new_item'   => __( 'Add New Invoice', 'wordcamporg' ),
				'add_new'        => __( 'Add New', 'wordcamporg' ),
				'new_item'       => __( 'New Invoice', 'wordcamporg' ),
				'edit_item'      => __( 'Edit Invoice', 'wordcamporg' ),
				'update_item'    => __( 'Update Invoice', 'wordcamporg' ),
				'view_item'      => __( 'View Invoice', 'wordcamporg' ),
				'view_items'     => __( 'View Invoices', 'wordcamporg' ),
				'search_items'   => __( 'Search Invoices', 'wordcamporg' ),
			),
			'supports'     => array( 'title' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=tix_ticket',
		)
	);

	register_post_status( 'refunded',
		array(
			'label'                     => _x( 'Refunded', 'post', 'wordcamporg' ),
			'public'                    => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Refunded <span class="count">(%s)</span>', 'Refunded <span class="count">(%s)</span>', 'wordcamporg' ),
		)
	);

	register_post_status( 'cancelled',
		array(
			'label'                     => _x( 'Cancelled', 'post', 'wordcamporg' ),
			'public'                    => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'wordcamporg' ),
		)
	);
}

/**
 * Register invoice CPT custom update messages.
 */
function ctx_set_invoice_updated_messages( $messages ) {

	$messages['tix_invoice'] = array(
		0  => '', // Unused. Messages start at index 1.
		1  => __( 'Invoice updated.', 'wordcamporg' ),
		2  => __( 'Custom field updated.', 'wordcamporg' ),
		3  => __( 'Custom field deleted.', 'wordcamporg' ),
		4  => __( 'Invoice updated.', 'wordcamporg' ),
		5  => __( 'Invoice restored.', 'wordcamporg' ),
		6  => __( 'Invoice saved.', 'wordcamporg' ),
		7  => __( 'Invoice saved.', 'wordcamporg' ),
		8  => __( 'Invoice submitted.', 'wordcamporg' ),
		9  => __( 'Invoice saved.', 'wordcamporg' ),
		10 => __( 'Invoice draft updated.', 'wordcamporg' ),
	);
	return $messages;
}
add_filter( 'post_updated_messages', 'ctx_set_invoice_updated_messages' );

/**
 * Display custom post statuses.
 */
function ctx_append_post_status_list() {

	global $post;
	$refunded_selected  = '';
	$cancelled_selected = '';
	$status             = '';
	$refunded           = __( 'refunded', 'wordcamporg' );
	$cancelled          = __( 'cancelled', 'wordcamporg' );
	$refunded_status    = _x( 'Refunded', 'post', 'wordcamporg' );
	$cancelled_status   = _x( 'Cancelled', 'post', 'wordcamporg' );

	if ( 'tix_invoice' === $post->post_type ) {

		if ( 'refunded' === $post->post_status ) {
			$refunded_selected = ' selected=\"selected\"';
			$status            = $refunded_status;
		}

		if ( 'cancelled' === $post->post_status ) {
			$cancelled_selected = ' selected=\"selected\"';
			$status             = $cancelled_status;
		}

		?>
		<script>
			jQuery( document ).ready( function($) {
				$( "select#post_status" ).append( "<option value=\"<?php echo esc_attr( $refunded ); ?>\" <?php echo esc_attr( $refunded_selected ); ?>><?php echo esc_html( $refunded_status ); ?></option>" );
				$( "select#post_status" ).append( "<option value=\"<?php echo esc_attr( $cancelled ); ?>\" <?php echo esc_attr( $cancelled_selected ); ?>><?php echo esc_html( $cancelled_status ); ?></option>" );
				<?php if ( ! empty( $status ) ) { ?>
					$( ".misc-pub-post-status #post-status-display" ).html( '<?php echo esc_html( $status ); ?>' );
				<?php } ?>
			});
		</script>
		<?php
	}
}
add_action( 'admin_footer-post.php', 'ctx_append_post_status_list' );

/**
 * Show custom statuses on invoices index.
 */
function ctx_display_custom_statuses( $states ) {

	global $post;
	$arg = get_query_var( 'post_status' );

	if ( 'refunded' !== $arg ) {
		if ( 'refunded' === $post->post_status ) {
			return array( _x( 'Refunded', 'post', 'wordcamporg' ) );
		}
	}

	if ( 'cancelled' !== $arg ) {
		if ( 'cancelled' === $post->post_status ) {
			return array( _x( 'Cancelled', 'post', 'wordcamporg' ) );
		}
	}

	return $states;
}
add_filter( 'display_post_states', 'ctx_display_custom_statuses' );

/**
 * Adding custom post status to Bulk and Quick Edit boxes: Status dropdown
 */
function ctx_append_post_status_bulk_edit() {

	?>
	<script>
		jQuery( document ).ready( function($) {
			$( ".inline-edit-status select " ).append("<option value=\"<?php echo esc_attr( __( 'refunded', 'wordcamporg' ) ); ?>\"><?php echo esc_html_x( 'Refunded', 'post', 'wordcamporg' ); ?></option>" );
			$( ".inline-edit-status select " ).append("<option value=\"<?php echo esc_attr( __( 'cancelled', 'wordcamporg' ) ); ?>\"><?php echo esc_html_x( 'Cancelled', 'post', 'wordcamporg' ); ?></option>" );
		});
	</script>
	<?php

}

add_action( 'admin_footer-edit.php', 'ctx_append_post_status_bulk_edit' );

/**
 * Display an invoice button.
 *
 * @param object $post The post.
 */
function ctx_invoice_link( $post ) {

	if ( 'tix_invoice' !== $post->post_type ) {
		return false;
	}//end if

	$invoice_number = get_post_meta( $post->ID, 'invoice_number', true );
	if ( empty( $invoice_number ) ) {
		return false;
	}

	$invoice_url = ctx_get_invoice_url( $post->ID );

	include CTX_INV_DIR . '/includes/views/invoice-download-button.php';
}
add_action( 'post_submitbox_misc_actions', 'ctx_invoice_link' );

/**
 * Register metabox on invoices.
 *
 * @param object $post The post.
 */
function ctx_register_invoice_metabox( $post ) {

	$non_editable_statuses = array( 'publish', 'cancelled', 'refunded' );
	if ( in_array( $post->post_status, $non_editable_statuses, true ) ) {
		add_meta_box(
			'ctx_invoice_metabox',
			esc_html__( 'Info', 'wordcamporg' ),
			'ctx_invoice_metabox_sent',
			'tix_invoice',
			'normal',
			'high'
		);
	} else {
		add_meta_box(
			'ctx_invoice_metabox',
			esc_html__( 'Info', 'wordcamporg' ),
			'ctx_invoice_metabox_editable',
			'tix_invoice',
			'normal',
			'high'
		);
	}//end if
}
add_action( 'add_meta_boxes_tix_invoice', 'ctx_register_invoice_metabox' );

/**
 * Metabox for editable invoice (not published).
 *
 * @param object $args The args.
 */
function ctx_invoice_metabox_editable( $args ) {

	$order              = get_post_meta( $args->ID, 'original_order', true );
	$metas              = get_post_meta( $args->ID, 'invoice_metas', true );
	$opt                = get_option( 'camptix_options' );
	$invoice_vat_number = $opt['invoice-vat-number'];

	if ( ! is_array( $order ) ) {
		$order = array();
	}//end if
	if ( ! is_array( $metas ) ) {
		$metas = array();
	}//end if

	if ( empty( $order['items'] ) || ! is_array( $order['items'] ) ) {
		$order['items'] = array();
	}//end if

	wp_nonce_field( 'edit-invoice-' . get_current_user_id() . '-' . $args->ID, 'edit-invoice' );

	include CTX_INV_DIR . '/includes/views/editable-invoice-metabox.php';
}

/**
 * Metabox for published invoices.
 *
 * @param object $args The args.
 */
function ctx_invoice_metabox_sent( $args ) {

	$order              = get_post_meta( $args->ID, 'original_order', true );
	$metas              = get_post_meta( $args->ID, 'invoice_metas', true );
	$opt                = get_option( 'camptix_options' );
	$invoice_vat_number = $opt['invoice-vat-number'];
	$txn_id             = isset( $metas['transaction_id'] ) ? $metas['transaction_id'] : '';

	include CTX_INV_DIR . '/includes/views/sent-invoice-metabox.php';
}

/**
 * Save invoice metabox.
 *
 * @param int $post_id The post ID.
 */
function ctx_save_invoice_details( $post_id ) {
	if ( ! isset( $_POST['edit-invoice'], $_POST['user_ID'], $_POST['post_ID'], $_POST['order'], $_POST['invoice_metas'] ) ) {
		return;
	}//end if

	check_admin_referer( 'edit-invoice-' . absint( $_POST['user_ID'] ) . '-' . absint( $_POST['post_ID'] ), 'edit-invoice' );

	$order = wp_parse_args(
		$_POST['order'],
		array(
			'total'  => 0,
			'items'  => array(),
			'coupon' => '',
		)
	);

	$final_order = array(
		'total'  => floatval( $order['total'] ),
		'items'  => array_filter( array_map( 'ctx_sanitize_order_item', $order['items'] ) ),
		'coupon' => sanitize_text_field( $order['coupon'] ),
	);

	$default_metas = array(
		'email'   => '',
		'name'    => '',
		'address' => '',
	);

	$opt = get_option( 'camptix_options' );
	if ( ! empty( $opt['invoice-vat-number'] ) ) {
		$default_metas['vat-number'] = '';
	}//end if

	$metas = wp_parse_args( $_POST['invoice_metas'], $default_metas );

	$final_metas = array(
		'email'   => sanitize_email( $metas['email'] ),
		'name'    => sanitize_text_field( $metas['name'] ),
		'address' => sanitize_textarea_field( $metas['address'] ),
	);
	if ( ! empty( $opt['invoice-vat-number'] ) ) {
		$final_metas['vat-number'] = sanitize_text_field( $metas['vat-number'] );
	}//end if

	update_post_meta( $post_id, 'original_order', $final_order );
	update_post_meta( $post_id, 'invoice_metas', $final_metas );
}
add_action( 'save_post_tix_invoice', 'ctx_save_invoice_details', 10, 2 );

/**
 * Sanitize order item.
 */
function ctx_sanitize_order_item( $item ) {

	$item = wp_parse_args(
		$item,
		array(
			'id'          => 0,
			'name'        => '',
			'description' => '',
			'quantity'    => 0,
			'price'       => 0,
		)
	);

	$item = array(
		'id'          => absint( $item['id'] ),
		'name'        => sanitize_text_field( $item['name'] ),
		'description' => sanitize_text_field( $item['description'] ),
		'quantity'    => absint( $item['quantity'] ),
		'price'       => floatval( $item['price'] ),
	);

	if ( empty( $item['name'] ) ) {
		return false;
	}

	if ( empty( $item['quantity'] ) ) {
		return false;
	}

	return $item;
}

/**
 * Mark an invoice as draft when incomplete.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_mark_incomplete_invoice_as_draft( $invoice_id ) {
	if ( wp_is_post_revision( $invoice_id ) || wp_is_post_autosave( $invoice_id ) ) {
		return;
	}

	if ( 'tix_invoice' !== get_post_type( $invoice_id ) ) {
		return;
	}

	if ( in_array( get_post_status( $invoice_id ), array( 'trash', 'pending' ), true ) ) {
		return;
	}

	if ( CampTix_Addon_Invoices::is_invoice_incomplete( $invoice_id ) ) {
		remove_action( 'save_post', 'ctx_mark_incomplete_invoice_as_draft' );
		wp_update_post(
			array(
				'ID'          => $invoice_id,
				'post_status' => 'draft',
			)
		);
		add_action( 'save_post', 'ctx_mark_incomplete_invoice_as_draft' );
	}
}
add_action( 'save_post', 'ctx_mark_incomplete_invoice_as_draft' );

/**
 * Assign an invoice number.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_assign_invoice_number( $invoice_id ) {
	if ( wp_is_post_revision( $invoice_id ) || wp_is_post_autosave( $invoice_id ) ) {
		return;
	}

	if ( 'tix_invoice' !== get_post_type( $invoice_id ) ) {
		return;
	}

	if ( ! get_post_meta( $invoice_id, 'invoice_number', true ) ) {
		$number = CampTix_Addon_Invoices::create_invoice_number();
		update_post_meta( $invoice_id, 'invoice_number', $number );
	}
}
add_action( 'save_post', 'ctx_assign_invoice_number' );

/**
 * Generate the invoice document.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_generate_invoice_document( $invoice_id ) {
	if ( wp_is_post_revision( $invoice_id ) || wp_is_post_autosave( $invoice_id ) ) {
		return;
	}

	if ( 'tix_invoice' !== get_post_type( $invoice_id ) ) {
		return;
	}

	if ( ! in_array( get_post_status( $invoice_id ), array( 'publish', 'cancelled', 'refunded' ), true ) ) {
		return;
	}

	CampTix_Addon_Invoices::create_invoice_document( $invoice_id );
}
add_action( 'save_post', 'ctx_generate_invoice_document' );

/**
 * Remove the invoice document in drafts.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_remove_invoice_document_in_draft( $invoice_id ) {
	if ( wp_is_post_revision( $invoice_id ) || wp_is_post_autosave( $invoice_id ) ) {
		return;
	}

	if ( 'tix_invoice' !== get_post_type( $invoice_id ) ) {
		return;
	}

	if ( ! in_array( get_post_status( $invoice_id ), array( 'draft', 'trash' ), true ) ) {
		return;
	}

	CampTix_Addon_Invoices::delete_invoice_document( $invoice_id );
}
add_action( 'save_post', 'ctx_remove_invoice_document_in_draft' );

/**
 * Invoice form generator.
 */
function ctx_invoice_form( $order, $options ) {

	if ( empty( $options['invoice-active'] ) ) {
		return;
	}

	$invoice_vat_number = $options['invoice-vat-number'];
	include CTX_INV_DIR . '/includes/views/invoice-form.php';

}
add_action( 'camptix_form_attendee_after_registration_information', 'ctx_invoice_form', 10, 2 );

/**
 * Recovers a path for a PDF invoice.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_get_invoice( $invoice_id ) {
	$invoice_document = get_post_meta( $invoice_id, 'invoice_document', true );
	$upload_dir       = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		wp_die( esc_html__( 'Base upload directory is empty.', 'wordcamporg' ) );
	}

	$invoices_dirname = $upload_dir['basedir'] . '/camptix-invoices';
	$path             = $invoices_dirname . '/' . $invoice_document;

	if ( ! file_exists( $path ) ) {
		wp_die( esc_html__( 'Invoice document does not exist.', 'wordcamporg' ) );
	}

	return $path;
}

/**
 * Recovers the URL for a PDF invoice.
 *
 * @param int $invoice_id The invoice id.
 */
function ctx_get_invoice_url( $invoice_id ) {

	$invoice_document = get_post_meta( $invoice_id, 'invoice_document', true );
	if ( empty( $invoice_document ) ) {
		return false;
	}

	$upload_dir = wp_upload_dir();
	if ( empty( $upload_dir['basedir'] ) ) {
		return false;
	}

	$invoices_dirurl = $upload_dir['baseurl'] . '/camptix-invoices';
	return $invoices_dirurl . '/' . $invoice_document;
}

/**
 * Registers the personal data exporter for invoices.
 *
 * @param array $exporters
 *
 * @return array
 */
function ctx_register_invoice_data_exporter( $exporters ) {
	$exporters['camptix-invoice'] = array(
		'exporter_friendly_name' => __( 'CampTix Invoice Data', 'camptix' ),
		'callback'               => 'ctx_invoice_data_exporter',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'ctx_register_invoice_data_exporter' );

/**
 * Finds and exports invoice data associated with an email address.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function ctx_invoice_data_exporter( $email_address, $page ) {
	$page = (int) $page;

	$data_to_export = array();

	$post_query = get_invoice_posts( $email_address, $page );

	foreach ( (array) $post_query->posts as $post ) {
		$invoice_data_to_export = array();

		$invoice_number = get_post_meta( $post->ID, 'invoice_number', true );
		$invoice_metas  = get_post_meta( $post->ID, 'invoice_metas', true );

		foreach ( $invoice_metas as $key => $value ) {

			switch ( $key ) {
				case 'email':
					$label = __( 'Email', 'wordcamporg' );
					break;

				case 'name':
					$label = __( 'Name', 'wordcamporg' );
					break;

				case 'address':
					$label = __( 'Address', 'wordcamporg' );
					break;

				case 'vat-number':
					$label = __( 'VAT Number', 'wordcamporg' );
					break;

				default:
					continue;
			}

			if ( ! empty( $value ) ) {
				$invoice_data_to_export[] = array(
					'name'  => $label,
					'value' => $value,
				);
			}
		}

		if ( ! empty( $invoice_number ) ) {
			$invoice_data_to_export[] = array(
				'name'  => __( 'Invoice Number', 'wordcamporg' ),
				'value' => $invoice_number,
			);
		}

		if ( ! empty( $invoice_data_to_export ) ) {
			$data_to_export[] = array(
				'group_id'    => 'camptix-invoice',
				'group_label' => __( 'CampTix Invoice Data', 'wordcamporg' ),
				'item_id'     => "camptix-invoice-{$post->ID}",
				'data'        => $invoice_data_to_export,
			);
		}
	}

	$done = $post_query->max_num_pages <= $page;

	return array(
		'data' => $data_to_export,
		'done' => $done,
	);
}

/**
 * Get the list of invoice posts related to a particular email address.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return WP_Query
 */
function get_invoice_posts( $email_address, $page ) {
	$number = 20;

	return new WP_Query(
		array(
			'posts_per_page' => $number,
			'paged'          => $page,
			'post_type'      => 'tix_invoice',
			'post_status'    => 'any',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => 'invoice_metas',
			'meta_compare'   => 'LIKE',
			'meta_value'     => $email_address,
		)
	);
}
