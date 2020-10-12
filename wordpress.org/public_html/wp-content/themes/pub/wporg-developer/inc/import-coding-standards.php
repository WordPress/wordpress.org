<?php

class DevHub_Coding_Standards_Importer extends DevHub_Docs_Importer {
	/**
	 * Initializes object.
	 */
	public function init() {
		parent::do_init(
			'wpcs', // 'coding-standards' makes for too long of a post type slug when appended with '-handbook'
			'coding-standards',
			'https://raw.githubusercontent.com/WordPress-Coding-Standards/docs/master/manifest.json'
		);

		add_filter( 'handbook_label', array( $this, 'change_handbook_label' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'fix_double_encoding' ) );
	}

	/**
	 * Overrides the default handbook label since post type name does not directly
	 * translate to post type label.
	 *
	 * @param string $label     The default label, which is merely a sanitized
	 *                          version of the handbook name.
	 * @param string $post_type The handbook post type.
	 * @return string
	 */
	public function change_handbook_label( $label, $post_type ) {
		if ( $this->get_post_type() === $post_type ) {
			$label = __( 'Coding Standards Handbook', 'wporg' );
		}

		return $label;
	}

	/**
	 * Fixes (as a stopgap) encoding of already encoded characters in code shortcodes.
	 *
	 * Affected characters:
	 * - '&` (sometimes)
	 * - `<`
	 * - `*` (when encoded in the first place)
	 * - `?` (when encoded in the first place)
	 * - `"` (in some places when used as opening quote)
	 *
	 * This could probably be abrogated by the source using triple backticks to
	 * denote code.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/5346
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function fix_double_encoding( $content ) {
		if ( $this->get_post_type() === get_post_type() ) {
			$content = str_replace(
				[ '&amp;amp;', '&amp;#039;', '&amp;042;', '&amp;#042;', '&amp;lt;', '&amp;quest;', '&amp;quot;' ],
				[ '&amp;', '&#039;', '&#042;', '&#042;', '&lt;', '&quest;', '&quot;' ],
				$content
			);
		}
		return $content;
	}
}

DevHub_Coding_Standards_Importer::instance()->init();
