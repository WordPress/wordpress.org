<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Markdown;

abstract class Markdown_Base extends HTML_Base {

	/**
	 * The Markdown content of the email.
	 *
	 * @return string
	 */
	abstract public function markdown();

	/**
	 * The plain-text content for the email template.
	 *
	 * The email may specify it, but otherwise we'll just use the raw Markdown as the plain-text variant.
	 *
	 * @return string
	 */
	public function body() {
		return $this->markdown();
	}

	/**
	 * The HTML content for the email template
	 *
	 * @return string
	 */
	public function html() {
		static $markdown_converter = null;

		$text = $this->markdown();

		// Return early if the Markdown processor isn't available.
		if ( class_exists( '\WordPressdotorg\Plugin_Directory\Markdown' ) ) {	
			if ( is_null( $markdown_converter ) ) {
				$markdown_converter = new Markdown();
			}

			$text = $markdown_converter->transform( $text );
		}

		$text = make_clickable( $text );

		return $text;
	}

}