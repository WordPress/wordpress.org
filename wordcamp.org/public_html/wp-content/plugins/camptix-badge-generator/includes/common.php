<?php

namespace CampTix\Badge_Generator;
use \CampTix\Badge_Generator\HTML;

defined( 'WPINC' ) or die();

if ( is_admin() ) {
	add_filter( 'camptix_menu_tools_tabs',   __NAMESPACE__ . '\add_badges_tab'     );
	add_action( 'camptix_menu_tools_badges', __NAMESPACE__ . '\render_badges_page' );
	add_action( 'admin_print_styles',        __NAMESPACE__ . '\print_admin_styles' );
}

add_filter( 'get_post_metadata', __NAMESPACE__ . '\add_dynamic_post_meta', 10, 3 );

/**
 * Add the Generate Badges tab to the CampTix Tools page
 *
 * @param array $sections
 *
 * @return array
 */
function add_badges_tab( $sections ) {
	$sections['badges'] = __( 'Generate Badges', 'wordcamporg' );

	return $sections;
}

/**
 * Render the main Generate Badges page
 */
function render_badges_page() {
	if ( ! current_user_can( REQUIRED_CAPABILITY ) ) {
		return;
	}

	$html_customizer_url = HTML\get_customizer_section_url();
	$notify_tool_url     = admin_url( 'edit.php?post_type=tix_ticket&page=camptix_tools&tix_section=notify' );
	$indesign_page_url   = admin_url( 'edit.php?post_type=tix_ticket&page=camptix_tools&tix_section=indesign_badges' );

	require_once( dirname( __DIR__ ) . '/views/common/page-generate-badges.php' );
}

/**
 * Print CSS styles for wp-admin
 */
function print_admin_styles() {
	$screen = get_current_screen();

	if ( 'tix_ticket_page_camptix_tools' !== $screen->id ) {
		return;
	}

	?>

	<!-- BEGIN CampTix Badge Generator -->
	<style type="text/css">
		<?php require_once( dirname( __DIR__ ) . '/css/common.css' ); ?>
	</style>
	<!-- END CampTix Badge Generator -->

	<?php
}

/**
 * Get the attendees
 *
 * @param string|array $ticket_ids        Reduce fetched attendees by the ticket they purchased. The string 'all'
 *                                        will result in all attendees being fetched, regardless of their
 *                                        ticket. An array of ticket IDs will result in only the attendees for
 *                                        those tickets being fetched.
 * @param string $registered_after        Reduce fetched attendees by their registration date. Any value parseable
 *                                        by strtotime().
 *
 * @todo Maybe optimize this by looking at post_date rather than tix_timestamp
 *
 * @return array
 */
function get_attendees( $ticket_ids = 'all', $registered_after = '' ) {
	$params = array(
		'post_type'      => 'tix_attendee',
		'posts_per_page' => 12000,
		'order'          => 'ASC',
		'orderby'        => 'title',
		'meta_query'     => array(),
	);

	if ( 'all' !== $ticket_ids ) {
		$params['meta_query'][] = array(
			'key'     => 'tix_ticket_id',
			'value'   => $ticket_ids,
			'compare' => 'IN',
		);
	}

	if ( ! empty( $registered_after ) ) {
		$params['meta_query'][] = array(
			'key'     => 'tix_timestamp',
			'value'   => strtotime( $registered_after ),
			'compare' => '>=',
		);
	}

	$attendees = get_posts( $params );

	return $attendees;
}

/**
 * Add dynamically-generated "post meta" to `\WP_Post` objects
 *
 * This makes it possible to access dynamic data related to a post object by simply referencing `$post->foo`.
 * That keeps the calling code much cleaner than if it were to have to do something like
 * `$foo = some_custom_logic( get_post_meta( $post->ID, 'bar', true ) ); echo esc_html( $foo )`.
 *
 * @param mixed  $value
 * @param int    $post_id
 * @param string $meta_key
 *
 * @return mixed
 *      `null` to instruct `get_metadata()` to pull the value from the database
 *      Any non-null value will be returned as if it were pulled from the database
 */
function add_dynamic_post_meta( $value, $post_id, $meta_key ) {
	/** @global \CampTix_Plugin $camptix */
	global $camptix;

	$attendee = get_post( $post_id );

	if ( ! is_a( $attendee, 'WP_Post' ) ) {
		return $value;
	}

	if ( 'tix_attendee' != $attendee->post_type ) {
		return $value;
	}

	switch ( $meta_key ) {
		case 'avatar_url':
			$value = get_avatar_url(
				$attendee->tix_email,
				array(
					'size'    => 1024,
					'default' => 'blank',
					'rating'  => 'g'
				)
			);
			$value = str_replace( 'secure.gravatar.com', 'en.gravatar.com', $value );   // work around Varnish bug on secure.gravatar.com that causes `503` response codes
			break;

		case 'coupon':
			if ( $attendee->tix_coupon_id ) {
				$coupon = get_post( $attendee->tix_coupon_id );
				$value  = $coupon->post_name;
			}
			break;

		case 'css_classes':
			$value = get_css_classes( $attendee );
			break;

		case 'formatted_name':
			$value = $camptix->format_name_string(
				'<span class="first-name">%first%</span>
				 <span class="last-name">%last%</span>',
				$attendee->tix_first_name,
				$attendee->tix_last_name
			);
			break;

		case 'ticket':
			$ticket = get_post( $attendee->tix_ticket_id );
			$value  = $ticket->post_name;
			break;
	}

	return $value;
}

/**
 * Get the CSS classes for an attendee element
 *
 * @param \WP_Post $attendee
 *
 * @return string
 */
function get_css_classes( $attendee ) {
	$classes = array(
		'attendee-' . $attendee->post_name,
		'ticket-'   . $attendee->ticket
	);

	if ( $attendee->coupon ) {
		$classes[] = 'coupon-' . $attendee->coupon;
	}

	return implode( ' ', $classes );
}
