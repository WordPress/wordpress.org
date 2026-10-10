<?php
/**
 * Shortens the subjects of the plugin directory's emails, for the browser tab.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Turns a subject like "[WordPress Plugin Directory] Review in Progress: My Plugin" into "R: My Plugin", so a reviewer
 * with many conversations open can tell their tabs apart.
 */
final class Subject {

	/**
	 * How the plugin directory's subjects start.
	 *
	 * @var string
	 */
	private const PREFIX = '[WordPress Plugin Directory]';

	/**
	 * Short names of the kinds of email, by how their subjects name them; others keep their own name.
	 *
	 * @var string[]
	 */
	private const KINDS = array(
		'Review in Progress'    => 'R',
		'Rejection Explanation' => 'Rejected',
		'Security Notice'       => 'Security',
		'Closure Notice'        => 'Closed',
	);

	/**
	 * A subject's short version.
	 *
	 * @param string $subject Subject.
	 * @return string Empty if it isn't one of the plugin directory's.
	 */
	public static function short( string $subject ): string {
		// Replies and forwards keep the subject they answer.
		$subject = trim( (string) preg_replace( '/^(?:(?:re|fwd?)\s*:\s*)+/i', '', trim( $subject ) ) );
		if ( 0 !== stripos( $subject, self::PREFIX ) ) {
			return '';
		}

		$subject = trim( substr( $subject, strlen( self::PREFIX ) ) );
		$colon   = strpos( $subject, ': ' );
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
}
