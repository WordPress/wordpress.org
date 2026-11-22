<?php
/**
 * Automatic WordCampTV event indexes; presentation lives in wordcamp-blocks.php.
 *
 * Two site-local data-only transients: recent recordings (one hour, capped at
 * local midnight) and all WordCamp events grouped by name-derived year (one day).
 * No posts, submission metadata, private fields or rendered HTML are cached.
 * See docs/wordcamptv.md for query costs, date rules and invalidation coverage.
 *
 * @package WordPressTV
 */

namespace WordPressTV\WordCamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RECENT_KEY    = 'wptv_wc_recent_v1';
const DIRECTORY_KEY = 'wptv_wc_directory_v1';

/**
 * Whether a taxonomy event name identifies a WordCamp (case-insensitive).
 *
 * @param string $name Event name.
 * @return bool
 */
function is_wordcamp( $name ) {
	return 1 === preg_match( '/\bwordcamp\b/iu', $name );
}

/**
 * One distinct standalone 2000–2099 year, or zero; never guess from a slug.
 *
 * @param string $name Event name.
 * @return int
 */
function event_year( $name ) {
	if ( ! is_wordcamp( $name ) ) {
		return 0;
	}
	preg_match_all( '/\b20[0-9]{2}\b/u', $name, $matches );
	$years = array_unique( $matches[0] );
	return 1 === count( $years ) ? (int) reset( $years ) : 0;
}

/**
 * Strict native date-input format; missing/invalid dates have no fallback.
 *
 * @param mixed $meta Unserialized submission metadata.
 * @return string Y-m-d date, or an empty string.
 */
function recorded_date( $meta ) {
	$value = is_array( $meta ) ? ( $meta['recorded'] ?? '' ) : '';
	if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) {
		return '';
	}
	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
}

/**
 * Inclusive calendar-date window in the site timezone, excluding future days.
 *
 * @param \DateTimeImmutable|null $now Current time; null uses the site time.
 * @return string[] First and last Y-m-d dates.
 */
function recording_window( $now = null ) {
	$now   = $now ?? current_datetime();
	$today = $now->setTime( 0, 0 );
	return array( $today->modify( '-12 months' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
}

/**
 * Shared SQL membership predicate, matching core's inclusion of child categories.
 * Only trusted wpdb table names and normalized integer term IDs are interpolated.
 * EXISTS avoids multiplying a video that has several category relationships.
 * An absent root returns no predicate: callers return empty, never all videos.
 */
function category_predicate() {
	global $wpdb;
	$root = get_term_by( 'slug', 'wordcamptv', 'category' );
	if ( ! $root instanceof \WP_Term ) {
		return '';
	}
	$children = get_term_children( $root->term_id, 'category' );
	$ids      = array_merge( array( $root->term_id ), is_wp_error( $children ) ? array() : $children );
	$ids      = implode( ',', wp_parse_id_list( $ids ) );
	return "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} cr
		INNER JOIN {$wpdb->term_taxonomy} ct ON ct.term_taxonomy_id = cr.term_taxonomy_id
		WHERE cr.object_id = p.ID AND ct.taxonomy = 'category' AND ct.term_id IN ($ids))";
}

/**
 * Name-sorted event IDs under descending years; also used by focused tests.
 *
 * @param \WP_Term[] $terms Event terms.
 * @return array Event term IDs keyed by year.
 */
function group_directory( $terms ) {
	usort(
		$terms,
		static function ( $a, $b ) {
			$order = strnatcasecmp( remove_accents( $a->name ), remove_accents( $b->name ) );
			return 0 !== $order ? $order : $a->term_id <=> $b->term_id;
		}
	);
	$groups = array();
	foreach ( $terms as $term ) {
		$year = event_year( $term->name );
		if ( $year ) {
			$groups[ $year ][] = (int) $term->term_id;
		}
	}
	krsort( $groups, SORT_NUMERIC );
	return $groups;
}

/**
 * Fetch category-associated events, not all video IDs; also supports CLI audits.
 * Portable joins/EXISTS work through wpdb on both MySQL and Studio SQLite.
 * Errors return WP_Error so a failed lookup is never cached as an empty index.
 *
 * @return \WP_Term[]|\WP_Error
 */
function directory_terms() {
	global $wpdb;
	$scope = category_predicate();
	if ( ! $scope ) {
		return array();
	}
	// $scope only contains integer IDs (category_predicate()); the result is cached by directory().
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = $wpdb->get_col(
		"SELECT DISTINCT et.term_id FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->term_relationships} er ON er.object_id = p.ID
		INNER JOIN {$wpdb->term_taxonomy} et ON et.term_taxonomy_id = er.term_taxonomy_id
		WHERE p.post_type = 'post' AND p.post_status = 'publish'
		AND et.taxonomy = 'event' AND $scope"
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->last_error ) {
		return new \WP_Error( 'wptv_wordcamp_index', __( 'The WordCamp event index could not be loaded.', 'wptv' ) );
	}
	return $ids ? get_terms(
		array(
			'taxonomy'   => 'event',
			'include'    => $ids,
			'hide_empty' => false,
		)
	) : array();
}

/** Cheap cold directory; cache only descending years and name-sorted event IDs. */
function directory() {
	$cached = get_transient( DIRECTORY_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$terms = directory_terms();
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$groups = group_directory( $terms );
	set_transient( DIRECTORY_KEY, $groups, DAY_IN_SECONDS );
	return $groups;
}

/**
 * Recent event IDs mapped to their newest qualifying recorded date, descending.
 *
 * The recorded date lives in serialized submission metadata, not an indexed date column.
 * On a miss, SQL prefilters its native YYYY-MM-DD string to candidate years;
 * PHP validates the actual field and exact inclusive window. Read at most 500
 * candidate metadata rows per batch with a primary-key cursor, never full posts
 * or the entire metadata catalogue in memory. The leading-wildcard LIKE still
 * costs a scan of eligible metadata: this is not an indexed date-range query.
 */
function recent() {
	global $wpdb;
	$cached = get_transient( RECENT_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$scope = category_predicate();
	if ( ! $scope ) {
		return array();
	}
	$now                  = current_datetime();
	list( $from, $until ) = recording_window( $now );
	$patterns             = array();
	foreach ( range( (int) substr( $from, 0, 4 ), (int) substr( $until, 0, 4 ) ) as $year ) {
		$patterns[] = '%' . $wpdb->esc_like( '"recorded";s:10:"' . $year . '-' ) . '%';
	}
	$likes  = implode( ' OR ', array_fill( 0, count( $patterns ), 'm.meta_value LIKE %s' ) );
	$cursor = 0;
	$events = array();
	do {
		$args = array_merge( array( $cursor ), $patterns );
		// $likes only contains %s placeholders and $scope integer IDs; the result is cached by recent().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_id, p.ID, m.meta_value FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_id > %d AND m.meta_key = '_wptv_submitted_video'
			AND p.post_type = 'post' AND p.post_status = 'publish'
			AND ($likes) AND $scope ORDER BY m.meta_id ASC LIMIT 500",
				$args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = count( $rows );
		if ( $wpdb->last_error ) {
			return array();
		}
		$dates = array();
		foreach ( $rows as $row ) {
			$cursor = (int) $row->meta_id;
			$date   = recorded_date( maybe_unserialize( $row->meta_value ) );
			if ( $date && $date >= $from && $date <= $until ) {
				$dates[ $row->ID ] = $date;
			}
		}
		$terms = $dates ? wp_get_object_terms( array_keys( $dates ), 'event', array( 'fields' => 'all_with_object_id' ) ) : array();
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		foreach ( $terms as $term ) {
			$date = $dates[ $term->object_id ];
			if ( is_wordcamp( $term->name ) && $date > ( $events[ $term->term_id ] ?? '' ) ) {
				$events[ $term->term_id ] = $date;
			}
		}
	} while ( 500 === $count );
	uksort(
		$events,
		static function ( $a, $b ) use ( $events ) {
			$order = strcmp( $events[ $b ], $events[ $a ] );
			return 0 !== $order ? $order : $a <=> $b;
		}
	);
	// The date window advances even if nobody edits a post. Never cross midnight.
	$ttl = min( HOUR_IN_SECONDS, max( 1, $now->modify( 'tomorrow' )->setTime( 0, 0 )->getTimestamp() - $now->getTimestamp() ) );
	set_transient( RECENT_KEY, $events, $ttl );
	return $events;
}

/** Clear derived indexes only; normal WordPress post/query caches remain native. */
function invalidate() {
	delete_transient( RECENT_KEY );
	delete_transient( DIRECTORY_KEY );
}

/**
 * Conservative invalidation also covers departures from the category/event.
 *
 * @param int    $post_id           Object ID.
 * @param array  $term_taxonomy_ids Term taxonomy IDs.
 * @param string $taxonomy          Taxonomy slug.
 */
function relationships_changed( $post_id, $term_taxonomy_ids, $taxonomy ) {
	if ( in_array( $taxonomy, array( 'category', 'event' ), true ) && 'post' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
		invalidate();
	}
}
add_action( 'added_term_relationship', __NAMESPACE__ . '\\relationships_changed', 10, 3 );
add_action( 'deleted_term_relationships', __NAMESPACE__ . '\\relationships_changed', 10, 3 );

/**
 * Includes slug, hierarchy and event-name/year changes, creation and deletion.
 *
 * @param int    $term_id          Term ID.
 * @param int    $term_taxonomy_id Term taxonomy ID.
 * @param string $taxonomy         Taxonomy slug.
 */
function term_changed( $term_id, $term_taxonomy_id, $taxonomy ) {
	if ( in_array( $taxonomy, array( 'category', 'event' ), true ) ) {
		invalidate();
	}
}
add_action( 'created_term', __NAMESPACE__ . '\\term_changed', 10, 3 );
add_action( 'edited_term', __NAMESPACE__ . '\\term_changed', 10, 3 );
add_action( 'delete_term', __NAMESPACE__ . '\\term_changed', 10, 3 );

/**
 * Metadata can be updated independently of save_post (REST/editor/moderation).
 *
 * @param int|int[] $meta_id Meta ID or IDs.
 * @param int       $post_id Post ID.
 * @param string    $key     Meta key.
 */
function metadata_changed( $meta_id, $post_id, $key ) {
	if ( '_wptv_submitted_video' === $key && 'post' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
		delete_transient( RECENT_KEY );
	}
}
add_action( 'added_post_meta', __NAMESPACE__ . '\\metadata_changed', 10, 3 );
add_action( 'updated_post_meta', __NAMESPACE__ . '\\metadata_changed', 10, 3 );
add_action( 'deleted_post_meta', __NAMESPACE__ . '\\metadata_changed', 10, 3 );

/**
 * Publication dates, publication, trash/unpublish and deletion may affect cards.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post.
 */
function post_changed( $post_id, $post ) {
	if ( $post instanceof \WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
		invalidate();
	}
}
add_action( 'save_post_post', __NAMESPACE__ . '\\post_changed', 10, 2 );
add_action( 'before_delete_post', __NAMESPACE__ . '\\post_changed', 10, 2 );

/**
 * The new status alone would miss an event's last published video disappearing.
 *
 * @param string   $new_status New post status.
 * @param string   $old_status Old post status.
 * @param \WP_Post $post       Post.
 */
function status_changed( $new_status, $old_status, $post ) {
	if ( 'post' === $post->post_type && ( 'publish' === $old_status || 'publish' === $new_status ) ) {
		invalidate();
	}
}
add_action( 'transition_post_status', __NAMESPACE__ . '\\status_changed', 10, 3 );
add_action( 'after_switch_theme', __NAMESPACE__ . '\\invalidate' );
