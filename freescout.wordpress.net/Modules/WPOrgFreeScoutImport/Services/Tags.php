<?php
/**
 * Gives imported conversations the other FreeScout's tags, in the Tags module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Tags conversations as the other FreeScout did, creating tags this one doesn't have yet in their color there.
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
	 * Brings a conversation's tags up to date with the source's, keeping agents' changes.
	 *
	 * Only tags the source added since the last import are added, so those agents removed, or administrators deleted,
	 * stay away. Tags the source removed since are taken away, if the import added them.
	 *
	 * @param int         $conversation_id FreeScout conversation ID.
	 * @param string[]    $tags            The source's tag names.
	 * @param bool[]|null $written         What the last import returned; null if none gave the conversation tags.
	 * @param int[]       $colors          The source's colors, by tag name.
	 * @return bool[] The source's tags by their name, and whether the import added each, to give the next import.
	 */
	public static function sync( int $conversation_id, array $tags, ?array $written, array $colors ): array {
		$wanted = array();
		foreach ( $tags as $tag ) {
			$name = self::normalize( (string) $tag );
			if ( '' !== $name && ! isset( $wanted[ $name ] ) ) {
				$wanted[ $name ] = (int) ( $colors[ (string) $tag ] ?? $colors[ $name ] ?? 0 );
			}
		}

		$written = (array) $written;
		$added   = array();
		foreach ( $wanted as $name => $color ) {
			$tag_id = (int) \DB::table( 'tags' )->where( 'name', $name )->value( 'id' );

			// The source had it last time already: it's added only once.
			if ( array_key_exists( $name, $written ) ) {
				$added[ $name ] = ! empty( $written[ $name ] ) && $tag_id && self::tagged( $conversation_id, $tag_id );
				continue;
			}

			if ( ! $tag_id ) {
				$tag_id = (int) \DB::table( 'tags' )->insertGetId(
					array(
						'name'    => $name,
						'color'   => $color,
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
			$tag_id = $was_added && ! isset( $wanted[ $name ] ) ? (int) \DB::table( 'tags' )->where( 'name', (string) $name )->value( 'id' ) : 0;
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
}
