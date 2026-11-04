<?php
/**
 * Renders the Open to Work block.
 *
 * Headings, intro copy and the people count stay editable core blocks
 * around this one (the count is the jobswp/stats `candidates` binding).
 * Pagination is server-side through the `cp` query argument, so it works
 * without JavaScript.
 *
 * @package jobswp-blocks
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'jobswp_get_open_to_work_candidates' ) ) {
	return;
}

$jobswp_blocks_variant  = in_array( $attributes['variant'] ?? 'grid', array( 'grid', 'compact', 'list' ), true ) ? $attributes['variant'] : 'grid';
$jobswp_blocks_per_page = max( 1, min( 30, (int) ( $attributes['perPage'] ?? 10 ) ) );
$jobswp_blocks_paged    = 'grid' === $jobswp_blocks_variant && ! empty( $attributes['showPagination'] );
$jobswp_blocks_page     = $jobswp_blocks_paged && isset( $_GET['cp'] ) ? max( 1, absint( $_GET['cp'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
$jobswp_blocks_result   = jobswp_get_open_to_work_candidates( $jobswp_blocks_page, $jobswp_blocks_per_page );
$jobswp_blocks_people   = (array) $jobswp_blocks_result['candidates'];

if ( ! $jobswp_blocks_people ) {
	return;
}

$jobswp_blocks_avatar = array(
	'grid'    => 80,
	'compact' => 64,
	'list'    => 32,
);
$jobswp_blocks_size   = $jobswp_blocks_avatar[ $jobswp_blocks_variant ];
$jobswp_blocks_new    = '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'jobswp-blocks' ) . '</span>';

$jobswp_blocks_items = array();
foreach ( $jobswp_blocks_people as $jobswp_blocks_person ) {
	if ( empty( $jobswp_blocks_person->user_login ) || empty( $jobswp_blocks_person->profile_url ) ) {
		continue;
	}

	$jobswp_blocks_role    = isset( $jobswp_blocks_person->current_role ) ? trim( (string) $jobswp_blocks_person->current_role ) : '';
	$jobswp_blocks_company = isset( $jobswp_blocks_person->current_company ) ? trim( (string) $jobswp_blocks_person->current_company ) : '';
	if ( '' === $jobswp_blocks_role && '' === $jobswp_blocks_company && ! empty( $jobswp_blocks_person->positions[0] ) ) {
		$jobswp_blocks_role    = isset( $jobswp_blocks_person->positions[0]->role ) ? (string) $jobswp_blocks_person->positions[0]->role : '';
		$jobswp_blocks_company = isset( $jobswp_blocks_person->positions[0]->company ) ? (string) $jobswp_blocks_person->positions[0]->company : '';
	}

	$jobswp_blocks_img  = sprintf(
		'<img class="jobswp-open-to-work__avatar" src="%1$s" alt="" width="%2$d" height="%2$d" loading="lazy" />',
		esc_url( 'https://wordpress.org/grav-redirect.php?user=' . rawurlencode( $jobswp_blocks_person->user_login ) . '&s=' . $jobswp_blocks_size ),
		$jobswp_blocks_size
	);
	$jobswp_blocks_name = '<span class="jobswp-open-to-work__name">' . esc_html( $jobswp_blocks_person->display_name ?? $jobswp_blocks_person->user_login ) . '</span>';

	if ( 'list' === $jobswp_blocks_variant ) {
		$jobswp_blocks_body = $jobswp_blocks_img . $jobswp_blocks_name;
	} else {
		$jobswp_blocks_img = sprintf(
			'<span class="jobswp-open-to-work__photo%1$s">%2$s</span>',
			empty( $jobswp_blocks_person->show_badge ) ? '' : ' has-badge',
			$jobswp_blocks_img
		);
		$jobswp_blocks_meta = '';
		if ( '' !== $jobswp_blocks_role || '' !== $jobswp_blocks_company ) {
			$jobswp_blocks_meta = '<span class="jobswp-open-to-work__role">' . esc_html( $jobswp_blocks_role )
				. ( '' !== $jobswp_blocks_company ? ' <span class="jobswp-open-to-work__company">' . esc_html( $jobswp_blocks_company ) . '</span>' : '' )
				. '</span>';
		}
		$jobswp_blocks_body = $jobswp_blocks_img
			. '<span class="jobswp-open-to-work__info">' . $jobswp_blocks_name . $jobswp_blocks_meta . '</span>'
			. '<span class="jobswp-open-to-work__arrow" aria-hidden="true">&#8594;</span>';
	}

	$jobswp_blocks_items[] = sprintf(
		'<li class="jobswp-open-to-work__person"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s%3$s</a></li>',
		esc_url( $jobswp_blocks_person->profile_url ),
		$jobswp_blocks_body,
		$jobswp_blocks_new
	);
}

/**
 * Filters the rendered people of the Open to Work block.
 *
 * @param string[] $items   One <li> per person.
 * @param string   $variant Layout: grid, compact or list.
 */
$jobswp_blocks_items = (array) apply_filters( 'jobswp_blocks_open_to_work_items', $jobswp_blocks_items, $jobswp_blocks_variant );

$jobswp_blocks_pagination = '';
$jobswp_blocks_pages      = (int) $jobswp_blocks_result['pages'];
if ( $jobswp_blocks_paged && $jobswp_blocks_pages > 1 ) {
	$jobswp_blocks_link = static function ( $page, $label, $enabled ) {
		if ( ! $enabled ) {
			return '<span class="jobswp-open-to-work__page is-disabled">' . $label . '</span>';
		}
		$url = add_query_arg( 'cp', $page, get_permalink() ? get_permalink() : home_url( '/' ) ) . '#candidates';
		return '<a class="jobswp-open-to-work__page" href="' . esc_url( $url ) . '">' . $label . '</a>';
	};

	$jobswp_blocks_pagination = sprintf(
		'<nav class="jobswp-open-to-work__pagination" aria-label="%1$s">%2$s<span class="jobswp-open-to-work__page-info">%3$s</span>%4$s</nav>',
		esc_attr__( 'People open to work, pages', 'jobswp-blocks' ),
		$jobswp_blocks_link( $jobswp_blocks_page - 1, '&larr; ' . esc_html__( 'Previous', 'jobswp-blocks' ), $jobswp_blocks_page > 1 ),
		/* translators: 1: current page, 2: total pages. */
		esc_html( sprintf( __( 'Page %1$s of %2$s', 'jobswp-blocks' ), number_format_i18n( $jobswp_blocks_page ), number_format_i18n( $jobswp_blocks_pages ) ) ),
		$jobswp_blocks_link( $jobswp_blocks_page + 1, esc_html__( 'Next', 'jobswp-blocks' ) . ' &rarr;', $jobswp_blocks_page < $jobswp_blocks_pages )
	);
}

printf(
	'<div %1$s><ul class="jobswp-open-to-work__people">%2$s</ul>%3$s</div>',
	wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'is-variant-' . $jobswp_blocks_variant ) ) ),
	implode( '', $jobswp_blocks_items ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is escaped above.
	$jobswp_blocks_pagination // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
