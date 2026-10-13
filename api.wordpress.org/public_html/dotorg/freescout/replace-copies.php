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
 * Most copies a request may replace; FreeScout's WPOrgHelpScoutImport sends a thousand at a time.
 *
 * @var int
 */
const MAX_COPIES = 1000;

/**
 * Swaps the copies of HelpScout's conversations for those of the FreeScout conversations imported from them.
 *
 * A copy keeps its plugins and themes, is marked as FreeScout's, and names the HelpScout conversation it replaces, which
 * keeps HelpScout's webhook from bringing it back; one FreeScout's webhook added already takes over the HelpScout copy's
 * plugins and themes instead, as does the copy of a conversation another was merged into, which is sent for that one.
 * Copies of conversations deleted since, or spam, go. Each is swapped in a transaction, and copies already swapped are
 * left alone, so a batch can be sent again.
 *
 * @param array $copies Each a HelpScout ID, the FreeScout conversation's ID and number, whether its copy goes, and
 *                      whether it was merged into that conversation.
 * @return int|null How many were swapped, or deleted; null if another write held one up, or one failed, so the batch
 *                  should be sent again.
 */
function replace_copies( array $copies ): ?int {
	global $wpdb;

	$emails_table = "{$wpdb->base_prefix}helpscout";
	$meta_table   = "{$wpdb->base_prefix}helpscout_meta";
	$replaced     = 0;

	foreach ( array_slice( $copies, 0, MAX_COPIES ) as $copy ) {
		$copy         = (object) $copy;
		$helpscout_id = (int) ( $copy->helpscout_id ?? 0 );
		$id           = (int) ( $copy->id ?? 0 );
		if ( $helpscout_id <= 0 || $id <= 0 || $helpscout_id === $id ) {
			continue;
		}

		if ( ! lock_email( $id ) ) {
			return null;
		}

		try {
			$has_helpscout = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE id = %d', $emails_table, $helpscout_id ) );
			$has_freescout = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE id = %d', $emails_table, $id ) );
			$names_it      = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE helpscout_id = %d AND meta_key = %s AND meta_value = %s', $meta_table, $id, REPLACED_HELPSCOUT_META_KEY, (string) $helpscout_id ) );
			$action        = plan_copy_replacement( $copy, $has_helpscout, $has_freescout, $names_it );
			if ( 'skip' === $action ) {
				continue;
			}

			in_transaction(
				static function () use ( $wpdb, $emails_table, $meta_table, $action, $copy, $helpscout_id, $id, $names_it ): void {
					if ( 'delete' === $action ) {
						delete_emails( array( $helpscout_id, $id ) );

						return;
					}

					/*
					 * The FreeScout copy's marks first: its helpdesk, and the HelpScout conversation it replaces. A meta row is
					 * unique, so nothing it has already is written again.
					 */
					$marks = array( REPLACED_HELPSCOUT_META_KEY => (string) $helpscout_id );
					if ( 'rename' === $action ) {
						$marks[ HELPDESK_META_KEY ] = 'freescout';
					}
					foreach ( $marks as $meta_key => $meta_value ) {
						check_write(
							$wpdb->query(
								$wpdb->prepare(
									'INSERT INTO %i ( helpscout_id, meta_key, meta_value )
										SELECT %d, %s, %s FROM DUAL
										WHERE NOT EXISTS ( SELECT 1 FROM %i WHERE helpscout_id = %d AND meta_key = %s AND meta_value = %s )',
									$meta_table,
									$id,
									$meta_key,
									$meta_value,
									$meta_table,
									$id,
									$meta_key,
									$meta_value
								)
							)
						);
					}

					if ( 'name' === $action ) {
						return;
					}

					// It takes the plugins and themes it doesn't have yet, like those of a conversation merged into it.
					check_write(
						$wpdb->query(
							$wpdb->prepare(
								'INSERT INTO %i ( helpscout_id, meta_key, meta_value )
									SELECT %d, hs.meta_key, hs.meta_value FROM %i hs
									WHERE hs.helpscout_id = %d
										AND NOT EXISTS ( SELECT 1 FROM %i fs WHERE fs.helpscout_id = %d AND fs.meta_key = hs.meta_key AND fs.meta_value = hs.meta_value )',
								$meta_table,
								$id,
								$meta_table,
								$helpscout_id,
								$meta_table,
								$id
							)
						)
					);

					if ( 'rename' === $action ) {
						check_write( $wpdb->delete( $meta_table, array( 'helpscout_id' => $helpscout_id ) ) );
						check_write( $wpdb->update( $emails_table, array( 'id' => $id, 'number' => (int) ( $copy->number ?? 0 ) ), array( 'id' => $helpscout_id ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Two columns.
					} else {
						delete_emails( array( $helpscout_id ) );
					}
				}
			);

			++$replaced;
		} catch ( \Throwable $e ) {
			trigger_error( 'FreeScout copy of conversation ' . $id . ' failed: ' . $e->getMessage(), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- Logged, not shown.

			return null;
		} finally {
			unlock_email( $id );
		}
	}

	return $replaced;
}

$request  = get_request( basename( __FILE__ ) );
$replaced = replace_copies( (array) ( $request->copies ?? array() ) );

if ( null === $replaced ) {
	status_header( 503 );
	header( 'Retry-After: ' . LOCK_TIMEOUT );
	exit;
}

wp_send_json( array( 'replaced' => $replaced ) );
