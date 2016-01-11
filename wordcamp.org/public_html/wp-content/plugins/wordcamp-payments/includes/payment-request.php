<?php

/*
 * Create the Payment Request post type and associated functionality
 */
class WCP_Payment_Request {
	const POST_TYPE = 'wcp_payment_request';

	public function __construct() {
		// Initialization
		add_action( 'init',                   array( $this, 'register_post_type' ));
		add_action( 'init',                   array( __CLASS__, 'register_post_statuses' ) );
		add_action( 'add_meta_boxes',         array( $this, 'init_meta_boxes' ) );

		// Miscellaneous
		add_filter( 'display_post_states',    array( $this, 'display_post_states' ) );

		// Saving posts
		add_filter( 'wp_insert_post_data',    array( $this, 'update_request_status' ), 10, 2 );
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
			'name'               => _x( 'Payment Requests', 'post type general name', 'wordcamporg' ),
			'singular_name'      => _x( 'Payment Request', 'post type singular name', 'wordcamporg' ),
			'menu_name'          => _x( 'Payment Requests', 'admin menu', 'wordcamporg' ),
			'name_admin_bar'     => _x( 'Payment Request', 'add new on admin bar', 'wordcamporg' ),
			'add_new'            => _x( 'Add New', 'payment', 'wordcamporg' ),
			'add_new_item'       => __( 'Add New Payment Request', 'wordcamporg' ),
			'new_item'           => __( 'New Payment Request', 'wordcamporg' ),
			'edit_item'          => __( 'Edit Payment Request', 'wordcamporg' ),
			'view_item'          => __( 'View Payment Request', 'wordcamporg' ),
			'all_items'          => __( 'All Payment Requests', 'wordcamporg' ),
			'search_items'       => __( 'Search Payment Requests', 'wordcamporg' ),
			'parent_item_colon'  => __( 'Parent Payment Requests:', 'wordcamporg' ),
			'not_found'          => __( 'No payment requests found.', 'wordcamporg' ),
			'not_found_in_trash' => __( 'No payment requests found in Trash.', 'wordcamporg' )
		);

		$args = array(
			'labels'            => $labels,
			'description'       => 'WordCamp Payment Requests',
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => true,
			'menu_position'     => 25,
			'supports'          => array( 'title' ),
			'has_archive'       => true,
		);

		return register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register our custom post statuses
	 */
	public static function register_post_statuses() {
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
				'label_count'        => _nx_noop( 'Incomplete<span class="count">(%s)</span>', 'Incomplete <span class="count">(%s)</span>', 'wordcamporg' ),
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

		if ( 'incomplete' != $post->post_status && current_user_can( 'manage_network' ) ) {
			add_meta_box(
				'wcp_mark_incomplete',
				__( 'Mark as Incomplete', 'wordcamporg' ),
				array( $this, 'render_mark_incomplete_metabox' ),
				self::POST_TYPE,
				'side',
				'high'
			);
		}

		add_meta_box(
			'wcp_general_info',
			__( 'General Information', 'wordcamporg' ),
			array( $this, 'render_general_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wcp_payment_details',
			__( 'Payment Details', 'wordcamporg' ),
			array( $this, 'render_payment_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wcp_vendor_details',
			__( 'Vendor Details', 'wordcamporg' ),
			array( $this, 'render_vendor_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wcp_files',
			__( 'Attach Supporting Documentation', 'wordcamporg' ),
			array( $this, 'render_files_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box( 'wcp_log', __( 'Log', 'wordcamporg' ), array( $this, 'render_log_metabox' ),
			self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render the Status metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_status_metabox( $post ) {
		wp_nonce_field( 'status', 'status_nonce' );

		$delete_text                   = EMPTY_TRASH_DAYS ? __( 'Move to Trash' ) : __( 'Delete Permanently' );
		$submit_text                   = 'auto-draft' == $post->post_status ? __( 'Submit Request', 'wordcamporg' ) : __( 'Update Request', 'wordcamporg' );
		$current_user_can_edit_request = 'paid' != $post->post_status || current_user_can( 'manage_network' );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-status.php' );
	}

	/**
	 * Render the Mark as Incomplete metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_mark_incomplete_metabox( $post ) {
		wp_nonce_field( 'mark_incomplete', 'mark_incomplete_nonce' );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-mark-incomplete.php' );
	}

	/**
	 * Render the General Information metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_general_metabox( $post ) {
		wp_nonce_field( 'general_info', 'general_info_nonce' );

		$categories        = self::get_payment_categories();
		$assigned_category = get_post_meta( $post->ID, '_camppayments_payment_category', true );

		$date_vendor_paid = get_post_meta( $post->ID, '_camppayments_date_vendor_paid', true );
		if ( current_user_can( 'manage_network' ) ) {
			$date_vendor_paid_readonly = false;
		} else {
			$date_vendor_paid_readonly = true;
		}

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
	 */
	public function render_payment_metabox( $post ) {
		wp_nonce_field( 'payment_details', 'payment_details_nonce' );
		$selected_payment_method = get_post_meta( $post->ID, '_camppayments_payment_method', true );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-payment.php' );
	}

	/**
	 * Render the Vendor Details metabox
	 *
	 * @param WP_Post $post
	 */
	public function render_files_metabox( $post ) {
		wp_nonce_field( 'wcp_files', 'wcp_files_nonce' );

		require_once( dirname( __DIR__ ) . '/views/payment-request/metabox-files.php' );
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

			return ( $a['timestamp'] < $b['timestamp'] ) ? -1 : 1;
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
		$date = get_post_meta( $post->ID, '_camppayments_' . $name, true );

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

	/**
	 * Render a <input type="radio"> field with the given attributes.
	 *
	 * @param WP_Post $post
	 * @param string $label
	 * @param string $name
	 */
	protected function render_radio_input( $post, $label, $name ) {
		$selected = get_post_meta( $post->ID, '_camppayments_' . $name, true );
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
		$files = get_posts( array(
			'post_parent'    => $post->ID,
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		foreach ( $files as &$file ) {
			$file->filename = wp_basename( $file->guid );
			$file->url      = wp_get_attachment_url( $file->ID );
		}

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
				$value = $this->get_requester_formatted_email( $post->post_author );
				break;

			case 'date_vendor_paid':
			case 'due_by':
				if ( $value = get_post_meta( $post->ID, '_camppayments_' . $name, true ) ) {
					$value = date( 'Y-m-d', $value );
				}
				break;

			case 'currency':
				$value = $this->get_currencies();
				break;

			case 'payment_method':
				$value = array( 'Check', 'Credit Card', 'Wire' );
				break;

			default:
				$value = get_post_meta( $post->ID, '_camppayments_' . $name, true );
				break;
		}

		$encrypted_fields = array(
			'payable_to',
			'beneficiary_name',
			'beneficiary_account_number',
			'beneficiary_street_address',
			'beneficiary_city',
			'beneficiary_state',
			'beneficiary_zip_code',
			'beneficiary_country',
		);

		if ( in_array( $name, $encrypted_fields ) ) {
			$decrypted = WCP_Encryption::maybe_decrypt( $value );
			if ( ! is_wp_error( $decrypted ) )
				$value = $decrypted;
		}


		return $value;
	}

	/**
	 * Get the e-mail address of the requester in `Name <address>` format
	 *
	 * @param int $post_author_id
	 *
	 * @return false|string
	 */
	protected function get_requester_formatted_email( $post_author_id ) {
		$address   = false;
		$requester = get_user_by( 'id', $post_author_id );

		if ( is_a( $requester, 'WP_User' ) ) {
			$address = sprintf( '%s <%s>', $requester->get( 'display_name' ), $requester->get( 'user_email' ) );
		}

		return $address;
	}

	/**
	 * Get a list of all world currencies, with the most frequently used at the top.
	 *
	 * @return array
	 */
	protected function get_currencies() {
		$currencies = array (
			'null-most-frequently-used' => 'Most Frequently Used:',
			'USD' => 'United States Dollar',
			'EUR' => 'Euro Member Countries',

			'null-separator2' => '',

			'null-all' => 'All:',
			'ALL' => 'Albania Lek',
			'AFN' => 'Afghanistan Afghani',
			'ARS' => 'Argentina Peso',
			'AWG' => 'Aruba Guilder',
			'AUD' => 'Australia Dollar',
			'AZN' => 'Azerbaijan New Manat',
			'BSD' => 'Bahamas Dollar',
			'BBD' => 'Barbados Dollar',
			'BDT' => 'Bangladeshi taka',
			'BYR' => 'Belarus Ruble',
			'BZD' => 'Belize Dollar',
			'BMD' => 'Bermuda Dollar',
			'BOB' => 'Bolivia Boliviano',
			'BAM' => 'Bosnia and Herzegovina Convertible Marka',
			'BWP' => 'Botswana Pula',
			'BGN' => 'Bulgaria Lev',
			'BRL' => 'Brazil Real',
			'BND' => 'Brunei Darussalam Dollar',
			'KHR' => 'Cambodia Riel',
			'CAD' => 'Canada Dollar',
			'KYD' => 'Cayman Islands Dollar',
			'CLP' => 'Chile Peso',
			'CNY' => 'China Yuan Renminbi',
			'COP' => 'Colombia Peso',
			'CRC' => 'Costa Rica Colon',
			'HRK' => 'Croatia Kuna',
			'CUP' => 'Cuba Peso',
			'CZK' => 'Czech Republic Koruna',
			'DKK' => 'Denmark Krone',
			'DOP' => 'Dominican Republic Peso',
			'XCD' => 'East Caribbean Dollar',
			'EGP' => 'Egypt Pound',
			'SVC' => 'El Salvador Colon',
			'EEK' => 'Estonia Kroon',
			'FKP' => 'Falkland Islands (Malvinas) Pound',
			'FJD' => 'Fiji Dollar',
			'GHC' => 'Ghana Cedis',
			'GIP' => 'Gibraltar Pound',
			'GTQ' => 'Guatemala Quetzal',
			'GGP' => 'Guernsey Pound',
			'GYD' => 'Guyana Dollar',
			'HNL' => 'Honduras Lempira',
			'HKD' => 'Hong Kong Dollar',
			'HUF' => 'Hungary Forint',
			'ISK' => 'Iceland Krona',
			'INR' => 'India Rupee',
			'IDR' => 'Indonesia Rupiah',
			'IRR' => 'Iran Rial',
			'IMP' => 'Isle of Man Pound',
			'ILS' => 'Israel Shekel',
			'JMD' => 'Jamaica Dollar',
			'JPY' => 'Japan Yen',
			'JEP' => 'Jersey Pound',
			'KZT' => 'Kazakhstan Tenge',
			'KPW' => 'Korea (North) Won',
			'KRW' => 'Korea (South) Won',
			'KGS' => 'Kyrgyzstan Som',
			'LAK' => 'Laos Kip',
			'LVL' => 'Latvia Lat',
			'LBP' => 'Lebanon Pound',
			'LRD' => 'Liberia Dollar',
			'LTL' => 'Lithuania Litas',
			'MKD' => 'Macedonia Denar',
			'MYR' => 'Malaysia Ringgit',
			'MUR' => 'Mauritius Rupee',
			'MXN' => 'Mexico Peso',
			'MNT' => 'Mongolia Tughrik',
			'MZN' => 'Mozambique Metical',
			'NAD' => 'Namibia Dollar',
			'NPR' => 'Nepal Rupee',
			'ANG' => 'Netherlands Antilles Guilder',
			'NZD' => 'New Zealand Dollar',
			'NIO' => 'Nicaragua Cordoba',
			'NGN' => 'Nigeria Naira',
			'NOK' => 'Norway Krone',
			'OMR' => 'Oman Rial',
			'PKR' => 'Pakistan Rupee',
			'PAB' => 'Panama Balboa',
			'PYG' => 'Paraguay Guarani',
			'PEN' => 'Peru Nuevo Sol',
			'PHP' => 'Philippines Peso',
			'PLN' => 'Poland Zloty',
			'QAR' => 'Qatar Riyal',
			'RON' => 'Romania New Leu',
			'RUB' => 'Russia Ruble',
			'SHP' => 'Saint Helena Pound',
			'SAR' => 'Saudi Arabia Riyal',
			'RSD' => 'Serbia Dinar',
			'SCR' => 'Seychelles Rupee',
			'SGD' => 'Singapore Dollar',
			'SBD' => 'Solomon Islands Dollar',
			'SOS' => 'Somalia Shilling',
			'ZAR' => 'South Africa Rand',
			'LKR' => 'Sri Lanka Rupee',
			'SEK' => 'Sweden Krona',
			'CHF' => 'Switzerland Franc',
			'SRD' => 'Suriname Dollar',
			'SYP' => 'Syria Pound',
			'TWD' => 'Taiwan New Dollar',
			'THB' => 'Thailand Baht',
			'TTD' => 'Trinidad and Tobago Dollar',
			'TRY' => 'Turkey Lira',
			'TRL' => 'Turkey Lira',
			'TVD' => 'Tuvalu Dollar',
			'UAH' => 'Ukraine Hryvna',
			'GBP' => 'United Kingdom Pound',
			'UYU' => 'Uruguay Peso',
			'UZS' => 'Uzbekistan Som',
			'VEF' => 'Venezuela Bolivar',
			'VND' => 'Viet Nam Dong',
			'YER' => 'Yemen Rial',
			'ZWD' => 'Zimbabwe Dollar'
		);

		return $currencies;
	}

	/**
	 * Define the payment categories
	 *
	 * The slugs are explicitly registered in English so they will match across sites that use different locales,
	 * which facilitates aggregating the data into reports.
	 *
	 * This is public so that WordCamp Payments Network Dashboard can access it, in order to aggregate posts by their slug.
	 *
	 * @return array
	 */
	public static function get_payment_categories() {
		return array(
			'after-party'     => __( 'After Party',                    'wordcamporg' ),
			'audio-visual'    => __( 'Audio Visual',                   'wordcamporg' ),
			'food-beverages'  => __( 'Food & Beverage',                'wordcamporg' ),
			'office-supplies' => __( 'Office Supplies',                'wordcamporg' ),
			'signage-badges'  => __( 'Signage & Badges',               'wordcamporg' ),
			'speaker-event'   => __( 'Speaker Event',                  'wordcamporg' ),
			'swag'            => __( 'Swag (t-shirts, stickers, etc)', 'wordcamporg' ),
			'venue'           => __( 'Venue',                          'wordcamporg' ),
			'other'           => __( 'Other',                          'wordcamporg' ), // This one is intentionally last, regardless of alphabetical order
		);
	}

	/**
	 * Display the status of a post after its title on the Payment Requests page
	 *
	 * @param array $states
	 *
	 * @return array
	 */
	function display_post_states( $states ) {
		global $post;

		if ( 'paid' == $post->post_status && 'paid' != get_query_var( 'post_status' ) ) {
			$states['paid'] = __( 'Paid', 'wordcamporg' );
		}

		if ( 'unpaid' == $post->post_status && 'unpaid' != get_query_var( 'post_status' ) ) {
			$states['unpaid'] = __( 'Unpaid', 'wordcamporg' );
		}

		if ( 'incomplete' == $post->post_status && 'incomplete' != get_query_var( 'post_status' ) ) {
			$states['incomplete'] = __( 'Incomplete', 'wordcamporg' );
		}

		return $states;
	}

	/**
	 * Determines whether we want to perform actions on the given post based on the current context.
	 *
	 * Examples of actions we might perform are saving the meta fields during the `save_post` hook, or send out an
	 * e-mail notification during the `transition_post_status` hook.
	 *
	 * This function is called by several other functions, each of which may require additional checks that are
	 * specific to their circumstances. This function only covers checks that are common to all of its callers.
	 *
	 * @param WP_Post | array $post
	 *
	 * @return bool
	 */
	protected function post_edit_is_actionable( $post ) {
		if ( is_array( $post ) ) {
			$post = (object) $post;
		}

		$is_actionable   = true;
		$ignored_actions = array( 'trash', 'untrash', 'restore', 'bulk_edit' ); // todo ignore bulk deletion too

		// Don't take action on other post types
		if ( ! $post || $post->post_type != self::POST_TYPE ) {
			$is_actionable = false;
		}

		// Don't take action if the user isn't allowed. The ID will be missing from new posts during `wp_insert_post_data`, though, so skip it then.
		if ( $is_actionable && isset( $post->ID ) && ! current_user_can( 'edit_post', $post->ID ) ) {
			$is_actionable = false;
		}

		// Don't take action while trashing the post, etc
		if ( $is_actionable && isset( $_GET['action'] ) && in_array( $_GET['action'], $ignored_actions ) ) {
			$is_actionable = false;
		}

		// Don't take action during autosaves
		if ( $is_actionable && ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) ) {
			$is_actionable = false;
		}

		return $is_actionable;
	}

	/**
	 * Set the request's status based on whether the vendor has been paid.
	 *
	 * @param array $post_data
	 * @param array $post_data_raw
	 * @return array
	 */
	public function update_request_status( $post_data, $post_data_raw ) {
		if ( $this->post_edit_is_actionable( $post_data ) ) {
			if ( $this->should_mark_request_incomplete() ) {
				$post_data['post_status'] = 'incomplete';
				$this->notify_requester_request_incomplete( $post_data_raw['ID'], $post_data, $post_data_raw );

				update_post_meta( $post_data_raw['ID'], '_wcp_incomplete_notes', sanitize_text_field( $post_data_raw['wcp_mark_incomplete_notes'] ) );
			} else {
				$previous_status          = $post_data['post_status'];
				$post_data['post_status'] = strtotime( sanitize_text_field( $_POST['date_vendor_paid'] ) ) ? 'paid' : 'unpaid';

				if ( 'paid' != $previous_status && 'paid' == $post_data['post_status'] ) {
					$this->notify_requester_payment_made( $post_data_raw['ID'], $post_data );
				}
			}
		}

		return $post_data;
	}

	/**
	 * Determine if the user wants to mark a payment request as incomplete, and if that is valid
	 *
	 * @return bool
	 */
	protected function should_mark_request_incomplete() {
		$mark_incomplete = false;

		if ( isset( $_POST['wcp_mark_incomplete_checkbox'] ) && 'on' == $_POST['wcp_mark_incomplete_checkbox'] && ! empty( $_POST['wcp_mark_incomplete_notes'] ) ) {
			if ( isset( $_POST['mark_incomplete_nonce'] ) && wp_verify_nonce( $_POST['mark_incomplete_nonce'], 'mark_incomplete' ) ) {
				if ( current_user_can( 'manage_network' ) ) {
					$mark_incomplete = true;
				}
			}
		}

		return $mark_incomplete;
	}

	/**
	 * Notify the payment requester that it has been marked as paid.
	 *
	 * @param int   $request_id
	 * @param array $post_data
	 */
	protected function notify_requester_payment_made( $request_id, $post_data ) {
		if ( ! $to = $this->get_requester_formatted_email( $post_data['post_author'] ) ) {
			return;
		}

		$subject = sprintf( '`%s` has been paid', $post_data['post_title'] );
		$headers = array( 'Reply-To: support@wordcamp.org' );

		$message = sprintf(
			"The request for `%s` has been marked as paid by WordCamp Central.

			You can view the request at:

			%s

			If you have any questions, please reply to let us know.",
			$post_data['post_title'],
			admin_url( sprintf( 'post.php?post=%s&action=edit', $request_id ) )
		);
		$message = str_replace( "\t", '', $message );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Notify the payment requester that it has been marked as paid.
	 *
	 * @param int   $request_id
	 * @param array $post_data
	 * @param array $post_data_raw
	 */
	protected function notify_requester_request_incomplete( $request_id, $post_data, $post_data_raw ) {
		if ( ! $to = $this->get_requester_formatted_email( $post_data['post_author'] ) ) {
			return;
		}

		$subject = sprintf( '`%s` is incomplete', $post_data['post_title'] );
		$headers = array( 'Reply-To: support@wordcamp.org' );

		$message = sprintf(
			"The request for `%s` has been marked as incomplete by WordCamp Central.

			The reason for this is: %s

			You can complete the request at:

			%s

			If you have any questions, please reply to let us know.",
			$post_data['post_title'],
			sanitize_text_field( stripslashes( $post_data_raw['wcp_mark_incomplete_notes'] ) ),
			admin_url( sprintf( 'post.php?post=%s&action=edit', $request_id ) )
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
		if ( ! $this->post_edit_is_actionable( $post ) ) {
			return;
		}

		// Verify nonces
		$nonces = array( 'status_nonce', 'general_info_nonce', 'payment_details_nonce', 'vendor_details_nonce', 'wcp_files_nonce' );    // todo add prefix to all of these

		foreach ( $nonces as $nonce ) {
			if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( $_POST[ $nonce ], str_replace( '_nonce', '', $nonce ) ) ) {
				return;
			}
		}

		// Sanitize and save the field values
		$this->sanitize_save_normal_fields( $post_id );
		$this->sanitize_save_misc_fields(   $post_id );
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
				case 'file_notes':
					$safe_value = wp_kses( $unsafe_value, wp_kses_allowed_html( 'strip' ) );
					break;

				case 'payment_amount':
					$safe_value = sanitize_text_field( $unsafe_value );
					$safe_value = preg_replace( '#[^\d.-]+#', '', $safe_value );
					$safe_value = round( floatval( $safe_value ), 2 );
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
				case 'bank_name':
				case 'bank_street_address':
				case 'bank_city':
				case 'bank_state':
				case 'bank_zip_code':
				case 'bank_country':
				case 'bank_bic':
				case 'beneficiary_account_number':
				case 'beneficiary_name':
				case 'beneficiary_street_address':
				case 'beneficiary_city':
				case 'beneficiary_state':
				case 'beneficiary_zip_code':
				case 'beneficiary_country':
				case 'payable_to':
				case 'vendor_contact_person':
				case 'other_category_explanation':
					$safe_value = sanitize_text_field( $unsafe_value );
					break;

				case 'payment_method':
					if ( in_array( $unsafe_value, $this->get_field_value( 'payment_method', null ) ) ) {
						$safe_value = $unsafe_value;
					} else {
						$safe_value = false;
					}
					break;

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
				$encrypted_fields = array(
					'payable_to',
					'beneficiary_name',
					'beneficiary_account_number',
					'beneficiary_street_address',
					'beneficiary_city',
					'beneficiary_state',
					'beneficiary_zip_code',
					'beneficiary_country',
				);

				if ( in_array( $key, $encrypted_fields ) ) {
					$encrypted_value = WCP_Encryption::encrypt( $safe_value );
					if ( ! is_wp_error( $encrypted_value ) )
						$safe_value = $encrypted_value;
				}

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
		// Status
		if ( current_user_can( 'manage_network' ) ) {
			$safe_value = strtotime( sanitize_text_field( $_POST['date_vendor_paid'] ) );
			update_post_meta( $post_id, '_camppayments_date_vendor_paid', $safe_value );
		}

		// Checkboxes
		$checkbox_fields = array( 'requesting_reimbursement' );
		foreach( $checkbox_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_camppayments_' . $field, $_POST[ $field ] );
			} else {
				delete_post_meta( $post_id, '_camppayments_' . $field );
			}
		}

		// Attach existing files
		$this->attach_existing_files( $post_id, $_POST );
	}

	public function transition_post_status( $new, $old, $post ) {
		if ( $post->post_type != self::POST_TYPE )
			return;

		$user = get_user_by( 'id', get_current_user_id() );
		if ( $new == 'auto-draft' )
			return;

		if ( $new == 'incomplete' && $old != 'incomplete' ) {
			$incomplete_text = get_post_meta( $post->ID, '_wcp_incomplete_notes', true );
			$incomplete_text = preg_replace( '#\.$#', '', $incomplete_text ); // trailing-undot-it.
			WordCamp_Budgets::log( $post->ID, sprintf( 'Marked as incomplete by %s: %s.', $user->display_name, $incomplete_text ), array(
				'user_id' => $user->ID,
				'action' => 'marked-incomplete',
				'reason' => 'maybe notes',
			) );
		} elseif ( $new == 'paid' && $old != 'paid' ) {
			WordCamp_Budgets::log( $post->ID, sprintf( 'Marked as paid by %s.', $user->display_name ), array(
				'user_id' => $user->ID,
				'action' => 'marked-paid',
			) );
		} elseif ( $old == 'auto-draft' && $new != 'auto-draft' ) {
			WordCamp_Budgets::log( $post->ID, sprintf( 'Request created by %s.', $user->display_name ), array(
				'user_id' => $user->ID,
				'action' => 'updated',
			) );
		} else {
			WordCamp_Budgets::log( $post->ID, sprintf( 'Request updated by %s.', $user->display_name ), array(
				'user_id' => $user->ID,
				'action' => 'updated',
			) );
		}
	}

	/**
	 * Attach unattached files to the payment request post
	 *
	 * Sometimes users will upload the files manually to the Media Library, instead of using the Add Files button,
	 * and we need to attach them to the request so that they show up in the metabox.
	 *
	 * @param int   $post_id
	 * @param array $request
	 */
	protected function attach_existing_files( $post_id, $request ) {
		if ( empty( $request['wcp_existing_files_to_attach'] ) ) {
			return;
		}

		if ( ! $files = json_decode( $request['wcp_existing_files_to_attach'] ) ) {
			return;
		}

		remove_action( 'save_post', array( $this, 'save_payment' ), 10 ); // avoid infinite recursion

		foreach( $files as $file_id ) {
			wp_update_post( array(
				'ID'          => $file_id,
				'post_parent' => $post_id,
			) );
		}

		add_action( 'save_post', array( $this, 'save_payment' ), 10, 2 );
	}

	/**
	 * Define columns for the Payment Requests screen.
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
	 * Render custom columns on the Payment Requests screen.
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
