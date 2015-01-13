<?php
/**
 * Code Reference formatting.
 *
 * @package wporg-developer
 */

/**
 * Class to handle content formatting.
 */
class DevHub_Formatting {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks to perform formatting as needed.
	 */
	public static function do_init() {
		add_filter( 'the_excerpt', array( __CLASS__, 'lowercase_P_dangit_just_once' ) );
		add_filter( 'the_content', array( __CLASS__, 'make_doclink_clickable' ), 10, 5 );

		add_filter( 'the_excerpt', array( __CLASS__, 'remove_inline_internal' ) );
		add_filter( 'the_content', array( __CLASS__, 'remove_inline_internal' ) );
	}

	/**
	 * Allows for "Wordpress" just for the excerpt value of the capital_P_dangit function.
	 *
	 * WP.org has a global output buffer that runs capital_P_dangit() over displayed
	 * content. For this one field of this one post, circumvent that function to
	 * to show the lowercase P.
	 *
	 * @param  string $excerpt The post excerpt.
	 * @return string
	 */
	public static function lowercase_P_dangit_just_once( $excerpt ) {
		if ( 'wp-parser-function' == get_post_type() && 'capital_P_dangit' == get_the_title() ) {
			$excerpt = str_replace( 'Wordpress', 'Word&#112;ress', $excerpt );
		}

		return $excerpt;
	}

	/**
	 * Prevents display of the inline use of {@internal}} as it is not meant to be shown.
	 *
	 * @param  string      $content   The post content.
	 * @param  null|string $post_type Optional. The post type. Default null.
	 * @return string
	 */
	public static function remove_inline_internal( $content, $post_type = null ) {
		// Only attempt a change for a parsed post type with an @internal reference in the text.
		if ( DevHub\is_parsed_post_type( $post_type ) && false !== strpos( $content, '{@internal ' ) ) {
			$content = preg_replace( '/\{@internal (.+)\}\}/', '', $content );
		}

		return $content;
	}

	/**
	 * Makes phpDoc @see and @link references clickable.
	 *
	 * Handles these six different types of links:
	 *
	 * - {@link http://en.wikipedia.org/wiki/ISO_8601}
	 * - {@see WP_Rewrite::$index}
	 * - {@see WP_Query::query()}
	 * - {@see esc_attr()}
	 * - {@see 'pre_get_search_form'}
	 * - {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
	 *
	 * Note: Though @see and @link are semantically different in meaning, that isn't always
	 * the case with use so this function handles them identically.
	 *
	 * @param  string $content The content.
	 * @return string
	 */
	public static function make_doclink_clickable( $content ) {

		// Nothing to change unless a @link or @see reference is in the text.
		if ( false === strpos( $content, '{@link ' ) && false === strpos( $content, '{@see ' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/\{@(?:link|see) ([^\}]+)\}/',
			function ( $matches ) {

				$link = $matches[1];

				// Undo links made clickable during initial parsing
				if ( 0 === strpos( $link, '<a ' ) ) {

					if ( preg_match( '/^<a .*href=[\'\"]([^\'\"]+)[\'\"]>(.*)<\/a>(.*)$/', $link, $parts ) ) {
						$link = $parts[1];
						if ( $parts[3] ) {
							$link .= ' ' . $parts[3];
						}
					}

				}

				// Link to an external resource.
				if ( 0 === strpos( $link, 'http' ) ) {

					$parts = explode( ' ', $link, 2 );

					// Link without linked text: {@link http://en.wikipedia.org/wiki/ISO_8601}
					if ( 1 === count( $parts ) ) {
						$link = '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a>';
					}

					// Link with linked text: {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
					else {
						$link = '<a href="' . esc_url( $parts[0] ) . '">' . esc_html( $parts[1] ) . '</a>';
					}

				}

				// Link to an internal resource.
				else {

					// Link to class variable: {@see WP_Rewrite::$index}
					if ( false !== strpos( $link, '::$' ) ) {
						// Nothing to link to currently.
					}

					// Link to class method: {@see WP_Query::query()}
					elseif ( false !== strpos( $link, '::' ) ) {
						$link = '<a href="' .
							get_post_type_archive_link( 'wp-parser-class' ) .
							str_replace( array( '::', '()' ), array( '/', '' ), $link ) .
							'">' . esc_html( $link ) . '</a>';
					}

					// Link to hook: {@see 'pre_get_search_form'}
					elseif ( 1 === preg_match( '/^(&#8216;)\w+(&#8217;)$/', $link, $hook ) ) {
						if ( ! empty( $hook[0] ) ) {
							$link = '<a href="' .
								get_post_type_archive_link( 'wp-parser-hook' ) .
								str_replace( array( '&#8216;', '&#8217;' ), '', $link ) .
								'">' . esc_html( $link ) . '</a>';
						}
					}

					// Link to function: {@see esc_attr()}
					else {
						$link = '<a href="' .
							get_post_type_archive_link( 'wp-parser-function' ) .
							str_replace( '()', '', $link ) .
							'">' . esc_html( $link ) . '</a>';
					}

				}

				return $link;
			},
			$content
		);
	}

} // DevHub_Formatting

DevHub_Formatting::init();
