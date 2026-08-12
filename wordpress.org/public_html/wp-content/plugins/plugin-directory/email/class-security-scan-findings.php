<?php
/**
 * Email to plugin committers about the outcome of a security scan.
 *
 * @package WordPressdotorg\Plugin_Directory\Email
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Email;

/**
 * Notifies plugin committers of a completed security scan's findings, and
 * whether the scanned release was blocked from being served pending review.
 *
 * Expects a `record` arg: the completed scan record built by
 * `Plugin_Scan_Gandalf`, with findings ordered highest risk first.
 */
class Security_Scan_Findings extends Markdown_Base {

	/**
	 * The completed scan record is required.
	 *
	 * @var array
	 */
	protected $required_args = [ 'record' ];

	/**
	 * The email subject.
	 *
	 * @return string The subject.
	 */
	public function subject(): string {
		$record = $this->args['record'];

		if ( 'blocked' === $record['action'] ) {
			/* translators: 1: Plugin name. 2: Plugin version. */
			$subject = __( '%1$s %2$s has been blocked due to security findings', 'wporg-plugins' );
		} else {
			/* translators: 1: Plugin name. 2: Plugin version. */
			$subject = __( 'Security scan findings in %1$s %2$s', 'wporg-plugins' );
		}

		return sprintf( $subject, $this->plugin_title(), $record['version'] );
	}

	/**
	 * The Markdown content of the email.
	 *
	 * @return string The email content.
	 */
	public function markdown(): string {
		$record = $this->args['record'];

		$greeting = sprintf(
			/* translators: %s: Committer's display name. */
			__( 'Howdy %s,', 'wporg-plugins' ),
			$this->user_text( $this->user )
		);

		$intro = sprintf(
			/* translators: 1: Plugin name. 2: Plugin version. 3: Maximum risk score, from 0 to 10. */
			__( 'An automated security scan of %1$s %2$s reported findings with a maximum risk score of %3$s out of 10.', 'wporg-plugins' ),
			$this->plugin_title(),
			$record['version'],
			number_format_i18n( (float) $record['max_risk_score'], 1 )
		);

		if ( 'blocked' === $record['action'] ) {
			$action = __( 'A security review of this version found issues severe enough to block it from being offered as an update, protecting sites from receiving it. Sites running a previous version keep receiving that version. Please address the findings and release a new version; the block applies only to this version.', 'wporg-plugins' );
		} else {
			$action = __( 'Please review the findings and address them in an upcoming release.', 'wporg-plugins' );
		}

		$outro = __( 'If you have questions or believe these findings to be in error, please reply to this email or contact plugins@wordpress.org.', 'wporg-plugins' );

		return implode( "\n\n", array_filter( [ $greeting, $intro, $action, $this->findings_text( $record ), $outro ] ) );
	}

	/**
	 * Format the findings as a list, highest risk first.
	 *
	 * Finding strings are untrusted scanner output; only the risk score is
	 * contractually guaranteed. Lines end in two spaces to hard-break in Markdown.
	 *
	 * @param array $record The completed scan record.
	 * @return string The findings list, or an empty string without findings.
	 */
	private function findings_text( array $record ): string {
		$items = [];

		foreach ( $record['findings'] as $finding ) {
			$title = $this->excerpt( (string) ( $finding['title'] ?? '' ), 300 );

			$item = sprintf(
				'* **%1$s** — %2$s',
				number_format_i18n( (float) ( $finding['risk_score'] ?? 0 ), 1 ),
				$title ?: __( '(no summary provided)', 'wporg-plugins' )
			);

			if ( ! empty( $finding['file_path'] ) ) {
				$file_path = $this->excerpt( (string) $finding['file_path'], 200 );
				$line      = (int) ( $finding['line'] ?? 0 );

				$item .= "  \n  " . $file_path . ( $line ? ':' . $line : '' );
				$item .= "  \n  " . $this->file_url( (string) $record['release_ref'], (string) $finding['file_path'], $line );
			}

			$items[] = $item;
		}

		if ( ! $items ) {
			return '';
		}

		return __( 'Findings:', 'wporg-plugins' ) . "\n\n" . implode( "\n", $items );
	}

	/**
	 * Return a link to the finding's file in the plugins Trac browser.
	 *
	 * @param string $release_ref The scanned release ref.
	 * @param string $file_path   The file path, relative to the plugin root.
	 * @param int    $line        The line number, or 0 for none.
	 * @return string The Trac browser URL.
	 */
	private function file_url( string $release_ref, string $file_path, int $line ): string {
		$url = sprintf(
			'https://plugins.trac.wordpress.org/browser/%s/%s/%s',
			$this->plugin->post_name,
			'trunk' === $release_ref ? 'trunk' : 'tags/' . rawurlencode( $release_ref ),
			implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $file_path, '/' ) ) ) )
		);

		if ( $line ) {
			$url .= '#L' . $line;
		}

		return $url;
	}

	/**
	 * Collapse untrusted text onto a single bounded line, without markup.
	 *
	 * @param string $text   The text to excerpt.
	 * @param int    $length Maximum length in characters.
	 * @return string The excerpted text.
	 */
	private function excerpt( string $text, int $length ): string {
		$text = wp_strip_all_tags( $text );

		// Neutralize Markdown link and image syntax; the HTML variant renders Markdown.
		$text = str_replace( [ '[', ']' ], [ '(', ')' ], $text );

		return mb_strimwidth( preg_replace( '/\s+/u', ' ', trim( $text ) ), 0, $length, '…' );
	}
}
