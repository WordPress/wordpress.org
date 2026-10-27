<?php
/**
 * Which sidebar panels show in which mailboxes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Services;

/**
 * Panels without `per_mailbox` show everywhere; the others only in the mailboxes chosen in their settings section.
 */
final class Panels {

	/**
	 * Prefix of the settings sections, one per panel with `per_mailbox`, followed by the panel ID.
	 *
	 * @var string
	 */
	public const SECTION_PREFIX = 'wporgsidebar-';

	/**
	 * Gets every configured panel, keyed by ID.
	 *
	 * @return array
	 */
	public static function all(): array {
		return (array) config( 'wporgsidebar.panels' );
	}

	/**
	 * Gets the panels whose mailboxes are chosen in settings, keyed by ID.
	 *
	 * @return array
	 */
	public static function per_mailbox(): array {
		return array_filter(
			self::all(),
			static function ( array $panel ): bool {
				return ! empty( $panel['per_mailbox'] );
			}
		);
	}

	/**
	 * Gets the panels that show in a mailbox, keyed by ID, in their configured order.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return array
	 */
	public static function for_mailbox( int $mailbox_id ): array {
		return array_filter(
			self::all(),
			static function ( string $panel_id ) use ( $mailbox_id ): bool {
				return self::shows( $panel_id, $mailbox_id );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Whether a panel shows in a mailbox.
	 *
	 * @param string $panel_id   Panel ID.
	 * @param int    $mailbox_id Mailbox ID.
	 * @return bool
	 */
	public static function shows( string $panel_id, int $mailbox_id ): bool {
		$panel = self::all()[ $panel_id ] ?? null;
		if ( ! is_array( $panel ) ) {
			return false;
		}

		return empty( $panel['per_mailbox'] ) || in_array( $mailbox_id, self::mailbox_ids( $panel_id ), true );
	}

	/**
	 * Gets the IDs of the mailboxes a panel with `per_mailbox` is chosen for.
	 *
	 * @param string $panel_id Panel ID.
	 * @return int[]
	 */
	public static function mailbox_ids( string $panel_id ): array {
		return array_values( array_unique( array_map( 'intval', (array) \Option::get( self::option( $panel_id ) ) ) ) );
	}

	/**
	 * Gets the name of the option a panel's mailboxes are saved in.
	 *
	 * @param string $panel_id Panel ID.
	 * @return string
	 */
	public static function option( string $panel_id ): string {
		return 'wporgsidebar.mailboxes_' . $panel_id;
	}

	/**
	 * Gets the panel a settings section is for, if it's one of this module's.
	 *
	 * @param string $section Settings section.
	 * @return string Panel ID, or empty.
	 */
	public static function panel_for_section( string $section ): string {
		if ( ! str_starts_with( $section, self::SECTION_PREFIX ) ) {
			return '';
		}

		$panel_id = substr( $section, strlen( self::SECTION_PREFIX ) );

		return isset( self::per_mailbox()[ $panel_id ] ) ? $panel_id : '';
	}
}
