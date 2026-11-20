<?php
/**
 * Event signup codes.
 *
 * Lets education events share a link like https://login.wordpress.org/register?event=K7P4M2QX
 * so a room of people on one network can register without tripping the per-IP registration limit.
 *
 * A valid code only resets the per-IP counter for the submitting IP. reCAPTCHA, heuristics,
 * blocked words and email confirmation all still apply. Every pending registration made with
 * a code is tagged with it, so a leaked code can be revoked and its signups reviewed.
 *
 * A confirmed account keeps a link to the code it was made with, so what its owner goes on to do
 * on WordPress.org can be counted against the event that brought them in. Counts leave this site,
 * people don't: wporg_login_event_code_report() holds no names, emails, addresses or codes, and a
 * country is shown only once a code has enough accounts for a count not to be a person.
 *
 * Codes are managed in wp-admin by users with the `manage_event_codes` capability, who can fill a
 * code's name, tracker ID and program from WordCamp Central's public list of events.
 *
 * @package wporg-login
 */

declare( strict_types = 1 );

/**
 * Post type that stores event codes.
 */
const WPORG_EVENT_CODE_POST_TYPE = 'wporg_event_code';

/**
 * Characters a code is made of. No 0/O, 1/I/L.
 */
const WPORG_EVENT_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/**
 * Length of a code.
 */
const WPORG_EVENT_CODE_LENGTH = 8;

/**
 * Hard cap on signups per code.
 */
const WPORG_EVENT_CODE_MAX_SIGNUPS = 200;

/**
 * Hard cap on how long a code works after its start date, in seconds.
 */
const WPORG_EVENT_CODE_MAX_DURATION = 3 * DAY_IN_SECONDS;

/**
 * User meta key holding the ID of the event code post an account was made with.
 *
 * On the user rather than in the registration record because user meta is indexed and every
 * WordPress.org site can read it, which is what lets an event's accounts be joined to their later
 * activity, and because registration records are purged after eight weeks. Never shown publicly.
 */
const WPORG_EVENT_CODE_USER_META = 'wporg_entry_event';

/**
 * Option holding the report rows of deleted codes that accounts still point at.
 */
const WPORG_EVENT_CODE_ARCHIVE_OPTION = 'wporg_event_code_archive';

/**
 * Confirmed accounts a code needs before its countries are reported at all.
 */
const WPORG_EVENT_CODE_REPORT_MIN = 10;

/**
 * Accounts a country needs to be reported on its own; smaller counts are pooled as "other".
 */
const WPORG_EVENT_CODE_REPORT_MIN_CELL = 5;

/**
 * WordCamp Central's public list of events: scheduled and past WordCamps and Campus Connect
 * events alike, with their start date and venue.
 */
const WPORG_EVENT_CODE_CENTRAL_API = 'https://central.wordcamp.org/wp-json/wp/v2/wordcamps';

/**
 * Programs a code can belong to, key => label.
 *
 * @return array
 */
function wporg_login_event_code_programs(): array {
	/**
	 * Filters the programs an event code can belong to.
	 *
	 * @param array $programs Program key => label.
	 */
	return apply_filters(
		'wporg_login_event_code_programs',
		array(
			'campus-connect'       => 'WordPress Campus Connect',
			'credits'              => 'WP Credits',
			'student-clubs'        => 'Student Clubs',
			'contributor-day'      => 'Contributor Day',
			'facilitator-training' => 'Facilitator Training Program',
			'other'                => 'Other',
		)
	);
}

/**
 * Register the event code post type.
 *
 * Title is the event name. The post date is when the code becomes active (scheduling works),
 * trashing or unpublishing a code revokes it.
 *
 * @return void
 */
function wporg_login_register_event_code_post_type(): void {
	register_post_type(
		WPORG_EVENT_CODE_POST_TYPE,
		array(
			'labels'               => array(
				'name'          => 'Event Codes',
				'singular_name' => 'Event Code',
				'add_new_item'  => 'Add Event Code',
				'edit_item'     => 'Edit Event Code',
			),
			'public'               => false,
			'show_ui'              => true,
			'show_in_rest'         => false,
			'menu_icon'            => 'dashicons-tickets-alt',
			'supports'             => array( 'title' ),
			'map_meta_cap'         => true,
			'capabilities'         => array_fill_keys(
				array(
					'create_posts',
					'edit_posts',
					'edit_others_posts',
					'edit_published_posts',
					'edit_private_posts',
					'publish_posts',
					'read_private_posts',
					'delete_posts',
					'delete_others_posts',
					'delete_published_posts',
					'delete_private_posts',
				),
				'manage_event_codes'
			),
			'register_meta_box_cb' => 'wporg_login_event_code_meta_box',
		)
	);
}
add_action( 'init', 'wporg_login_register_event_code_post_type' );

/**
 * People who already manage registrations (promote_users) can also manage event codes.
 *
 * @param array $allcaps All capabilities of the user.
 * @return array
 */
function wporg_login_event_code_caps( array $allcaps ): array {
	if ( ! empty( $allcaps['promote_users'] ) ) {
		$allcaps['manage_event_codes'] = true;
	}

	return $allcaps;
}
add_filter( 'user_has_cap', 'wporg_login_event_code_caps' );

/**
 * Register a role that can manage event codes.
 */
function wporg_login_register_event_code_role(): void {
	if ( get_role( 'event_code_manager' ) ) {
		return;
	}

	add_role(
		'event_code_manager',
		'Event Code Manager',
		array(
			'read'               => true,
			'manage_event_codes' => true,
		)
	);
}
add_action( 'admin_init', 'wporg_login_register_event_code_role' );

/**
 * Normalise a user-supplied code. Returns an empty string if it can't be a valid code.
 *
 * @param mixed $code Raw code.
 * @return string
 */
function wporg_login_normalize_event_code( mixed $code ): string {
	if ( ! is_string( $code ) ) {
		return '';
	}

	$code    = strtoupper( trim( $code ) );
	$pattern = sprintf( '/^[%s]{%d}$/', WPORG_EVENT_CODE_ALPHABET, WPORG_EVENT_CODE_LENGTH );

	return preg_match( $pattern, $code ) ? $code : '';
}

/**
 * Generate a new, unused code.
 *
 * @return string
 */
function wporg_login_generate_event_code(): string {
	$max = strlen( WPORG_EVENT_CODE_ALPHABET ) - 1;

	do {
		$code = '';
		for ( $i = 0; $i < WPORG_EVENT_CODE_LENGTH; $i++ ) {
			$code .= WPORG_EVENT_CODE_ALPHABET[ random_int( 0, $max ) ];
		}
		// 'any' skips trashed posts, and a restored code must not collide.
	} while ( wporg_login_find_event_code_post( $code, array( 'any', 'trash' ) ) );

	return $code;
}

/**
 * Find the post for a code.
 *
 * @param string       $code   Normalised code.
 * @param string|array $status Post status to match.
 * @return WP_Post|null
 */
function wporg_login_find_event_code_post( string $code, string|array $status = 'publish' ): ?WP_Post {
	// The theme short-circuits all front-end queries, see functions.php.
	$short_circuited = remove_filter( 'posts_pre_query', '__return_empty_array' );

	$posts = get_posts(
		array(
			'post_type'        => WPORG_EVENT_CODE_POST_TYPE,
			'post_status'      => $status,
			'meta_key'         => '_event_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small, admin-curated table.
			'meta_value'       => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small, admin-curated table.
			'posts_per_page'   => 1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);

	if ( $short_circuited ) {
		add_filter( 'posts_pre_query', '__return_empty_array' );
	}

	return $posts[0] ?? null;
}

/**
 * The effective limits for a code, with the hard caps applied.
 *
 * @param WP_Post $post Event code post.
 * @return array {
 *     @type int $expires Unix timestamp.
 *     @type int $cap     Max signups.
 *     @type int $uses    Signups so far.
 * }
 */
function wporg_login_event_code_limits( WP_Post $post ): array {
	$starts      = (int) get_post_time( 'U', true, $post ) ?: time(); // Unsaved drafts have no date yet.
	$max_expires = $starts + WPORG_EVENT_CODE_MAX_DURATION;
	$expires     = (int) get_post_meta( $post->ID, '_expires', true );
	$cap         = (int) get_post_meta( $post->ID, '_cap', true );

	return array(
		'expires' => $expires ? min( $expires, $max_expires ) : $max_expires,
		'cap'     => max( 1, min( $cap ?: WPORG_EVENT_CODE_MAX_SIGNUPS, WPORG_EVENT_CODE_MAX_SIGNUPS ) ),
		'uses'    => (int) get_post_meta( $post->ID, '_uses', true ),
	);
}

/**
 * Get the event code post for a code if it can be used right now.
 *
 * @param mixed $code Raw code.
 * @return WP_Post|null
 */
function wporg_login_get_valid_event_code( mixed $code ): ?WP_Post {
	$code = wporg_login_normalize_event_code( $code );
	if ( ! $code ) {
		return null;
	}

	$post = wporg_login_find_event_code_post( $code );
	if ( ! $post ) {
		return null;
	}

	$limits = wporg_login_event_code_limits( $post );
	if ( time() >= $limits['expires'] || $limits['uses'] >= $limits['cap'] ) {
		return null;
	}

	return $post;
}

/**
 * Whether the current request carries a usable event code.
 *
 * @return bool
 */
function wporg_login_has_valid_event_code(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a strict pattern.
	return (bool) wporg_login_get_valid_event_code( wp_unslash( $_REQUEST['event'] ?? '' ) );
}

/**
 * Carry the code through the registration form and tell the person which event they're joining.
 *
 * @return void
 */
function wporg_login_event_code_register_form(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a strict pattern.
	$post = wporg_login_get_valid_event_code( wp_unslash( $_REQUEST['event'] ?? '' ) );
	if ( ! $post ) {
		return;
	}

	printf(
		'<input type="hidden" name="event" value="%s" /><div class="message info"><p>%s %s</p></div>',
		esc_attr( get_post_meta( $post->ID, '_event_code', true ) ),
		esc_html(
			sprintf(
				/* translators: %s: Event name. */
				__( 'You are creating an account for %s.', 'wporg' ),
				get_the_title( $post )
			)
		),
		esc_html__( 'WordPress.org counts what accounts made at events go on to do here, to see what the event led to. Counts only, never names.', 'wporg' )
	);
}
add_action( 'register_form', 'wporg_login_event_code_register_form' );

/**
 * The event code used for the registration being processed in this request.
 *
 * @param WP_Post|null $set Set the code for this request.
 * @return WP_Post|null
 */
function wporg_login_current_event_code( ?WP_Post $set = null ): ?WP_Post {
	static $current = null;

	if ( $set ) {
		$current = $set;
	}

	return $current;
}

/**
 * Before registration checks run, reset the per-IP registration counter for this IP.
 * A blocked IP stays blocked.
 *
 * Runs early so it happens before the per-IP limit is checked. Never blocks a registration.
 *
 * @param mixed $pre Pre-registration result.
 * @return mixed Unchanged.
 */
function wporg_login_event_code_pre_registration( mixed $pre ): mixed {
	// The resend-confirmation endpoint runs this filter too, without reCAPTCHA.
	if ( null !== $pre || wp_is_serving_rest_request() || empty( $_POST['event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public signup form.
		return $pre;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a strict pattern.
	$post = wporg_login_get_valid_event_code( wp_unslash( $_POST['event'] ) );
	if ( ! $post ) {
		return $pre;
	}

	wporg_login_current_event_code( $post );

	// Same cache group the IP Allow / IP Block admin tools use. 999 = blocked.
	wp_cache_add_global_groups( array( 'registration-limit' ) );
	$ip    = $_SERVER['REMOTE_ADDR']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Cache key only, matches the IP tools.
	$count = wp_cache_get( $ip, 'registration-limit' );
	if ( is_numeric( $count ) && (int) $count < 999 ) {
		wp_cache_delete( $ip, 'registration-limit' );
	}

	return $pre;
}
add_filter( 'wporg_login_pre_registration', 'wporg_login_event_code_pre_registration', 1 );

/**
 * Add one to a counter on an event code.
 *
 * @param int    $post_id Event code post ID.
 * @param string $key     Meta key of the counter.
 * @return void
 */
function wporg_login_event_code_increment( int $post_id, string $key ): void {
	global $wpdb;

	// Codes older than a counter don't have its row yet, and the update below can't create it.
	add_post_meta( $post_id, $key, 0, true );

	// Atomic, so a room signing up at once doesn't lose counts.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = CAST( meta_value AS UNSIGNED ) + 1 WHERE post_id = %d AND meta_key = %s",
			$post_id,
			$key
		)
	);
	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * Count the signup against its code and tag the new pending registration with it.
 *
 * Counting here rather than before registration checks means attempts that get blocked don't use up the code.
 *
 * @param array $pending_user Pending user record.
 * @return array
 */
function wporg_login_event_code_tag_pending_user( array $pending_user ): array {
	$post = wporg_login_current_event_code();

	if ( $post && empty( $pending_user['pending_id'] ) ) {
		$pending_user['meta']['event_code'] = get_post_meta( $post->ID, '_event_code', true );

		wporg_login_event_code_increment( $post->ID, '_uses' );
	}

	return $pending_user;
}
add_filter( 'wporg_login_registration_update_pending_user', 'wporg_login_event_code_tag_pending_user' );

/**
 * Link a confirmed account to the code it was made with, and count it.
 *
 * This filter runs on every save of a pending record. `event_code_recorded` on the record is what
 * keeps later saves cheap, and re-saving the records confirmed before this existed is the backfill.
 * The user meta is the one source of truth: the counters move only when the link is added, so a
 * record saved again without its marker cannot count an account twice.
 *
 * @param array $pending_user Pending user record.
 * @return array
 */
function wporg_login_event_code_record_account( array $pending_user ): array {
	$code = $pending_user['meta']['event_code'] ?? '';

	if ( ! $code || empty( $pending_user['created'] ) || ! empty( $pending_user['meta']['event_code_recorded'] ) ) {
		return $pending_user;
	}

	// A revoked code still counts: the account was made while it worked.
	$post = wporg_login_find_event_code_post( $code, array( 'any', 'trash' ) );
	$user = get_user_by( 'login', $pending_user['user_login'] );

	// Nothing to link, now or on any later save: the code was purged before this ran, or the
	// account has since been deleted.
	if ( ! $post || ! $user ) {
		$pending_user['meta']['event_code_recorded'] = 'none';

		return $pending_user;
	}

	if ( add_user_meta( $user->ID, WPORG_EVENT_CODE_USER_META, $post->ID, true ) ) {
		wporg_login_event_code_increment( $post->ID, '_confirmed' );

		$country = $pending_user['meta']['registration_ip_country'] ?? '';
		if ( preg_match( '/^[A-Z]{2}$/', $country ) ) {
			wporg_login_event_code_increment( $post->ID, '_country_' . $country );
		}
	}

	$pending_user['meta']['event_code_recorded'] = 1;

	return $pending_user;
}
add_filter( 'wporg_login_registration_update_pending_user', 'wporg_login_event_code_record_account' );

/**
 * Link the accounts confirmed with a code before this existed, once.
 *
 * Re-saves every confirmed registration record that carries a code and no marker, which runs
 * wporg_login_event_code_record_account() over it. Registration records are purged eight weeks
 * after sign-up, so this is for soon after deploy. Running it again finds nothing: every record
 * it touched is marked, linked or settled.
 *
 * @param bool $dry_run Count the records without saving anything.
 * @return array {
 *     @type int $found   Records waiting.
 *     @type int $linked  Records marked as linked (or already linked).
 *     @type int $settled Records marked as having nothing to link.
 * }
 */
function wporg_login_event_code_link_accounts( bool $dry_run = false ): array {
	global $wpdb;

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT pending_id FROM %i WHERE created = 1 AND meta LIKE %s AND meta NOT LIKE %s',
			"{$wpdb->base_prefix}user_pending_registrations",
			'%' . $wpdb->esc_like( '"event_code":"' ) . '%',
			'%' . $wpdb->esc_like( 'event_code_recorded' ) . '%'
		)
	);

	$result = array(
		'found'   => count( $ids ),
		'linked'  => 0,
		'settled' => 0,
	);

	if ( $dry_run ) {
		return $result;
	}

	foreach ( $ids as $id ) {
		$record = wporg_get_pending_user( (int) $id );

		if ( ! $record ) {
			continue;
		}

		wporg_update_pending_user( $record );

		$after  = wporg_get_pending_user( (int) $id );
		$marker = is_array( $after ) ? ( $after['meta']['event_code_recorded'] ?? '' ) : '';

		if ( 'none' === $marker ) {
			++$result['settled'];
		} elseif ( $marker ) {
			++$result['linked'];
		}
	}

	return $result;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Link the accounts confirmed with an event code to it, once.
	 *
	 * Handles every confirmed registration record that carries a code and has not been handled
	 * yet. Registration records are purged eight weeks after sign-up, so run it soon after deploy.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count the records without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wporg-login event-codes link-accounts --url=login.wordpress.org
	 *
	 * @param array $args       Positional arguments, none.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	function wporg_login_event_code_link_accounts_command( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$result  = wporg_login_event_code_link_accounts( $dry_run );

		WP_CLI::success(
			sprintf(
				'%d record(s) found%s: %d linked, %d settled with nothing to link.',
				$result['found'],
				$dry_run ? ' (dry run, nothing changed)' : '',
				$result['linked'],
				$result['settled']
			)
		);
	}
	WP_CLI::add_command( 'wporg-login event-codes link-accounts', 'wporg_login_event_code_link_accounts_command' );
}

/**
 * Keep a deleted code's counts.
 *
 * Trashing revokes a code and the trash is emptied on its own. The accounts made with the code
 * still carry its post ID, so its report row is kept, without the code itself, where the report
 * and the personal data exporter can still find it.
 *
 * @param int $post_id Post about to be deleted.
 * @return void
 */
function wporg_login_event_code_archive( int $post_id ): void {
	$post = get_post( $post_id );

	if ( ! $post || WPORG_EVENT_CODE_POST_TYPE !== $post->post_type || ! get_post_meta( $post_id, '_confirmed', true ) ) {
		return;
	}

	$archive = get_option( WPORG_EVENT_CODE_ARCHIVE_OPTION, array() );
	$row     = wporg_login_event_code_report_row( $post );

	$row['status']       = 'deleted';
	$archive[ $post_id ] = $row;

	update_option( WPORG_EVENT_CODE_ARCHIVE_OPTION, $archive, false );
}
add_action( 'before_delete_post', 'wporg_login_event_code_archive' );

/**
 * Countries a code can report without a count being a person.
 *
 * A code is one room, so nearly everyone registers from the same country and a country split
 * only ever names the one or two who were elsewhere. Nothing is reported under
 * WPORG_EVENT_CODE_REPORT_MIN accounts, and smaller countries are pooled as "other".
 *
 * @param array $counts    Country => confirmed accounts.
 * @param int   $confirmed Confirmed accounts on the code.
 * @return array Country => count, biggest first, "other" last.
 */
function wporg_login_event_code_countries( array $counts, int $confirmed ): array {
	if ( $confirmed < WPORG_EVENT_CODE_REPORT_MIN ) {
		return array();
	}

	arsort( $counts );

	$out   = array();
	$other = 0;

	foreach ( $counts as $country => $count ) {
		if ( $count < WPORG_EVENT_CODE_REPORT_MIN_CELL ) {
			$other += $count;
		} else {
			$out[ $country ] = $count;
		}
	}

	if ( $other ) {
		$out['other'] = $other;
	}

	return $out;
}

/**
 * Tell a person which event their account was made at, in their personal data export.
 *
 * @param array $exporters Registered exporters.
 * @return array
 */
function wporg_login_event_code_register_exporter( array $exporters ): array {
	$exporters['wporg-event-code'] = array(
		'exporter_friendly_name' => 'Event signup code',
		'callback'               => 'wporg_login_event_code_export',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'wporg_login_event_code_register_exporter' );

/**
 * Personal data exporter: the event an account was made at, if any.
 *
 * @param string $email Email address of the person.
 * @param int    $page  Page, unused: there is at most one item.
 * @return array
 */
function wporg_login_event_code_export( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Exporter signature.
	$data = array();
	$user = get_user_by( 'email', $email );
	$id   = $user ? (int) get_user_meta( $user->ID, WPORG_EVENT_CODE_USER_META, true ) : 0;

	if ( $id ) {
		$post    = get_post( $id );
		$archive = get_option( WPORG_EVENT_CODE_ARCHIVE_OPTION, array() );
		$event   = $post ? $post->post_title : (string) ( $archive[ $id ]['event'] ?? '' );

		$data[] = array(
			'group_id'    => 'wporg_event_code',
			'group_label' => 'Event signup code',
			'item_id'     => 'wporg_event_code-' . $id,
			'data'        => array(
				array(
					'name'  => 'Account created at',
					'value' => $event,
				),
			),
		);
	}

	return array(
		'data' => $data,
		'done' => true,
	);
}

/**
 * The counts for one code.
 *
 * @param WP_Post $post Event code post.
 * @return array {
 *     @type int    $id         Post ID, the value stored in WPORG_EVENT_CODE_USER_META.
 *     @type string $event      Event name.
 *     @type string $program    Key of wporg_login_event_code_programs(), empty if not set.
 *     @type int    $tracker_id The event's ID on WordCamp Central, 0 if not set.
 *     @type string $status     Post status, `trash` for a revoked code.
 *     @type string $starts     When the code starts working.
 *     @type string $expires    When the code stops working.
 *     @type int    $signups    Registrations started, including those the spam checks stopped.
 *     @type int    $confirmed  Accounts confirmed.
 *     @type array  $countries  Confirmed accounts by registration country, most first; empty
 *                              under WPORG_EVENT_CODE_REPORT_MIN accounts.
 * }
 */
function wporg_login_event_code_report_row( WP_Post $post ): array {
	$limits    = wporg_login_event_code_limits( $post );
	$confirmed = (int) get_post_meta( $post->ID, '_confirmed', true );
	$countries = array();

	foreach ( get_post_meta( $post->ID ) as $key => $values ) {
		if ( str_starts_with( $key, '_country_' ) ) {
			$countries[ substr( $key, strlen( '_country_' ) ) ] = (int) $values[0];
		}
	}

	return array(
		'id'         => $post->ID,
		'event'      => $post->post_title,
		'program'    => (string) get_post_meta( $post->ID, '_program', true ),
		'tracker_id' => (int) get_post_meta( $post->ID, '_tracker_id', true ),
		'status'     => $post->post_status,
		'starts'     => (string) get_post_time( 'c', true, $post ),
		'expires'    => gmdate( 'c', $limits['expires'] ),
		'signups'    => $limits['uses'],
		'confirmed'  => $confirmed,
		'countries'  => wporg_login_event_code_countries( $countries, $confirmed ),
	);
}

/**
 * The counts for every code, whatever its status, deleted ones included. Counts only, nothing
 * about any one person.
 *
 * Read in-process by whatever reports on the network; nothing serves it over HTTP.
 *
 * @return array[] See wporg_login_event_code_report_row().
 */
function wporg_login_event_code_report(): array {
	// The theme short-circuits all front-end queries, see functions.php.
	$short_circuited = remove_filter( 'posts_pre_query', '__return_empty_array' );

	$posts = get_posts(
		array(
			'post_type'        => WPORG_EVENT_CODE_POST_TYPE,
			'post_status'      => array( 'any', 'trash' ),
			'posts_per_page'   => -1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);

	if ( $short_circuited ) {
		add_filter( 'posts_pre_query', '__return_empty_array' );
	}

	$rows = array_map( 'wporg_login_event_code_report_row', $posts );

	foreach ( get_option( WPORG_EVENT_CODE_ARCHIVE_OPTION, array() ) as $row ) {
		$rows[] = $row;
	}

	return $rows;
}

/**
 * Admin: add the counts to the Event Codes screen.
 *
 * @param array $columns Columns.
 * @return array
 */
function wporg_login_event_code_columns( array $columns ): array {
	$date = $columns['date'];
	unset( $columns['date'] );

	return $columns + array(
		'program'    => 'Program',
		'tracker_id' => 'Tracker ID',
		'signups'    => 'Signups',
		'confirmed'  => 'Confirmed accounts',
		'countries'  => 'Countries',
		'date'       => $date,
	);
}
add_filter( 'manage_' . WPORG_EVENT_CODE_POST_TYPE . '_posts_columns', 'wporg_login_event_code_columns' );

/**
 * Admin: render a column of the Event Codes screen.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 * @return void
 */
function wporg_login_event_code_column( string $column, int $post_id ): void {
	static $rows = array();

	if ( ! isset( $rows[ $post_id ] ) ) {
		$rows[ $post_id ] = wporg_login_event_code_report_row( get_post( $post_id ) );
	}

	$row = $rows[ $post_id ];

	switch ( $column ) {
		case 'program':
			echo esc_html( wporg_login_event_code_programs()[ $row['program'] ] ?? '' );
			break;

		case 'tracker_id':
			echo esc_html( $row['tracker_id'] ?: '' );
			break;

		case 'signups':
		case 'confirmed':
			echo (int) $row[ $column ];
			break;

		case 'countries':
			foreach ( $row['countries'] as $country => $count ) {
				printf( '%s %d<br>', esc_html( $country ), (int) $count );
			}
			break;
	}
}
add_action( 'manage_' . WPORG_EVENT_CODE_POST_TYPE . '_posts_custom_column', 'wporg_login_event_code_column', 10, 2 );

/**
 * Events on WordCamp Central whose name matches, for the Code box's picker.
 *
 * Central lists scheduled and past events only, so an event still being planned is found once it
 * is scheduled. Central has no field for the program; a Campus Connect event is known by its name.
 *
 * @param string $search What the organizer typed.
 * @return array[]|WP_Error Matches, each with id, title, starts (Y-m-d), place and program; none
 *                          under three characters.
 */
function wporg_login_event_code_central_matches( string $search ): array|WP_Error {
	$search = trim( $search );

	if ( mb_strlen( $search ) < 3 ) {
		return array();
	}

	$response = wp_remote_get(
		WPORG_EVENT_CODE_CENTRAL_API . '?' . http_build_query(
			array(
				'search'   => $search,
				'per_page' => 10,
				'_fields'  => 'id,title,Start Date (YYYY-mm-dd),_venue_city,_venue_country_name',
			)
		),
		array( 'timeout' => 10 )
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $code ) {
		return new WP_Error( 'wporg_event_code_central', sprintf( 'WordCamp Central answered HTTP %d.', $code ) );
	}

	$events = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $events ) ) {
		return new WP_Error( 'wporg_event_code_central', 'WordCamp Central did not answer with a list of events.' );
	}

	$matches = array();

	foreach ( $events as $event ) {
		$id = (int) ( $event['id'] ?? 0 );

		if ( ! $id ) {
			continue;
		}

		$title = html_entity_decode( wp_strip_all_tags( (string) ( $event['title']['rendered'] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw   = $event['Start Date (YYYY-mm-dd)'] ?? '';
		$ts    = is_numeric( $raw ) ? (int) $raw : (int) strtotime( (string) $raw );
		$place = array_filter( array( (string) ( $event['_venue_city'] ?? '' ), (string) ( $event['_venue_country_name'] ?? '' ) ) );

		$matches[] = array(
			'id'      => $id,
			'title'   => $title,
			'starts'  => $ts ? gmdate( 'Y-m-d', $ts ) : '',
			'place'   => implode( ', ', $place ),
			'program' => str_starts_with( $title, 'WordPress Campus Connect' ) ? 'campus-connect' : '',
		);
	}

	return $matches;
}

/**
 * Admin: answer the Code box's picker.
 *
 * @return void
 */
function wporg_login_event_code_central_search(): void {
	check_ajax_referer( 'wporg_event_code_central', 'nonce' );

	if ( ! current_user_can( 'manage_event_codes' ) ) {
		wp_send_json_error( 'Not allowed.', 403 );
	}

	$matches = wporg_login_event_code_central_matches( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );

	if ( is_wp_error( $matches ) ) {
		wp_send_json_error( $matches->get_error_message(), 502 );
	}

	wp_send_json_success( $matches );
}
add_action( 'wp_ajax_wporg_event_code_central_search', 'wporg_login_event_code_central_search' );

/**
 * Admin: settings meta box.
 *
 * @param WP_Post $post Event code post.
 * @return void
 */
function wporg_login_event_code_meta_box( WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by register_meta_box_cb.
	add_meta_box( 'wporg-event-code', 'Code', 'wporg_login_event_code_meta_box_render', null, 'normal', 'high' );
}

/**
 * Admin: render the meta box.
 *
 * @param WP_Post $post Event code post.
 * @return void
 */
function wporg_login_event_code_meta_box_render( WP_Post $post ): void {
	$code    = get_post_meta( $post->ID, '_event_code', true );
	$limits  = wporg_login_event_code_limits( $post );
	$tz      = wp_timezone();
	$program = get_post_meta( $post->ID, '_program', true );

	wp_nonce_field( 'wporg_event_code', 'wporg_event_code_nonce' );

	if ( $code ) {
		$link = add_query_arg( 'event', $code, home_url( '/register' ) );
		printf(
			'<p><strong>%s</strong><br><code>%s</code></p><p>Signups: %d of %d<br>Confirmed accounts: %d</p>',
			esc_html( $code ),
			esc_url( $link ),
			(int) $limits['uses'],
			(int) $limits['cap'],
			(int) get_post_meta( $post->ID, '_confirmed', true )
		);
	} else {
		echo '<p>A code and link are created when you save.</p>';
	}

	printf(
		'<p><label>Expires <input type="datetime-local" name="event_code_expires" value="%s"></label></p>
		<p><label>Signup limit <input type="number" name="event_code_cap" min="1" max="%d" value="%d"></label></p>
		<p class="description">The code is active from the publish date. Codes stop working after %d signups or %d days, whichever comes first. Move a code to the trash to revoke it.</p>',
		esc_attr( wp_date( 'Y-m-d\TH:i', $limits['expires'], $tz ) ),
		(int) WPORG_EVENT_CODE_MAX_SIGNUPS,
		(int) $limits['cap'],
		(int) WPORG_EVENT_CODE_MAX_SIGNUPS,
		(int) ( WPORG_EVENT_CODE_MAX_DURATION / DAY_IN_SECONDS )
	);

	echo '<p><label>Program <select name="event_code_program"><option value="">None</option>';
	foreach ( wporg_login_event_code_programs() as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $program, $value, false ), esc_html( $label ) );
	}
	echo '</select></label></p>';

	printf(
		'<p><label>Tracker ID <input type="number" name="event_code_tracker_id" min="1" value="%s"></label></p>
		<p class="description">The event\'s ID on WordCamp Central, when it has one.</p>',
		esc_attr( get_post_meta( $post->ID, '_tracker_id', true ) )
	);

	printf(
		'<p><label>Find on WordCamp Central <input type="search" id="wporg-event-code-central" placeholder="Event name" autocomplete="off" data-nonce="%s"></label></p>
		<ul id="wporg-event-code-central-matches" aria-live="polite"></ul>
		<p class="description">Pick the event and its name, tracker ID and program fill in. Central lists scheduled and past events. Set the publish date to the event\'s day yourself.</p>',
		esc_attr( wp_create_nonce( 'wporg_event_code_central' ) )
	);

	wp_print_inline_script_tag(
		<<<'JS'
		( function () {
			var input = document.getElementById( 'wporg-event-code-central' );
			var list  = document.getElementById( 'wporg-event-code-central-matches' );
			var timer;
			var latest = 0;

			if ( ! input || ! list ) {
				return;
			}

			function say( text ) {
				list.textContent = '';
				if ( text ) {
					list.appendChild( document.createElement( 'li' ) ).textContent = text;
				}
			}

			function fill( match ) {
				var title   = document.getElementById( 'title' );
				var tracker = document.querySelector( '[name="event_code_tracker_id"]' );
				var program = document.querySelector( '[name="event_code_program"]' );

				if ( title ) {
					title.value = match.title;
					title.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					title.dispatchEvent( new Event( 'keyup', { bubbles: true } ) );
				}
				if ( tracker ) {
					tracker.value = match.id;
				}
				if ( program && match.program ) {
					program.value = match.program;
				}

				input.value = '';
				say( '' );
			}

			function show( matches ) {
				if ( ! matches.length ) {
					say( 'No event by that name on WordCamp Central.' );
					return;
				}

				list.textContent = '';
				matches.forEach( function ( match ) {
					var item   = document.createElement( 'li' );
					var button = document.createElement( 'button' );

					button.type      = 'button';
					button.className = 'button-link';
					button.textContent = match.title;
					button.addEventListener( 'click', function () {
						fill( match );
					} );

					item.appendChild( button );
					item.appendChild( document.createTextNode( ' ' + [ match.starts, match.place ].filter( Boolean ).join( ', ' ) ) );
					list.appendChild( item );
				} );
			}

			input.addEventListener( 'input', function () {
				var search = input.value.trim();
				// Every keystroke makes the answers still in flight stale, before the next
				// request has even been sent.
				var id     = ++latest;

				clearTimeout( timer );

				if ( search.length < 3 ) {
					say( '' );
					return;
				}

				timer = setTimeout( function () {
					var body = new URLSearchParams( { action: 'wporg_event_code_central_search', nonce: input.dataset.nonce, search: search } );

					say( 'Looking on WordCamp Central...' );

					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( json ) {
							if ( id !== latest ) {
								return;
							}
							if ( json.success ) {
								show( json.data );
							} else {
								say( json.data || 'WordCamp Central did not answer.' );
							}
						} )
						.catch( function () {
							if ( id === latest ) {
								say( 'WordCamp Central did not answer.' );
							}
						} );
				}, 300 );
			} );
		} )();
		JS
	);
}

/**
 * Admin: save settings and assign a code on first save.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function wporg_login_event_code_save( int $post_id ): void {
	if (
		! isset( $_POST['wporg_event_code_nonce'] ) ||
		! wp_verify_nonce( sanitize_key( $_POST['wporg_event_code_nonce'] ), 'wporg_event_code' ) ||
		! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	if ( ! get_post_meta( $post_id, '_event_code', true ) ) {
		update_post_meta( $post_id, '_event_code', wporg_login_generate_event_code() );
		add_post_meta( $post_id, '_uses', 0, true );
		add_post_meta( $post_id, '_confirmed', 0, true );
	}

	if ( ! empty( $_POST['event_code_expires'] ) ) {
		$expires = date_create_immutable( sanitize_text_field( wp_unslash( $_POST['event_code_expires'] ) ), wp_timezone() );
		$starts  = (int) get_post_time( 'U', true, $post_id );

		// The form defaults to 3 days from now, which would kill a code scheduled further out.
		if ( $expires && $expires->getTimestamp() > $starts ) {
			update_post_meta( $post_id, '_expires', $expires->getTimestamp() );
		} else {
			delete_post_meta( $post_id, '_expires' );
		}
	}

	if ( isset( $_POST['event_code_cap'] ) ) {
		update_post_meta( $post_id, '_cap', absint( $_POST['event_code_cap'] ) );
	}

	if ( isset( $_POST['event_code_program'] ) ) {
		$program = sanitize_key( wp_unslash( $_POST['event_code_program'] ) );

		if ( isset( wporg_login_event_code_programs()[ $program ] ) ) {
			update_post_meta( $post_id, '_program', $program );
		} else {
			delete_post_meta( $post_id, '_program' );
		}
	}

	if ( isset( $_POST['event_code_tracker_id'] ) ) {
		$tracker_id = absint( $_POST['event_code_tracker_id'] );

		if ( $tracker_id ) {
			update_post_meta( $post_id, '_tracker_id', $tracker_id );
		} else {
			delete_post_meta( $post_id, '_tracker_id' );
		}
	}
}
add_action( 'save_post_' . WPORG_EVENT_CODE_POST_TYPE, 'wporg_login_event_code_save' );
