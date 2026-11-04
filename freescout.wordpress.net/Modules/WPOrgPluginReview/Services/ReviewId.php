<?php
/**
 * Reads the Review ID line that ends every plugin review email.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Takes a Review ID line apart.
 *
 * The line is `Review ID: {type} ❗{flags} {slug}/{username}/{first review}/T{reviews} {date}/{version} (P0TDX{plugin ID}HGN)`,
 * put together from whatever the review knew, so any piece but the type can be missing. Each piece is recognized by its
 * own shape, so a missing one only costs its own value. Dates are written like 29Jul26: their month starts with a capital,
 * so they can't be mistaken for a slug, which is always lowercase.
 */
final class ReviewId {

	/**
	 * A date, as the reviews write them.
	 *
	 * @var string
	 */
	private const DATE = '/^\d{1,2}[A-Z][a-z]{2}\d{2}$/';

	/**
	 * How many times the plugin was reviewed: T3, or TX when it isn't known.
	 *
	 * @var string
	 */
	private const TIMES = '/^T(\d+|X)$/';

	/**
	 * The plugin's ID, hidden between P0TDX and HGN.
	 *
	 * @var string
	 */
	private const PLUGIN_ID = '/P0TDX(\d+)HGN/';

	/**
	 * The mark before flags.
	 *
	 * @var string
	 */
	private const FLAG_MARK = '❗';

	/**
	 * What a review writes for a piece it couldn't tell, like the security scanner's reviews do; it's read as missing.
	 *
	 * @var string
	 */
	private const UNKNOWN = 'unknown';

	/**
	 * Types of the emails that follow up on a review instead of being one, which write `Review:` instead of `Review ID:`.
	 *
	 * @var string[]
	 */
	public const FOLLOW_UP_TYPES = array( 'CHANGESNOTMADE', 'WRONGFORMAT' );

	/**
	 * Parses a line.
	 *
	 * The follow-up emails write `Review:` instead of `Review ID:`; such a line only counts with one of their types, so
	 * a sentence starting with "Review:" isn't taken for one.
	 *
	 * @param string $line Line of text.
	 * @return array|null The pieces: type, flags, slug, username, started, reviews, reviewed, version, plugin_id; null if it isn't a Review ID line.
	 */
	public static function parse( string $line ): ?array {
		// Without the variation selector that sometimes follows the mark.
		$line = trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', str_replace( "\u{FE0F}", '', $line ) ) );
		if ( ! preg_match( '/^Review( ID)?:\s*(.*)$/iu', $line, $matches ) ) {
			return null;
		}

		$tokens = array_values( array_filter( explode( ' ', trim( $matches[2] ) ), 'strlen' ) );
		if ( ! $tokens ) {
			return null;
		}

		$type = array_shift( $tokens );
		if ( '' === $matches[1] && ! self::is_follow_up( $type ) ) {
			return null;
		}

		/*
		 * Flags follow one or more marks, glued to them or not. Some templates carry a flag of their own and the review
		 * adds the ones it found, so there can be several marks; they're read as one list, like "OWN-LIC".
		 */
		$flags = array();
		while ( $tokens && str_starts_with( $tokens[0], self::FLAG_MARK ) ) {
			$glued = trim( substr( (string) array_shift( $tokens ), strlen( self::FLAG_MARK ) ) );
			if ( '' !== $glued ) {
				$flags[] = $glued;
			} elseif ( $tokens && preg_match( '/^[A-Za-z0-9-]+$/', $tokens[0] ) ) {
				$flags[] = (string) array_shift( $tokens );
			}
		}
		$flags = array_values( array_filter( explode( '-', implode( '-', $flags ) ), 'strlen' ) );

		$plugin_id = 0;
		$tokens    = array_values(
			array_filter(
				$tokens,
				static function ( string $token ) use ( &$plugin_id ): bool {
					if ( ! preg_match( self::PLUGIN_ID, $token, $id ) ) {
						return true;
					}
					$plugin_id = (int) $id[1];

					return false;
				}
			)
		);

		/*
		 * What's left is "{slug}/{username}/{first review}/T{reviews} {date}/{version}". The review's date is the last
		 * piece, so it's looked for from the end; when nothing looks like a date, the last piece is still the date and
		 * version, unless it's the only one, which can then only describe the plugin.
		 */
		$tail = -1;
		for ( $i = count( $tokens ) - 1; $i >= 0; $i-- ) {
			if ( preg_match( self::DATE, explode( '/', $tokens[ $i ] )[0] ) ) {
				$tail = $i;
				break;
			}
		}
		if ( -1 === $tail && count( $tokens ) > 1 ) {
			$tail = count( $tokens ) - 1;
		}

		$reviewed = '';
		$version  = '';
		if ( -1 !== $tail ) {
			$pieces   = explode( '/', $tokens[ $tail ] );
			$reviewed = self::known( (string) array_shift( $pieces ) );
			// Anything else is the version, even one with a space in it.
			$version = self::known( implode( ' ', array_filter( array( implode( '/', $pieces ), implode( ' ', array_slice( $tokens, $tail + 1 ) ) ), 'strlen' ) ) );
		}

		// Placeholders keep their place, so a slug that wasn't known doesn't make the username the slug.
		$segments = array_map(
			static function ( string $piece ): string {
				return self::known( $piece );
			},
			array_values(
				array_filter(
					array_map( 'trim', explode( '/', implode( '/', array_slice( $tokens, 0, -1 === $tail ? count( $tokens ) : $tail ) ) ) ),
					'strlen'
				)
			)
		);

		// Read from the end, in the order they're written: the number of reviews last, the first review's date before it.
		$reviews = null;
		if ( $segments && preg_match( self::TIMES, (string) end( $segments ), $times ) ) {
			array_pop( $segments );
			$reviews = 'X' === $times[1] ? null : (int) $times[1];
		}

		$started = '';
		if ( $segments && preg_match( self::DATE, (string) end( $segments ) ) ) {
			$started = (string) array_pop( $segments );
		}

		return array(
			'type'      => $type,
			'flags'     => $flags,
			'slug'      => (string) ( $segments[0] ?? '' ),
			'username'  => (string) ( $segments[1] ?? '' ),
			'started'   => $started,
			'reviews'   => $reviews,
			'reviewed'  => $reviewed,
			'version'   => $version,
			'plugin_id' => $plugin_id,
		);
	}

	/**
	 * Whether a type is one of the emails that follow up on a review, rather than a review.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_follow_up( string $type ): bool {
		return in_array( $type, self::FOLLOW_UP_TYPES, true );
	}

	/**
	 * A piece of the line, empty if the review wrote that it didn't know it.
	 *
	 * @param string $piece Piece.
	 * @return string
	 */
	private static function known( string $piece ): string {
		return self::UNKNOWN === $piece ? '' : $piece;
	}
}
