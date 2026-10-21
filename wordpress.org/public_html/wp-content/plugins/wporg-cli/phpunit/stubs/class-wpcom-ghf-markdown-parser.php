<?php
/**
 * Stands in for the Markdown parser the importer borrows from Jetpack.
 *
 * The importer bails before fetching anything when the class is missing, so it has
 * to exist for the fetch itself to be exercised. Jetpack is not a test dependency.
 *
 * @package wporg-cli
 */

declare( strict_types = 1 );

/**
 * Minimal stand-in for Jetpack's Markdown parser.
 */
class WPCom_GHF_Markdown_Parser {

	/**
	 * Returns the Markdown unchanged.
	 *
	 * @param string $text Markdown to transform.
	 * @return string
	 */
	public function transform( string $text ): string {
		return $text;
	}
}
