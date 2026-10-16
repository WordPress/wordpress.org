<?php
/**
 * Shortens the subjects of the plugin directory's emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Turns a subject like "[WordPress Plugin Directory] Review in Progress: My Plugin" into "🔌 My Plugin", so a reviewer
 * can tell conversations, and tabs, apart at a glance.
 */
final class Subject {

	/**
	 * How the plugin directory's subjects start.
	 *
	 * @var string
	 */
	private const PREFIX = '[WordPress Plugin Directory]';

	/**
	 * What goes before the plugin's name, by how the subjects of the most common kinds of email name them.
	 *
	 * @var string[]
	 */
	private const MARKS = array(
		'Review in Progress'                             => '🔌',
		'Closure Notice - Licensing/Trademark Violation' => '🔌™️',
		'Closure Notice - Guideline Violation'           => '🔌📜',
		'Notice'                                         => '🔌ℹ️',
		'Security Notice'                                => '🔌🛡️',
		'Rejection Explanation'                          => '🔌🚫',
	);

	/**
	 * Short names of the other kinds of email, for the browser tab; others keep their own name.
	 *
	 * @var string[]
	 */
	private const KINDS = array(
		'Closure Notice' => 'Closed',
	);

	/**
	 * A subject with its kind's mark in place of everything before the plugin's name, which the conversation, its tab,
	 * and lists of conversations show.
	 *
	 * @param string $subject Subject.
	 * @return string Empty if it isn't of one of the kinds with a mark.
	 */
	public static function marked( string $subject ): string {
		$subject = self::without_prefix( $subject );
		$colon   = null === $subject ? false : strpos( $subject, ': ' );
		if ( false === $colon ) {
			return '';
		}

		$mark = self::MARKS[ trim( substr( $subject, 0, $colon ) ) ] ?? '';

		return '' === $mark ? '' : $mark . ' ' . trim( substr( $subject, $colon + 2 ) );
	}

	/**
	 * A subject's short version, for the browser tab: the marked one, or one without the prefix for other kinds.
	 *
	 * @param string $subject Subject.
	 * @return string Empty if it isn't one of the plugin directory's.
	 */
	public static function short( string $subject ): string {
		$marked = self::marked( $subject );
		if ( '' !== $marked ) {
			return $marked;
		}

		$subject = self::without_prefix( $subject );
		if ( null === $subject ) {
			return '';
		}

		$colon = strpos( $subject, ': ' );
		if ( false === $colon ) {
			return $subject;
		}

		$kind = trim( substr( $subject, 0, $colon ) );
		$name = trim( substr( $subject, $colon + 2 ) );

		// A closure names its reason after a dash: "Closure Notice - Trademarks".
		$reason = '';
		if ( preg_match( '/^(.+?)\s+-\s+(.+)$/', $kind, $matches ) ) {
			$kind   = $matches[1];
			$reason = $matches[2];
		}

		$short = self::KINDS[ $kind ] ?? $kind;
		if ( '' !== $reason ) {
			$short .= ' (' . $reason . ')';
		}

		return $short . ': ' . $name;
	}

	/**
	 * A plugin directory's subject without its prefix, nor those of replies and forwards.
	 *
	 * @param string $subject Subject.
	 * @return string|null Null if it isn't one of the plugin directory's.
	 */
	private static function without_prefix( string $subject ): ?string {
		// Replies and forwards keep the subject they answer.
		$subject = trim( (string) preg_replace( '/^(?:(?:re|fwd?)\s*:\s*)+/i', '', trim( $subject ) ) );
		if ( 0 !== stripos( $subject, self::PREFIX ) ) {
			return null;
		}

		return trim( substr( $subject, strlen( self::PREFIX ) ) );
	}
}
