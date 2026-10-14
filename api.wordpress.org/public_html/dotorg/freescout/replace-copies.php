<?php
/**
 * FreeScout: points WordPress.org's copies of HelpScout's conversations at the conversations imported from them.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

require __DIR__ . '/common.php';

/**
 * Swaps the copies of HelpScout's conversations for those of the FreeScout conversations imported from them; see
 * plan_copy_replacement(). Writes go in an order that the batch, sent again after one failed, finishes.
 *
 * @param array $copies Each a HelpScout ID, the FreeScout conversation's ID, number, and status, and whether its copy
 *                      goes.
 * @return int How many were swapped, or deleted.
 *
 * @throws \RuntimeException If a write failed.
 */
function replace_copies( array $copies ): int {
	global $wpdb;

	$copies = array_map(
		static function ( mixed $copy ): object {
			return (object) $copy;
		},
		$copies
	);
	$ids    = array_values( array_filter( array_map( 'intval', array_merge( array_column( $copies, 'helpscout_id' ), array_column( $copies, 'id' ) ) ) ) );
	if ( ! $ids ) {
		return 0;
	}

	// Read from the primary database, not a lagging replica.
	if ( is_callable( array( $wpdb, 'send_reads_to_masters' ) ) ) {
		$wpdb->send_reads_to_masters();
	}

	$emails_table = "{$wpdb->base_prefix}helpscout";
	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are added for each ID.
	$existing = array_fill_keys( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( $emails_table ), $ids ) ) ) ), true );
	$replaced = 0;

	foreach ( $copies as $copy ) {
		$helpscout_id = (int) ( $copy->helpscout_id ?? 0 );
		$id           = (int) ( $copy->id ?? 0 );
		$action       = plan_copy_replacement( $copy, isset( $existing[ $helpscout_id ] ), isset( $existing[ $id ] ) );
		if ( 'skip' === $action ) {
			continue;
		}

		++$replaced;
		if ( 'delete' === $action ) {
			delete_emails( array( $helpscout_id, $id ) );
			unset( $existing[ $helpscout_id ], $existing[ $id ] );
			continue;
		}

		take_over_meta( $id, array( $helpscout_id ) );
		add_copy_meta( $id, array( $helpscout_id ), array() );
		if ( 'rename' === $action ) {
			// What HelpScout's webhook last wrote may be out of date, as it no longer runs for the mailbox.
			$columns = array(
				'id'     => $id,
				'number' => (int) ( $copy->number ?? 0 ),
				'status' => (string) ( $copy->status ?? '' ),
			);
			check_write( $wpdb->update( $emails_table, $columns, array( 'id' => $helpscout_id ) ) );
			$existing[ $id ] = true;
		}

		delete_emails( array( $helpscout_id ) );
		unset( $existing[ $helpscout_id ] );
	}

	return $replaced;
}

$request = get_request( basename( __FILE__ ) );

try {
	$replaced = replace_copies( (array) ( $request->copies ?? array() ) );
} catch ( \Throwable $e ) {
	trigger_error( 'FreeScout copies failed: ' . $e->getMessage(), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- Logged, not shown.
	status_header( 503 );
	exit;
}

wp_send_json( array( 'replaced' => $replaced ) );
