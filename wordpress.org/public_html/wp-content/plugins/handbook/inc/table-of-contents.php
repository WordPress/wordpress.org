<?php
/**
 * Adds a table of contents to your pages based on h1, h2, h3 and h4 tags. Useful for documention-centric sites.
 *
 * @author Automattic, modifed by Nacin
 */
class WPorg_Handbook_TOC {
	protected $post_types = array();

	protected $styles = '<style> .toc-jump { text-align: right; font-size: 12px; } .page .toc-heading { margin-top: -50px; padding-top: 50px !important; }</style>';

	/**
	 * Array of HTML ids known to exist on the page or that have been auto-generated.
	 *
	 * @var array
	 */
	private $used_ids = [];

	/**
	 * Arguments.
	 *
	 * @access protected
	 * @var array
	 */
	protected $args = array();

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param array $post_types Post types.
	 * @param array $args {
	 *     Optional. Table of Contents arguments. Defualt emtpy array.
	 *
	 *     @type string $header_text Header text for the table. HTML-escaped on output.
	 * }
	 */
	public function __construct( $post_types, $args = array() ) {
		$this->post_types = (array) $post_types;
		add_action( 'template_redirect', array( $this, 'load_filters' ) );

		$this->args = (object) wp_parse_args( $args, array(
			'header_text' => __( 'Topics', 'wporg' ),
			'top_text'    => __( 'Top &uarr;', 'wporg' ),
		) );
	}

	public function load_filters() {
		if ( is_singular( $this->post_types ) && ! is_embed() ) {
			add_filter( 'the_content', array( $this, 'add_toc' ) );
		}
	}

	/**
	 * Returns reserved markup IDs likely to conflict with ToC-generated heading IDs.
	 *
	 * This list isn't meant to be exhaustive, just IDs that are likely to conflict
	 * with ToC-generated section heading IDs.
	 *
	 * If a reserved ID is encountered when a ToC section heading ID is being
	 * generated, the generated ID is incremented to avoid a conflict.
	 *
	 * @return array
	 */
	public function get_reserved_ids() {
		/**
		 * Filters the array of reserved IDs considered when auto-generating IDs for
		 * ToC sections.
		 *
		 * This is mostly for specifying markup IDs that may appear on the same page
		 * as the ToC for which any ToC-generated IDs would conflict. In such
		 * cases, the first instance of the ID on the page would be the target of
		 * the ToC section permalink which is likely not the ToC section itself.
		 *
		 * By specifying these reserved IDs, any potential use of the IDs by the theme
		 * can be accounted for by incrementing the auto-generated ID to avoid conflict.
		 *
		 * E.g. if the theme has `<div id="main">`, a ToC with a section titled "Main"
		 * would have a permalink that links to the div and not the ToC section.
		 *
		 * @param array $ids Array of IDs.
		 */
		return (array) apply_filters(
			'handbooks_reserved_ids',
			[
				'main', 'masthead', 'menu-header', 'page', 'primary', 'secondary', 'secondary-content', 'site-navigation',
				'wordpress-org', 'wp-toolbar', 'wpadminbar', 'wporg-footer', 'wporg-header'
			]
		);
	}

	/**
	 * Converts given content to dynamically add the ToC.
	 *
	 * @access public
	 *
	 * @param string $content Content.
	 * @return string Modified content.
	 */
	public function add_toc( $content ) {
		if ( ! in_the_loop() ) {
			return $content;
		}

		$toc   = '';
		$items = $this->get_tags( 'h(?P<level>[1-4])', $content );

		if ( count( $items ) < 2 ) {
			return $content;
		}

		// Remove any links we don't need.
		foreach ( $items as $i => $item ) {
			// If an element is all HTML, don't link to it.
			if ( empty( trim( strip_tags( $item['title'] ) ) ) ) {
				unset( $items[ $i ] );
			}
		}

		// Generate a list of the IDs in the document (generating them as needed).
		$this->used_ids = $this->get_reserved_ids();
		foreach ( $items as $i => $item ) {
			$items[ $i ]['id'] = $this->get_id_for_item( $item );
		}

		// Replace each level of the headings.
		$content .= $this->styles . "\n";
		$content = $this->add_ids_and_jumpto_links( $items, $content );

		if ( ! apply_filters( 'handbook_display_toc', true ) ) {
			return $content;
		}

		$contents_header = 'h' . reset( $items )['level']; // Duplicate the first <h#> tag in the document for the TOC header
		$toc            .= '<div class="table-of-contents">';
		$toc            .= "<$contents_header>" . esc_html( $this->args->header_text ) . "</$contents_header><ul class=\"items\">";
		$last_item       = false;

		foreach ( $items as $item ) {
			if ( $last_item ) {
				if ( $last_item < $item['level'] ) {
					$toc .= "\n<ul>\n";
				} elseif ( $last_item > $item['level'] ) {
					$toc .= "\n</ul></li>\n";
				} else {
					$toc .= "</li>\n";
				}
			}

			$last_item = $item['level'];

			$toc .= '<li><a href="#' . esc_attr( $item['id']  ) . '">' . $item['title']  . '</a>';
		}

		$toc .= "</ul>\n</div>\n";

		return $toc . $content;
	}

	/**
	 * Add the HTML markup for the in-content header elements.
	 */
	protected function add_ids_and_jumpto_links( $items, $content ) {
		$first = true;
		$matches = array();
		$replacements = array();

		foreach ( $items as $item ) {
			$replacement = '';
			$matches[]   = $item[0];
			$tag         = 'h' . $item['level']; // 'h2'
			$id          = $item['id'];
			$title       = $item['title'];
			$extra_attrs = $item['attrs']; // 'class="" style=""'
			$class       = 'toc-heading';

			if ( $extra_attrs ) {
				// Strip all IDs from the heading attributes (including empty), we'll replace it with one below.
				$extra_attrs = trim( preg_replace( '/id=(["\'])[^"\']*\\1/i', '', $extra_attrs ) );

				// Extract any classes present, we're adding our own attribute.
				if ( preg_match( '/class=(["\'])(?P<class>[^"\']+)\\1/i', $extra_attrs, $m ) ) {
					$extra_attrs = str_replace( $m[0], '', $extra_attrs );
					$class      .= ' ' . $m['class'];
				}
			}

			if ( ! $first ) {
				$replacement .= '<p class="toc-jump"><a href="#top">' . $this->args->top_text . '</a></p>';
			} else {
				$first = false;
			}

			$a11y_text      = sprintf( '<span class="screen-reader-text">%s</span>', $title );
			$anchor         = sprintf( '<a href="#%1$s" class="anchor"><span aria-hidden="true">#</span>%2$s</a>', $id, $a11y_text );
			$replacement   .= sprintf( '<%1$s id="%2$s" class="%3$s" tabindex="-1" %4$s>%5$s %6$s</%1$s>', $tag, $id, $class, $extra_attrs, $title, $anchor );
			$replacements[] = $replacement;
		}

		if ( $replacements ) {
			if ( count( array_unique( $matches ) ) !== count( $matches ) ) {
				foreach ( $matches as $i => $match ) {
					$content = preg_replace( '/' . preg_quote( $match, '/' ) . '/', $replacements[ $i ], $content, 1 );
				}
			} else {
				$content = str_replace( $matches, $replacements, $content );
			}
		}

		return $content;
	}

	/**
	 * Generate an ID for a given HTML element, use the tags `id` attribute if set.
	 */
	protected function get_id_for_item( $item ) {
		if ( ! empty( $item['id'] ) ) {
			return $item['id'];
		}

		// Check to see if the item already had a non-empty ID, else generate one from the title.
		if ( preg_match( '/id=(["\'])(?P<id>[^"\']+)\\1/', $item['attrs'], $m ) ) {
			$id = $m['id'];
		} else {
			$id = sanitize_title( $item['title'] );
		}

		// Append unique suffix if anchor ID isn't unique in the document.
		$count   = 2;
		$orig_id = $id;
		while ( in_array( $id, $this->used_ids ) && $count < 50 ) {
			$id = $orig_id . '-' . $count;
			$count++;
		}

		$this->used_ids[] = $id;

		return $id;
	}

	protected function get_tags( $tag, $content = '' ) {
		if ( empty( $content ) ) {
			$content = get_the_content();
		}

		preg_match_all( "/(?P<tag><{$tag}(?P<attrs>[^>]*)>)(?P<title>.*?)(<\/{$tag}>)/iJ", $content, $matches, PREG_SET_ORDER );

		return $matches;
	}
}

