<?php
/**
 * Event signup codes.
 *
 * Lets education events share a link like https://login.wordpress.org/register?event=K7P4M2
 * so a room of people on one network can register without tripping the per-IP registration limit.
 *
 * A valid code only resets the per-IP counter for the submitting IP. reCAPTCHA, heuristics,
 * blocked words and email confirmation all still apply. Every pending registration made with
 * a code is tagged with it, so a leaked code can be revoked and its signups reviewed.
 *
 * Codes are managed in wp-admin by users with the `manage_event_codes` capability.
 * Signup behaviour is off until the `wporg_login_event_codes_enabled` option is truthy.
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
const WPORG_EVENT_CODE_LENGTH = 6;

/**
 * Hard cap on signups per code.
 */
const WPORG_EVENT_CODE_MAX_SIGNUPS = 200;

/**
 * Hard cap on how long a code works after its start date, in seconds.
 */
const WPORG_EVENT_CODE_MAX_DURATION = 3 * DAY_IN_SECONDS;

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
 * Whether signup handling for event codes is switched on.
 *
 * @return bool
 */
function wporg_login_event_codes_enabled(): bool {
	return (bool) get_option( 'wporg_login_event_codes_enabled', false );
}

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
	return wporg_login_event_codes_enabled() && (bool) wporg_login_get_valid_event_code( wp_unslash( $_REQUEST['event'] ?? '' ) );
}

/**
 * Carry the code through the registration form and tell the person which event they're joining.
 *
 * @return void
 */
function wporg_login_event_code_register_form(): void {
	if ( ! wporg_login_event_codes_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a strict pattern.
	$post = wporg_login_get_valid_event_code( wp_unslash( $_REQUEST['event'] ?? '' ) );
	if ( ! $post ) {
		return;
	}

	printf(
		'<input type="hidden" name="event" value="%s" /><div class="message info"><p>%s</p></div>',
		esc_attr( get_post_meta( $post->ID, '_event_code', true ) ),
		esc_html(
			sprintf(
				/* translators: %s: Event name. */
				__( 'You are creating an account for %s.', 'wporg' ),
				get_the_title( $post )
			)
		)
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
	if ( null !== $pre || wp_is_serving_rest_request() || empty( $_POST['event'] ) || ! wporg_login_event_codes_enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public signup form.
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

		update_post_meta( $post->ID, '_uses', wporg_login_event_code_limits( $post )['uses'] + 1 );
	}

	return $pending_user;
}
add_filter( 'wporg_login_registration_update_pending_user', 'wporg_login_event_code_tag_pending_user' );

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
	$code   = get_post_meta( $post->ID, '_event_code', true );
	$limits = wporg_login_event_code_limits( $post );
	$tz     = wp_timezone();

	wp_nonce_field( 'wporg_event_code', 'wporg_event_code_nonce' );

	if ( $code ) {
		$link = add_query_arg( 'event', $code, home_url( '/register' ) );
		printf(
			'<p><strong>%s</strong><br><code>%s</code></p><p>Signups: %d of %d</p>',
			esc_html( $code ),
			esc_url( $link ),
			(int) $limits['uses'],
			(int) $limits['cap']
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
}
add_action( 'save_post_' . WPORG_EVENT_CODE_POST_TYPE, 'wporg_login_event_code_save' );
