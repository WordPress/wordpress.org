<?php
/**
 * Renders the Open to Work block, paginated on the server through the `cp` query argument.
 *
 * @package wporg-jobs-blocks
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'jobswp_get_open_to_work_candidates' ) ) {
	return;
}

$wporg_jobs_blocks_variant  = in_array( $attributes['variant'] ?? 'grid', array( 'grid', 'compact', 'list' ), true ) ? $attributes['variant'] : 'grid';
$wporg_jobs_blocks_per_page = max( 1, min( 30, (int) ( $attributes['perPage'] ?? 10 ) ) );
$wporg_jobs_blocks_paged    = 'grid' === $wporg_jobs_blocks_variant && ! empty( $attributes['showPagination'] );
$wporg_jobs_blocks_page     = $wporg_jobs_blocks_paged && isset( $_GET['cp'] ) ? max( 1, min( 100, absint( $_GET['cp'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
$wporg_jobs_blocks_result   = jobswp_get_open_to_work_candidates( $wporg_jobs_blocks_page, $wporg_jobs_blocks_per_page );
$wporg_jobs_blocks_people   = (array) $wporg_jobs_blocks_result['candidates'];

if ( ! $wporg_jobs_blocks_people ) {
	return;
}

$wporg_jobs_blocks_avatar = array(
	'grid'    => 80,
	'compact' => 64,
	'list'    => 32,
);
$wporg_jobs_blocks_size   = $wporg_jobs_blocks_avatar[ $wporg_jobs_blocks_variant ];
$wporg_jobs_blocks_new    = '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'wporg-jobs-blocks' ) . '</span>';

$wporg_jobs_blocks_items = array();
foreach ( $wporg_jobs_blocks_people as $wporg_jobs_blocks_person ) {
	if ( empty( $wporg_jobs_blocks_person->user_login ) || empty( $wporg_jobs_blocks_person->profile_url ) ) {
		continue;
	}

	$wporg_jobs_blocks_role    = isset( $wporg_jobs_blocks_person->current_role ) ? trim( (string) $wporg_jobs_blocks_person->current_role ) : '';
	$wporg_jobs_blocks_company = isset( $wporg_jobs_blocks_person->current_company ) ? trim( (string) $wporg_jobs_blocks_person->current_company ) : '';
	if ( '' === $wporg_jobs_blocks_role && '' === $wporg_jobs_blocks_company && ! empty( $wporg_jobs_blocks_person->positions[0] ) ) {
		$wporg_jobs_blocks_role    = isset( $wporg_jobs_blocks_person->positions[0]->role ) ? (string) $wporg_jobs_blocks_person->positions[0]->role : '';
		$wporg_jobs_blocks_company = isset( $wporg_jobs_blocks_person->positions[0]->company ) ? (string) $wporg_jobs_blocks_person->positions[0]->company : '';
	}

	$wporg_jobs_blocks_img  = sprintf(
		'<img class="wporg-jobs-open-to-work__avatar" src="%1$s" alt="" width="%2$d" height="%2$d" loading="lazy" />',
		esc_url( 'https://wordpress.org/grav-redirect.php?user=' . rawurlencode( (string) $wporg_jobs_blocks_person->user_login ) . '&s=' . $wporg_jobs_blocks_size ),
		$wporg_jobs_blocks_size
	);
	$wporg_jobs_blocks_name = '<span class="wporg-jobs-open-to-work__name">' . esc_html( $wporg_jobs_blocks_person->display_name ?? $wporg_jobs_blocks_person->user_login ) . '</span>';

	if ( 'list' === $wporg_jobs_blocks_variant ) {
		$wporg_jobs_blocks_body = $wporg_jobs_blocks_img . $wporg_jobs_blocks_name;
	} else {
		$wporg_jobs_blocks_img  = sprintf(
			'<span class="wporg-jobs-open-to-work__photo%1$s">%2$s</span>',
			empty( $wporg_jobs_blocks_person->show_badge ) ? '' : ' has-badge',
			$wporg_jobs_blocks_img
		);
		$wporg_jobs_blocks_meta = '';
		if ( '' !== $wporg_jobs_blocks_role || '' !== $wporg_jobs_blocks_company ) {
			$wporg_jobs_blocks_meta = '<span class="wporg-jobs-open-to-work__role">' . esc_html( $wporg_jobs_blocks_role )
				. ( '' !== $wporg_jobs_blocks_company ? ' <span class="wporg-jobs-open-to-work__company">' . esc_html( $wporg_jobs_blocks_company ) . '</span>' : '' )
				. '</span>';
		}
		$wporg_jobs_blocks_body = $wporg_jobs_blocks_img
			. '<span class="wporg-jobs-open-to-work__info">' . $wporg_jobs_blocks_name . $wporg_jobs_blocks_meta . '</span>'
			. '<span class="wporg-jobs-open-to-work__arrow" aria-hidden="true">&#8594;</span>';
	}

	$wporg_jobs_blocks_items[] = sprintf(
		'<li class="wporg-jobs-open-to-work__person"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s%3$s</a></li>',
		esc_url( $wporg_jobs_blocks_person->profile_url ),
		$wporg_jobs_blocks_body,
		$wporg_jobs_blocks_new
	);
}

/**
 * Filters the rendered people of the Open to Work block.
 *
 * @param string[] $items   One <li> per person.
 * @param string   $variant Layout: grid, compact or list.
 */
$wporg_jobs_blocks_items = (array) apply_filters( 'wporg_jobs_blocks_open_to_work_items', $wporg_jobs_blocks_items, $wporg_jobs_blocks_variant );

$wporg_jobs_blocks_pagination = '';
$wporg_jobs_blocks_pages      = min( 100, (int) $wporg_jobs_blocks_result['pages'] ); // The candidates helper serves pages 1-100.
if ( $wporg_jobs_blocks_paged && $wporg_jobs_blocks_pages > 1 ) {
	$wporg_jobs_blocks_link = static function ( int $page, string $label, bool $enabled ): string {
		if ( ! $enabled ) {
			return '<span class="wporg-jobs-open-to-work__page is-disabled">' . $label . '</span>';
		}
		$url = add_query_arg( 'cp', $page, get_permalink() ? get_permalink() : home_url( '/' ) ) . '#candidates';
		return '<a class="wporg-jobs-open-to-work__page" href="' . esc_url( $url ) . '">' . $label . '</a>';
	};

	$wporg_jobs_blocks_pagination = sprintf(
		'<nav class="wporg-jobs-open-to-work__pagination" aria-label="%1$s">%2$s<span class="wporg-jobs-open-to-work__page-info">%3$s</span>%4$s</nav>',
		esc_attr__( 'People open to work, pages', 'wporg-jobs-blocks' ),
		$wporg_jobs_blocks_link( $wporg_jobs_blocks_page - 1, '&larr; ' . esc_html__( 'Previous', 'wporg-jobs-blocks' ), $wporg_jobs_blocks_page > 1 ),
		/* translators: 1: current page, 2: total pages. */
		esc_html( sprintf( __( 'Page %1$s of %2$s', 'wporg-jobs-blocks' ), number_format_i18n( $wporg_jobs_blocks_page ), number_format_i18n( $wporg_jobs_blocks_pages ) ) ),
		$wporg_jobs_blocks_link( $wporg_jobs_blocks_page + 1, esc_html__( 'Next', 'wporg-jobs-blocks' ) . ' &rarr;', $wporg_jobs_blocks_page < $wporg_jobs_blocks_pages )
	);
}

printf(
	'<div %1$s><ul class="wporg-jobs-open-to-work__people">%2$s</ul>%3$s</div>',
	wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'is-variant-' . $wporg_jobs_blocks_variant ) ) ),
	implode( '', $wporg_jobs_blocks_items ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is escaped above.
	$wporg_jobs_blocks_pagination // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
