<?php
namespace WordPressdotorg\Plugin_Directory;

if ( ! class_exists( '\Michelf\MarkdownExtra' ) ) {
	include __DIR__ . '/libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php';
}

/**
 * WordPress.org Plugin Readme Parser Markdown.
 *
 * Relies on \Michaelf\Markdown_Extra
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Markdown extends \Michelf\MarkdownExtra {

	public function transform( $text ) {
		$text = $this->code_trick( trim( $text ) );
		// Convert any `= Section =` headers into a real header
		$text = preg_replace( '/^[\s]*=[\s]+(.+?)[\s]+=/m', "\n" . '<h4>$1</h4>' . "\n", $text );

		$text = parent::transform( trim( $text ) );

		return trim( $text );
	}

	protected function code_trick( $text ) {
		// when doing markdown, first take any user formatted code blocks and turn them into backticks so that
		// markdown will preserve things like underscores in code blocks
		$text = preg_replace_callback( "!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", array( $this, 'code_trick_decodeit_cb' ), $text );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		// Markdown can do inline code, we convert bbPress style block level code to Markdown style
		$text = preg_replace_callback( "!(^|\n)([ \t]*?)`(.*?)`!s", array( $this, 'code_trick_indent_cb' ), $text );

		return $text;
	}

	protected function code_trick_indent_cb( $matches ) {
		$text = $matches[3];
		$text = preg_replace( '|^|m', $matches[2] . '    ', $text );

		return $matches[1] . $text;
	}

	protected function code_trick_decodeit_cb( $matches ) {
		$text        = $matches[2];
		$trans_table = array_flip( get_html_translation_table( HTML_ENTITIES ) );
		$text        = strtr( $text, $trans_table );
		$text        = str_replace( '<br />', '', $text );
		$text        = str_replace( '&#38;', '&', $text );
		$text        = str_replace( '&#39;', "'", $text );

		if ( '<pre><code>' == $matches[1] ) {
			$text = "\n$text\n";
		}

		return "`$text`";
	}
}
