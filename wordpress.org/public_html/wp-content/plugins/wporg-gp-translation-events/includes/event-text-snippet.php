<?php

namespace Wporg\TranslationEvents;

class Event_Text_Snippet {

	/**
	 * Generate links for text snippets.
	 *
	 * @return string The snippet links in a list.
	 */
	public static function get_snippet_links(): string {
		$snippets           = apply_filters( 'wporg_translation_events_snippets', array() );
		$snippets_link_list = '<ul class="text-snippets">';
		foreach ( $snippets as $snippet ) {
			$snippets_link_list .= sprintf( '<li><a href="#" class="text-snippet" data-snippet="%s">%s</a></li>', esc_html( $snippet['snippet'] ), esc_html( $snippet['title'] ) );
		}
		$snippets_link_list .= '</ul>';
		return $snippets_link_list;
	}
}
