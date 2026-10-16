<?php
/**
 * Reads what reviewers need from a review email.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Takes a review email's HTML apart: its Review ID, the issues it lists, the names it suggests, and the plugin's URLs.
 *
 * Quoted text is left out, so an author's reply that quotes a review isn't read as one.
 */
final class ReviewEmail {

	/**
	 * Marks that start an issue's title: the red circle of the headings, and the older Markdown heading.
	 *
	 * Yellow circles head warnings, which don't hold a review up, so they aren't listed.
	 *
	 * @var string
	 */
	private const ISSUE_PREFIX = '/^(?:##|🔴)[\x{FE0F}\s]*/u';

	/**
	 * A web address in text, up to whatever can't be part of one.
	 *
	 * @var string
	 */
	private const URL = '#\bhttps?://[^\s<>"\']+#i';

	/**
	 * Punctuation that ends a sentence rather than an address written in it.
	 *
	 * @var string
	 */
	private const URL_TRAILER = '.,;:!?)]}';

	/**
	 * Elements that start a line of text.
	 *
	 * @var string[]
	 */
	private const BLOCKS = array( 'address', 'blockquote', 'br', 'div', 'dd', 'dl', 'dt', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'li', 'ol', 'p', 'pre', 'table', 'td', 'th', 'tr', 'ul' );

	/**
	 * The email, without quoted text.
	 *
	 * @var \DOMDocument
	 */
	private $document;

	/**
	 * Lines of text.
	 *
	 * @var string[]
	 */
	private $lines;

	/**
	 * Whether the body is plain text, whose lines end with line breaks instead of elements.
	 *
	 * @var bool
	 */
	private $plain;

	/**
	 * Constructor.
	 *
	 * @param string $html Email body.
	 */
	public function __construct( string $html ) {
		$this->plain    = ! preg_match( '#<(?:' . implode( '|', self::BLOCKS ) . ')\b#i', $html );
		$this->document = new \DOMDocument();

		$errors = libxml_use_internal_errors( true );
		// The meta element tells libxml the body is UTF-8.
		$this->document->loadHTML( '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $errors );

		$xpath = new \DOMXPath( $this->document );
		foreach ( iterator_to_array( $xpath->query( '//blockquote | //*[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]' ) ) as $quote ) {
			$quote->remove();
		}

		$text        = $this->text( $this->document->getElementsByTagName( 'body' )->item( 0 ) );
		$this->lines = array_values( array_filter( array_map( 'trim', preg_split( '/\n/', $text ) ), 'strlen' ) );
	}

	/**
	 * The email's Review ID: its last Review ID line.
	 *
	 * An upload confirmation has none: it carries the author's comment, which could be a Review ID line.
	 *
	 * @return array|null The line's pieces, see ReviewId::parse(); null if the email has none.
	 */
	public function review_id(): ?array {
		if ( $this->is_upload_confirmation() ) {
			return null;
		}

		foreach ( array_reverse( $this->lines ) as $line ) {
			$review_id = ReviewId::parse( $line );
			if ( $review_id ) {
				return $review_id;
			}
		}

		return null;
	}

	/**
	 * Whether the email is WordPress.org's confirmation of an uploaded update.
	 *
	 * @return bool
	 */
	private function is_upload_confirmation(): bool {
		foreach ( $this->lines as $line ) {
			if ( str_contains( $line, Review::UPLOAD_CONFIRMATION ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The issues the email lists, in its order, each once.
	 *
	 * @return array[] Title as the email writes it, short name, and priority; see IssueNames::name().
	 */
	public function issues(): array {
		$issues = array();

		foreach ( $this->lines as $line ) {
			if ( ! preg_match( self::ISSUE_PREFIX, $line ) ) {
				continue;
			}

			$title = trim( (string) preg_replace( self::ISSUE_PREFIX, '', $line ) );
			if ( '' === $title ) {
				continue;
			}

			$issue = IssueNames::name( $title );
			if ( ! isset( $issues[ $issue['name'] ] ) ) {
				$issues[ $issue['name'] ] = array( 'title' => $title ) + $issue;
			}
		}

		return array_values( $issues );
	}

	/**
	 * The name, or slug, the email suggests instead of the plugin's: the code, or bold text, of the list item that offers it.
	 *
	 * @param string $what Name, or slug.
	 * @return string Empty if it suggests none.
	 */
	public function suggested( string $what ): string {
		foreach ( $this->document->getElementsByTagName( 'li' ) as $item ) {
			if ( false === mb_stripos( $item->textContent, 'alternative ' . $what ) ) {
				continue;
			}

			foreach ( array( 'code', 'strong' ) as $tag ) {
				$element = $item->getElementsByTagName( $tag )->item( 0 );
				if ( $element && '' !== trim( $element->textContent ) ) {
					return trim( $element->textContent );
				}
			}
		}

		return '';
	}

	/**
	 * A URL the plugin's header declares, which the email lists, like "Author URI".
	 *
	 * The review tools write the address as text, which the email may have turned into a link on its way; a link's own
	 * address is taken when there is one.
	 *
	 * @param string $label The header's name.
	 * @return string Empty if the email doesn't list it.
	 */
	public function declared_url( string $label ): string {
		foreach ( $this->document->getElementsByTagName( 'li' ) as $item ) {
			$text = trim( $item->textContent );
			if ( 0 !== mb_stripos( $text, $label . ':' ) ) {
				continue;
			}

			$link = $item->getElementsByTagName( 'a' )->item( 0 );
			if ( $link instanceof \DOMElement && '' !== trim( $link->getAttribute( 'href' ) ) ) {
				return trim( $link->getAttribute( 'href' ) );
			}

			return preg_match( self::URL, mb_substr( $text, mb_strlen( $label ) + 1 ), $url ) ? rtrim( $url[0], self::URL_TRAILER ) : '';
		}

		return '';
	}

	/**
	 * An element's text, with a line for each block, or for each line break of a plain text body.
	 *
	 * @param \DOMNode|null $node Element.
	 * @return string
	 */
	private function text( ?\DOMNode $node ): string {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM's own property names.
		if ( ! $node ) {
			return '';
		}

		if ( XML_TEXT_NODE === $node->nodeType ) {
			// No-break spaces, which emails are full of, are spaces; line breaks too, unless they're all that ends a line.
			return (string) preg_replace( $this->plain ? '/\x{00A0}/u' : '/[\n\x{00A0}]/u', ' ', $node->nodeValue );
		}

		$text = '';
		foreach ( $node->childNodes as $child ) {
			$text .= $this->text( $child );
		}

		return in_array( strtolower( $node->nodeName ), self::BLOCKS, true ) ? "\n" . $text . "\n" : $text;
		// phpcs:enable
	}
}
