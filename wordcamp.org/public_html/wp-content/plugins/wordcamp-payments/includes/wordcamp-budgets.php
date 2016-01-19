<?php

/*
 * Main class to provide functionality common to all other classes
 */
class WordCamp_Budgets {
	const VERSION = '0.1.3';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu',             array( $this, 'register_budgets_menu' )     );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_common_assets' ), 11 );
	}

	/**
	 * Register the Budgets menu
	 *
	 * This is just an empty page so that a top-level menu can be created to hold the various post types and pages.
	 *
	 * @todo This may no longer be needed once the Budgets post type and Overview pages are added
	 */
	public function register_budgets_menu() {
		add_menu_page(
			__( 'WordCamp Budget', 'wordcamporg' ),
			__( 'Budget',          'wordcamporg' ),
			'manage_options',
			'wordcamp-budget',
			'__return_empty_string',
			plugins_url( 'images/dollar-sign-icon.svg', dirname( __FILE__ ) ),
			30
		);
	}

	/**
	 * Enqueue scripts and stylesheets common to all modules
	 */
	public function enqueue_common_assets() {
		// todo setup grunt to concat/minify js

		wp_enqueue_script(
			'wordcamp-budgets',
			plugins_url( 'javascript/wordcamp-budgets.js', __DIR__ ),
			array( 'jquery', 'jquery-ui-datepicker', 'media-upload', 'media-views' ),
			self::VERSION,
			true
		);

		wp_register_script(
			'wcb-attached-files',
			plugins_url( 'javascript/attached-files.js', __DIR__ ),
			array( 'wordcamp-budgets', 'backbone', 'wp-util' ),
			WordCamp_Budgets::VERSION,
			true
		);

		wp_localize_script(
			'wordcamp-budgets',
			'wcbLocalizedStrings',		// todo merge into WordCampBudgets var
			array(
				'uploadModalTitle'  => __( 'Attach Supporting Documentation', 'wordcamporg' ),
				'uploadModalButton' => __( 'Attach Files', 'wordcamporg' ),
			)
		);

		// Let's still include our .css file even if these are unavailable.
		$soft_deps = array( 'jquery-ui', 'wp-datepicker-skins' );
		foreach ( $soft_deps as $key => $handle )
			if ( ! wp_style_is( $handle, 'registered' ) )
				unset( $soft_deps[ $key ] );

		// Enqueue it on every screen, because it styles the menu icon
		wp_enqueue_style(
			'wordcamp-budgets',
			plugins_url( 'css/wordcamp-budgets.css', __DIR__ ),
			$soft_deps,
			self::VERSION
		);
	}

	/**
	 * Get a list of all world currencies, with the most frequently used at the top.
	 *
	 * @return array
	 */
	public static function get_currencies() {
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
	 * Determines whether we want to perform actions on the given post based on the current context.
	 *
	 * Examples of actions we might perform are saving the meta fields during the `save_post` hook, or send out an
	 * e-mail notification during the `transition_post_status` hook.
	 *
	 * This function is called by several other functions, each of which may require additional checks that are
	 * specific to their circumstances. This function only covers checks that are common to all of its callers.
	 *
	 * @param WP_Post|array $post
	 * @param string        $valid_post_type
	 *
	 * @return bool
	 */
	public static function post_edit_is_actionable( $post, $valid_post_type ) {
		if ( is_array( $post ) ) {
			$post = (object) $post;
		}

		$is_actionable   = true;
		$ignored_actions = array( 'trash', 'untrash', 'restore', 'bulk_edit' ); // todo ignore bulk deletion too

		// Don't take action on other post types
		if ( ! $post || $post->post_type != $valid_post_type ) {
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
	 * Get the e-mail address of the requester in `Name <address>` format
	 *
	 * @param int $post_author_id
	 *
	 * @return false|string
	 */
	public static function get_requester_formatted_email( $post_author_id ) {
		$address   = false;
		$requester = get_user_by( 'id', $post_author_id );

		if ( is_a( $requester, 'WP_User' ) ) {
			$address = sprintf( '%s <%s>', $requester->get( 'display_name' ), $requester->get( 'user_email' ) );
		}

		return $address;
	}

	/**
	 * Insert an entry into a log for one of the custom post types
	 *
	 * @param int    $post_id The post ID.
	 * @param string $message A log message.
	 * @param array  $data    Optional data.
	 */
	public static function log( $post_id, $message, $data = array() ) {
		global $wpdb;

		$entry = array(
			'timestamp' => time(),
			'message' => $message,
			'data' => $data,
		);

		$log = get_post_meta( $post_id, '_wcp_log', true );
		if ( empty( $log ) )
			$log = '[]';

		$log = json_decode( $log, true );
		$log[] = $entry;
		$log = json_encode( $log );

		update_post_meta( $post_id, '_wcp_log', wp_slash( $log ) );
	}
}
