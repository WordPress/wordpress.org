<?php

namespace WordCamp\CampTix_Tweaks;
use CampTix_Plugin;
use WP_Post;

defined( 'WPINC' ) or die();

// Tickets
add_action( 'camptix_admin_notices',                         __NAMESPACE__ . '\show_sandbox_mode_warning'           );
add_filter( 'camptix_dashboard_paypal_credentials',          __NAMESPACE__ . '\paypal_credentials'                  );
add_filter( 'camptix_paypal_predefined_accounts',            __NAMESPACE__ . '\paypal_credentials'                  );
add_filter( 'camptix_stripe_predefined_accounts',            __NAMESPACE__ . '\stripe_credentials'                  );
add_action( 'init',                                          __NAMESPACE__ . '\hide_empty_tickets'                  );
add_action( 'wp_print_styles',                               __NAMESPACE__ . '\print_login_message_styles'          );
add_filter( 'camptix_require_login_please_login_message',    __NAMESPACE__ . '\override_please_login_message'       );
add_action( 'camptix_form_start_errors',                     __NAMESPACE__ . '\add_form_start_error_messages'       );
add_action( 'transition_post_status',                        __NAMESPACE__ . '\ticket_sales_opened',          10, 3 );
add_action( 'camptix_payment_result',                        __NAMESPACE__ . '\track_payment_results',        10, 3 );
add_filter( 'camptix_shortcode_contents',                    __NAMESPACE__ . '\modify_shortcode_contents',    10, 2 );

// Attendees
add_filter( 'camptix_name_order',                            __NAMESPACE__ . '\set_name_order'                      );
add_action( 'camptix_form_edit_attendee_custom_error_flags', __NAMESPACE__ . '\disable_attendee_edits'              );
add_action( 'transition_post_status',                        __NAMESPACE__ . '\log_publish_to_cancel',        10, 3 );
add_filter( 'camptix_privacy_erase_attendee',                __NAMESPACE__ . '\retain_attendee_data',         10, 2 );
add_action( 'admin_notices',                                 __NAMESPACE__ . '\admin_notice_attendee_privacy'       );
add_filter( 'wp_privacy_personal_data_erasers',              __NAMESPACE__ . '\modify_erasers',                  99 );

// Miscellaneous
add_filter( 'camptix_beta_features_enabled',                 '__return_true' );
add_action( 'camptix_nt_file_log',                           '__return_false' );
add_action( 'init',                                          __NAMESPACE__ . '\camptix_debug',                    9 ); // CampTix does this at 10.
add_filter( 'camptix_default_addons',                        __NAMESPACE__ . '\load_addons'                         );
add_action( 'camptix_load_addons',                           __NAMESPACE__ . '\load_custom_addons',              11 );
add_filter( 'camptix_metabox_questions_default_fields_list', __NAMESPACE__ . '\modify_default_fields_list'          );
add_filter( 'camptix_capabilities',                          __NAMESPACE__ . '\modify_capabilities'                 );
add_filter( 'camptix_default_options',                       __NAMESPACE__ . '\modify_default_options'              );
add_filter( 'camptix_options',                               __NAMESPACE__ . '\modify_email_templates'              );
add_filter( 'camptix_email_tickets_template',                __NAMESPACE__ . '\switch_email_template'               );
add_filter( 'camptix_html_message',                          __NAMESPACE__ . '\render_html_emails',           10, 2 );
add_action( 'camptix_tshirt_report_intro',                   __NAMESPACE__ . '\tshirt_report_intro_message',  10, 3 );


/**
 * Warn organizers when CampTix is in sandbox mode
 *
 * If they open ticket sales while in sandbox mode, then attendees will be confused, etc.
 *
 * @todo This should probably be moved to CampTix itself, except for the check for the 'wordcamp-sandbox' account,
 * which can stay here.
 */
function show_sandbox_mode_warning() {
	/** @var $post    \WP_Post        */
	/** @var $camptix \CampTix_Plugin */
	global $post, $camptix;

	$camptix_options = $camptix->get_options();
	$post_types      = array( 'tix_ticket', 'tix_coupon', 'tix_email', 'tix_attendee', 'tix_question' );
	$current_screen  = get_current_screen();

	if ( ! isset( $camptix_options ) ) {
		return;
	}

	$camptix_post_type      = in_array( $current_screen->post_type, $post_types );
	$camptix_shortcode_page = isset( $post->post_content ) && has_shortcode( $post->post_content, 'camptix' );

	if ( $camptix_post_type || $camptix_shortcode_page ) {
		$sandbox = false;

		// If any payment addons are in sandbox mode
		foreach ( $camptix_options as $option_key => $option_value ) {
			if ( 'payment_options_' === substr( $option_key, 0, 16 ) ) {
				if ( isset( $option_value['sandbox'] ) && true === $option_value['sandbox'] ) {
					$sandbox = true;
				}
			}
		}

		// If the WordCamp sandbox is picked from the predefs
		if ( ! empty( $camptix_options['payment_options_paypal']['api_predef'] ) ) {
			if ( 'wordcamp-sandbox' == $camptix_options['payment_options_paypal']['api_predef'] ) {
				$sandbox = true;
			}
		}

		if ( ! empty( $camptix_options['payment_options_stripe']['api_predef'] ) ) {
			if ( 'wpcs-sandbox' == $camptix_options['payment_options_stripe']['api_predef'] ) {
				$sandbox = true;
			}
		}

		// And the event is not archived
		if ( $sandbox && ! $camptix_options['archived'] ) {
			require_once( __DIR__ . '/views/notice-sandbox-mode.php' );
		}
	}
}

/**
 * Setup our pre-defined payment credentials
 *
 * @param array $credentials
 *
 * @return array
 */
function paypal_credentials( $credentials ) {
	// Sandbox account
	$credentials = array(
		"wordcamp-sandbox" => array(
			'label'         => "WordCamp Sandbox",
			'sandbox'       => true,
			'api_username'  => defined( 'WC_SANDBOX_PAYPAL_NVP_USERNAME'  ) ? WC_SANDBOX_PAYPAL_NVP_USERNAME  : '',
			'api_password'  => defined( 'WC_SANDBOX_PAYPAL_NVP_PASSWORD'  ) ? WC_SANDBOX_PAYPAL_NVP_PASSWORD  : '',
			'api_signature' => defined( 'WC_SANDBOX_PAYPAL_NVP_SIGNATURE' ) ? WC_SANDBOX_PAYPAL_NVP_SIGNATURE : '',
		),
	);

	/*
	 * Live API credentials for WordPress Community Support, PBC
	 *
	 * This still uses the old 'foundation' array index in order to maintain backwards-compatibility. When an
	 * organizer saves the form on the Tickets > Setup > Payment screen, an option is saved with the key of the
	 * selected pre-defined credentials, which correspond to the indexes in the $credentials array. If those keys
	 * are ever changed, that would break the mapping for sites that that were previously saved using the old key.
	 */
	if ( defined( 'WPCS_PAYPAL_NVP_USERNAME' ) ) {
		$credentials['foundation'] = array(
			'label'         => 'WordPress Community Support, PBC',
			'sandbox'       => false,
			'api_username'  => WPCS_PAYPAL_NVP_USERNAME,
			'api_password'  => WPCS_PAYPAL_NVP_PASSWORD,
			'api_signature' => WPCS_PAYPAL_NVP_SIGNATURE,
		);
	}

	return $credentials;
}

/**
 * Setup our pre-defined Stripe payment credentials.
 *
 * @param array $credentials
 *
 * @return array
 */
function stripe_credentials( $credentials ) {
	// Sandbox account
	$credentials = array(
		'wpcs-sandbox' => array(
			'label'               => 'WordCamp Sandbox',
			'sandbox'             => true,
			'api_test_public_key' => defined( 'WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC' ) ? WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC : '',
			'api_test_secret_key' => defined( 'WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET' ) ? WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET : '',
			// Backcompat
			'api_public_key'      => defined( 'WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC' ) ? WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC : '',
			'api_secret_key'      => defined( 'WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET' ) ? WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET : '',
		),
	);

	// Production account
	if ( defined( 'WORDCAMP_CAMPTIX_STRIPE_LIVE_PUBLIC' ) ) {
		$credentials['wpcs-production'] = array(
			'label'          => 'WordPress Community Support, PBC',
			'sandbox'        => false,
			'api_public_key' => defined( 'WORDCAMP_CAMPTIX_STRIPE_LIVE_PUBLIC' ) ? WORDCAMP_CAMPTIX_STRIPE_LIVE_PUBLIC : '',
			'api_secret_key' => defined( 'WORDCAMP_CAMPTIX_STRIPE_LIVE_SECRET' ) ? WORDCAMP_CAMPTIX_STRIPE_LIVE_SECRET : '',
		);
	}

	return $credentials;
}

/*
 * Show empty tickets
 *
 * This provides a way for individual WordCamps to decide if they want to show sold-out tickets in the [tickets]
 * shortcode output. This can help avoid confusion if the camp has several types of tickets (e.g., General
 * Admission, Micro-sponsorship, etc) and the General Admission ticket sells out. If the General Admission ticket
 * was hidden, some users may mistakenly think that the Micro-sponsorship ticket is the "normal" ticket, even
 * though it costs several hundred dollars. Since we value keeping regular tickets accessible by as many people
 * as possible, we don't want anyone getting the impression that WordCamps are expensive to attend.
 *
 * @todo change this to use feature-flags similar to the skip-feature flags
 */
function hide_empty_tickets() {
	$targeted_wordcamps_ids = array(
		299, // San Francisco 2013
		364, // San Francisco 2014
	);

	if ( in_array( get_current_blog_id(), $targeted_wordcamps_ids ) ) {
		add_filter( 'camptix_hide_empty_tickets', '__return_false' );
	}
}

/**
 * Enqueue the login message styles on the tickets screen.
 */
function print_login_message_styles() {
	global $post;

	if ( $post && has_shortcode( $post->post_content, 'camptix' ) ) {
		wcorg_login_css();
	}
}

/**
 * Override the default 'please login...' message that Require Login places on the ticket registration screen
 *
 * @param string $message
 *
 * @return string
 */
function override_please_login_message( $message ) {
	$please_login_message = sprintf(
		__( 'Before purchasing your tickets, please <a href="%s">log in with your WordPress.org account</a>*.', 'wordcamporg' ),
		wp_login_url( get_redirect_return_url() )
	);

	$message = str_replace(
		__( 'Please use your <strong>WordPress.org</strong>* account to log in.', 'wordcamporg' ),
		$please_login_message,
		wcorg_login_message( '', get_redirect_return_url() )
	);

	return $message;
}

/**
 * Get the URL to return to after logging in or creating an account.
 *
 * @todo It'd be better to refactor CampTix so addons can be accessed directly by the class name,
 *       but there could be backcompat issues, etc, and there's no time to deal with that right now.
 *
 * @return string
 */
function get_redirect_return_url() {
	/** @var $camptix \CampTix_Plugin */
	global $camptix;
	$camptix_url = false;

	foreach ( $camptix->addons_loaded as $addon ) {
		if ( is_a( $addon, 'CampTix_Require_Login' ) ) {
			/** @var $addon \CampTix_Require_Login */
			$camptix_url = $addon->get_redirect_return_url();
		}
	}

	if ( ! $camptix_url ) {
		$camptix_url = $camptix->get_tickets_url();
	}

	return $camptix_url;
}

/**
 * Define the error messages that correspond to our custom error codes.
 *
 * @param array $errors
 */
function add_form_start_error_messages( $errors ) {
	/** @var $camptix \CampTix_Plugin */
	global $camptix;

	if ( isset( $errors['cannot_edit_registration_closed'] ) ) {
		$camptix->error( __(
			"To help ensure that registration goes smoothly during WordCamp, we've stopped accepting new ticket purchases and are no longer allowing changes to be made to existing tickets.",
			'wordcamporg'
		) );
	}
}

/**
 * Perform various actions when ticket sales are opened
 *
 * @param string $new_status
 * @param string $old_status
 * @param \WP_Post $tickets_page
 */
function ticket_sales_opened( $new_status, $old_status, $tickets_page ) {
	if ( 'publish' != $new_status || 'publish' == $old_status || ! has_shortcode( $tickets_page->post_content, 'camptix' ) ) {
		return;
	}

	$all_pages = get_posts( array(
		'post_status'    => 'any',
		'post_type'      => 'page',
		'posts_per_page' => -1,
	) );

	foreach ( $all_pages as $attendees_page ) {
		if ( 'publish' != $attendees_page->post_status && has_shortcode( $attendees_page->post_content, 'camptix_attendees' ) ) {
			wp_publish_post( $attendees_page );
			assign_no_sidebar_template( $attendees_page );
			add_attendees_page_to_primary_menu( $attendees_page );
			break;
		}
	}
}

/**
 * Track payment result stats
 *
 * @param string $payment_token
 * @param int    $result
 * @param array  $data
 */
function track_payment_results( $payment_token, $result, $data ) {
	if ( is_sandboxed() ) {
		return;
	}

	$valid_results = array(
		CampTix_Plugin::PAYMENT_STATUS_COMPLETED     => 'purchased',
		CampTix_Plugin::PAYMENT_STATUS_FAILED        => 'failed',
		CampTix_Plugin::PAYMENT_STATUS_CANCELLED     => 'cancelled',
		CampTix_Plugin::PAYMENT_STATUS_PENDING       => 'pending',
		CampTix_Plugin::PAYMENT_STATUS_TIMEOUT       => 'timeout',
		CampTix_Plugin::PAYMENT_STATUS_REFUNDED      => 'refunded',
		CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED => 'refund-failed',
	);

	$stat_key = ( array_key_exists( $result, $valid_results ) ) ? $valid_results[ $result ] : null;

	if ( ! $stat_key ) {
		return;
	}

	/*
	 * Stats are sent to the local host in dev environments, to avoid distorting the real stats
	 *
	 * This is better than returning early, because that would create a situation where an entire function would
	 * go un-tested until it was deployed to production. The runtime differences between development and
	 * production should always be kept as minimal as possible.
	 */
	$request_domain = 'production' === WORDCAMP_ENVIRONMENT ? 'stats.wordpress.com' : 'wordcamp.test';
	$request_url    = sprintf( 'https://%s/g.gif?v=wpcom-no-pv&x_wcorg-tickets=%s', $request_domain, $stat_key );
	$request_args   = array( 'blocking' => false );
	$request_result = wp_remote_get( esc_url_raw( $request_url ), $request_args );
}

/**
 * Returns true if CampTix is running in sandbox mode.
 *
 * @return bool
 */
function is_sandboxed() {
	/** @var $camptix CampTix_Plugin */
	global $camptix;
	static $is_sandboxed = null;

	if ( ! is_null( $is_sandboxed ) ) {
		return $is_sandboxed;
	}

	$options      = $camptix->get_options();
	$is_sandboxed = false;

	// If the PayPal sandbox checkbox is set to true in manual settings
	if ( isset( $options['payment_options_paypal']['sandbox'] ) && $options['payment_options_paypal']['sandbox'] ) {
		$is_sandboxed = true;
	}
	if ( isset( $options['payment_options_stripe']['sandbox'] ) && $options['payment_options_stripe']['sandbox'] ) {
		$is_sandboxed = true;
	}

	// If the WordCamp sandbox is picked from the predefs
	if ( ! empty( $options['payment_options_paypal']['api_predef'] ) && 'wordcamp-sandbox' == $options['payment_options_paypal']['api_predef'] ) {
		$is_sandboxed = true;
	}
	if ( ! empty( $options['payment_options_stripe']['api_predef'] ) && 'wpcs-sandbox' == $options['payment_options_stripe']['api_predef'] ) {
		$is_sandboxed = true;
	}

	return $is_sandboxed;
}

/**
 * Assign the template with no sidebar to the Attendees page
 *
 * @param \WP_Post $attendees_page
 */
function assign_no_sidebar_template( $attendees_page ) {
	switch( get_template() ) {
		case 'twentyten':
			$page_template = 'onecolumn-page.php';
			break;

		case 'twentytwelve':
			$page_template = 'page-templates/full-width.php';
			break;

		case 'twentyfourteen':
			$page_template = 'page-templates/full-width.php';
			break;

		case 'wordcamp-base':
			$page_template = 'template-full-width.php';
			break;

		case 'twentyeleven':
		case 'twentythirteen':
		default:
			$page_template = false;
			break;
	}

	if ( $page_template ) {
		update_post_meta( $attendees_page->ID, '_wp_page_template', $page_template );
	} else {
		delete_post_meta( $attendees_page->ID, '_wp_page_template' );
	}
}

/**
 * Add the Attendees page to the primary menu
 *
 * @param \WP_Post $attendees_page
 */
function add_attendees_page_to_primary_menu( $attendees_page ) {
	$menu_locations = get_nav_menu_locations();

	if ( isset( $menu_locations['primary'] ) && $menu_locations['primary'] ) {
		$existing_menu_items = wp_get_nav_menu_items( $menu_locations['primary'] );
		$existing_menu_items = wp_list_pluck( $existing_menu_items, 'object_id' );

		if ( ! in_array( $attendees_page->ID, $existing_menu_items ) ) {
			$attendees_menu_item = array(
				'menu-item-object-id' => $attendees_page->ID,
				'menu-item-object'    => $attendees_page->post_type,
				'menu-item-type'      => 'post_type',
				'menu-item-title'     => __( 'Attendees', 'camptix' ),
				'menu-item-status'    => 'publish',
			);
			wp_update_nav_menu_item( $menu_locations['primary'], 0, $attendees_menu_item );
		}
	}
}

/*
 * Determines the name ordering scheme on a per-blog basis
 */
function set_name_order( $order ) {
	/*
	 * These cities normally use an alternate order, but used western in the past, because alternate
	 * orders were not available. Attendees entered their names in reverse in order to get them to
	 * appear correctly, so switching to eastern now would result in the names appearing wrong.
	 */
	$western_back_compat = array(
		112, // 2011.tokyo
		217, // 2012.tokyo
		406, // 2014.tokyo
		558, // 2015.tokyo
	);

	// These cities should always use an alternate order
	$alternate_orders = array(
		'tokyo' => 'eastern',
	);

	if ( in_array( get_current_blog_id(), $western_back_compat, true ) ) {
		$order = 'western';
	} else {
		$current_city = wcorg_get_url_part( site_url(), 'city' );

		if ( array_key_exists( $current_city, $alternate_orders ) ) {
			// todo PHP Warning:  array_key_exists(): The first argument should be either a string or an integer
			$order = $alternate_orders[ $current_city ];
		}
	}

	return $order;
}

/**
 * Prevent attendees from making changes to their information after registration has closed.
 *
 * @param \WP_Post $attendee
 */
function disable_attendee_edits( $attendee ) {
	/** @var $camptix \CampTix_Plugin */
	global $camptix;

	$disabled_sites_tickets = array(
		364 => array( 648462, 648704 ),  // 2014.sf
	);

	$current_site_id    = get_current_blog_id();
	$attendee_ticket_id = get_post_meta( $attendee->ID, 'tix_ticket_id', true );

	foreach ( $disabled_sites_tickets as $disabled_site_id => $disabled_ticket_ids ) {
		if ( $current_site_id == $disabled_site_id && in_array( $attendee_ticket_id, $disabled_ticket_ids ) ) {
			$camptix->error_flag( 'cannot_edit_registration_closed' );
			$camptix->redirect_with_error_flags();
		}
	}
}

/**
 * Log when published attendees are cancelled.
 *
 * @param string  $to
 * @param string  $from
 * @param WP_Post $post
 */
function log_publish_to_cancel( $to, $from, $post ) {
	/** @var $camptix CampTix_Plugin */
	global $camptix;

	if ( 'tix_attendee' !== $post->post_type || $to === $from ) {
		return;
	}

	if ( 'publish' === $from && 'cancel' === $to ) {
		$camptix->log( 'Publish to cancel transition, possible bug.', $post->ID );
	}
}

/**
 * Enable debugging info for super admins.
 *
 * It's not done for others because it can expose sensitive information.
 */
function camptix_debug() {
	if ( ! current_user_can( 'manage_network' ) ) {
		return;
	}

	add_filter( 'camptix_debug', '__return_true' );
}

/**
 * Enable addons that are disabled by default
 *
 * @param array $addons
 *
 * @return array
 */
function load_addons( $addons ) {
	/** @var $camptix \CampTix_Plugin */
	global $camptix;

	$require_login_sites = apply_filters( 'camptix_extras_require_login_site_ids', array(
		206,    // testing.wordcamp.org
		364,    // 2014.sf.wordcamp.org
		447,    // belohorizonte.wordcamp.org/2015
	) );

	if ( in_array( get_current_blog_id(), $require_login_sites, true ) ) {
		/*
		 * todo -- NOTE: when this is opened up for all camps, it will have to be enabled ONLY on WCSF14 and sites
		 * that haven't opened tickets yet. Otherwise CampTix_Requre_login::hide_unconfirmed_attendees()
		 * will break pre-existing [attendee] pages.
		 */
		$addons['require-login'] = $camptix->get_default_addon_path( 'require-login.php' );
	}

	$addons['track-attendance'] = $camptix->get_default_addon_path( 'track-attendance.php' );

	return $addons;
}

/**
 * WordCamp-specific addons.
 */
function load_custom_addons() {
	// Allergy field
	require_once( __DIR__ . '/addons/allergy.php' );
	// Accommodations field
	require_once( __DIR__ . '/addons/accommodations.php' );
	// Code of Conduct field
	require_once( __DIR__ . '/addons/code-of-conduct.php' );
	// Privacy field
	require_once( __DIR__ . '/addons/privacy.php' );
}

/**
 * Modify the list of default questions on the ticket registration form.
 *
 * @param string $default_fields
 *
 * @return string
 */
function modify_default_fields_list( $default_fields ) {
	return __( 'Top three fields: First name, last name, e-mail address.<br />Bottom four fields: Attendee list opt-out, life-threatening allergy, accessibility needs, Code of Conduct agreement.', 'wordcamporg' );
}

/**
 * Modify CampTix's default capabilities
 */
function modify_capabilities( $capabilities ) {
	$capabilities['delete_attendees'] = 'manage_network';
	$capabilities['refund_all']       = 'manage_network';

	return $capabilities;
}

/**
 * Modify CampTix's default options
 */
function modify_default_options( $options ) {
	$options['payment_methods']        = array( 'paypal'     => true               );
	$options['payment_options_paypal'] = array( 'api_predef' => 'wordcamp-sandbox' );

	return $options;
}

/**
 * Append a footer string to some email templates.
 *
 * This copies some email template strings from the options array into new array items where the key has
 * '_with_footer' appended. This ensures the footer is included in the desired templates without it getting
 * repeatedly appended to the option values every time the templates are customized.
 *
 * @param array $options
 *
 * @return array
 */
function modify_email_templates( $options ) {
	$sponsors_string = get_global_sponsors_string();
	$donation_string = get_donation_string();

	$email_footer_string = "\n\n===\n\n$sponsors_string\n\n$donation_string";

	$templates_that_need_footers = array(
		'email_template_single_purchase',
		'email_template_multiple_purchase',
		'email_template_multiple_purchase_receipt',
	);

	foreach ( $templates_that_need_footers as $template ) {
		// We can't add the string to the original option or it will keep getting added over and over again
		// whenever the email templates are customized and saved.
		$options[ $template . '_with_footer' ] = $options[ $template ] . $email_footer_string;
	}

	return $options;
}

/**
 * Specify an alternate for email templates that should have our custom footer.
 *
 * @param string $template_slug
 *
 * @return string
 */
function switch_email_template( $template_slug ) {
	$templates_that_need_footers = array(
		'email_template_single_purchase',
		'email_template_multiple_purchase',
		'email_template_multiple_purchase_receipt',
	);

	if ( in_array( $template_slug, $templates_that_need_footers, true ) ) {
		$template_slug .= '_with_footer';
	}

	return $template_slug;
}

/**
 * Get a string for HTML email footers listing global sponsors.
 *
 * @param array $sponsor_args Args for filtering which sponsors to include.
 *
 * @return string
 */
function get_global_sponsors_string( $sponsor_args = array() ) {
	$sponsor_message_html = '';
	$sponsors             = get_global_sponsors( $sponsor_args );
	$sponsor_count        = count( $sponsors );

	if ( $sponsor_count > 0 ) {
		$sponsors = wp_list_pluck( $sponsors, 'name' );

		$sponsors = array_map( function( $string ) {
			return "<strong>$string</strong>";
		}, $sponsors );

		shuffle( $sponsors );

		switch ( $sponsor_count ) {
			case 1 :
				$sponsors_string = array_shift( $sponsors );
				break;
			case 2 :
				$sponsors_string = sprintf(
					/* translators: The %s placeholders are the names of sponsors. */
					__( '%s and %s', 'wordcamporg' ),
					array_shift( $sponsors ),
					array_shift( $sponsors )
				);
				break;
			default :
				$last_sponsor = array_pop( $sponsors );

				$sponsors_string = sprintf(
					'%1$s%2$s %3$s',
					/* translators: Used between sponsor names in a list, there is a space after the comma. */
					implode( _x( ', ', 'list item separator', 'wordcamporg' ), $sponsors ),
					/* translators: List item separator, used before the last sponsor name in the list. */
					__( ', and', 'wordcamporg' ),
					$last_sponsor
				);
				break;
		}

		$intro = __( 'WordPress Global Community Sponsors help fund WordCamps and meetups around the world.', 'wordcamporg' );

		$thank_you = sprintf(
			/* translators: %1$s: list of sponsor names; %2$s: URL; */
			_n(
				'Thank you to %1$s for <a href="%2$s">its support</a>!',
				'Thank you to %1$s for <a href="%2$s">their support</a>!',
				$sponsor_count,
				'wordcamporg'
			),
			$sponsors_string,
			'https://central.wordcamp.org/global-community-sponsors/'
		);

		if ( isset( $sponsor_args['region_id'], $sponsor_args['level_id'] ) ) {
			$sponsor_level = get_sponsorship_level_name_from_id( $sponsor_args['level_id'] );
			$sponsor_region = get_sponsorship_region_description_from_id( $sponsor_args['region_id'] );

			if ( $sponsor_level && $sponsor_region ) {
				$thank_you = sprintf(
					/* translators: %1$s: sponsorship type; %2$s: list of sponsor names; %3$s: URL; %4$s: sponsorship region; */
					_n(
						'Thank you to our %1$s sponsor %2$s for <a href="%3$s">its support</a> in %4$s!',
						'Thank you to our %1$s sponsors %2$s for <a href="%3$s">their support</a> in %4$s!',
						$sponsor_count,
						'wordcamporg'
					),
					$sponsor_level,
					$sponsors_string,
					'https://central.wordcamp.org/global-community-sponsors/',
					$sponsor_region
				);
			}
		}

		$sponsor_message_html = sprintf( '%s %s', $intro, $thank_you );
	}

	return $sponsor_message_html;
}

/**
 * Get a string for HTML email footers requesting donations to WPF.
 *
 * @return string
 */
function get_donation_string() {
	return sprintf(
		/* translators: %s is a placeholder for a URL. */
		__( 'Do you love WordPress events? To support open source education and charity hackathons, please <a href="%s">donate to the WordPress Foundation</a>.', 'wordcamporg' ),
		'https://wordpressfoundation.org/donate/'
	);
}

/**
 * Get an array of Global Sponsor names and URLs.
 *
 * @param array $args Args for filtering which sponsors to include.
 *
 * @return array
 */
function get_global_sponsors( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'region_id' => '',
		'level_id'  => '',
	) );

	$sponsors = array();

	switch_to_blog( BLOG_ID_CURRENT_SITE );

	$sponsor_posts = get_posts( array(
		'post_type'   => 'mes',
		'post_status' => 'publish',
		'numberposts' => -1,
	) );

	foreach ( $sponsor_posts as $sponsor_post ) {
		$sponsorships = $sponsor_post->mes_regional_sponsorships;

		if ( $args['region_id'] ) {
			if ( ! isset( $sponsorships[ $args['region_id'] ] ) || empty( $sponsorships[ $args['region_id'] ] ) ) {
				continue;
			}

			$sponsorships = array_intersect_key( $sponsorships, array( $args['region_id'] => '' ) );
		}

		if ( $args['level_id'] ) {
			$levels = array_map( 'absint', $sponsorships );

			if ( ! in_array( $args['level_id'], $levels, true ) ) {
				continue;
			}
		}

		$sponsors[] = array(
			'name' => $sponsor_post->post_title,
			'url'  => $sponsor_post->mes_website,
		);
	}

	restore_current_blog();

	return $sponsors;
}

/**
 * Get a sponsorship region description.
 *
 * The two sponsorship regions currently in use are hard-coded so they can be translated. If a different region is
 * specified, this function will attempt to retrieve a description string from the database (which won't be translated).
 *
 * @param int $region_id
 *
 * @return string
 */
function get_sponsorship_region_description_from_id( $region_id ) {
	$region_id          = absint( $region_id );
	$region_description = '';

	if ( $region_id ) {
		// Make the two standard options translatable.
		switch( $region_id ) {
			case 558366 : // Eastern
				$region_description = __( 'Europe, Africa, and Asia Pacific', 'wordcamporg' );
				break;
			case 558365 : // Western
				$region_description = __( 'North, Central, and South America', 'wordcamporg' );
				break;
		}

		if ( ! $region_description ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE );

			$region = get_term( $region_id, 'mes-regions' );

			if ( $region instanceof \WP_Term ) {
				$region_description = $region->description;
			}

			restore_current_blog();
		}
	}

	return $region_description;
}

/**
 * Get the name of a sponsorship level.
 *
 * @param int $level_id
 *
 * @return string
 */
function get_sponsorship_level_name_from_id( $level_id ) {
	$level_id   = absint( $level_id );
	$level_name = '';

	if ( $level_id ) {
		switch_to_blog( BLOG_ID_CURRENT_SITE );

		$level_name = get_the_title( $level_id );

		restore_current_blog();
	}

	return $level_name;
}

/**
 * Render an HTML message from the plain-text version
 *
 * @param string|false $html_message
 * @param \PHPMailer   $phpmailer
 *
 * @return string
 */
function render_html_emails( $html_message, $phpmailer ) {
	if ( ! is_callable( 'CampTix_Plugin::sanitize_format_html_message' ) ) {
		return $html_message;
	}

	$logo_url = plugins_url( '/images/wordpress-logo.png', __FILE__ );

	ob_start();
	require( __DIR__ . '/views/html-mail-header.php' );
	echo \CampTix_Plugin::sanitize_format_html_message( $phpmailer->Body );
	require( __DIR__ . '/views/html-mail-footer.php' );
	$html_message = ob_get_clean();

	return $html_message;
}

/**
 * Extend the introduction message for each camp in the tshirt report
 *
 * @todo It'd probably be better to pull estimates from the tickets, rather than the wcpt post. Count total # of
 * tickets, remove any that have "live" or "stream" in the name, then say "Expecting up to N attendees"
 *
 * @param string $message
 * @param int    $site_id
 * @param array  $sizes
 *
 * @return string
 */
function tshirt_report_intro_message( $message, $site_id, $sizes ) {
	switch_to_blog( $site_id );

	$wordcamp = get_wordcamp_post();

	if ( ! empty( $wordcamp->meta['Number of Anticipated Attendees'][0] ) ) {
		$message = sprintf(
			"<p>This camp is expecting %d attendees.</p>",
			$wordcamp->meta['Number of Anticipated Attendees'][0]
		);
	}

	restore_current_blog();
	return $message;
}

/**
 * Modify the output of the `[camptix]` shortcode when certain conditions are met.
 *
 * @param string $shortcode_contents
 * @param string $tix_action
 *
 * @return string
 */
function modify_shortcode_contents( $shortcode_contents, $tix_action ) {
	switch ( $tix_action ) {
		case 'access_tickets' : // Screen after purchase is complete.
			$content_end = '</div><!-- #tix -->';

			$current_wordcamp = get_wordcamp_post();
			$region_id = isset( $current_wordcamp->meta['Multi-Event Sponsor Region'][0] ) ? $current_wordcamp->meta['Multi-Event Sponsor Region'][0] : '';

			$sponsors_string = get_global_sponsors_string( array(
				'region_id' => $region_id,
				'level_id'  => 3040794, // Gold
			) );
			$donation_string = get_donation_string();

			if ( false !== strpos( $shortcode_contents, $content_end ) ) {
				$shortcode_contents = str_replace(
					$content_end,
					wpautop( "$sponsors_string\n\n$donation_string" ) . $content_end,
					$shortcode_contents
				);
			}
			break;
	}

	return $shortcode_contents;
}

/**
 * Modify the list of personal data eraser callbacks.
 *
 * @param array $erasers
 *
 * @return array mixed
 */
function modify_erasers( $erasers ) {
	// Temporarily disable the default eraser callbacks for CampTix.
	unset( $erasers['camptix-attendee'] );

	return $erasers;
}

/**
 * Short-circuit the CampTix attendee data erasure callback if the attendee data is still within the retention period.
 *
 * @param bool|string $erase
 * @param WP_Post     $post
 *
 * @return bool|string
 */
function retain_attendee_data( $erase, $post ) {
	$created          = strtotime( get_the_date( 'c', $post ) );
	$now              = time();
	$retention_period = YEAR_IN_SECONDS * 3;

	if ( ( $now - $created ) < $retention_period ) {
		return __( 'Attendee data could not be anonymized because it is still within the data retention period.', 'wordcamporg' );
	}

	return $erase;
}

/**
 * Add a notice about data confidentiality to the Edit Attendee screen.
 */
function admin_notice_attendee_privacy() {
	$screen = get_current_screen();

	if ( 'tix_attendee' === $screen->id ) {
		$notice_classes = 'notice notice-info';
		$message        = wp_kses_post( sprintf(
			__( 'The personal information displayed here is <strong>confidential</strong>, and should not be shown publicly, except under the circumstances described in the <a href="%s">privacy policy</a>.', 'wordcamporg' ),
			esc_url( get_privacy_policy_url() )
		) );

		printf(
			'<div class="%1$s">%2$s</div>',
			esc_attr( $notice_classes ),
			wpautop( $message )
		);
	}
}
