<?php

/*
 * Main class to provide functionality common to all other classes
 */
class WordCamp_Budgets {
	const VERSION                       = '0.1.4';
	const PAYMENT_INFO_RETENTION_PERIOD = 7; // days

	const VIEWER_CAP = 'publish_posts';
	const ADMIN_CAP  = 'manage_options';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',                   array( __CLASS__, 'register_post_statuses' )               );
		add_action( 'admin_menu',             array( $this, 'register_budgets_menu' )                    );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_common_assets' ),             11    );
		add_filter( 'user_has_cap',           array( __CLASS__, 'user_can_view_payment_details' ), 10, 4 );
		add_filter( 'default_title',          array( $this, 'set_default_payments_title' ),        10, 2 );
	}

	/**
	 * Register all post statuses used by any CPTs.
	 */
	public static function register_post_statuses() {
		// Uses core's draft status too.

		register_post_status( 'wcb-incomplete', array(
			'label'       => esc_html_x( 'Incomplete', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Incomplete <span class="count">(%s)</span>',
				'Incomplete <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-pending-approval', array(
			'label'       => esc_html_x( 'Pending Approval', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Pending Approval <span class="count">(%s)</span>',
				'Pending Approval <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-approved', array(
			'label'       => esc_html_x( 'Approved', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-pending-payment', array(
			'label'       => esc_html_x( 'Payment Sent', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Payment Sent <span class="count">(%s)</span>',
				'Payment Sent <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-paid', array(
			'label'       => esc_html_x( 'Paid', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Paid <span class="count">(%s)</span>',
				'Paid <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-failed', array(
			'label'       => esc_html_x( 'Failed', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Failed <span class="count">(%s)</span>',
				'Failed <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );

		register_post_status( 'wcb-cancelled', array(
			'label'       => esc_html_x( 'Cancelled', 'payment request', 'wordcamporg' ),
			'public'      => false,
			'protected'   => true,
			'label_count' => _nx_noop(
				'Cancelled <span class="count">(%s)</span>',
				'Cancelled <span class="count">(%s)</span>',
				'wordcamporg'
			),
		) );
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
			esc_html__( 'WordCamp Budget', 'wordcamporg' ),
			esc_html__( 'Budget',          'wordcamporg' ),
			self::VIEWER_CAP,
			'wordcamp-budget',
			function() {
				do_action( 'wcb_render_budget_page' );
			},
			plugins_url( 'images/dollar-sign-icon.svg', dirname( __FILE__ ) ),
			30
		);
	}

	/**
	 * Set default post title for reimbursements, vendor payments and sponsor invoices.
	 *
	 * @param  string  $post_title Default post title.
	 * @param  WP_Post $post       Current post object.
	 *
	 * @return string $post_title Post title.
	 */
	public function set_default_payments_title( $post_title, $post ) {
		if ( $post instanceof WP_Post && ! empty( $post->post_type ) ) {
			$new_title = '';

			// Generate default title for payment CPTs.
			switch ( $post->post_type ) {
				case 'wcb_reimbursement':
					$new_title = __( 'Reimbursement Request', 'wordcamporg' );
					break;
				case 'wcp_payment_request':
					$new_title = __( 'Vendor Payment', 'wordcamporg' );
					break;
				case 'wcb_sponsor_invoice':
					$new_title = __( 'Sponsor Invoice', 'wordcamporg' );
					break;
			}

			// Prepend title with post ID to make it unique.
			if ( $new_title ) {
				$post_title = sprintf( __( '[%1$s] Untitled %2$s', 'wordcamporg' ), $post->ID, $new_title );
			}
		}

		return $post_title;
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
			3,
			true
		);

		wp_register_script(
			'wcb-attached-files',
			plugins_url( 'javascript/attached-files.js', __DIR__ ),
			array( 'wordcamp-budgets', 'backbone', 'wp-util' ),
			1,
			true
		);

		wp_localize_script(
			'wordcamp-budgets',
			'wcbLocalizedStrings',      // todo merge into WordCampBudgets var
			array(
				'uploadModalTitle'  => esc_html__( 'Attach Supporting Documentation', 'wordcamporg' ),
				'uploadModalButton' => esc_html__( 'Attach Files', 'wordcamporg' ),
			)
		);

		// Let's still include our .css file even if these are unavailable.
		$soft_deps = array( 'jquery-ui', 'wp-datepicker-skins' );
		foreach ( $soft_deps as $key => $handle ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				unset( $soft_deps[ $key ] );
			}
		}

		// Enqueue it on every screen, because it styles the menu icon
		wp_enqueue_style(
			'wordcamp-budgets',
			plugins_url( 'css/wordcamp-budgets.css', __DIR__ ),
			$soft_deps,
			7
		);
	}

	/**
	 * Validate an amount value
	 *
	 * @param string $amount
	 *
	 * @return float
	 */
	public static function validate_amount( $amount ) {
		$amount = sanitize_text_field( $amount );
		$amount = preg_replace( '#[^\d.-]+#', '', $amount );
		$amount = round( floatval( $amount ), 2 );

		return $amount;
	}

	/**
	 * Return an array of countries where the key is an ISO 3166 code
	 * and the value is a label.
	 *
	 * @see https://gist.github.com/vxnick/380904
	 *
	 * @return array
	 */
	public static function get_valid_countries_iso3166() {
		$countries = array(
			'AF' => array(
				'alpha2'    => 'AF',
				'alpha3'    => 'AFG',
				'num'       => '004',
				'isd'       => '93',
				'name'      => 'Afghanistan',
				'continent' => 'Asia',
			),
			'AX' => array(
				'alpha2'    => 'AX',
				'alpha3'    => 'ALA',
				'num'       => '248',
				'isd'       => '358',
				'name'      => 'Åland Islands',
				'continent' => 'Europe',
			),
			'AL' => array(
				'alpha2'    => 'AL',
				'alpha3'    => 'ALB',
				'num'       => '008',
				'isd'       => '355',
				'name'      => 'Albania',
				'continent' => 'Europe',
			),
			'DZ' => array(
				'alpha2'    => 'DZ',
				'alpha3'    => 'DZA',
				'num'       => '012',
				'isd'       => '213',
				'name'      => 'Algeria',
				'continent' => 'Africa',
			),
			'AS' => array(
				'alpha2'    => 'AS',
				'alpha3'    => 'ASM',
				'num'       => '016',
				'isd'       => '1684',
				'name'      => 'American Samoa',
				'continent' => 'Oceania',
			),
			'AD' => array(
				'alpha2'    => 'AD',
				'alpha3'    => 'AND',
				'num'       => '020',
				'isd'       => '376',
				'name'      => 'Andorra',
				'continent' => 'Europe',
			),
			'AO' => array(
				'alpha2'    => 'AO',
				'alpha3'    => 'AGO',
				'num'       => '024',
				'isd'       => '244',
				'name'      => 'Angola',
				'continent' => 'Africa',
			),
			'AI' => array(
				'alpha2'    => 'AI',
				'alpha3'    => 'AIA',
				'num'       => '660',
				'isd'       => '1264',
				'name'      => 'Anguilla',
				'continent' => 'North America',
			),
			'AQ' => array(
				'alpha2'    => 'AQ',
				'alpha3'    => 'ATA',
				'num'       => '010',
				'isd'       => '672',
				'name'      => 'Antarctica',
				'continent' => 'Antarctica',
			),
			'AG' => array(
				'alpha2'    => 'AG',
				'alpha3'    => 'ATG',
				'num'       => '028',
				'isd'       => '1268',
				'name'      => 'Antigua and Barbuda',
				'continent' => 'North America',
			),
			'AR' => array(
				'alpha2'    => 'AR',
				'alpha3'    => 'ARG',
				'num'       => '032',
				'isd'       => '54',
				'name'      => 'Argentina',
				'continent' => 'South America',
			),
			'AM' => array(
				'alpha2'    => 'AM',
				'alpha3'    => 'ARM',
				'num'       => '051',
				'isd'       => '374',
				'name'      => 'Armenia',
				'continent' => 'Asia',
			),
			'AW' => array(
				'alpha2'    => 'AW',
				'alpha3'    => 'ABW',
				'num'       => '533',
				'isd'       => '297',
				'name'      => 'Aruba',
				'continent' => 'North America',
			),
			'AU' => array(
				'alpha2'    => 'AU',
				'alpha3'    => 'AUS',
				'num'       => '036',
				'isd'       => '61',
				'name'      => 'Australia',
				'continent' => 'Oceania',
			),
			'AT' => array(
				'alpha2'    => 'AT',
				'alpha3'    => 'AUT',
				'num'       => '040',
				'isd'       => '43',
				'name'      => 'Austria',
				'continent' => 'Europe',
			),
			'AZ' => array(
				'alpha2'    => 'AZ',
				'alpha3'    => 'AZE',
				'num'       => '031',
				'isd'       => '994',
				'name'      => 'Azerbaijan',
				'continent' => 'Asia',
			),
			'BS' => array(
				'alpha2'    => 'BS',
				'alpha3'    => 'BHS',
				'num'       => '044',
				'isd'       => '1242',
				'name'      => 'Bahamas',
				'continent' => 'North America',
			),
			'BH' => array(
				'alpha2'    => 'BH',
				'alpha3'    => 'BHR',
				'num'       => '048',
				'isd'       => '973',
				'name'      => 'Bahrain',
				'continent' => 'Asia',
			),
			'BD' => array(
				'alpha2'    => 'BD',
				'alpha3'    => 'BGD',
				'num'       => '050',
				'isd'       => '880',
				'name'      => 'Bangladesh',
				'continent' => 'Asia',
			),
			'BB' => array(
				'alpha2'    => 'BB',
				'alpha3'    => 'BRB',
				'num'       => '052',
				'isd'       => '1246',
				'name'      => 'Barbados',
				'continent' => 'North America',
			),
			'BY' => array(
				'alpha2'    => 'BY',
				'alpha3'    => 'BLR',
				'num'       => '112',
				'isd'       => '375',
				'name'      => 'Belarus',
				'continent' => 'Europe',
			),
			'BE' => array(
				'alpha2'    => 'BE',
				'alpha3'    => 'BEL',
				'num'       => '056',
				'isd'       => '32',
				'name'      => 'Belgium',
				'continent' => 'Europe',
			),
			'BZ' => array(
				'alpha2'    => 'BZ',
				'alpha3'    => 'BLZ',
				'num'       => '084',
				'isd'       => '501',
				'name'      => 'Belize',
				'continent' => 'North America',
			),
			'BJ' => array(
				'alpha2'    => 'BJ',
				'alpha3'    => 'BEN',
				'num'       => '204',
				'isd'       => '229',
				'name'      => 'Benin',
				'continent' => 'Africa',
			),
			'BM' => array(
				'alpha2'    => 'BM',
				'alpha3'    => 'BMU',
				'num'       => '060',
				'isd'       => '1441',
				'name'      => 'Bermuda',
				'continent' => 'North America',
			),
			'BT' => array(
				'alpha2'    => 'BT',
				'alpha3'    => 'BTN',
				'num'       => '064',
				'isd'       => '975',
				'name'      => 'Bhutan',
				'continent' => 'Asia',
			),
			'BO' => array(
				'alpha2'    => 'BO',
				'alpha3'    => 'BOL',
				'num'       => '068',
				'isd'       => '591',
				'name'      => 'Bolivia',
				'continent' => 'South America',
			),
			'BA' => array(
				'alpha2'    => 'BA',
				'alpha3'    => 'BIH',
				'num'       => '070',
				'isd'       => '387',
				'name'      => 'Bosnia and Herzegovina',
				'continent' => 'Europe',
			),
			'BW' => array(
				'alpha2'    => 'BW',
				'alpha3'    => 'BWA',
				'num'       => '072',
				'isd'       => '267',
				'name'      => 'Botswana',
				'continent' => 'Africa',
			),
			'BV' => array(
				'alpha2'    => 'BV',
				'alpha3'    => 'BVT',
				'num'       => '074',
				'isd'       => '61',
				'name'      => 'Bouvet Island',
				'continent' => 'Antarctica',
			),
			'BR' => array(
				'alpha2'    => 'BR',
				'alpha3'    => 'BRA',
				'num'       => '076',
				'isd'       => '55',
				'name'      => 'Brazil',
				'continent' => 'South America',
			),
			'IO' => array(
				'alpha2'    => 'IO',
				'alpha3'    => 'IOT',
				'num'       => '086',
				'isd'       => '246',
				'name'      => 'British Indian Ocean Territory',
				'continent' => 'Asia',
			),
			'BN' => array(
				'alpha2'    => 'BN',
				'alpha3'    => 'BRN',
				'num'       => '096',
				'isd'       => '672',
				'name'      => 'Brunei Darussalam',
				'continent' => 'Asia',
			),
			'BG' => array(
				'alpha2'    => 'BG',
				'alpha3'    => 'BGR',
				'num'       => '100',
				'isd'       => '359',
				'name'      => 'Bulgaria',
				'continent' => 'Europe',
			),
			'BF' => array(
				'alpha2'    => 'BF',
				'alpha3'    => 'BFA',
				'num'       => '854',
				'isd'       => '226',
				'name'      => 'Burkina Faso',
				'continent' => 'Africa',
			),
			'BI' => array(
				'alpha2'    => 'BI',
				'alpha3'    => 'BDI',
				'num'       => '108',
				'isd'       => '257',
				'name'      => 'Burundi',
				'continent' => 'Africa',
			),
			'KH' => array(
				'alpha2'    => 'KH',
				'alpha3'    => 'KHM',
				'num'       => '116',
				'isd'       => '855',
				'name'      => 'Cambodia',
				'continent' => 'Asia',
			),
			'CM' => array(
				'alpha2'    => 'CM',
				'alpha3'    => 'CMR',
				'num'       => '120',
				'isd'       => '231',
				'name'      => 'Cameroon',
				'continent' => 'Africa',
			),
			'CA' => array(
				'alpha2'    => 'CA',
				'alpha3'    => 'CAN',
				'num'       => '124',
				'isd'       => '1',
				'name'      => 'Canada',
				'continent' => 'North America',
			),
			'CV' => array(
				'alpha2'    => 'CV',
				'alpha3'    => 'CPV',
				'num'       => '132',
				'isd'       => '238',
				'name'      => 'Cape Verde',
				'continent' => 'Africa',
			),
			'KY' => array(
				'alpha2'    => 'KY',
				'alpha3'    => 'CYM',
				'num'       => '136',
				'isd'       => '1345',
				'name'      => 'Cayman Islands',
				'continent' => 'North America',
			),
			'CF' => array(
				'alpha2'    => 'CF',
				'alpha3'    => 'CAF',
				'num'       => '140',
				'isd'       => '236',
				'name'      => 'Central African Republic',
				'continent' => 'Africa',
			),
			'TD' => array(
				'alpha2'    => 'TD',
				'alpha3'    => 'TCD',
				'num'       => '148',
				'isd'       => '235',
				'name'      => 'Chad',
				'continent' => 'Africa',
			),
			'CL' => array(
				'alpha2'    => 'CL',
				'alpha3'    => 'CHL',
				'num'       => '152',
				'isd'       => '56',
				'name'      => 'Chile',
				'continent' => 'South America',
			),
			'CN' => array(
				'alpha2'    => 'CN',
				'alpha3'    => 'CHN',
				'num'       => '156',
				'isd'       => '86',
				'name'      => 'China',
				'continent' => 'Asia',
			),
			'CX' => array(
				'alpha2'    => 'CX',
				'alpha3'    => 'CXR',
				'num'       => '162',
				'isd'       => '61',
				'name'      => 'Christmas Island',
				'continent' => 'Asia',
			),
			'CC' => array(
				'alpha2'    => 'CC',
				'alpha3'    => 'CCK',
				'num'       => '166',
				'isd'       => '891',
				'name'      => 'Cocos (Keeling) Islands',
				'continent' => 'Asia',
			),
			'CO' => array(
				'alpha2'    => 'CO',
				'alpha3'    => 'COL',
				'num'       => '170',
				'isd'       => '57',
				'name'      => 'Colombia',
				'continent' => 'South America',
			),
			'KM' => array(
				'alpha2'    => 'KM',
				'alpha3'    => 'COM',
				'num'       => '174',
				'isd'       => '269',
				'name'      => 'Comoros',
				'continent' => 'Africa',
			),
			'CG' => array(
				'alpha2'    => 'CG',
				'alpha3'    => 'COG',
				'num'       => '178',
				'isd'       => '242',
				'name'      => 'Congo',
				'continent' => 'Africa',
			),
			'CD' => array(
				'alpha2'    => 'CD',
				'alpha3'    => 'COD',
				'num'       => '180',
				'isd'       => '243',
				'name'      => 'The Democratic Republic of The Congo',
				'continent' => 'Africa',
			),
			'CK' => array(
				'alpha2'    => 'CK',
				'alpha3'    => 'COK',
				'num'       => '184',
				'isd'       => '682',
				'name'      => 'Cook Islands',
				'continent' => 'Oceania',
			),
			'CR' => array(
				'alpha2'    => 'CR',
				'alpha3'    => 'CRI',
				'num'       => '188',
				'isd'       => '506',
				'name'      => 'Costa Rica',
				'continent' => 'North America',
			),
			'CI' => array(
				'alpha2'    => 'CI',
				'alpha3'    => 'CIV',
				'num'       => '384',
				'isd'       => '225',
				'name'      => 'Cote D\'ivoire',
				'continent' => 'Africa',
			),
			'HR' => array(
				'alpha2'    => 'HR',
				'alpha3'    => 'HRV',
				'num'       => '191',
				'isd'       => '385',
				'name'      => 'Croatia',
				'continent' => 'Europe',
			),
			'CU' => array(
				'alpha2'    => 'CU',
				'alpha3'    => 'CUB',
				'num'       => '192',
				'isd'       => '53',
				'name'      => 'Cuba',
				'continent' => 'North America',
			),
			'CY' => array(
				'alpha2'    => 'CY',
				'alpha3'    => 'CYP',
				'num'       => '196',
				'isd'       => '357',
				'name'      => 'Cyprus',
				'continent' => 'Asia',
			),
			'CZ' => array(
				'alpha2'    => 'CZ',
				'alpha3'    => 'CZE',
				'num'       => '203',
				'isd'       => '420',
				'name'      => 'Czech Republic',
				'continent' => 'Europe',
			),
			'DK' => array(
				'alpha2'    => 'DK',
				'alpha3'    => 'DNK',
				'num'       => '208',
				'isd'       => '45',
				'name'      => 'Denmark',
				'continent' => 'Europe',
			),
			'DJ' => array(
				'alpha2'    => 'DJ',
				'alpha3'    => 'DJI',
				'num'       => '262',
				'isd'       => '253',
				'name'      => 'Djibouti',
				'continent' => 'Africa',
			),
			'DM' => array(
				'alpha2'    => 'DM',
				'alpha3'    => 'DMA',
				'num'       => '212',
				'isd'       => '1767',
				'name'      => 'Dominica',
				'continent' => 'North America',
			),
			'DO' => array(
				'alpha2'    => 'DO',
				'alpha3'    => 'DOM',
				'num'       => '214',
				'isd'       => '1809',
				'name'      => 'Dominican Republic',
				'continent' => 'North America',
			),
			'EC' => array(
				'alpha2'    => 'EC',
				'alpha3'    => 'ECU',
				'num'       => '218',
				'isd'       => '593',
				'name'      => 'Ecuador',
				'continent' => 'South America',
			),
			'EG' => array(
				'alpha2'    => 'EG',
				'alpha3'    => 'EGY',
				'num'       => '818',
				'isd'       => '20',
				'name'      => 'Egypt',
				'continent' => 'Africa',
			),
			'SV' => array(
				'alpha2'    => 'SV',
				'alpha3'    => 'SLV',
				'num'       => '222',
				'isd'       => '503',
				'name'      => 'El Salvador',
				'continent' => 'North America',
			),
			'GQ' => array(
				'alpha2'    => 'GQ',
				'alpha3'    => 'GNQ',
				'num'       => '226',
				'isd'       => '240',
				'name'      => 'Equatorial Guinea',
				'continent' => 'Africa',
			),
			'ER' => array(
				'alpha2'    => 'ER',
				'alpha3'    => 'ERI',
				'num'       => '232',
				'isd'       => '291',
				'name'      => 'Eritrea',
				'continent' => 'Africa',
			),
			'EE' => array(
				'alpha2'    => 'EE',
				'alpha3'    => 'EST',
				'num'       => '233',
				'isd'       => '372',
				'name'      => 'Estonia',
				'continent' => 'Europe',
			),
			'ET' => array(
				'alpha2'    => 'ET',
				'alpha3'    => 'ETH',
				'num'       => '231',
				'isd'       => '251',
				'name'      => 'Ethiopia',
				'continent' => 'Africa',
			),
			'FK' => array(
				'alpha2'    => 'FK',
				'alpha3'    => 'FLK',
				'num'       => '238',
				'isd'       => '500',
				'name'      => 'Falkland Islands (Malvinas)',
				'continent' => 'South America',
			),
			'FO' => array(
				'alpha2'    => 'FO',
				'alpha3'    => 'FRO',
				'num'       => '234',
				'isd'       => '298',
				'name'      => 'Faroe Islands',
				'continent' => 'Europe',
			),
			'FJ' => array(
				'alpha2'    => 'FJ',
				'alpha3'    => 'FJI',
				'num'       => '243',
				'isd'       => '679',
				'name'      => 'Fiji',
				'continent' => 'Oceania',
			),
			'FI' => array(
				'alpha2'    => 'FI',
				'alpha3'    => 'FIN',
				'num'       => '246',
				'isd'       => '238',
				'name'      => 'Finland',
				'continent' => 'Europe',
			),
			'FR' => array(
				'alpha2'    => 'FR',
				'alpha3'    => 'FRA',
				'num'       => '250',
				'isd'       => '33',
				'name'      => 'France',
				'continent' => 'Europe',
			),
			'GF' => array(
				'alpha2'    => 'GF',
				'alpha3'    => 'GUF',
				'num'       => '254',
				'isd'       => '594',
				'name'      => 'French Guiana',
				'continent' => 'South America',
			),
			'PF' => array(
				'alpha2'    => 'PF',
				'alpha3'    => 'PYF',
				'num'       => '258',
				'isd'       => '689',
				'name'      => 'French Polynesia',
				'continent' => 'Oceania',
			),
			'TF' => array(
				'alpha2'    => 'TF',
				'alpha3'    => 'ATF',
				'num'       => '260',
				'isd'       => '262',
				'name'      => 'French Southern Territories',
				'continent' => 'Antarctica',
			),
			'GA' => array(
				'alpha2'    => 'GA',
				'alpha3'    => 'GAB',
				'num'       => '266',
				'isd'       => '241',
				'name'      => 'Gabon',
				'continent' => 'Africa',
			),
			'GM' => array(
				'alpha2'    => 'GM',
				'alpha3'    => 'GMB',
				'num'       => '270',
				'isd'       => '220',
				'name'      => 'Gambia',
				'continent' => 'Africa',
			),
			'GE' => array(
				'alpha2'    => 'GE',
				'alpha3'    => 'GEO',
				'num'       => '268',
				'isd'       => '995',
				'name'      => 'Georgia',
				'continent' => 'Asia',
			),
			'DE' => array(
				'alpha2'    => 'DE',
				'alpha3'    => 'DEU',
				'num'       => '276',
				'isd'       => '49',
				'name'      => 'Germany',
				'continent' => 'Europe',
			),
			'GH' => array(
				'alpha2'    => 'GH',
				'alpha3'    => 'GHA',
				'num'       => '288',
				'isd'       => '233',
				'name'      => 'Ghana',
				'continent' => 'Africa',
			),
			'GI' => array(
				'alpha2'    => 'GI',
				'alpha3'    => 'GIB',
				'num'       => '292',
				'isd'       => '350',
				'name'      => 'Gibraltar',
				'continent' => 'Europe',
			),
			'GR' => array(
				'alpha2'    => 'GR',
				'alpha3'    => 'GRC',
				'num'       => '300',
				'isd'       => '30',
				'name'      => 'Greece',
				'continent' => 'Europe',
			),
			'GL' => array(
				'alpha2'    => 'GL',
				'alpha3'    => 'GRL',
				'num'       => '304',
				'isd'       => '299',
				'name'      => 'Greenland',
				'continent' => 'North America',
			),
			'GD' => array(
				'alpha2'    => 'GD',
				'alpha3'    => 'GRD',
				'num'       => '308',
				'isd'       => '1473',
				'name'      => 'Grenada',
				'continent' => 'North America',
			),
			'GP' => array(
				'alpha2'    => 'GP',
				'alpha3'    => 'GLP',
				'num'       => '312',
				'isd'       => '590',
				'name'      => 'Guadeloupe',
				'continent' => 'North America',
			),
			'GU' => array(
				'alpha2'    => 'GU',
				'alpha3'    => 'GUM',
				'num'       => '316',
				'isd'       => '1871',
				'name'      => 'Guam',
				'continent' => 'Oceania',
			),
			'GT' => array(
				'alpha2'    => 'GT',
				'alpha3'    => 'GTM',
				'num'       => '320',
				'isd'       => '502',
				'name'      => 'Guatemala',
				'continent' => 'North America',
			),
			'GG' => array(
				'alpha2'    => 'GG',
				'alpha3'    => 'GGY',
				'num'       => '831',
				'isd'       => '44',
				'name'      => 'Guernsey',
				'continent' => 'Europe',
			),
			'GN' => array(
				'alpha2'    => 'GN',
				'alpha3'    => 'GIN',
				'num'       => '324',
				'isd'       => '224',
				'name'      => 'Guinea',
				'continent' => 'Africa',
			),
			'GW' => array(
				'alpha2'    => 'GW',
				'alpha3'    => 'GNB',
				'num'       => '624',
				'isd'       => '245',
				'name'      => 'Guinea-bissau',
				'continent' => 'Africa',
			),
			'GY' => array(
				'alpha2'    => 'GY',
				'alpha3'    => 'GUY',
				'num'       => '328',
				'isd'       => '592',
				'name'      => 'Guyana',
				'continent' => 'South America',
			),
			'HT' => array(
				'alpha2'    => 'HT',
				'alpha3'    => 'HTI',
				'num'       => '332',
				'isd'       => '509',
				'name'      => 'Haiti',
				'continent' => 'North America',
			),
			'HM' => array(
				'alpha2'    => 'HM',
				'alpha3'    => 'HMD',
				'num'       => '334',
				'isd'       => '672',
				'name'      => 'Heard Island and Mcdonald Islands',
				'continent' => 'Antarctica',
			),
			'VA' => array(
				'alpha2'    => 'VA',
				'alpha3'    => 'VAT',
				'num'       => '336',
				'isd'       => '379',
				'name'      => 'Holy See (Vatican City State)',
				'continent' => 'Europe',
			),
			'HN' => array(
				'alpha2'    => 'HN',
				'alpha3'    => 'HND',
				'num'       => '340',
				'isd'       => '504',
				'name'      => 'Honduras',
				'continent' => 'North America',
			),
			'HK' => array(
				'alpha2'    => 'HK',
				'alpha3'    => 'HKG',
				'num'       => '344',
				'isd'       => '852',
				'name'      => 'Hong Kong',
				'continent' => 'Asia',
			),
			'HU' => array(
				'alpha2'    => 'HU',
				'alpha3'    => 'HUN',
				'num'       => '348',
				'isd'       => '36',
				'name'      => 'Hungary',
				'continent' => 'Europe',
			),
			'IS' => array(
				'alpha2'    => 'IS',
				'alpha3'    => 'ISL',
				'num'       => '352',
				'isd'       => '354',
				'name'      => 'Iceland',
				'continent' => 'Europe',
			),
			'IN' => array(
				'alpha2'    => 'IN',
				'alpha3'    => 'IND',
				'num'       => '356',
				'isd'       => '91',
				'name'      => 'India',
				'continent' => 'Asia',
			),
			'ID' => array(
				'alpha2'    => 'ID',
				'alpha3'    => 'IDN',
				'num'       => '360',
				'isd'       => '62',
				'name'      => 'Indonesia',
				'continent' => 'Asia',
			),
			'IR' => array(
				'alpha2'    => 'IR',
				'alpha3'    => 'IRN',
				'num'       => '364',
				'isd'       => '98',
				'name'      => 'Iran',
				'continent' => 'Asia',
			),
			'IQ' => array(
				'alpha2'    => 'IQ',
				'alpha3'    => 'IRQ',
				'num'       => '368',
				'isd'       => '964',
				'name'      => 'Iraq',
				'continent' => 'Asia',
			),
			'IE' => array(
				'alpha2'    => 'IE',
				'alpha3'    => 'IRL',
				'num'       => '372',
				'isd'       => '353',
				'name'      => 'Ireland',
				'continent' => 'Europe',
			),
			'IM' => array(
				'alpha2'    => 'IM',
				'alpha3'    => 'IMN',
				'num'       => '833',
				'isd'       => '44',
				'name'      => 'Isle of Man',
				'continent' => 'Europe',
			),
			'IL' => array(
				'alpha2'    => 'IL',
				'alpha3'    => 'ISR',
				'num'       => '376',
				'isd'       => '972',
				'name'      => 'Israel',
				'continent' => 'Asia',
			),
			'IT' => array(
				'alpha2'    => 'IT',
				'alpha3'    => 'ITA',
				'num'       => '380',
				'isd'       => '39',
				'name'      => 'Italy',
				'continent' => 'Europe',
			),
			'JM' => array(
				'alpha2'    => 'JM',
				'alpha3'    => 'JAM',
				'num'       => '388',
				'isd'       => '1876',
				'name'      => 'Jamaica',
				'continent' => 'North America',
			),
			'JP' => array(
				'alpha2'    => 'JP',
				'alpha3'    => 'JPN',
				'num'       => '392',
				'isd'       => '81',
				'name'      => 'Japan',
				'continent' => 'Asia',
			),
			'JE' => array(
				'alpha2'    => 'JE',
				'alpha3'    => 'JEY',
				'num'       => '832',
				'isd'       => '44',
				'name'      => 'Jersey',
				'continent' => 'Europe',
			),
			'JO' => array(
				'alpha2'    => 'JO',
				'alpha3'    => 'JOR',
				'num'       => '400',
				'isd'       => '962',
				'name'      => 'Jordan',
				'continent' => 'Asia',
			),
			'KZ' => array(
				'alpha2'    => 'KZ',
				'alpha3'    => 'KAZ',
				'num'       => '398',
				'isd'       => '7',
				'name'      => 'Kazakhstan',
				'continent' => 'Asia',
			),
			'KE' => array(
				'alpha2'    => 'KE',
				'alpha3'    => 'KEN',
				'num'       => '404',
				'isd'       => '254',
				'name'      => 'Kenya',
				'continent' => 'Africa',
			),
			'KI' => array(
				'alpha2'    => 'KI',
				'alpha3'    => 'KIR',
				'num'       => '296',
				'isd'       => '686',
				'name'      => 'Kiribati',
				'continent' => 'Oceania',
			),
			'KP' => array(
				'alpha2'    => 'KP',
				'alpha3'    => 'PRK',
				'num'       => '408',
				'isd'       => '850',
				'name'      => 'Democratic People\'s Republic of Korea',
				'continent' => 'Asia',
			),
			'KR' => array(
				'alpha2'    => 'KR',
				'alpha3'    => 'KOR',
				'num'       => '410',
				'isd'       => '82',
				'name'      => 'Republic of Korea',
				'continent' => 'Asia',
			),
			'KW' => array(
				'alpha2'    => 'KW',
				'alpha3'    => 'KWT',
				'num'       => '414',
				'isd'       => '965',
				'name'      => 'Kuwait',
				'continent' => 'Asia',
			),
			'KG' => array(
				'alpha2'    => 'KG',
				'alpha3'    => 'KGZ',
				'num'       => '417',
				'isd'       => '996',
				'name'      => 'Kyrgyzstan',
				'continent' => 'Asia',
			),
			'LA' => array(
				'alpha2'    => 'LA',
				'alpha3'    => 'LAO',
				'num'       => '418',
				'isd'       => '856',
				'name'      => 'Lao People\'s Democratic Republic',
				'continent' => 'Asia',
			),
			'LV' => array(
				'alpha2'    => 'LV',
				'alpha3'    => 'LVA',
				'num'       => '428',
				'isd'       => '371',
				'name'      => 'Latvia',
				'continent' => 'Europe',
			),
			'LB' => array(
				'alpha2'    => 'LB',
				'alpha3'    => 'LBN',
				'num'       => '422',
				'isd'       => '961',
				'name'      => 'Lebanon',
				'continent' => 'Asia',
			),
			'LS' => array(
				'alpha2'    => 'LS',
				'alpha3'    => 'LSO',
				'num'       => '426',
				'isd'       => '266',
				'name'      => 'Lesotho',
				'continent' => 'Africa',
			),
			'LR' => array(
				'alpha2'    => 'LR',
				'alpha3'    => 'LBR',
				'num'       => '430',
				'isd'       => '231',
				'name'      => 'Liberia',
				'continent' => 'Africa',
			),
			'LY' => array(
				'alpha2'    => 'LY',
				'alpha3'    => 'LBY',
				'num'       => '434',
				'isd'       => '218',
				'name'      => 'Libya',
				'continent' => 'Africa',
			),
			'LI' => array(
				'alpha2'    => 'LI',
				'alpha3'    => 'LIE',
				'num'       => '438',
				'isd'       => '423',
				'name'      => 'Liechtenstein',
				'continent' => 'Europe',
			),
			'LT' => array(
				'alpha2'    => 'LT',
				'alpha3'    => 'LTU',
				'num'       => '440',
				'isd'       => '370',
				'name'      => 'Lithuania',
				'continent' => 'Europe',
			),
			'LU' => array(
				'alpha2'    => 'LU',
				'alpha3'    => 'LUX',
				'num'       => '442',
				'isd'       => '352',
				'name'      => 'Luxembourg',
				'continent' => 'Europe',
			),
			'MO' => array(
				'alpha2'    => 'MO',
				'alpha3'    => 'MAC',
				'num'       => '446',
				'isd'       => '853',
				'name'      => 'Macao',
				'continent' => 'Asia',
			),
			'MK' => array(
				'alpha2'    => 'MK',
				'alpha3'    => 'MKD',
				'num'       => '807',
				'isd'       => '389',
				'name'      => 'Macedonia',
				'continent' => 'Europe',
			),
			'MG' => array(
				'alpha2'    => 'MG',
				'alpha3'    => 'MDG',
				'num'       => '450',
				'isd'       => '261',
				'name'      => 'Madagascar',
				'continent' => 'Africa',
			),
			'MW' => array(
				'alpha2'    => 'MW',
				'alpha3'    => 'MWI',
				'num'       => '454',
				'isd'       => '265',
				'name'      => 'Malawi',
				'continent' => 'Africa',
			),
			'MY' => array(
				'alpha2'    => 'MY',
				'alpha3'    => 'MYS',
				'num'       => '458',
				'isd'       => '60',
				'name'      => 'Malaysia',
				'continent' => 'Asia',
			),
			'MV' => array(
				'alpha2'    => 'MV',
				'alpha3'    => 'MDV',
				'num'       => '462',
				'isd'       => '960',
				'name'      => 'Maldives',
				'continent' => 'Asia',
			),
			'ML' => array(
				'alpha2'    => 'ML',
				'alpha3'    => 'MLI',
				'num'       => '466',
				'isd'       => '223',
				'name'      => 'Mali',
				'continent' => 'Africa',
			),
			'MT' => array(
				'alpha2'    => 'MT',
				'alpha3'    => 'MLT',
				'num'       => '470',
				'isd'       => '356',
				'name'      => 'Malta',
				'continent' => 'Europe',
			),
			'MH' => array(
				'alpha2'    => 'MH',
				'alpha3'    => 'MHL',
				'num'       => '584',
				'isd'       => '692',
				'name'      => 'Marshall Islands',
				'continent' => 'Oceania',
			),
			'MQ' => array(
				'alpha2'    => 'MQ',
				'alpha3'    => 'MTQ',
				'num'       => '474',
				'isd'       => '596',
				'name'      => 'Martinique',
				'continent' => 'North America',
			),
			'MR' => array(
				'alpha2'    => 'MR',
				'alpha3'    => 'MRT',
				'num'       => '478',
				'isd'       => '222',
				'name'      => 'Mauritania',
				'continent' => 'Africa',
			),
			'MU' => array(
				'alpha2'    => 'MU',
				'alpha3'    => 'MUS',
				'num'       => '480',
				'isd'       => '230',
				'name'      => 'Mauritius',
				'continent' => 'Africa',
			),
			'YT' => array(
				'alpha2'    => 'YT',
				'alpha3'    => 'MYT',
				'num'       => '175',
				'isd'       => '262',
				'name'      => 'Mayotte',
				'continent' => 'Africa',
			),
			'MX' => array(
				'alpha2'    => 'MX',
				'alpha3'    => 'MEX',
				'num'       => '484',
				'isd'       => '52',
				'name'      => 'Mexico',
				'continent' => 'North America',
			),
			'FM' => array(
				'alpha2'    => 'FM',
				'alpha3'    => 'FSM',
				'num'       => '583',
				'isd'       => '691',
				'name'      => 'Micronesia',
				'continent' => 'Oceania',
			),
			'MD' => array(
				'alpha2'    => 'MD',
				'alpha3'    => 'MDA',
				'num'       => '498',
				'isd'       => '373',
				'name'      => 'Moldova',
				'continent' => 'Europe',
			),
			'MC' => array(
				'alpha2'    => 'MC',
				'alpha3'    => 'MCO',
				'num'       => '492',
				'isd'       => '377',
				'name'      => 'Monaco',
				'continent' => 'Europe',
			),
			'MN' => array(
				'alpha2'    => 'MN',
				'alpha3'    => 'MNG',
				'num'       => '496',
				'isd'       => '976',
				'name'      => 'Mongolia',
				'continent' => 'Asia',
			),
			'ME' => array(
				'alpha2'    => 'ME',
				'alpha3'    => 'MNE',
				'num'       => '499',
				'isd'       => '382',
				'name'      => 'Montenegro',
				'continent' => 'Europe',
			),
			'MS' => array(
				'alpha2'    => 'MS',
				'alpha3'    => 'MSR',
				'num'       => '500',
				'isd'       => '1664',
				'name'      => 'Montserrat',
				'continent' => 'North America',
			),
			'MA' => array(
				'alpha2'    => 'MA',
				'alpha3'    => 'MAR',
				'num'       => '504',
				'isd'       => '212',
				'name'      => 'Morocco',
				'continent' => 'Africa',
			),
			'MZ' => array(
				'alpha2'    => 'MZ',
				'alpha3'    => 'MOZ',
				'num'       => '508',
				'isd'       => '258',
				'name'      => 'Mozambique',
				'continent' => 'Africa',
			),
			'MM' => array(
				'alpha2'    => 'MM',
				'alpha3'    => 'MMR',
				'num'       => '104',
				'isd'       => '95',
				'name'      => 'Myanmar',
				'continent' => 'Asia',
			),
			'NA' => array(
				'alpha2'    => 'NA',
				'alpha3'    => 'NAM',
				'num'       => '516',
				'isd'       => '264',
				'name'      => 'Namibia',
				'continent' => 'Africa',
			),
			'NR' => array(
				'alpha2'    => 'NR',
				'alpha3'    => 'NRU',
				'num'       => '520',
				'isd'       => '674',
				'name'      => 'Nauru',
				'continent' => 'Oceania',
			),
			'NP' => array(
				'alpha2'    => 'NP',
				'alpha3'    => 'NPL',
				'num'       => '524',
				'isd'       => '977',
				'name'      => 'Nepal',
				'continent' => 'Asia',
			),
			'NL' => array(
				'alpha2'    => 'NL',
				'alpha3'    => 'NLD',
				'num'       => '528',
				'isd'       => '31',
				'name'      => 'Netherlands',
				'continent' => 'Europe',
			),
			'AN' => array(
				'alpha2'    => 'AN',
				'alpha3'    => 'ANT',
				'num'       => '530',
				'isd'       => '599',
				'name'      => 'Netherlands Antilles',
				'continent' => 'North America',
			),
			'NC' => array(
				'alpha2'    => 'NC',
				'alpha3'    => 'NCL',
				'num'       => '540',
				'isd'       => '687',
				'name'      => 'New Caledonia',
				'continent' => 'Oceania',
			),
			'NZ' => array(
				'alpha2'    => 'NZ',
				'alpha3'    => 'NZL',
				'num'       => '554',
				'isd'       => '64',
				'name'      => 'New Zealand',
				'continent' => 'Oceania',
			),
			'NI' => array(
				'alpha2'    => 'NI',
				'alpha3'    => 'NIC',
				'num'       => '558',
				'isd'       => '505',
				'name'      => 'Nicaragua',
				'continent' => 'North America',
			),
			'NE' => array(
				'alpha2'    => 'NE',
				'alpha3'    => 'NER',
				'num'       => '562',
				'isd'       => '227',
				'name'      => 'Niger',
				'continent' => 'Africa',
			),
			'NG' => array(
				'alpha2'    => 'NG',
				'alpha3'    => 'NGA',
				'num'       => '566',
				'isd'       => '234',
				'name'      => 'Nigeria',
				'continent' => 'Africa',
			),
			'NU' => array(
				'alpha2'    => 'NU',
				'alpha3'    => 'NIU',
				'num'       => '570',
				'isd'       => '683',
				'name'      => 'Niue',
				'continent' => 'Oceania',
			),
			'NF' => array(
				'alpha2'    => 'NF',
				'alpha3'    => 'NFK',
				'num'       => '574',
				'isd'       => '672',
				'name'      => 'Norfolk Island',
				'continent' => 'Oceania',
			),
			'MP' => array(
				'alpha2'    => 'MP',
				'alpha3'    => 'MNP',
				'num'       => '580',
				'isd'       => '1670',
				'name'      => 'Northern Mariana Islands',
				'continent' => 'Oceania',
			),
			'NO' => array(
				'alpha2'    => 'NO',
				'alpha3'    => 'NOR',
				'num'       => '578',
				'isd'       => '47',
				'name'      => 'Norway',
				'continent' => 'Europe',
			),
			'OM' => array(
				'alpha2'    => 'OM',
				'alpha3'    => 'OMN',
				'num'       => '512',
				'isd'       => '968',
				'name'      => 'Oman',
				'continent' => 'Asia',
			),
			'PK' => array(
				'alpha2'    => 'PK',
				'alpha3'    => 'PAK',
				'num'       => '586',
				'isd'       => '92',
				'name'      => 'Pakistan',
				'continent' => 'Asia',
			),
			'PW' => array(
				'alpha2'    => 'PW',
				'alpha3'    => 'PLW',
				'num'       => '585',
				'isd'       => '680',
				'name'      => 'Palau',
				'continent' => 'Oceania',
			),
			'PS' => array(
				'alpha2'    => 'PS',
				'alpha3'    => 'PSE',
				'num'       => '275',
				'isd'       => '970',
				'name'      => 'Palestinia',
				'continent' => 'Asia',
			),
			'PA' => array(
				'alpha2'    => 'PA',
				'alpha3'    => 'PAN',
				'num'       => '591',
				'isd'       => '507',
				'name'      => 'Panama',
				'continent' => 'North America',
			),
			'PG' => array(
				'alpha2'    => 'PG',
				'alpha3'    => 'PNG',
				'num'       => '598',
				'isd'       => '675',
				'name'      => 'Papua New Guinea',
				'continent' => 'Oceania',
			),
			'PY' => array(
				'alpha2'    => 'PY',
				'alpha3'    => 'PRY',
				'num'       => '600',
				'isd'       => '595',
				'name'      => 'Paraguay',
				'continent' => 'South America',
			),
			'PE' => array(
				'alpha2'    => 'PE',
				'alpha3'    => 'PER',
				'num'       => '604',
				'isd'       => '51',
				'name'      => 'Peru',
				'continent' => 'South America',
			),
			'PH' => array(
				'alpha2'    => 'PH',
				'alpha3'    => 'PHL',
				'num'       => '608',
				'isd'       => '63',
				'name'      => 'Philippines',
				'continent' => 'Asia',
			),
			'PN' => array(
				'alpha2'    => 'PN',
				'alpha3'    => 'PCN',
				'num'       => '612',
				'isd'       => '870',
				'name'      => 'Pitcairn',
				'continent' => 'Oceania',
			),
			'PL' => array(
				'alpha2'    => 'PL',
				'alpha3'    => 'POL',
				'num'       => '616',
				'isd'       => '48',
				'name'      => 'Poland',
				'continent' => 'Europe',
			),
			'PT' => array(
				'alpha2'    => 'PT',
				'alpha3'    => 'PRT',
				'num'       => '620',
				'isd'       => '351',
				'name'      => 'Portugal',
				'continent' => 'Europe',
			),
			'PR' => array(
				'alpha2'    => 'PR',
				'alpha3'    => 'PRI',
				'num'       => '630',
				'isd'       => '1',
				'name'      => 'Puerto Rico',
				'continent' => 'North America',
			),
			'QA' => array(
				'alpha2'    => 'QA',
				'alpha3'    => 'QAT',
				'num'       => '634',
				'isd'       => '974',
				'name'      => 'Qatar',
				'continent' => 'Asia',
			),
			'RE' => array(
				'alpha2'    => 'RE',
				'alpha3'    => 'REU',
				'num'       => '638',
				'isd'       => '262',
				'name'      => 'Reunion',
				'continent' => 'Africa',
			),
			'RO' => array(
				'alpha2'    => 'RO',
				'alpha3'    => 'ROU',
				'num'       => '642',
				'isd'       => '40',
				'name'      => 'Romania',
				'continent' => 'Europe',
			),
			'RU' => array(
				'alpha2'    => 'RU',
				'alpha3'    => 'RUS',
				'num'       => '643',
				'isd'       => '7',
				'name'      => 'Russian Federation',
				'continent' => 'Europe',
			),
			'RW' => array(
				'alpha2'    => 'RW',
				'alpha3'    => 'RWA',
				'num'       => '646',
				'isd'       => '250',
				'name'      => 'Rwanda',
				'continent' => 'Africa',
			),
			'SH' => array(
				'alpha2'    => 'SH',
				'alpha3'    => 'SHN',
				'num'       => '654',
				'isd'       => '290',
				'name'      => 'Saint Helena',
				'continent' => 'Africa',
			),
			'KN' => array(
				'alpha2'    => 'KN',
				'alpha3'    => 'KNA',
				'num'       => '659',
				'isd'       => '1869',
				'name'      => 'Saint Kitts and Nevis',
				'continent' => 'North America',
			),
			'LC' => array(
				'alpha2'    => 'LC',
				'alpha3'    => 'LCA',
				'num'       => '662',
				'isd'       => '1758',
				'name'      => 'Saint Lucia',
				'continent' => 'North America',
			),
			'PM' => array(
				'alpha2'    => 'PM',
				'alpha3'    => 'SPM',
				'num'       => '666',
				'isd'       => '508',
				'name'      => 'Saint Pierre and Miquelon',
				'continent' => 'North America',
			),
			'VC' => array(
				'alpha2'    => 'VC',
				'alpha3'    => 'VCT',
				'num'       => '670',
				'isd'       => '1784',
				'name'      => 'Saint Vincent and The Grenadines',
				'continent' => 'North America',
			),
			'WS' => array(
				'alpha2'    => 'WS',
				'alpha3'    => 'WSM',
				'num'       => '882',
				'isd'       => '685',
				'name'      => 'Samoa',
				'continent' => 'Oceania',
			),
			'SM' => array(
				'alpha2'    => 'SM',
				'alpha3'    => 'SMR',
				'num'       => '674',
				'isd'       => '378',
				'name'      => 'San Marino',
				'continent' => 'Europe',
			),
			'ST' => array(
				'alpha2'    => 'ST',
				'alpha3'    => 'STP',
				'num'       => '678',
				'isd'       => '239',
				'name'      => 'Sao Tome and Principe',
				'continent' => 'Africa',
			),
			'SA' => array(
				'alpha2'    => 'SA',
				'alpha3'    => 'SAU',
				'num'       => '682',
				'isd'       => '966',
				'name'      => 'Saudi Arabia',
				'continent' => 'Asia',
			),
			'SN' => array(
				'alpha2'    => 'SN',
				'alpha3'    => 'SEN',
				'num'       => '686',
				'isd'       => '221',
				'name'      => 'Senegal',
				'continent' => 'Africa',
			),
			'RS' => array(
				'alpha2'    => 'RS',
				'alpha3'    => 'SRB',
				'num'       => '688',
				'isd'       => '381',
				'name'      => 'Serbia',
				'continent' => 'Europe',
			),
			'SC' => array(
				'alpha2'    => 'SC',
				'alpha3'    => 'SYC',
				'num'       => '690',
				'isd'       => '248',
				'name'      => 'Seychelles',
				'continent' => 'Africa',
			),
			'SL' => array(
				'alpha2'    => 'SL',
				'alpha3'    => 'SLE',
				'num'       => '694',
				'isd'       => '232',
				'name'      => 'Sierra Leone',
				'continent' => 'Africa',
			),
			'SG' => array(
				'alpha2'    => 'SG',
				'alpha3'    => 'SGP',
				'num'       => '702',
				'isd'       => '65',
				'name'      => 'Singapore',
				'continent' => 'Asia',
			),
			'SK' => array(
				'alpha2'    => 'SK',
				'alpha3'    => 'SVK',
				'num'       => '703',
				'isd'       => '421',
				'name'      => 'Slovakia',
				'continent' => 'Europe',
			),
			'SI' => array(
				'alpha2'    => 'SI',
				'alpha3'    => 'SVN',
				'num'       => '705',
				'isd'       => '386',
				'name'      => 'Slovenia',
				'continent' => 'Europe',
			),
			'SB' => array(
				'alpha2'    => 'SB',
				'alpha3'    => 'SLB',
				'num'       => '090',
				'isd'       => '677',
				'name'      => 'Solomon Islands',
				'continent' => 'Oceania',
			),
			'SO' => array(
				'alpha2'    => 'SO',
				'alpha3'    => 'SOM',
				'num'       => '706',
				'isd'       => '252',
				'name'      => 'Somalia',
				'continent' => 'Africa',
			),
			'ZA' => array(
				'alpha2'    => 'ZA',
				'alpha3'    => 'ZAF',
				'num'       => '729',
				'isd'       => '27',
				'name'      => 'South Africa',
				'continent' => 'Africa',
			),
			'SS' => array(
				'alpha2'    => 'SS',
				'alpha3'    => 'SSD',
				'num'       => '710',
				'isd'       => '211',
				'name'      => 'South Sudan',
				'continent' => 'Africa',
			),
			'GS' => array(
				'alpha2'    => 'GS',
				'alpha3'    => 'SGS',
				'num'       => '239',
				'isd'       => '500',
				'name'      => 'South Georgia and The South Sandwich Islands',
				'continent' => 'Antarctica',
			),
			'ES' => array(
				'alpha2'    => 'ES',
				'alpha3'    => 'ESP',
				'num'       => '724',
				'isd'       => '34',
				'name'      => 'Spain',
				'continent' => 'Europe',
			),
			'LK' => array(
				'alpha2'    => 'LK',
				'alpha3'    => 'LKA',
				'num'       => '144',
				'isd'       => '94',
				'name'      => 'Sri Lanka',
				'continent' => 'Asia',
			),
			'SD' => array(
				'alpha2'    => 'SD',
				'alpha3'    => 'SDN',
				'num'       => '736',
				'isd'       => '249',
				'name'      => 'Sudan',
				'continent' => 'Africa',
			),
			'SR' => array(
				'alpha2'    => 'SR',
				'alpha3'    => 'SUR',
				'num'       => '740',
				'isd'       => '597',
				'name'      => 'Suriname',
				'continent' => 'South America',
			),
			'SJ' => array(
				'alpha2'    => 'SJ',
				'alpha3'    => 'SJM',
				'num'       => '744',
				'isd'       => '47',
				'name'      => 'Svalbard and Jan Mayen',
				'continent' => 'Europe',
			),
			'SZ' => array(
				'alpha2'    => 'SZ',
				'alpha3'    => 'SWZ',
				'num'       => '748',
				'isd'       => '268',
				'name'      => 'Swaziland',
				'continent' => 'Africa',
			),
			'SE' => array(
				'alpha2'    => 'SE',
				'alpha3'    => 'SWE',
				'num'       => '752',
				'isd'       => '46',
				'name'      => 'Sweden',
				'continent' => 'Europe',
			),
			'CH' => array(
				'alpha2'    => 'CH',
				'alpha3'    => 'CHE',
				'num'       => '756',
				'isd'       => '41',
				'name'      => 'Switzerland',
				'continent' => 'Europe',
			),
			'SY' => array(
				'alpha2'    => 'SY',
				'alpha3'    => 'SYR',
				'num'       => '760',
				'isd'       => '963',
				'name'      => 'Syrian Arab Republic',
				'continent' => 'Asia',
			),
			'TW' => array(
				'alpha2'    => 'TW',
				'alpha3'    => 'TWN',
				'num'       => '158',
				'isd'       => '886',
				'name'      => 'Taiwan, Province of China',
				'continent' => 'Asia',
			),
			'TJ' => array(
				'alpha2'    => 'TJ',
				'alpha3'    => 'TJK',
				'num'       => '762',
				'isd'       => '992',
				'name'      => 'Tajikistan',
				'continent' => 'Asia',
			),
			'TZ' => array(
				'alpha2'    => 'TZ',
				'alpha3'    => 'TZA',
				'num'       => '834',
				'isd'       => '255',
				'name'      => 'Tanzania, United Republic of',
				'continent' => 'Africa',
			),
			'TH' => array(
				'alpha2'    => 'TH',
				'alpha3'    => 'THA',
				'num'       => '764',
				'isd'       => '66',
				'name'      => 'Thailand',
				'continent' => 'Asia',
			),
			'TL' => array(
				'alpha2'    => 'TL',
				'alpha3'    => 'TLS',
				'num'       => '626',
				'isd'       => '670',
				'name'      => 'Timor-leste',
				'continent' => 'Asia',
			),
			'TG' => array(
				'alpha2'    => 'TG',
				'alpha3'    => 'TGO',
				'num'       => '768',
				'isd'       => '228',
				'name'      => 'Togo',
				'continent' => 'Africa',
			),
			'TK' => array(
				'alpha2'    => 'TK',
				'alpha3'    => 'TKL',
				'num'       => '772',
				'isd'       => '690',
				'name'      => 'Tokelau',
				'continent' => 'Oceania',
			),
			'TO' => array(
				'alpha2'    => 'TO',
				'alpha3'    => 'TON',
				'num'       => '776',
				'isd'       => '676',
				'name'      => 'Tonga',
				'continent' => 'Oceania',
			),
			'TT' => array(
				'alpha2'    => 'TT',
				'alpha3'    => 'TTO',
				'num'       => '780',
				'isd'       => '1868',
				'name'      => 'Trinidad and Tobago',
				'continent' => 'North America',
			),
			'TN' => array(
				'alpha2'    => 'TN',
				'alpha3'    => 'TUN',
				'num'       => '788',
				'isd'       => '216',
				'name'      => 'Tunisia',
				'continent' => 'Africa',
			),
			'TR' => array(
				'alpha2'    => 'TR',
				'alpha3'    => 'TUR',
				'num'       => '792',
				'isd'       => '90',
				'name'      => 'Turkey',
				'continent' => 'Asia',
			),
			'TM' => array(
				'alpha2'    => 'TM',
				'alpha3'    => 'TKM',
				'num'       => '795',
				'isd'       => '993',
				'name'      => 'Turkmenistan',
				'continent' => 'Asia',
			),
			'TC' => array(
				'alpha2'    => 'TC',
				'alpha3'    => 'TCA',
				'num'       => '796',
				'isd'       => '1649',
				'name'      => 'Turks and Caicos Islands',
				'continent' => 'North America',
			),
			'TV' => array(
				'alpha2'    => 'TV',
				'alpha3'    => 'TUV',
				'num'       => '798',
				'isd'       => '688',
				'name'      => 'Tuvalu',
				'continent' => 'Oceania',
			),
			'UG' => array(
				'alpha2'    => 'UG',
				'alpha3'    => 'UGA',
				'num'       => '800',
				'isd'       => '256',
				'name'      => 'Uganda',
				'continent' => 'Africa',
			),
			'UA' => array(
				'alpha2'    => 'UA',
				'alpha3'    => 'UKR',
				'num'       => '804',
				'isd'       => '380',
				'name'      => 'Ukraine',
				'continent' => 'Europe',
			),
			'AE' => array(
				'alpha2'    => 'AE',
				'alpha3'    => 'ARE',
				'num'       => '784',
				'isd'       => '971',
				'name'      => 'United Arab Emirates',
				'continent' => 'Asia',
			),
			'GB' => array(
				'alpha2'    => 'GB',
				'alpha3'    => 'GBR',
				'num'       => '826',
				'isd'       => '44',
				'name'      => 'United Kingdom',
				'continent' => 'Europe',
			),
			'US' => array(
				'alpha2'    => 'US',
				'alpha3'    => 'USA',
				'num'       => '840',
				'isd'       => '1',
				'name'      => 'United States',
				'continent' => 'North America',
			),
			'UM' => array(
				'alpha2'    => 'UM',
				'alpha3'    => 'UMI',
				'num'       => '581',
				'isd'       => '1',
				'name'      => 'United States Minor Outlying Islands',
				'continent' => 'Oceania',
			),
			'UY' => array(
				'alpha2'    => 'UY',
				'alpha3'    => 'URY',
				'num'       => '858',
				'isd'       => '598',
				'name'      => 'Uruguay',
				'continent' => 'South America',
			),
			'UZ' => array(
				'alpha2'    => 'UZ',
				'alpha3'    => 'UZB',
				'num'       => '860',
				'isd'       => '998',
				'name'      => 'Uzbekistan',
				'continent' => 'Asia',
			),
			'VU' => array(
				'alpha2'    => 'VU',
				'alpha3'    => 'VUT',
				'num'       => '548',
				'isd'       => '678',
				'name'      => 'Vanuatu',
				'continent' => 'Oceania',
			),
			'VE' => array(
				'alpha2'    => 'VE',
				'alpha3'    => 'VEN',
				'num'       => '862',
				'isd'       => '58',
				'name'      => 'Venezuela',
				'continent' => 'South America',
			),
			'VN' => array(
				'alpha2'    => 'VN',
				'alpha3'    => 'VNM',
				'num'       => '704',
				'isd'       => '84',
				'name'      => 'Vietnam',
				'continent' => 'Asia',
			),
			'VG' => array(
				'alpha2'    => 'VG',
				'alpha3'    => 'VGB',
				'num'       => '092',
				'isd'       => '1284',
				'name'      => 'Virgin Islands, British',
				'continent' => 'North America',
			),
			'VI' => array(
				'alpha2'    => 'VI',
				'alpha3'    => 'VIR',
				'num'       => '850',
				'isd'       => '1430',
				'name'      => 'Virgin Islands, U.S.',
				'continent' => 'North America',
			),
			'WF' => array(
				'alpha2'    => 'WF',
				'alpha3'    => 'WLF',
				'num'       => '876',
				'isd'       => '681',
				'name'      => 'Wallis and Futuna',
				'continent' => 'Oceania',
			),
			'EH' => array(
				'alpha2'    => 'EH',
				'alpha3'    => 'ESH',
				'num'       => '732',
				'isd'       => '212',
				'name'      => 'Western Sahara',
				'continent' => 'Africa',
			),
			'YE' => array(
				'alpha2'    => 'YE',
				'alpha3'    => 'YEM',
				'num'       => '887',
				'isd'       => '967',
				'name'      => 'Yemen',
				'continent' => 'Asia',
			),
			'ZM' => array(
				'alpha2'    => 'ZM',
				'alpha3'    => 'ZMB',
				'num'       => '894',
				'isd'       => '260',
				'name'      => 'Zambia',
				'continent' => 'Africa',
			),
			'ZW' => array(
				'alpha2'    => 'ZW',
				'alpha3'    => 'ZWE',
				'num'       => '716',
				'isd'       => '263',
				'name'      => 'Zimbabwe',
				'continent' => 'Africa',
			),
		);

		return $countries;
	}

	/**
	 * Get a country name from an alpha-2 or alpha-3 code
	 *
	 * @param string $country_code
	 *
	 * @return string
	 */
	public static function get_country_name( $country_code ) {
		$countries = self::get_valid_countries_iso3166();
		$name      = '';

		foreach ( $countries as $country ) {
			if ( $country_code === $country['alpha2'] || $country_code === $country['alpha3'] ) {
				$name = $country['name'];
			}
		}

		return $name;
	}

	/**
	 * Get a list of valid payment methods
	 *
	 * @param $post_type
	 *
	 * @return array
	 */
	public static function get_valid_payment_methods( $post_type ) {
		$methods = array( 'Direct Deposit', 'Check', 'Wire' );

		if ( WCP_Payment_Request::POST_TYPE === $post_type ) {
			$methods[] = 'Credit Card';
		}

		return $methods;
	}

	/**
	 * Validate and save payment method fields
	 *
	 * @param int $post_id
	 */
	public static function validate_save_payment_method_fields( $post_id, $meta_key_prefix ) {
		if ( ! current_user_can( 'view_wordcamp_payment_details' ) ) {
			return;
		}

		foreach ( $_POST as $key => $unsafe_value ) {
			$unsafe_value = wp_unslash( $unsafe_value );

			switch ( $key ) {
				case 'invoice_number':
				case 'bank_name':
				case 'bank_street_address':
				case 'bank_city':
				case 'bank_state':
				case 'bank_zip_code':
				case 'bank_bic':

				case 'interm_bank_name':
				case 'interm_bank_street_address':
				case 'interm_bank_city':
				case 'interm_bank_state':
				case 'interm_bank_zip_code':
				case 'interm_bank_swift':
				case 'interm_bank_account':

				case 'beneficiary_account_number':
				case 'beneficiary_name':
				case 'beneficiary_street_address':
				case 'beneficiary_city':
				case 'beneficiary_state':
				case 'beneficiary_zip_code':

				case 'payable_to':
				case 'check_street_address':
				case 'check_city':
				case 'check_state':
				case 'check_zip_code':

				case 'ach_bank_name':
				case 'ach_routing_number':
				case 'ach_account_number':
				case 'ach_account_holder_name':
					$safe_value = sanitize_text_field( $unsafe_value );
					break;

				case 'ach_account_type':
					if ( in_array( $unsafe_value, array( 'Personal', 'Company' ) ) ) {
						$safe_value = $unsafe_value;
					} else {
						$safe_value = false;
					}
					break;

				case 'payment_method':
					if ( in_array( $unsafe_value, self::get_valid_payment_methods( $_POST['post_type'] ), true ) ) {
						$safe_value = $unsafe_value;
					} else {
						$safe_value = false;
					}
					break;

				case 'vendor_country_iso3166':
				case 'bank_country_iso3166':
				case 'interm_bank_country_iso3166':
				case 'beneficiary_country_iso3166':
				case 'check_country':
					if ( array_key_exists( $unsafe_value, self::get_valid_countries_iso3166() ) ) {
						$safe_value = $unsafe_value;
					}
					break;

				default:
					$safe_value = null;
					break;
			}

			if ( is_null( $safe_value ) ) {
				continue;
			}

			if ( in_array( $key, self::get_encrypted_fields() ) ) {
				$encrypted_value = WCP_Encryption::encrypt( $safe_value );

				if ( ! is_wp_error( $encrypted_value ) ) {
					$safe_value = $encrypted_value;
				}
			}

			update_post_meta( $post_id, "_{$meta_key_prefix}_" . $key, $safe_value );
		}

		// Checkboxes
		foreach ( array( 'needs_intermediary_bank' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, "_{$meta_key_prefix}_" . $key, sanitize_text_field( $_POST[ $key ] ) );
			} else {
				delete_post_meta( $post_id, "_{$meta_key_prefix}_" . $key );
			}
		}
	}

	/**
	 * Get the names of all the fields that should be encrypted
	 *
	 * @return array
	 */
	public static function get_encrypted_fields() {
		return array(
			'payable_to',
			'check_street_address',
			'check_city',
			'check_state',
			'check_zip_code',
			'check_country',
			'beneficiary_name',
			'beneficiary_account_number',
			'beneficiary_street_address',
			'beneficiary_city',
			'beneficiary_state',
			'beneficiary_zip_code',
			'beneficiary_country_iso3166',
			'bank_name',
			'bank_street_address',
			'bank_city',
			'bank_state',
			'bank_zip_code',
			'bank_country_iso3166',
			'bank_bic',
			'interm_bank_name',
			'interm_bank_street_address',
			'interm_bank_city',
			'interm_bank_state',
			'interm_bank_zip_code',
			'interm_bank_country_iso3166',
			'interm_bank_swift',
			'interm_bank_account',
			'ach_bank_name',
			'ach_routing_number',
			'ach_account_number',
			'ach_account_holder_name',
		);
	}

	/**
	 * Get a list of ISO 4217 currencies, sorted by name.
	 *
	 * @todo Move this to helper-functions.php so it can be more cleanly reused, and update calls to it.
	 *
	 * @return array
	 */
	public static function get_currencies() {
		$currencies = array(
			'AFN' => 'Afghan Afghani',
			'ALL' => 'Albanian Lek',
			'DZD' => 'Algerian Dinar',
			'AOA' => 'Angolan Kwanza',
			'ARS' => 'Argentine Peso',
			'AMD' => 'Armenian Dram',
			'AWG' => 'Aruban Florin',
			'AUD' => 'Australian Dollar',
			'AZN' => 'Azerbaijan Manat',
			'BSD' => 'Bahamian Dollar',
			'BHD' => 'Bahraini Dinar',
			'BDT' => 'Bangladeshi Taka',
			'BBD' => 'Barbados Dollar',
			'BYN' => 'Belarusian Ruble',
			'BZD' => 'Belize Dollar',
			'BMD' => 'Bermudian Dollar',
			'BTN' => 'Bhutanese Ngultrum',
			'BOB' => 'Bolivian Boliviano',
			'BOV' => 'Bolivian Mvdol',
			'BAM' => 'Bosnia and Herzegovina Convertible Mark',
			'BWP' => 'Botswana Pula',
			'BRL' => 'Brazilian Real',
			'BND' => 'Bruneian Dollar',
			'BGN' => 'Bulgarian Lev',
			'MMK' => 'Burmese (Myanmar) Kyat',
			'BIF' => 'Burundian Franc',
			'CVE' => 'Cabo Verde Escudo',
			'KHR' => 'Cambodian Riel',
			'CAD' => 'Canadian Dollar',
			'KYD' => 'Cayman Islands Dollar',
			'XAF' => 'Central African CFA Franc BEAC',
			'XPF' => 'Change Franc Pacifique (CFP) Franc',
			'CLP' => 'Chilean Peso',
			'CNY' => 'Chinese Yuan Renminbi',
			'COP' => 'Colombian Peso',
			'KMF' => 'Comorian Franc ',
			'CDF' => 'Congolese Franc',
			'NIO' => 'Cordoba Oro',
			'CRC' => 'Costa Rican Colon',
			'HRK' => 'Croatian Kuna',
			'CUC' => 'Cuban Convertible Peso',
			'CUP' => 'Cuban Peso',
			'CZK' => 'Czech Koruna',
			'DKK' => 'Danish Krone',
			'DJF' => 'Djibouti Franc',
			'DOP' => 'Dominican Peso',
			'XCD' => 'East Caribbean Dollar',
			'XSU' => 'Ecuadorian Sucre',
			'EGP' => 'Egyptian Pound',
			'SVC' => 'El Salvador Colon',
			'ERN' => 'Eritrean Nakfa',
			'ETB' => 'Ethiopian Birr',
			'EUR' => 'European Zone Euro',
			'FKP' => 'Falkland Islands Pound',
			'FJD' => 'Fiji Dollar',
			'GMD' => 'Gambian Dalasi',
			'GEL' => 'Georgian Lari',
			'GHS' => 'Ghana Cedi',
			'GIP' => 'Gibraltar Pound',
			'GTQ' => 'Guatemalan Quetzal',
			'GNF' => 'Guinean Franc',
			'GYD' => 'Guyana Dollar',
			'HTG' => 'Haitian Gourde',
			'HNL' => 'Honduran Lempira',
			'HKD' => 'Hong Kong Dollar',
			'HUF' => 'Hungarian Forint',
			'ISK' => 'Iceland Krona',
			'INR' => 'Indian Rupee',
			'IDR' => 'Indonesian Rupiah',
			'IRR' => 'Iranian Rial',
			'IQD' => 'Iraqi Dinar',
			'JMD' => 'Jamaican Dollar',
			'JPY' => 'Japanese Yen',
			'JOD' => 'Jordanian Dinar',
			'KZT' => 'Kazakhstani Tenge',
			'KES' => 'Kenyan Shilling',
			'KWD' => 'Kuwaiti Dinar',
			'KGS' => 'Kyrgystani Som',
			'LAK' => 'Lao Kip',
			'LBP' => 'Lebanese Pound',
			'LSL' => 'Lesotho Loti',
			'LRD' => 'Liberian Dollar',
			'LYD' => 'Libyan Dinar',
			'MOP' => 'Macanese Pataca',
			'MKD' => 'Macedonian Denar',
			'MGA' => 'Malagasy Ariary',
			'MWK' => 'Malawi Kwacha',
			'MYR' => 'Malaysian Ringgit',
			'MVR' => 'Maldivian Rufiyaa',
			'MRO' => 'Mauritanian Ouguiya',
			'MUR' => 'Mauritius Rupee',
			'MXN' => 'Mexican Peso',
			'MXV' => 'Mexican Unidad de Inversion (UDI)',
			'MDL' => 'Moldovan Leu',
			'MNT' => 'Mongolian Tugrik',
			'MAD' => 'Moroccan Dirham',
			'MZN' => 'Mozambique Metical',
			'NAD' => 'Namibia Dollar',
			'NPR' => 'Nepalese Rupee',
			'ANG' => 'Netherlands Antillean Guilder',
			'ILS' => 'New Israeli Sheqel',
			'TWD' => 'New Taiwan Dollar',
			'NZD' => 'New Zealand Dollar',
			'NGN' => 'Nigerian Naira',
			'KPW' => 'North Korean Won',
			'NOK' => 'Norwegian Krone',
			'PKR' => 'Pakistan Rupee',
			'PAB' => 'Panamanian Balboa',
			'PGK' => 'Papua New Guinean Kina',
			'PYG' => 'Paraguayan Guarani',
			'PEN' => 'Peruvian Sol',
			'UYU' => 'Peso Uruguayo',
			'PHP' => 'Philippine Piso',
			'PLN' => 'Polish Zloty',
			'QAR' => 'Qatari Rial',
			'OMR' => 'Rial Omani',
			'RON' => 'Romanian Leu',
			'RUB' => 'Russian Ruble',
			'RWF' => 'Rwanda Franc',
			'SHP' => 'Saint Helena Pound',
			'WST' => 'Samoan Tala',
			'SAR' => 'Saudi Riyal',
			'RSD' => 'Serbian Dinar',
			'SCR' => 'Seychelles Rupee',
			'SLL' => 'Sierra Leonean Leone',
			'SGD' => 'Singapore Dollar',
			'SBD' => 'Solomon Islands Dollar',
			'SOS' => 'Somali Shilling',
			'ZAR' => 'South African Rand',
			'KRW' => 'South Korean Won',
			'SSP' => 'South Sudanese Pound',
			'LKR' => 'Sri Lanka Rupee',
			'SDG' => 'Sudanese Pound',
			'SRD' => 'Surinam Dollar',
			'SZL' => 'Swazi Lilangeni',
			'SEK' => 'Swedish Krona',
			'CHF' => 'Swiss Franc',
			'SYP' => 'Syrian Pound',
			'STD' => 'São Tomé & Príncipe Dobra',
			'TJS' => 'Tajikistani Somoni',
			'TZS' => 'Tanzanian Shilling',
			'THB' => 'Thai Baht',
			'TOP' => 'Tongan Paʻanga',
			'TTD' => 'Trinidad and Tobago Dollar',
			'TND' => 'Tunisian Dinar',
			'TRY' => 'Turkish Lira',
			'TMT' => 'Turkmenistan New Manat',
			'UGX' => 'Uganda Shilling',
			'UAH' => 'Ukrainian Hryvnia',
			'CLF' => 'Unidad de Fomento',
			'COU' => 'Unidad de Valor Real',
			'AED' => 'United Arab Emirates Dirham',
			'GBP' => 'United Kingdom Pound Sterling',
			'USD' => 'United States Dollar',
			'UZS' => 'Uzbekistan Sum',
			'VUV' => 'Vanuatu Vatu',
			'VEF' => 'Venezuelan Bolívar',
			'VND' => 'Vietnamese Dong',
			'CHE' => 'WIR Euro',
			'CHW' => 'WIR Franc',
			'XOF' => 'West African CFA Franc',
			'YER' => 'Yemeni Rial',
			'ZMW' => 'Zambian Kwacha',
			'ZWL' => 'Zimbabwe Dollar',
		);

		return $currencies;
	}

	/**
	 * Define the payment categories
	 *
	 * The slugs are explicitly registered in English so they will match across sites that use different locales,
	 * which facilitates aggregating the data into reports.
	 *
	 * @return array
	 */
	public static function get_payment_categories() {
		return array(
			'after-party'     => esc_html__( 'After Party',                    'wordcamporg' ),
			'audio-visual'    => esc_html__( 'Audio Visual',                   'wordcamporg' ),
			'camera-shipping' => esc_html__( 'Camera Shipping',                'wordcamporg' ),
			'food-beverages'  => esc_html__( 'Food & Beverage',                'wordcamporg' ),
			'office-supplies' => esc_html__( 'Office Supplies',                'wordcamporg' ),
			'signage-badges'  => esc_html__( 'Signage & Badges',               'wordcamporg' ),
			'speaker-event'   => esc_html__( 'Speaker Event',                  'wordcamporg' ),
			'swag'            => esc_html__( 'Swag (t-shirts, stickers, etc)', 'wordcamporg' ),
			'venue'           => esc_html__( 'Venue',                          'wordcamporg' ),
			'other'           => esc_html__( 'Other',                          'wordcamporg' ), // This one is intentionally last, regardless of alphabetical order
		);
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
	 * Get the name of the requester
	 *
	 * @param int $post_author_id
	 *
	 * @return string
	 */
	public static function get_requester_name( $post_author_id ) {
		$requester_name = '';

		$author = get_user_by( 'id', $post_author_id );

		if ( is_a( $author, 'WP_User' ) ) {
			$requester_name = $author->get( 'display_name' );
		}

		return $requester_name;
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
	 * Get the files attached to a post
	 *
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public static function get_attached_files( $post ) {
		$files = get_posts( array(
			'post_parent'    => $post->ID,
			'post_type'      => 'attachment',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',

			// Make sure that `WordCamp\Budgets\Privacy\hide_others_payment_files()` runs.
			'suppress_filters' => false,
		) );

		foreach ( $files as &$file ) {
			$file->filename = wp_basename( $file->guid );
			$file->url      = wp_get_attachment_url( $file->ID );
		}

		return $files;
	}

	/**
	 * Attach unattached files to the Vendor Payment post
	 *
	 * Sometimes users will upload the files manually to the Media Library, instead of using the Add Files button,
	 * and we need to attach them to the request so that they show up in the metabox.
	 *
	 * NOTE: The calling function must remove any of its save_post callbacks before calling this, in order to
	 * avoid infinite recursion
	 *
	 * @param int   $post_id
	 * @param array $request
	 */
	public static function attach_existing_files( $post_id, $request ) {
		if ( empty( $request['wcb_existing_files_to_attach'] ) ) {
			return;
		}

		if ( ! $files = json_decode( $request['wcb_existing_files_to_attach'] ) ) {
			return;
		}

		foreach ( $files as $file_id ) {
			wp_update_post( array(
				'ID'          => $file_id,
				'post_parent' => $post_id,
			) );
		}
	}

	/**
	 * Display the indicator that marks a form field as required
	 */
	public static function render_form_field_required_indicator() {
		require( dirname( __DIR__ ) . '/views/wordcamp-budgets/form-field-required-indicator.php' );
	}

	/**
	 * Get the current post when inside a `map_meta_cap` callback
	 *
	 * Normally it's just the global $post, but sometimes you have to dig it out of $args (e.g., a bulk edit)
	 *
	 * @param array $args The $args that was passed to the `map_meta_cap` callback
	 *
	 * @return WP_Post|null
	 */
	public static function get_map_meta_cap_post( $args ) {
		$post = null;

		/*
		 * Use a reference to the global $post if it already exists, but don't create one otherwise
		 *
		 * i.e., Don't use `global $post` and then assign the result of `get_post()` to that.
		 *
		 * If $GLOBAL['post'] didn't already exist and then we created one, then that could have unintentional
		 * side-effects outside of this function.
		 */
		if ( isset( $GLOBALS['post'] ) ) {
			$post = $GLOBALS['post'];
		} elseif ( isset( $args[0] ) && is_int( $args[0] ) ) {
			$post = get_post( $args[0] );
		}

		return $post;
	}

	/**
	 * Limit access to payment details to protect privacy.
	 *
	 * Only network admins and the request's author should be able to see the details. Trusted deputies
	 * do not need access, since they can't issue payments.
	 *
	 * @filter user_has_cap.
	 *
	 * @param array   $users_capabilities  All of the user's capabilities.
	 * @param array   $mapped_capabilities All capabilities required to perform the given capability.
	 * @param array   $args                (optional) Additional parameters passed to WP_User::has_cap().
	 * @param WP_User $user                The user whose capabilities we're modifying.
	 *
	 * @return array
	 */
	public static function user_can_view_payment_details( $users_capabilities, $mapped_capabilities, $args, $user ) {
		global $post;

		$target_capability                        = 'view_wordcamp_payment_details';
		$users_capabilities[ $target_capability ] = false;

		/*
		 * We also want network admins to have access, but it isn't necessary to explicitly add them
		 * here, because `has_cap()` always returns `true` for them.
		 */
		if ( in_array( $target_capability, $args ) && isset( $post->post_author ) && $post->post_author == $user->ID ) {
			$users_capabilities[ $target_capability ] = true;
		}

		return $users_capabilities;
	}

	/**
	 * Insert an entry into a log for one of the custom post types
	 *
	 * @param int    $post_id The post ID.
	 * @param string $message A log message.
	 * @param array  $data    Optional data.
	 */
	public static function log( $post_id, $user_id, $message, $data = array() ) {
		global $wpdb;

		$data['user_id'] = $user_id; // for backwards-compatibility

		$entry = array(
			'timestamp' => time(),
			'message'   => $message,
			'data'      => $data,
		);

		$log = get_post_meta( $post_id, '_wcp_log', true );
		if ( empty( $log ) ) {
			$log = '[]';
		}

		$log   = json_decode( $log, true );
		$log[] = $entry;
		$log   = json_encode( $log );

		update_post_meta( $post_id, '_wcp_log', wp_slash( $log ) );
	}
}
