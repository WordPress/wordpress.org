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
			/* translators: 1: Plugin name. 2: Plugin version. 3: URL to the automated security review documentation. */
			__( 'An automated security review of %1$s %2$s reported the following findings. Learn more about these reviews in the [plugin developer handbook](%3$s).', 'wporg-plugins' ),
			$this->excerpt( $this->plugin_title(), 200 ),
			$this->excerpt( (string) $record['version'], 32 ),
			'https://developer.wordpress.org/plugins/wordpress-org/automated-security-review/'
		);

		if ( 'blocked' === $record['action'] ) {
			$action = __( 'The issues found were severe enough to block this version from being offered as an update. Sites running a previous version keep receiving that version. Please address the findings and release a new version.', 'wporg-plugins' );
		} else {
			$action = __( 'Please review the findings and address them in an upcoming release.', 'wporg-plugins' );
		}

		$parts = [ $greeting, $intro, $action ];

		$findings = $this->findings_text( $record );
		if ( '' !== $findings ) {
			array_push( $parts, $findings, '---' );
		}

		$parts[] = __( 'If you have questions or believe a finding does not apply, please reply to this email with the details.', 'wporg-plugins' );

		return implode( "\n\n", $parts );
	}

	/**
	 * The plain-text content for the email template.
	 *
	 * Decodes the entities prose() encodes into the Markdown source.
	 *
	 * @return string The plain-text content.
	 */
	public function body(): string {
		return html_entity_decode( $this->markdown(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Format the findings, highest risk first.
	 *
	 * Finding strings are untrusted scanner output; only the risk score is
	 * contractually guaranteed. The title line ends in two spaces to
	 * hard-break in Markdown.
	 *
	 * @param array $record The completed scan record.
	 * @return string The findings, or an empty string without findings.
	 */
	private function findings_text( array $record ): string {
		$items = [];

		foreach ( $record['findings'] as $finding ) {
			$title = $this->excerpt( (string) ( $finding['title'] ?? '' ), 300 );

			$item = sprintf(
				'**%1$s** — %2$s',
				number_format_i18n( (float) ( $finding['risk_score'] ?? 0 ), 1 ),
				$title ?: __( '(no summary provided)', 'wporg-plugins' )
			);

			if ( ! empty( $finding['file_path'] ) ) {
				$file_path = $this->excerpt( (string) $finding['file_path'], 200 );
				$line      = (int) ( $finding['line'] ?? 0 );

				// The excerpted label can't contain a `]`, the URL is percent-encoded; the link syntax stays intact.
				$item .= sprintf(
					"  \n[%1\$s](%2\$s)",
					$file_path . ( $line ? ':' . $line : '' ),
					$this->file_url( (string) $record['release_ref'], (string) $finding['file_path'], $line )
				);
			}

			$snippet = $this->snippet_text( (string) ( $finding['code_snippet'] ?? '' ) );
			if ( '' !== $snippet ) {
				$item .= "\n\n" . $snippet;
			}

			$explanation = $this->prose( (string) ( $finding['explanation'] ?? '' ), 2000 );
			if ( '' !== $explanation ) {
				$item .= "\n\n" . $explanation;
			}

			$items[] = $item;
		}

		if ( ! $items ) {
			return '';
		}

		return '### ' . __( 'Findings', 'wporg-plugins' ) . "\n\n" . implode( "\n\n---\n\n", $items );
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
	 * Format an untrusted code snippet as an indented Markdown code block.
	 *
	 * Unlike a fence, an indented code block cannot be broken out of, and
	 * Markdown escapes its content in the HTML variant.
	 *
	 * @param string $snippet The code snippet.
	 * @return string The code block, or an empty string for an empty snippet.
	 */
	private function snippet_text( string $snippet ): string {
		// Normalize every newline the Markdown processor recognizes (it maps \r\n and lone \r to \n): an unindented line it splits out later escapes the code block.
		$snippet = str_replace( [ "\r\n", "\r" ], "\n", trim( $snippet, "\n\r" ) );
		$lines   = array_slice( explode( "\n", $snippet ), 0, 10 );
		$snippet = mb_strimwidth( implode( "\n", $this->outdent( $lines ) ), 0, 1000, '…' );

		if ( '' === trim( $snippet ) ) {
			return '';
		}

		return '    ' . str_replace( "\n", "\n    ", $snippet );
	}

	/**
	 * Strip the whitespace prefix shared by all non-blank lines, keeping
	 * the block's relative indentation.
	 *
	 * @param array $lines The lines to outdent.
	 * @return array The outdented lines.
	 */
	private function outdent( array $lines ): array {
		$prefix = null;

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$indent = substr( $line, 0, strspn( $line, " \t" ) );

			if ( null === $prefix ) {
				$prefix = $indent;
				continue;
			}

			$length     = 0;
			$max_length = min( strlen( $prefix ), strlen( $indent ) );
			while ( $length < $max_length && $prefix[ $length ] === $indent[ $length ] ) {
				++$length;
			}
			$prefix = substr( $prefix, 0, $length );

			if ( '' === $prefix ) {
				break;
			}
		}

		if ( ! $prefix ) {
			return $lines;
		}

		return array_map(
			static function ( string $line ) use ( $prefix ): string {
				return str_starts_with( $line, $prefix ) ? substr( $line, strlen( $prefix ) ) : $line;
			},
			$lines
		);
	}

	/**
	 * Bound untrusted prose, preserving paragraphs, without live markup.
	 *
	 * Angle brackets are encoded rather than stripped, so text like `<slug>`
	 * or `<?php` survives as text; body() decodes the plain-text variant.
	 * Backticks become apostrophes: a line-leading backtick pair would turn
	 * into a code block (Markdown::code_trick()). Line indents go for the
	 * same reason, and brackets can't form Markdown links or images. A
	 * line-leading heading or rule marker is emitted as a numeric entity, so
	 * it displays as typed but can't forge a heading or horizontal rule; a
	 * lone carriage return is folded first, since the Markdown processor
	 * treats it as a newline and would otherwise expose a marker mid-text.
	 *
	 * @param string $text   The text to bound.
	 * @param int    $length Maximum length in characters.
	 * @return string The bounded text.
	 */
	private function prose( string $text, int $length ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", trim( $text ) );
		$text = preg_replace( '/^[ \t]+/m', '', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = mb_strimwidth( $text, 0, $length, '…' );
		$text = str_replace( [ '[', ']', '`' ], [ '(', ')', "'" ], $text );
		$text = htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );

		return preg_replace_callback(
			'/^([#=*_-])/m',
			static function ( array $matches ): string {
				return '&#' . ord( $matches[1] ) . ';';
			},
			$text
		);
	}

	/**
	 * Collapse untrusted text onto a single bounded line, without markup.
	 *
	 * @param string $text   The text to excerpt.
	 * @param int    $length Maximum length in characters.
	 * @return string The excerpted text.
	 */
	private function excerpt( string $text, int $length ): string {
		return preg_replace( '/\s+/u', ' ', $this->prose( $text, $length ) );
	}
}
