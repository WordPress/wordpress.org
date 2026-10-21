<?php
/**
 * Gives imported conversations HelpScout's tags, in the Tags module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Tags conversations as HelpScout did, creating tags FreeScout doesn't have yet in HelpScout's color.
 *
 * The Tags module is a paid one, so its tables are written directly, without its classes; that way, nothing reacts to
 * the tags either, like workflows do when an agent adds one.
 */
final class Tags {

	/**
	 * Alias of the Tags module.
	 *
	 * @var string
	 */
	public const MODULE = 'tags';

	/**
	 * Longest tag name the module keeps.
	 *
	 * @var int
	 */
	private const NAME_LENGTH = 191;

	/**
	 * The module's tag colors, by their number, as its stylesheet shows them.
	 *
	 * @var string[]
	 */
	private const PALETTE = array(
		0  => '97a4b0',
		1  => '52ad67',
		2  => '349de9',
		3  => 'f68f33',
		4  => '8c75bd',
		5  => 'f0554f',
		6  => '9e6937',
		7  => 'e0e700',
		8  => 'c505ac',
		9  => '8fcb3d',
		10 => '02d7d7',
		11 => 'b7b7ff',
	);

	/**
	 * Whether the Tags module is on, so there's somewhere to keep tags.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		try {
			return (bool) \App\Module::isActive( self::MODULE ) && Schema::hasTable( 'tags' ) && Schema::hasTable( 'conversation_tag' );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * A HelpScout tag's name, as HelpScout gives it.
	 *
	 * @param mixed $tag Tag, with `tag` or `name`; or its name.
	 * @return string
	 */
	public static function name( $tag ): string {
		if ( is_array( $tag ) ) {
			return (string) ( $tag['tag'] ?? $tag['name'] ?? '' );
		}

		return is_scalar( $tag ) ? (string) $tag : '';
	}

	/**
	 * Brings a conversation's tags up to date with HelpScout's, keeping agents' changes.
	 *
	 * Only tags HelpScout added since the last import are added, so those agents removed, or administrators deleted,
	 * stay away. Tags HelpScout removed since are taken away, if the import added them.
	 *
	 * @param int         $conversation_id FreeScout conversation ID.
	 * @param array       $tags            HelpScout's tags, each with `tag` and `color`.
	 * @param bool[]|null $written         What the last import returned; null if none gave the conversation tags.
	 * @return bool[] HelpScout's tags by their name, and whether the import added each, to give the next import.
	 */
	public static function sync( int $conversation_id, array $tags, ?array $written ): array {
		$colors = array();
		foreach ( $tags as $tag ) {
			$name = self::normalize( self::name( $tag ) );
			if ( '' !== $name && ! isset( $colors[ $name ] ) ) {
				$colors[ $name ] = is_array( $tag ) ? (string) ( $tag['color'] ?? '' ) : '';
			}
		}

		$written = (array) $written;
		$added   = array();
		foreach ( $colors as $name => $color ) {
			$tag_id = (int) \DB::table( 'tags' )->where( 'name', $name )->value( 'id' );

			// HelpScout had it last time already: it's added only once.
			if ( array_key_exists( $name, $written ) ) {
				$added[ $name ] = ! empty( $written[ $name ] ) && $tag_id && self::tagged( $conversation_id, $tag_id );
				continue;
			}

			if ( ! $tag_id ) {
				$tag_id = (int) \DB::table( 'tags' )->insertGetId(
					array(
						'name'    => $name,
						'color'   => self::color( $color ),
						'counter' => 0,
					)
				);
			}

			$added[ $name ] = ! self::tagged( $conversation_id, $tag_id );
			if ( $added[ $name ] ) {
				\DB::table( 'conversation_tag' )->insert(
					array(
						'conversation_id' => $conversation_id,
						'tag_id'          => $tag_id,
					)
				);
				\DB::table( 'tags' )->where( 'id', $tag_id )->increment( 'counter' );
			}
		}

		foreach ( $written as $name => $was_added ) {
			$tag_id = $was_added && ! isset( $colors[ $name ] ) ? (int) \DB::table( 'tags' )->where( 'name', (string) $name )->value( 'id' ) : 0;
			if ( $tag_id && self::tagged( $conversation_id, $tag_id ) ) {
				\DB::table( 'conversation_tag' )->where( 'conversation_id', $conversation_id )->where( 'tag_id', $tag_id )->delete();
				\DB::table( 'tags' )->where( 'id', $tag_id )->where( 'counter', '>', 0 )->decrement( 'counter' );
			}
		}

		return $added;
	}

	/**
	 * Whether a conversation has a tag.
	 *
	 * @param int $conversation_id FreeScout conversation ID.
	 * @param int $tag_id          Tag ID.
	 * @return bool
	 */
	private static function tagged( int $conversation_id, int $tag_id ): bool {
		return \DB::table( 'conversation_tag' )->where( 'conversation_id', $conversation_id )->where( 'tag_id', $tag_id )->exists();
	}

	/**
	 * A tag's name as the module keeps it: trimmed, and lowercase.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function normalize( string $name ): string {
		return mb_substr( mb_strtolower( (string) preg_replace( '/^\s+|\s+$/u', '', $name ) ), 0, self::NAME_LENGTH );
	}

	/**
	 * The module's color nearest to HelpScout's.
	 *
	 * @param string $hex HelpScout's color, like `#929499`.
	 * @return int
	 */
	private static function color( string $hex ): int {
		$rgb = self::rgb( $hex );
		if ( ! $rgb ) {
			return 0;
		}

		$nearest  = 0;
		$distance = PHP_INT_MAX;
		foreach ( self::PALETTE as $color => $palette_hex ) {
			$palette = (array) self::rgb( $palette_hex );
			$squares = ( $rgb[0] - $palette[0] ) ** 2 + ( $rgb[1] - $palette[1] ) ** 2 + ( $rgb[2] - $palette[2] ) ** 2;
			if ( $squares < $distance ) {
				$nearest  = $color;
				$distance = $squares;
			}
		}

		return $nearest;
	}

	/**
	 * A hex color's red, green, and blue.
	 *
	 * @param string $hex Color, like `#929499`.
	 * @return int[]|null Null if it isn't a 6-digit hex color.
	 */
	private static function rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null;
		}

		return array_map( 'hexdec', str_split( $hex, 2 ) );
	}
}
