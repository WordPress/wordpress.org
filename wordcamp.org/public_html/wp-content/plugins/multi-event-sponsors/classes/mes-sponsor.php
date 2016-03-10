<?php

/*
 * Register the Multi-Event Sponsors custom post type and manage all of its functionality
 *
 * The use of custom post types and taxonomies for the regions and sponsorships levels is a little questionable,
 * since neither fits perfectly. In an abstract sense, they're taxonomies, but they map between terms for a given post,
 * rather than mapping terms to posts directly. Also, the sponsorship levels have extra meta data associated with them.
 *
 * So, a custom taxonomy is used for regions, and a custom post type is used for sponsorship levels, and then meta data
 * is used on each sponsor post to map each region to a sponsorship level.
 */

class MES_Sponsor {
	const POST_TYPE_SLUG = 'mes';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',        array( $this, 'create_post_type' ) );
		add_action( 'admin_init',  array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post',   array( $this, 'save_post' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'add_header_footer_text' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function create_post_type() {
		if ( post_type_exists( self::POST_TYPE_SLUG ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'Multi-Event Sponsors',                   'wordcamporg' ),
			'singular_name'      => __( 'Multi-Event Sponsor',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                                'wordcamporg' ),
			'add_new_item'       => __( 'Add New Multi-Event Sponsor',            'wordcamporg' ),
			'edit'               => __( 'Edit',                                   'wordcamporg' ),
			'edit_item'          => __( 'Edit Multi-Event Sponsor',               'wordcamporg' ),
			'new_item'           => __( 'New Multi-Event Sponsor',                'wordcamporg' ),
			'view'               => __( 'View Multi-Event Sponsors',              'wordcamporg' ),
			'view_item'          => __( 'View Multi-Event Sponsor',               'wordcamporg' ),
			'search_items'       => __( 'Search Multi-Event Sponsors',            'wordcamporg' ),
			'not_found'          => __( 'No Multi-Event Sponsors found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No Multi-Event Sponsors found in Trash', 'wordcamporg' ),
			'parent'             => __( 'Parent Multi-Event Sponsor',             'wordcamporg' ),
		);

		$post_type_params = array(
			'labels'          => $labels,
			'singular_label'  => __( 'Multi-Event Sponsor', 'wordcamporg' ),
			'public'          => true,
			'menu_position'   => 20,
			'hierarchical'    => false,
			'capability_type' => 'page',
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => 'multi-event-sponsor', 'with_front' => false ),
			'query_var'       => true,
			'supports'        => array( 'title', 'editor', 'author', 'excerpt', 'revisions', 'thumbnail' ),
			'taxonomies'      => array( MES_Region::TAXONOMY_SLUG ),
		);

		register_post_type( self::POST_TYPE_SLUG, $post_type_params );
	}

	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'mes_regional_sponsorships',
			__( 'Regional Sponsorships', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			MES_Sponsor::POST_TYPE_SLUG,
			'normal',
			'default'
		);

		add_meta_box(
			'mes_contact_information',
			__( 'Contact Information', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			MES_Sponsor::POST_TYPE_SLUG,
			'normal',
			'default'
		);
	}

	/**
	 * Builds the markup for all meta boxes
	 *
	 * @param WP_Post $post
	 * @param array   $box
	 */
	public function markup_meta_boxes( $post, $box ) {
		/** @var $view string */

		switch ( $box['id'] ) {
			case 'mes_regional_sponsorships':
				$regions               = get_terms( MES_Region::TAXONOMY_SLUG, array( 'hide_empty' => false ) );
				$sponsorship_levels    = get_posts( array( 'post_type' => MES_Sponsorship_Level::POST_TYPE_SLUG, 'numberposts' => -1 ) );
				$regional_sponsorships = $this->populate_default_regional_sponsorships( get_post_meta( $post->ID, 'mes_regional_sponsorships', true ), $regions );
				$view                  = 'metabox-regional-sponsorships.php';
				break;

			case 'mes_contact_information':
				$company_name  = get_post_meta( $post->ID, 'mes_company_name',  true );
				$website       = get_post_meta( $post->ID, 'mes_website',       true );
				$first_name    = get_post_meta( $post->ID, 'mes_first_name',    true );
				$last_name     = get_post_meta( $post->ID, 'mes_last_name',     true );
				$email_address = get_post_meta( $post->ID, 'mes_email_address', true );
				$phone_number  = get_post_meta( $post->ID, 'mes_phone_number',  true );

				$street_address1 = get_post_meta( $post->ID, 'mes_street_address1', true );
				$street_address2 = get_post_meta( $post->ID, 'mes_street_address2', true );
				$city            = get_post_meta( $post->ID, 'mes_city',            true );
				$state           = get_post_meta( $post->ID, 'mes_state',           true );
				$zip_code        = get_post_meta( $post->ID, 'mes_zip_code',        true );
				$country         = get_post_meta( $post->ID, 'mes_country',         true );

				$available_countries = array( 'Abkhazia', 'Afghanistan', 'Aland', 'Albania', 'Algeria', 'American Samoa', 'Andorra', 'Angola', 'Anguilla', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Aruba', 'Ascension', 'Ashmore and Cartier Islands', 'Australia', 'Australian Antarctic Territory', 'Austria', 'Azerbaijan', 'Bahamas, The', 'Bahrain', 'Baker Island', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bermuda', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Bouvet Island', 'Brazil', 'British Antarctic Territory', 'British Indian Ocean Territory', 'British Sovereign Base Areas', 'British Virgin Islands', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Cayman Islands', 'Central African Republic', 'Chad', 'Chile', "China, People's Republic of", 'China, Republic of (Taiwan)', 'Christmas Island', 'Clipperton Island', 'Cocos (Keeling) Islands', 'Colombia', 'Comoros', 'Congo, (Congo  Brazzaville)', 'Congo, (Congo  Kinshasa)', 'Cook Islands', 'Coral Sea Islands', 'Costa Rica', "Cote d'Ivoire (Ivory Coast)", 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Ethiopia', 'Falkland Islands (Islas Malvinas)', 'Faroe Islands', 'Fiji', 'Finland', 'France', 'French Guiana', 'French Polynesia', 'French Southern and Antarctic Lands', 'Gabon', 'Gambia, The', 'Georgia', 'Germany', 'Ghana', 'Gibraltar', 'Greece', 'Greenland', 'Grenada', 'Guadeloupe', 'Guam', 'Guatemala', 'Guernsey', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Heard Island and McDonald Islands', 'Honduras', 'Hong Kong', 'Howland Island', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Isle of Man', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jarvis Island', 'Jersey', 'Johnston Atoll', 'Jordan', 'Kazakhstan', 'Kenya', 'Kingman Reef', 'Kiribati', 'Korea, North', 'Korea, South', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Macau', 'Macedonia', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Martinique', 'Mauritania', 'Mauritius', 'Mayotte', 'Mexico', 'Micronesia', 'Midway Islands', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Montserrat', 'Morocco', 'Mozambique', 'Myanmar (Burma)', 'Nagorno-Karabakh', 'Namibia', 'Nauru', 'Navassa Island', 'Nepal', 'Netherlands', 'Netherlands Antilles', 'New Caledonia', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'Niue', 'Norfolk Island', 'Northern Cyprus', 'Northern Mariana Islands', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palmyra Atoll', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Peter I Island', 'Philippines', 'Pitcairn Islands', 'Poland', 'Portugal', 'Pridnestrovie (Transnistria)', 'Puerto Rico', 'Qatar', 'Queen Maud Land', 'Reunion', 'Romania', 'Ross Dependency', 'Russia', 'Rwanda', 'Saint Barthelemy', 'Saint Helena', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Martin', 'Saint Pierre and Miquelon', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'Somaliland', 'South Africa', 'South Georgia & South Sandwich Islands', 'South Ossetia', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Svalbard', 'Swaziland', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste (East Timor)', 'Togo', 'Tokelau', 'Tonga', 'Trinidad and Tobago', 'Tristan da Cunha', 'Tunisia', 'Turkey', 'Turkmenistan', 'Turks and Caicos Islands', 'Tuvalu', 'U.S. Virgin Islands', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Wake Island', 'Wallis and Futuna', 'Yemen', 'Zambia', 'Zimbabwe' );
				// todo use WordCamp_Budgets::get_valid_countries_iso3166() instead. have to convert wc-post-types::metabox_sponsor_info() at same time

				$view = 'metabox-contact-information.php';
				break;
		}

		require_once( dirname( __DIR__ ) . '/views/'. $view );
	}

	/**
	 * Populate the regional sponsorships array with default values.
	 *
	 * This helps to avoid any PHP notices from trying to access undefined indices.
	 *
	 * @param array $regional_sponsorships
	 * @param array $regions
	 *
	 * @return array
	 */
	protected function populate_default_regional_sponsorships( $regional_sponsorships, $regions ) {
		$region_ids = wp_list_pluck( $regions, 'term_id' );

		foreach ( $region_ids as $region_id ) {
			if ( empty ( $regional_sponsorships[ $region_id ] ) ) {
				$regional_sponsorships[ $region_id ] = 'null';
			}
		}

		return $regional_sponsorships;
	}

	/**
	 * Save the post data
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		$ignored_actions = array( 'trash', 'untrash', 'restore' );

		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $ignored_actions ) ) {
			return;
		}

		if ( ! $post || $post->post_type != self::POST_TYPE_SLUG || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) {
			return;
		}

		$this->save_post_meta( $post_id, $_POST );
	}

	/**
	 * Save the post's meta fields
	 *
	 * @param int   $post_id
	 * @param array $new_values
	 */
	protected function save_post_meta( $post_id, $new_values ) {
		if ( isset( $new_values[ 'mes_regional_sponsorships' ] ) ) {
			array_walk( $new_values[ 'mes_regional_sponsorships' ], 'absint' );
			update_post_meta( $post_id, 'mes_regional_sponsorships', $new_values[ 'mes_regional_sponsorships' ] );
		} else {
			delete_post_meta( $post_id, 'mes_regional_sponsorships' );
		}

		if ( isset( $new_values["mes_email_address"] ) ) {
			$new_values['mes_email_address'] = is_email( $new_values['mes_email_address'] );
		}

		$text_fields = array(
			'company_name', 'website', 'first_name', 'last_name', 'email_address', 'phone_number',
			'street_address1', 'street_address2', 'city', 'state', 'zip_code', 'country'
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $new_values["mes_$field"] ) ) {
				update_post_meta( $post_id, "mes_$field", sanitize_text_field( $new_values["mes_$field"] ) );
			} else {
				delete_post_meta( $post_id, "mes_$field" );
			}
		}
	}

	/**
	 * Add the header and footer copy to the sponsorship level content
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_header_footer_text( $content ) {
		global $post;

		if ( ! empty ( $post->post_type ) && self::POST_TYPE_SLUG == $post->post_type ) {
			$content = sprintf(
				'<p>Here’s the company description for %s for your website:</p>
				<div>%s</div>',
				$post->post_title,
				$content
			);
		}

		return $content;
	}
} // end MES_Sponsor
