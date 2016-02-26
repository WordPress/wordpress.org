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
			'name'               => _x( 'Vendor Payments', 'post type general name', 'wordcamporg' ),
			'singular_name'      => _x( 'Vendor Payment', 'post type singular name', 'wordcamporg' ),
			'menu_name'          => _x( 'Vendor Payments', 'admin menu',             'wordcamporg' ),
			'name_admin_bar'     => _x( 'Vendor Payment', 'add new on admin bar',    'wordcamporg' ),
			'add_new'            => _x( 'Add New', 'payment',                        'wordcamporg' ),

			'add_new_item'       => __( 'Add New Vendor Payment',             'wordcamporg' ),
			'new_item'           => __( 'New Vendor Payment',                 'wordcamporg' ),
			'edit_item'          => __( 'Edit Vendor Payment',                'wordcamporg' ),
			'view_item'          => __( 'View Vendor Paymentt',               'wordcamporg' ),
			'all_items'          => __( 'Vendor Payments',                    'wordcamporg' ),
			'search_items'       => __( 'Search Vendor Payments',             'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Vendor Payments:',            'wordcamporg' ),
			'not_found'          => __( 'No Vendor Payments found.',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No Vendor Payments found in Trash.', 'wordcamporg' )
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
				'label'              => _x( 'Paid', 'post', 'wordcamporg' ),
				'label_count'        => _nx_noop( 'Paid <span class="count">(%s)</span>', 'Paid <span class="count">(%s)</span>', 'wordcamporg' ),
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		register_post_status(
			'unpaid',
			array(
				'label'              => _x( 'Unpaid', 'post', 'wordcamporg' ),
				'label_count'        => _nx_noop( 'Unpaid <span class="count">(%s)</span>', 'Unpaid <span class="count">(%s)</span>', 'wordcamporg' ),
				'public'             => true,
				'publicly_queryable' => false,
			)
		);

		register_post_status(
			'incomplete',
			array(
				'label'              => _x( 'Incomplete', 'post', 'wordcamporg' ),
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
			__( 'Status', 'wordcamporg' ),
			array( $this, 'render_status_metabox' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wcp_general_info',
			__( 'General Information', 'wordcamporg' ),
			array( $this, 'render_general_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		$introduction_message = sprintf(
			'<p>%s</p> <p>%s</p>',
			__( 'Direct Deposit or Wire is the fastest way to pay a vendor. Checks and credit card payments can take 3-5 days for us and/or the bank to process.', 'wordcamporg' ),
			__( 'Each wire transfer costs us processing fees, so please try to avoid multiple wire requests for one vendor.', 'wordcamporg' )
		);

		add_meta_box(
			'wcp_payment_details',
			__( 'Payment Details', 'wordcamporg' ),
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
			__( 'Vendor Details', 'wordcamporg' ),
			array( $this, 'render_vendor_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box( 'wcp_log', __( 'Log', 'wordcamporg' ), array( $this, 'render_log_metabox' ),
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
			2,
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
		$submit_text = _x( 'Update', 'payment request', 'wordcamporg' );
		$submit_note = '';

		if ( current_user_can( 'manage_network' ) ) {
			$current_user_can_edit_request = true;
		} elseif ( in_array( $post->post_status, $editable_statuses ) ) {
			$submit_text = __( 'Submit for Review', 'wordcamporg' );
			$submit_note = __( 'Once submitted for review, this request can not be edited.', 'wordcamporg' );
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

		$categories        = WordCamp_Budgets::get_payment_categories();
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
	 */
	protected function render_textarea_input( $post, $label, $name, $description = '' ) {
		$text = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-textarea.php' );
	}

	/**
	 * Render a <select> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 */
	protected function render_select_input( $post, $label, $name ) {
		$selected = get_post_meta( $post->ID, '_camppayments_' . $name, true );
		$options  = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-select.php' );
	}

	protected function render_country_input( $post, $label, $name ) {
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
	 */
	protected function render_radio_input( $post, $label, $name ) {
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
	 */
	protected function render_checkbox_input( $post, $label, $name, $description = '' ) {
		$value = $this->get_field_value( $name, $post );

		require( dirname( __DIR__ ) . '/views/payment-request/input-checkbox.php' );
	}

	/**
	 * Render a <input type="text"> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 */
	protected function render_text_input( $post, $label, $name, $description = '', $variant = 'text', $row_classes = array(), $readonly = false ) {
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

		// Update the timestamp and logs.
		update_post_meta( $post_id, '_wcb_updated_timestamp', time() );

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
				case 'vendor_country':
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
			'author'         => __( 'Author' ),
			'title'          => $_columns['title'],
			'date'           => $_columns['date'],
			'due_by'         => __( 'Due by', 'wordcamporg' ),
			'vendor_name'    => __( 'Vendor', 'wordcamporg' ),
			'payment_amount' => __( 'Amount', 'wordcamporg' ),
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
		global $post;

		if ( is_a( $post, 'WP_Post' ) && self::POST_TYPE == $post->post_type ) {
			/*
			 * Only network admins can edit requests once they've been paid.
			 *
			 * They can still open the request (in order to view the status and details), but won't be allowed to make any changes to it.
			 */
			if ( 'edit_post' == $requested_capability && 'paid' == $post->post_status && isset( $_REQUEST['action'] ) && 'edit' != $_REQUEST['action'] ) {
				$required_capabilities[] = 'manage_network';
			}

			// Only network admins can delete requests
			if ( 'delete_post' == $requested_capability ) {
				$required_capabilities[] = 'manage_network';
			}
		}

		return $required_capabilities;
	}
}
