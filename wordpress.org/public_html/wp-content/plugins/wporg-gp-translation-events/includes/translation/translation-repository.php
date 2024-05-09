<?php

namespace Wporg\TranslationEvents\Translation;

use DateTimeImmutable;

class Translation_Repository {
	/**
	 * Count the number of translations made by given users before a specified datetime.
	 *
	 * @param int[]             $user_ids Ids of users for which to count translations.
	 * @param DateTimeImmutable $before   Only include translations before this datetime.
	 *
	 * @return array Associative array with user id as key and number of translations as value.
	 */
	public function count_translations_before( array $user_ids, DateTimeImmutable $before ): array {
		if ( empty( $user_ids ) ) {
			return array();
		}

		// Prevent SQL injection.
		foreach ( $user_ids as $user_id ) {
			if ( ! is_int( $user_id ) ) {
				return array();
			}
		}

		global $wpdb, $gp_table_prefix;
		$user_id_params = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
					select user_id, count(*) as cnt
					from {$gp_table_prefix}translations
					where user_id in ($user_id_params)
					  and date_added < %s
					group by user_id
				",
				array_merge(
					$user_ids,
					array( $before->format( 'Y-m-d H:i:s' ) ),
				),
			),
			OBJECT_K
		);
		// phpcs:enable

		$results = array_fill_keys( $user_ids, 0 );
		foreach ( $rows as $user_id => $row ) {
			$results[ $user_id ] = $row->cnt;
		}

		return $results;
	}
}
