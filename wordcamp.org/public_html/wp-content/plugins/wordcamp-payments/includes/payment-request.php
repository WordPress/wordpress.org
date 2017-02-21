<?php

/*
 * Create the Vendor Payments post type and associated functionality
 */
class WCP_Payment_Request {
	var $meta_key_prefix = 'camppayments'; // Dirty hack so that Payment Method metabox rendering can be reused by other modules

	const POST_TYPE = 'wcp_payment_request';

	// @see https://core.trac.wordpress.org/ticket/19074
	public static $transition_post_status = array();

	public function __construct() {
		// Initialization
		add_action( 'init',                   array( $this, 'register_post_type' ));
		add_action( 'init',                   array( __CLASS__, 'register_post_statuses' ) );
		add_action( 'add_meta_boxes',         array( $this, 'init_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ), 11 );

		// Miscellaneous
		add_filter( 'display_post_states',    array( $this, 'display_post_states' ) );

		// Saving posts
		add_filter( 'wp_insert_post_data',    array( $this, 'wp_insert_post_data' ), 10, 2 );
		add_action( 'save_post',              array( $this, 'save_payment' ), 10, 2 );
		add_filter( 'map_meta_cap',           array( $this, 'modify_capabilities' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );

		// Columns
		add_filter( 'manage_'.      self::POST_TYPE .'_posts_columns',       array( $this, 'get_columns' ) );
		add_filter( 'manage_edit-'. self::POST_TYPE .'_sortable_columns',    array( $this, 'get_sortable_columns' ) );
		add_action( 'manage_'.      self::POST_TYPE .'_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
		add_action( 'pre_get_posts',                                         array( $this, 'sort_columns' ) );
	}

	/**
	 * Register the custom post type
	 *
	 * @return object | WP_Error
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => esc_html_x( 'Vendor Payments', 'post type general name', 'wordcamporg' ),
			'singular_name'      => esc_html_x( 'Vendor Payment', 'post type singular name', 'wordcamporg' ),
			'menu_name'          => esc_html_x( 'Vendor Payments', 'admin menu',             'wordcamporg' ),
			'name_admin_bar'     => esc_html_x( 'Vendor Payment', 'add new on admin bar',    'wordcamporg' ),
			'add_new'            => esc_html_x( 'Add New', 'payment',                        'wordcamporg' ),

			'add_new_item'       => esc_html__( 'Add New Vendor Payment',             'wordcamporg' ),
			'new_item'           => esc_html__( 'New Vendor Payment',                 'wordcamporg' ),
			'edit_item'          => esc_html__( 'Edit Vendor Payment',                'wordcamporg' ),
			'view_item'          => esc_html__( 'View Vendor Payment',                'wordcamporg' ),
			'all_items'          => esc_html__( 'Vendor Payments',                    'wordcamporg' ),
			'search_items'       => esc_html__( 'Search Vendor Payments',             'wordcamporg' ),
			'parent_item_colon'  => esc_html__( 'Parent Vendor Payments:',            'wordcamporg' ),
			'not_found'          => esc_html__( 'No Vendor Payments found.',          'wordcamporg' ),
			'not_found_in_trash' => esc_html__( 'No Vendor Payments found in Trash.', 'wordcamporg' )
		);

		$args = array(
			'labels'            => $labels,
			'description'       => 'WordCamp Vendor Payments',
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => 'wordcamp-budget',
			'show_in_nav_menus' => true,
			'supports'          => array( 'title' ),
			'has_archive'       => true,
		);

		return register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register our custom post statuses
	 */
	public static function register_post_statuses() {
		// Legacy statuses. Real statuses are registered in wordcamp-budgets.php.
		register_post_status(
			'paid',
			array(
				'label'              => esc_html_x( 'Paid', 'post', 'wordcamporg' ),
				'label_count'        => _nx_noop( 'Paid <span class="count">(%s)</span>', 'Paid <span class="count">(%s)</span>', 'wordcamporg' ),
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		register_post_status(
			'unpaid',
			array(
				'label'              => esc_html_x( 'Unpaid', 'post', 'wordcamporg' ),
				'label_count'        => _nx_noop( 'Unpaid <span class="count">(%s)</span>', 'Unpaid <span class="count">(%s)</span>', 'wordcamporg' ),
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		register_post_status(
			'incomplete',
			array(
				'label'              => esc_html_x( 'Incomplete', 'post', 'wordcamporg' ),
				'label_count'        => _nx_noop( 'Incomplete <span class="count">(%s)</span>', 'Incomplete <span class="count">(%s)</span>', 'wordcamporg' ),
				'public'             => true,
				'publicly_queryable' => false,
			)
		);
	}

	/**
	 * Register meta boxes
	 */
	public function init_meta_boxes() {
		/** @var $post WP_Post */
		global $post;

		// We're build our own Publish box, thankyouverymuch
		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );

		add_meta_box(
			'submitdiv',
			esc_html__( 'Status', 'wordcamporg' ),
			array( $this, 'render_status_metabox' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wcp_general_info',
			esc_html__( 'General Information', 'wordcamporg' ),
			array( $this, 'render_general_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		$introduction_message = sprintf(
			'<p>%s</p> <p>%s</p>',
			esc_html__( 'Direct Deposit or Wire is the fastest way to pay a vendor. Checks and credit card payments can take 3-5 days for us and/or the bank to process.', 'wordcamporg' ),
			esc_html__( 'Each wire transfer costs us processing fees, so please try to avoid multiple wire requests for one vendor.', 'wordcamporg' )
		);

		add_meta_box(
			'wcp_payment_details',
			esc_html__( 'Payment Details', 'wordcamporg' ),
			array( $this, 'render_payment_metabox' ),
			self::POST_TYPE,
			'normal',
			'high',
			array(
				'meta_key_prefix'      => 'camppayments',
				'introduction_message' => $introduction_message,
			)
		);

		add_meta_box(
			'wcp_vendor_details',
			esc_html__( 'Vendor Details', 'wordcamporg' ),
			array( $this, 'render_vendor_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box( 'wcp_log', esc_html__( 'Log', 'wordcamporg' ), array( $this, 'render_log_metabox' ),
			self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Enqueue scripts and stylesheets
	 */
	public function enqueue_assets() {
		global $post;

		// Register our assets
		wp_register_script(
			'payment-requests',
			plugins_url( 'javascript/payment-requests.js', __DIR__ ),
			array( 'wordcamp-budgets', 'wcb-attached-files', 'jquery' ),
			3,
			true
		);

		// Enqueue our assets if they're needed on the current screen
		$current_screen = get_current_screen();

		if ( 'wcp_payment_request' !== $current_screen->id ) {
			return;
		}

		wp_enqueue_script( 'payment-requests' );

		if ( isset( $post->ID ) ) {
			wp_enqueue_media( array( 'post' => $post->ID ) );
			wp_enqueue_script( 'wcb-attached-files' );
		}
	}

	/**
	 * Render the Status metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_status_metabox( $post ) {
		wp_nonce_field( 'status', 'status_nonce' );

		$back_compat_statuses = array(
			'unpaid' => 'draft',
			'incomplete' => 'wcb-incomplete',
			'paid' => 'wcb-paid',
		);

		// Map old statuses to new statuses.
		if ( array_key_exists( $post->post_status, $back_compat_statuses ) ) {
			$post->post_status = $back_compat_statuses[ $post->post_status ];
		}

		$editable_statuses = array( 'auto-draft', 'draft', 'wcb-incomplete' );
		$current_user_can_edit_request = false;
		$submit_text = esc_html_x( 'Update', 'payment request', 'wordcamporg' );
		$submit_note = '';

		if ( current_user_can( 'manage_network' ) ) {
			$current_user_can_edit_request = true;
		} elseif ( in_array( $post->post_status, $editable_statuses ) ) {
			$submit_text = esc_html__( 'Submit for Review', 'wordcamporg' );
			$submit_note = esc_html__( 'Once submitted for review, this request can not be edited.', 'wordcamporg' );
			$current_user_can_edit_request = true;
		}

		$date_vendor_paid = get_post_meta( $post->ID, '_camppayments_date_vendor_paid', true );
		if ( current_user_can( 'manage_network' ) ) {
			$date_vendor_paid_readonly = false;
		} else {
			$date_vendor_paid_readonly = true;
		}

		$incomplete_notes = get_post_meta( $post->ID, '_wcp_incomplete_notes', true );
		$incomplete_readonly = ! current_user_can( 'manage_network' ) ? 'readonly' : '';

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-status.php' );
	}

	public static function get_post_statuses() {
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
	 * Render the General Information metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_general_metabox( $post ) {
		wp_nonce_field( 'general_info', 'general_info_nonce' );

		$assigned_category = get_post_meta( $post->ID, '_camppayments_payment_category', true );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-general.php' );

		// todo If they select other but don't fill in the explanation, set to draft and display error msg, similar to require_complete_meta_to_publish_wordcamp()
	}

	/**
	 * Render the Vendor Details metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_vendor_metabox( $post ) {
		wp_nonce_field( 'vendor_details', 'vendor_details_nonce' );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-vendor.php' );
	}

	/**
	 * Render the Payment Details
	 *
	 * @param $post
	 * @param array $box
	 */
	public function render_payment_metabox( $post, $box ) {
		// todo centralize this, since it's also used by the reimbursements module

		wp_nonce_field( 'payment_details', 'payment_details_nonce' );

		$this->meta_key_prefix   = $box['args']['meta_key_prefix'];

		if ( ! isset( $box['args']['fields_enabled'] ) ) {
			$box['args']['fields_enabled'] = true;
		}

		if ( ! isset( $box['args']['show_vendor_requested_payment_method'] ) ) {
			$box['args']['show_vendor_requested_payment_method'] = true;
		}

		$selected_payment_method = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_payment_method", true );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-payment.php' );
	}

	/**
	 * Render the Log metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_log_metabox( $post ) {
		$log = get_post_meta( $post->ID, '_wcp_log', true );
		if ( empty( $log ) )
			$log = '[]';

		$log = json_decode( $log, true );

		// I wish I had a spaceship.
		uasort( $log, function( $a, $b ) {
			if ( $b['timestamp'] == $a )
				return 0;

			return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
		});

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-log.php' );
	}

	/**
	 * Render a <textarea> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param string $description
	 * @param bool   $required
	 */
	protected function render_textarea_input( $post, $label, $name, $description = '', $required = true ) {
		$text = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-textarea.php' );
	}

	/**
	 * Render a <select> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param bool   $required
	 */
	protected function render_select_input( $post, $label, $name, $required = true ) {
		$selected = get_post_meta( $post->ID, '_camppayments_' . $name, true );
		$options  = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-select.php' );
	}

	/**
	 * Render a select dropdown for countries
	 *
	 * @param WP_Post $post
	 * @param string  $label
	 * @param string  $name
	 * @param bool    $required
	 */
	protected function render_country_input( $post, $label, $name, $required = true ) {
		$selected = $this->get_field_value( $name, $post );
		$options = WordCamp_Budgets::get_valid_countries_iso3166();

		require( dirname( __DIR__ ) . '/views/payment-request/input-country.php' );
	}

	/**
	 * Render a <input type="radio"> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param bool   $required
	 */
	protected function render_radio_input( $post, $label, $name, $required = true ) {
		$selected = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . $name, true );
		$options  = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-radio.php' );
	}

	/**
	 * Render a <input type="checkbox"> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param bool   $required
	 */
	protected function render_checkbox_input( $post, $label, $name, $description = '', $required = true ) {
		$value = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-checkbox.php' );
	}

	/**
	 * Render a <input type="text"> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param string $description
	 * @param string $variant
	 * @param array  $row_classes
	 * @param bool   $readonly
	 * @param bool   $required
	 */
	protected function render_text_input( $post, $label, $name, $description = '', $variant = 'text', $row_classes = array(), $readonly = false, $required = true ) {
		$value = $this->get_field_value( $name, $post );
		array_walk( $row_classes, 'sanitize_html_class' );
		$row_classes = implode( ' ', $row_classes );

		require( dirname( __DIR__ ) . '/views/payment-request/input-text.php' );
	}

	/**
	 * Render an upload button and list of uploaded files.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 * @param string $description
	 */
	protected function render_files_input( $post, $label, $name, $description = '' ) {
		$files = WordCamp_Budgets::get_attached_files( $post );

		wp_localize_script( 'wcb-attached-files', 'wcbAttachedFiles', $files ); // todo merge into wordcampBudgets var

		require( dirname( __DIR__ ) . '/views/payment-request/input-files.php' );
	}

	/**
	 * Get the value of a given field.
	 *
	 * @param string $name
	 * @param WP_Post $post
	 *
	 * @return mixed
	 */
	protected function get_field_value( $name, $post ) {
		switch( $name ) {
			case 'request_id':
				$value = get_current_blog_id() . '-' . $post->ID;
				break;

			case 'requester':
				$value = WordCamp_Budgets::get_requester_name( $post->post_author );
				break;

			case 'date_vendor_paid':
			case 'invoice_date':
			case 'due_by':
				if ( $value = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . $name, true ) ) {
					$value = date( 'Y-m-d', $value );
				}
				break;

			case 'currency':
				$value = WordCamp_Budgets::get_currencies();
				break;

			case 'payment_category':
				$value = WordCamp_Budgets::get_payment_categories();
				break;

			case 'payment_method':
				$value = WordCamp_Budgets::get_valid_payment_methods( $post->post_type );
				break;

			case 'general_notes':
				// The files_notes field was removed from the UI, so combine its value with general notes
				$file_notes    = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . 'file_notes',    true );
				$general_notes = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . 'general_notes', true );

				if ( $file_notes ) {
					$general_notes .= ' ' . $file_notes;
					update_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . 'general_notes', $general_notes );
					delete_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . 'file_notes' );
				}

				$value = $general_notes;
				break;

			case 'ach_account_type':
				$value = array( 'Personal', 'Company' );
				break;

			default:
				$value = get_post_meta( $post->ID, "_{$this->meta_key_prefix}_" . $name, true );
				break;
		}

		if ( in_array( $name, WordCamp_Budgets::get_encrypted_fields() ) ) {
			$decrypted = WCP_Encryption::maybe_decrypt( $value );
			if ( ! is_wp_error( $decrypted ) )
				$value = $decrypted;
		}

		return $value;
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

		if ( $post->post_type != self::POST_TYPE )
			return $states;

		// Back-compat
		$back_compat_statuses = array(
			'unpaid' => 'draft',
			'incomplete' => 'wcb-incomplete',
			'paid' => 'wcb-paid',
		);

		// Map old statuses to new statuses.
		if ( array_key_exists( $post->post_status, $back_compat_statuses ) ) {
			$post->post_status = $back_compat_statuses[ $post->post_status ];
		}

		$status = get_post_status_object( $post->post_status );
		if ( get_query_var( 'post_status' ) != $post->post_status ) {
			$states[ $status->name ] = $status->label;
		}

		return $states;
	}

	/**
	 *
	 * @param array $post_data
	 * @param array $post_data_raw
	 *
	 * @return array
	 */
	public function wp_insert_post_data( $post_data, $post_data_raw ) {
		if ( $post_data['post_type'] != self::POST_TYPE )
			return $post_data;

		// Ensure that new posts have the `post_date_gmt` field populated.
		if ( 'auto-draft' !== $post_data['post_status'] ) {
			if ( '0000-00-00 00:00:00' === $post_data['post_date_gmt'] ) {
				$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
			}
		}

		// Save Draft button was clicked.
		if ( ! empty( $post_data_raw['wcb-save-draft'] ) ) {
			$post_data['post_status'] = 'draft';
		}

		// Submit for Review button was clicked.
		if ( ! current_user_can( 'manage_network' ) ) {
			$editable_statuses = array( 'auto-draft', 'draft', 'wcb-incomplete' );
			if ( ! empty( $post_data_raw['wcb-update'] ) && in_array( $post_data['post_status'], $editable_statuses ) ) {
				$post_data['post_status'] = 'wcb-pending-approval';
			}
		}

		return $post_data;
	}

	/**
	 * Notify the payment requester that it has been marked as paid.
	 *
	 * @param int|WP_Post $post
	 */
	protected function notify_requester_payment_made( $post ) {
		$post = get_post( $post );

		if ( ! $to = WordCamp_Budgets::get_requester_formatted_email( $post->post_author ) ) {
			return;
		}

		$subject = sprintf( '"%s" has been paid', $post->post_title );
		$headers = array( 'Reply-To: support@wordcamp.org' );

		$message = sprintf(
			"The request for \"%s\" has been marked as paid by WordCamp Central.

			You can view the request at:

			%s

			If you have any questions, please reply to let us know.",
			$post->post_title,
			admin_url( sprintf( 'post.php?post=%s&action=edit', $post->ID ) )
		);
		$message = str_replace( "\t", '', $message );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Notify the payment requester that it has been marked as paid.
	 *
	 * @param int|WP_Post $post
	 */
	protected function notify_requester_request_incomplete( $post ) {
		$post = get_post( $post );

		if ( ! $to = WordCamp_Budgets::get_requester_formatted_email( $post->post_author ) ) {
			return;
		}

		$subject = sprintf( '"%s" is incomplete', $post->post_title );
		$headers = array( 'Reply-To: support@wordcamp.org' );
		$notes = get_post_meta( $post->ID, '_wcp_incomplete_notes', true );

		$message = sprintf(
			"The request for \"%s\" has been marked as incomplete by WordCamp Central.

The reason for this is: %s

Please provide more information or clarify payment instructions here:

%s

More information about making payment requests can be found here:

https://make.wordpress.org/community/handbook/community-deputy-handbook/wordcamp-program-basics/payment-requests/

Thanks for helping us with these details!",
			$post->post_title,
			esc_html( $notes ),
			admin_url( sprintf( 'post.php?post=%s&action=edit', $post->ID ) )
		);
		$message = str_replace( "\t", '', $message );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Save the post's data
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_payment( $post_id, $post ) {
		if ( $post->post_type != self::POST_TYPE )
			return;

		if ( WordCamp_Budgets::post_edit_is_actionable( $post, self::POST_TYPE ) ) {
			// Verify nonces
			$nonces = array( 'status_nonce', 'general_info_nonce', 'payment_details_nonce', 'vendor_details_nonce' );    // todo add prefix to all of these

			foreach ( $nonces as $nonce ) {
				if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( $_POST[ $nonce ], str_replace( '_nonce', '', $nonce ) ) ) {
					return;
				}
			}

			// Sanitize and save the field values
			$this->sanitize_save_normal_fields( $post_id );
			WordCamp_Budgets::validate_save_payment_method_fields( $post_id, 'camppayments' );
			$this->sanitize_save_misc_fields( $post_id );
		}

		$user = get_user_by( 'id', get_current_user_id() );

		// Look at post status transitions.
		foreach ( self::$transition_post_status as $data ) {
			list( $new, $old, $transition_post ) = $data;

			// Transitioning a different post.
			if ( $transition_post->ID != $post->ID )
				continue;

			if ( $new == 'incomplete' || $new == 'wcb-incomplete' ) {
				$incomplete_text = get_post_meta( $post->ID, '_wcp_incomplete_notes', true );
				$incomplete_text = preg_replace( '#\.$#', '', $incomplete_text ); // trailing-undot-it.
				WordCamp_Budgets::log( $post->ID, $user->ID, sprintf( 'Marked as incomplete: %s', $incomplete_text ), array(
					'action' => 'marked-incomplete',
					'reason' => 'maybe notes',
				) );

				$this->notify_requester_request_incomplete( $post->ID );
				WordCamp_Budgets::log( $post->ID, $user->ID, 'Incomplete notification e-mail sent.', array(
					'action' => 'incomplete-notification-sent',
				) );

			} elseif ( $new == 'paid' || $new == 'wcb-paid' ) {
				WordCamp_Budgets::log( $post->ID, $user->ID, 'Marked as paid', array(
					'action' => 'marked-paid',
				) );

				$this->notify_requester_payment_made( $post->ID );
				WordCamp_Budgets::log( $post->ID, $user->ID, 'Paid notification e-mail sent.', array(
					'action' => 'paid-notification-sent',
				) );

			} elseif ( $old == 'auto-draft' ) {
				WordCamp_Budgets::log( $post->ID, $user->ID, 'Request created', array(
					'action' => 'updated',
				) );
			}
		}

		WordCamp_Budgets::log( $post->ID, $user->ID, 'Request updated', array(
			'action' => 'updated',
		) );
	}

	/**
	 * Sanitize and save values for all normal fields
	 *
	 * @param int $post_id
	 */
	protected function sanitize_save_normal_fields( $post_id ) {
		foreach ( $_POST as $key => $unsafe_value ) {
			$unsafe_value = wp_unslash( $unsafe_value );

			switch ( $key ) {
				case 'description':
				case 'general_notes':
				case 'vendor_requested_payment_method':
					$safe_value = wp_kses( $unsafe_value, wp_kses_allowed_html( 'strip' ) );
					break;

				case 'payment_amount':
					$safe_value = WordCamp_Budgets::validate_amount( $unsafe_value );
					break;

				case 'currency':
				case 'payment_category':
				case 'vendor_name':
				case 'vendor_phone_number':
				case 'vendor_email_address':
				case 'vendor_street_address':
				case 'vendor_city':
				case 'vendor_state':
				case 'vendor_zip_code':
				case 'vendor_contact_person':
				case 'other_category_explanation':
					$safe_value = sanitize_text_field( $unsafe_value );
					break;

				case 'invoice_number':
				case 'invoice_date':
				case 'due_by':
					if ( empty( $_POST[ $key ] ) ) {
						$safe_value = '';
					} else {
						$safe_value = strtotime( sanitize_text_field( $unsafe_value ) );
					}
					break;

				default:
					$safe_value = null;
					break;
			}

			if ( ! is_null( $safe_value ) ) {
				update_post_meta( $post_id, '_camppayments_' . $key, $safe_value );
			}
		}
	}

	/**
	 * Sanitize and save values for all checkbox fields
	 *
	 * @param int $post_id
	 */
	protected function sanitize_save_misc_fields( $post_id ) {
		$post = get_post( $post_id );

		// Status
		if ( current_user_can( 'manage_network' ) ) {
			$safe_value = strtotime( sanitize_text_field( $_POST['date_vendor_paid'] ) );
			update_post_meta( $post_id, '_camppayments_date_vendor_paid', $safe_value );
		}

		// Incomplete Notes
		if ( isset( $_POST['wcp_mark_incomplete_notes'] ) ) {
			$safe_value = '';
			if ( $post->post_status == 'wcb-incomplete' ) {
				$safe_value = wp_kses( $_POST['wcp_mark_incomplete_notes'], wp_kses_allowed_html( 'strip' ) );
			}

			update_post_meta( $post_id, '_wcp_incomplete_notes', $safe_value );
		}

		// Attach existing files
		remove_action( 'save_post', array( $this, 'save_payment' ), 10 ); // avoid infinite recursion
		WordCamp_Budgets::attach_existing_files( $post_id, $_POST );
		add_action( 'save_post', array( $this, 'save_payment' ), 10, 2 );
	}

	/**
	 * Add log entries when the post status changes
	 *
	 * @param string  $new
	 * @param string  $old
	 * @param WP_Post $post
	 */
	public function transition_post_status( $new, $old, $post ) {
		if ( $post->post_type != self::POST_TYPE )
			return;

		if ( $new == 'auto-draft' || $new == $old )
			return;

		// Move logging to save_post because transitions are fired before save_post.
		self::$transition_post_status[] = array( $new, $old, $post );
	}

	/**
	 * Define columns for the Vendor Payments screen.
	 *
	 * @param array $_columns
	 * @return array
	 */
	public function get_columns( $_columns ) {
		$columns = array(
			'cb'             => $_columns['cb'],
			'author'         => esc_html__( 'Author' ),
			'title'          => $_columns['title'],
			'date'           => $_columns['date'],
			'due_by'         => esc_html__( 'Due by', 'wordcamporg' ),
			'vendor_name'    => esc_html__( 'Vendor', 'wordcamporg' ),
			'payment_amount' => esc_html__( 'Amount', 'wordcamporg' ),
		);

		return $columns;
	}

	/**
	 * Register our sortable columns.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function get_sortable_columns( $columns ) {
		$columns['due_by']   = '_camppayments_due_by';

		return $columns;
	}

	/**
	 * Render custom columns on the Vendor Payments screen.
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	public function render_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'payment_amount':
				$currency = get_post_meta( $post_id, '_camppayments_currency', true );
				if ( false === strpos( $currency, 'null' ) ) {
					echo esc_html( $currency ) . ' ';
				}

				echo esc_html( get_post_meta( $post_id, '_camppayments_payment_amount', true ) );
				break;

			case 'due_by':
				if ( $date = get_post_meta( $post_id, '_camppayments_due_by', true ) ) {
					echo date( 'F jS, Y', $date );
				}
				break;

			default:
				echo esc_html( get_post_meta( $post_id, '_camppayments_' . $column, true ) );
				break;
		}
	}

	/**
	 * Sort our custom columns.
	 *
	 * @param WP_Query $query
	 */
	public function sort_columns( $query ) {
		if ( self::POST_TYPE != $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		switch( $orderby ) {
			case '_camppayments_due_by':
				$query->set( 'meta_key', '_camppayments_due_by' );
				$query->set( 'orderby', 'meta_value_num' );
				break;

			default:
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
	public function modify_capabilities( $required_capabilities, $requested_capability, $user_id, $args ) {
		// todo maybe centralize this, since almost identical to counterparts in other modules
		$post = \WordCamp_Budgets::get_map_meta_cap_post( $args );

		if ( is_a( $post, 'WP_Post' ) && self::POST_TYPE == $post->post_type ) {
			/*
			 * Only network admins can edit requests once they've been paid.
			 *
			 * They can still open the request (in order to view the status and details), but won't be allowed to make any changes to it.
			 * They can also edit and re-submit requests that were marked as incomplete.
			 */
			if ( ! in_array( $post->post_status, array( 'auto-draft', 'draft' ), true ) ) {
				if ( 'edit_post' == $requested_capability && 'wcb-incomplete' != $post->post_status ) {
					$is_saving_edit = isset( $_REQUEST['action'] ) && 'edit' != $_REQUEST['action'];  // 'edit' is opening the Edit Invoice screen, 'editpost' is when it's submitted
					$is_bulk_edit   = isset( $_REQUEST['bulk_edit'] );

					if ( $is_saving_edit || $is_bulk_edit ) {
						$required_capabilities[] = 'manage_network';
					}
				}

				// Only network admins can delete requests
				if ( 'delete_post' == $requested_capability ) {
					$required_capabilities[] = 'manage_network';
				}
			}
		}

		return $required_capabilities;
	}

	public static function _generate_payment_report_default( $args ) {
		$column_headings = array(
			'WordCamp', 'ID', 'Title', 'Status', 'Date Vendor was Paid', 'Creation Date', 'Due Date', 'Amount',
			'Currency', 'Category', 'Payment Method','Vendor Name', 'Vendor Contact Person', 'Vendor Country',
			'Check Payable To', 'URL', 'Supporting Documentation Notes',
		);

		ob_start();
		$report = fopen( 'php://output', 'w' );

		fputcsv( $report, wcorg_esc_csv( $column_headings ) );

		foreach( $args['data'] as $entry ) {
			switch_to_blog( $entry->blog_id );

			$post = get_post( $entry->post_id );

			$back_compat_statuses = array(
				'unpaid' => 'draft',
				'incomplete' => 'wcb-incomplete',
				'paid' => 'wcb-paid',
			);

			// Map old statuses to new statuses.
			if ( array_key_exists( $post->post_status, $back_compat_statuses ) ) {
				$post->post_status = $back_compat_statuses[ $post->post_status ];
			}

			if ( $args['status'] && $post->post_status != $args['status'] ) {
				restore_current_blog();
				continue;
			} elseif ( $post->post_type != self::POST_TYPE ) {
				restore_current_blog();
				continue;
			}

			$currency = get_post_meta( $post->ID, '_camppayments_currency', true );
			$category = get_post_meta( $post->ID, '_camppayments_payment_category', true );
			$date_vendor_paid = get_post_meta( $post->ID, '_camppayments_date_vendor_paid', true );

			if ( $date_vendor_paid ) {
				$date_vendor_paid = date( 'Y-m-d', $date_vendor_paid );
			}

			$due_date = get_post_meta( $post->ID, '_camppayments_due_by', true );

			if ( $due_date ) {
				$due_date = date( 'Y-m-d', absint( $due_date ) );
			}

			if ( 'null-select-one' === $currency ) {
				$currency = '';
			}

			if ( 'null' === $category ) {
				$category = '';
			}

			$country_name = WordCamp_Budgets::get_country_name(
				get_post_meta( $post->ID, '_camppayments_vendor_country_iso3166', true )
			);

			$status = get_post_status_object( $post->post_status );

			$row = array(
				get_wordcamp_name(),
				sprintf( '%d-%d', $entry->blog_id, $entry->post_id ),
				html_entity_decode( $post->post_title ),
				$status->label,
				$date_vendor_paid,
				date( 'Y-m-d', get_post_time( 'U', true, $post->ID ) ),
				$due_date,
				get_post_meta( $post->ID, '_camppayments_payment_amount', true ),
				$currency,
				$category,
				get_post_meta( $post->ID, '_camppayments_payment_method', true ),
				get_post_meta( $post->ID, '_camppayments_vendor_name', true ),
				get_post_meta( $post->ID, '_camppayments_vendor_contact_person', true ),
				$country_name,
				WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_payable_to', true ) ),
				get_edit_post_link( $post->ID ),
				get_post_meta( $post->ID, '_camppayments_file_notes', true ),
			);

			restore_current_blog();

			if ( ! empty( $row ) ) {
				fputcsv( $report, wcorg_esc_csv( $row ) );
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
	public static function _generate_payment_report_jpm_checks( $args ) {
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
		fputcsv( $report, wcorg_esc_csv( array( 'FILHDR', 'PWS', $options['pws_customer_id'], date( 'm/d/Y' ), date( 'Hi' ) ) ), ',', '|' );

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
			$post = get_post( $entry->post_id );

			if ( $args['status'] && $post->post_status != $args['status'] ) {
				restore_current_blog();
				continue;
			} elseif ( $args['post_type'] != self::POST_TYPE ) {
				restore_current_blog();
				continue;
			} elseif ( get_post_meta( $post->ID, '_camppayments_payment_method', true ) != 'Check' ) {
				restore_current_blog();
				continue;
			}

			$count++;
			$amount = round( floatval( get_post_meta( $post->ID, '_camppayments_payment_amount', true ) ), 2 );
			$total += $amount;

			$payable_to = WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_payable_to', true ) );
			$payable_to = html_entity_decode( $payable_to ); // J&amp;J to J&J
			$countries = WordCamp_Budgets::get_valid_countries_iso3166();
			$vendor_country_code = get_post_meta( $post->ID, '_camppayments_vendor_country_iso3166', true );
			if ( ! empty( $countries[ $vendor_country_code ] ) ) {
				$vendor_country_code = $countries[ $vendor_country_code ]['alpha3'];
			}

			$description = sanitize_text_field( get_post_meta( $post->ID, '_camppayments_description', true ) );
			$description = html_entity_decode( $description );
			$invoice_number = get_post_meta( $post->ID, '_camppayments_invoice_number', true );
			if ( ! empty( $invoice_number ) ) {
				$description = sprintf( 'Invoice %s. %s', $invoice_number, $description );
			}

			// Payment Header
			fputcsv( $report, wcorg_esc_csv( array(
				'PMTHDR',
				'USPS',
				'QKCHECKS',
				date( 'm/d/Y' ),
				number_format( $amount, 2, '.', '' ),
				$options['account_number'],
				$start + $count, // must be globally unique?
				$options['contact_email'],
				$options['contact_phone'],
			) ), ',', '|' );

			// Payee Name Record
			fputcsv( $report, wcorg_esc_csv( array(
				'PAYENM',
				substr( $payable_to, 0, 35 ),
				'',
				sprintf( '%d-%d', $entry->blog_id, $entry->post_id ),
			) ), ',', '|' );

			// Payee Address Record
			fputcsv( $report, wcorg_esc_csv( array(
				'PYEADD',
				substr( get_post_meta( $post->ID, '_camppayments_vendor_street_address', true ), 0, 35 ),
				'',
			) ), ',', '|' );

			// Additional Payee Address Record
			fputcsv( $report, wcorg_esc_csv( array( 'ADDPYE', '', '' ) ), ',', '|' );

			// Payee Postal Record
			fputcsv( $report, wcorg_esc_csv( array(
				'PYEPOS',
				substr( get_post_meta( $post->ID, '_camppayments_vendor_city', true ), 0, 35 ),
				substr( get_post_meta( $post->ID, '_camppayments_vendor_state', true ), 0, 35 ),
				substr( get_post_meta( $post->ID, '_camppayments_vendor_zip_code', true ), 0, 10 ),
				substr( $vendor_country_code, 0, 3 ),
			) ), ',', '|' );

			// Payment Description
			fputcsv( $report, wcorg_esc_csv( array(
				'PYTDES',
				substr( $description, 0, 122 ),
			) ), ',', '|' );

			restore_current_blog();
		}

		// File Trailer
		fputcsv( $report, wcorg_esc_csv( array( 'FILTRL', $count * 6 + 2 ) ), ',', '|' );

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
	public static function _generate_payment_report_jpm_ach( $args ) {
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
			$post = get_post( $entry->post_id );

			if ( $args['status'] && $post->post_status != $args['status'] ) {
				restore_current_blog();
				continue;
			} elseif ( $post->post_type != self::POST_TYPE ) {
				restore_current_blog();
				continue;
			} elseif ( get_post_meta( $post->ID, '_camppayments_payment_method', true ) != 'Direct Deposit' ) {
				restore_current_blog();
				continue;
			}

			$account_type = get_post_meta( $post->ID, '_camppayments_ach_account_type', true );
			restore_current_blog();
			break;
		}

		$entry_class = $account_type == 'Personal' ? 'PPD' : 'CCD';
		echo $entry_class; // Standard Entry Class

		echo 'Vendor Pay'; // Entry Description
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
			$post = get_post( $entry->post_id );

			if ( $args['status'] && $post->post_status != $args['status'] ) {
				restore_current_blog();
				continue;
			} elseif ( $post->post_type != self::POST_TYPE ) {
				restore_current_blog();
				continue;
			} elseif ( get_post_meta( $post->ID, '_camppayments_payment_method', true ) != 'Direct Deposit' ) {
				restore_current_blog();
				continue;
			}

			$count++;

			// Entry Detail Record

			echo '6'; // Record Type Code
			echo '22'; // Transaction code for Automated Deposit

			// Transit/Routing Number of Destination Bank + Check digit
			$routing_number = get_post_meta( $post->ID, '_camppayments_ach_routing_number', true );
			$routing_number = WCP_Encryption::maybe_decrypt( $routing_number );
			$routing_number = substr( $routing_number, 0, 8 + 1 );
			$routing_number = str_pad( $routing_number, 8 + 1 );
			$hash += absint( substr( $routing_number, 0, 8 ) );
			echo $routing_number;

			// Bank Account Number
			$account_number = get_post_meta( $post->ID, '_camppayments_ach_account_number', true );
			$account_number = WCP_Encryption::maybe_decrypt( $account_number );
			$account_number = substr( $account_number, 0, 17 );
			$account_number = str_pad( $account_number, 17 );
			echo $account_number;

			// Amount
			$amount = round( floatval( get_post_meta( $post->ID, '_camppayments_payment_amount', true ) ), 2 );
			$total += $amount;
			$amount = str_pad( number_format( $amount, 2, '', '' ), 10, '0', STR_PAD_LEFT );
			echo $amount;

			// Individual Identification Number
			echo str_pad( sprintf( '%d-%d', $entry->blog_id, $entry->post_id ), 15 );

			// Individual Name
			$name = get_post_meta( $post->ID, '_camppayments_ach_account_holder_name', true );
			$name = WCP_Encryption::maybe_decrypt( $name );
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

	/**
	 * Wires via JP Morgan
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public static function _generate_payment_report_jpm_wires( $args ) {
		$args = wp_parse_args( $args, array(
			'data' => array(),
			'status' => '',
			'post_type' => '',
		) );

		ob_start();
		$report = fopen( 'php://output', 'w' );

		// JPM Header
		fputcsv( $report, wcorg_esc_csv( array( 'HEADER', gmdate( 'YmdHis' ), '1' ) ) );

		$total = 0;
		$count = 0;

		foreach ( $args['data'] as $entry ) {
			switch_to_blog( $entry->blog_id );
			$post = get_post( $entry->post_id );

			if ( $args['status'] && $post->post_status != $args['status'] ) {
				restore_current_blog();
				continue;
			} elseif ( $post->post_type != self::POST_TYPE ) {
				restore_current_blog();
				continue;
			} elseif ( get_post_meta( $post->ID, '_camppayments_payment_method', true ) != 'Wire' ) {
				restore_current_blog();
				continue;
			}

			$amount = round( floatval( get_post_meta( $post->ID, '_camppayments_payment_amount', true ) ), 2);
			$total += $amount;
			$count += 1;

			// If account starts with two letters, it's most likely an IBAN
			$account = get_post_meta( $post->ID, '_camppayments_beneficiary_account_number', true );
			$account = WCP_Encryption::maybe_decrypt( $account );
			$account = preg_replace( '#\s#','', $account );
			$account_type = preg_match( '#^[a-z]{2}#i', $account ) ? 'IBAN' : 'ACCT';

			$row = array(
				'1-input-type' => 'P',
				'2-payment-method' => 'WIRES',
				'3-debit-bank-id' => apply_filters( 'wcb_payment_req_bank_id', '' ), // external file
				'4-account-number' => apply_filters( 'wcb_payment_req_bank_number', '' ), // external file
				'5-bank-to-bank' => 'N',
				'6-txn-currency' => get_post_meta( $post->ID, '_camppayments_currency', true ),
				'7-txn-amount' => number_format( $amount, 2, '.', '' ),
				'8-equiv-amount' => '',
				'9-clearing' => '',
				'10-ben-residence' => '',
				'11-rate-type' => '',
				'12-blank' => '',
				'13-value-date' => '',

				'14-id-type' => $account_type,
				'15-id-value' => $account,
				'16-ben-name' => substr( WCP_Encryption::maybe_decrypt(
					get_post_meta( $post->ID, '_camppayments_beneficiary_name', true ) ), 0, 35 ),
				'17-address-1' => substr( WCP_Encryption::maybe_decrypt(
					get_post_meta( $post->ID, '_camppayments_beneficiary_street_address', true ) ), 0, 35 ),
				'18-address-2' => '',
				'19-city-state-zip' => substr( sprintf( '%s %s %s',
						WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_city', true ) ),
						WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_state', true ) ),
						WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_zip_code', true ) )
					), 0, 32 ),
				'20-blank' => '',
				'21-country' => WCP_Encryption::maybe_decrypt(
					get_post_meta( $post->ID, '_camppayments_beneficiary_country_iso3166', true ) ),
				'22-blank' => '',
				'23-blank' => '',

				'24-id-type' => 'SWIFT',
				'25-id-value' => get_post_meta( $post->ID, '_camppayments_bank_bic', true ),
				'26-ben-bank-name' => substr( get_post_meta( $post->ID, '_camppayments_bank_name', true ), 0, 35 ),
				'27-ben-bank-address-1' => substr( get_post_meta( $post->ID, '_camppayments_bank_street_address', true ), 0, 35 ),
				'28-ben-bank-address-2' => '',
				'29-ben-bank-address-3' => substr( sprintf( '%s %s %s',
						get_post_meta( $post->ID, '_camppayments_bank_city', true ),
						get_post_meta( $post->ID, '_camppayments_bank_state', true ),
						get_post_meta( $post->ID, '_camppayments_bank_zip_code', true )
					 ), 0, 32 ),
				'30-ben-bank-country' => get_post_meta( $post->ID, '_camppayments_bank_country_iso3166', true ),
				'31-supl-id-type' => '',
				'32-supl-id-value' => '',

				'33-blank' => '',
				'34-blank' => '',
				'35-blank' => '',
				'36-blank' => '',
				'37-blank' => '',
				'38-blank' => '',
				'39-blank' => '',

				// Filled out later if not empty.
				'40-id-type' => '',
				'41-id-value' => '',
				'42-interm-bank-name' => '',
				'43-interm-bank-address-1' => '',
				'44-interm-bank-address-2' => '',
				'45-interm-bank-address-3' => '',
				'46-interm-bank-country' => '',
				'47-supl-id-type' => '',
				'48-supl-id-value' => '',

				'49-id-type' => '',
				'50-id-value' => '',
				'51-party-name' => '',
				'52-party-address-1' => '',
				'53-party-address-2' => '',
				'54-party-address-3' => '',
				'55-party-country' => '',

				'56-blank' => '',
				'57-blank' => '',
				'58-blank' => '',
				'59-blank' => '',
				'60-blank' => '',
				'61-blank' => '',
				'62-blank' => '',
				'63-blank' => '',
				'64-blank' => '',
				'65-blank' => '',
				'66-blank' => '',
				'67-blank' => '',
				'68-blank' => '',
				'69-blank' => '',
				'70-blank' => '',
				'71-blank' => '',
				'72-blank' => '',
				'73-blank' => '',

				'74-ref-text' => substr( get_post_meta( $post->ID, '_camppayments_invoice_number', true ), 0, 16 ),
				'75-internal-ref' => '',
				'76-on-behalf-of' => '',

				'77-detial-1' => '',
				'78-detial-2' => '',
				'79-detial-3' => '',
				'80-detail-4' => '',

				'81-blank' => '',
				'82-blank' => '',
				'83-blank' => '',
				'84-blank' => '',
				'85-blank' => '',
				'86-blank' => '',
				'87-blank' => '',
				'88-blank' => '',

				'89-reporting-code' => '',
				'90-country' => '',
				'91-inst-1' => '',
				'92-inst-2' => '',
				'93-inst-3' => '',
				'94-inst-code-1' => '',
				'95-inst-text-1' => '',
				'96-inst-code-2' => '',
				'97-inst-text-2' => '',
				'98-inst-code-3' => '',
				'99-inst-text-3' => '',

				'100-stor-code-1' => '',
				'101-stor-line-2' => '', // Hmm?
				'102-stor-code-2' => '',
				'103-stor-line-2' => '',
				'104-stor-code-3' => '',
				'105-stor-line-3' => '',
				'106-stor-code-4' => '',
				'107-stor-line-4' => '',
				'108-stor-code-5' => '',
				'109-stor-line-5' => '',
				'110-stor-code-6' => '',
				'111-stor-line-6' => '',

				'112-priority' => '',
				'113-blank' => '',
				'114-charges' => '',
				'115-blank' => '',
				'116-details' => '',
				'117-note' => substr( sprintf( 'wcb-%d-%d', $entry->blog_id, $entry->post_id ), 0, 70 ),
			);

			// If an intermediary bank is given.
			$interm_swift = get_post_meta( $post->ID, '_camppayments_interm_bank_swift', true );
			if ( ! empty( $iterm_swift ) ) {
				$row['40-id-type'] = 'SWIFT';
				$row['41-id-value'] = $interm_swift;

				$row['42-interm-bank-name'] = substr( get_post_meta( $post->ID, '_camppayments_interm_bank_name', true ), 0, 35 );
				$row['43-interm-bank-address-1'] = substr( get_post_meta( $post->ID, '_camppayments_interm_bank_street_address', true ), 0, 35 );

				$row['44-interm-bank-address-2'] = '';
				$row['45-interm-bank-address-3'] = substr( sprintf( '%s %s %s',
					get_post_meta( $post->ID, '_camppayments_interm_bank_city', true ),
					get_post_meta( $post->ID, '_camppayments_interm_bank_state', true ),
					get_post_meta( $post->ID, '_camppayments_interm_bank_zip_code', true )
				), 0, 32 );

				$row['46-interm-bank-country'] = get_post_meta( $post->ID, '_camppayments_interm_bank_country_iso3166', true );

				$row['47-supl-id-type'] = 'ACCT';
				$row['48-supl-id-value'] = get_post_meta( $post->ID, '_camppayments_interm_bank_account', true );
			}

			if ( get_post_meta( $post->ID, '_camppayments_currency', true ) == 'CAD' ) {
				$row['114-charges'] = 'OUR';
			}

			// Use for debugging.
			// print_r( $row );

			fputcsv( $report, wcorg_esc_csv( array_values( $row ) ) );
			restore_current_blog();
		}

		// JPM Trailer
		fputcsv( $report, wcorg_esc_csv( array( 'TRAILER', $count, $total ) ) );

		fclose( $report );
		$results = ob_get_clean();

		// JPM chokes on accents and non-latin characters.
		$results = remove_accents( $results );
		return $results;
	}
}
