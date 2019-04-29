<?php

class DevHub_Block_Editor_Importer extends DevHub_Docs_Importer {
	/**
	 * Initializes object.
	 */
	public function init() {
		parent::do_init(
			'blocks',
			'block-editor',
			'https://raw.githubusercontent.com/WordPress/gutenberg/master/docs/manifest.json'
		);

		add_filter( 'handbook_label', array( $this, 'change_handbook_label' ), 10, 2 );
		add_filter( 'get_post_metadata',               array( $this, 'fix_markdown_source_meta' ), 10, 4 );
		add_filter( 'wporg_markdown_before_transform', array( $this, 'wporg_markdown_before_transform' ),  10, 2 );
		add_filter( 'wporg_markdown_after_transform',  array( $this, 'wporg_markdown_after_transform' ), 10, 2 );

		add_action( 'pre_post_update', function( $post_id, $data ) {
			if ( $this->get_post_type() === $data['post_type'] ) {
				add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_extra_tags' ), 10, 1 );
			}
		}, 10, 2 );

		add_action( 'edit_post_' . $this->get_post_type(), function( $post_id ) {
			remove_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_extra_tags' ), 10, 1 );
		} );
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
			$label = __( 'Block Editor Handbook', 'wporg' );
		}

		return $label;
	}

	/**
	 * Fixes fetched value of markdown_source meta field to not be the
	 * raw.githubcontent.com domain that is currently incorrectly used
	 * in the block editor manifest.
	 *
	 * @param mixed  $null      A value for the meta if its data retrieval is
	 *                          overridden, else null.
	 * @param int	 $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether to return only the first value of the specified $meta_key.
	 * @return mixed
	 */
	public function fix_markdown_source_meta( $null, $object_id, $meta_key, $single ) {
		if (
			// Not the markdown source meta key.
			$meta_key !== $this->meta_key
		||
			// Not the block editor handbook.
			$this->get_post_type() !== get_post_type( $object_id )
		) {
			return $null;
		}

		$post = get_post( $object_id );

		$meta_type = 'post';

		/* Most of the rest of this is taken from get_metadata() */

		$meta_cache = wp_cache_get( $object_id, $meta_type . '_meta' );

		if ( ! $meta_cache ) {
			$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
			$meta_cache = $meta_cache[ $object_id ];
		}

		if ( ! $meta_key ) {
			return $null;
		}

		if ( isset( $meta_cache[ $meta_key ] ) ) {
			if ( $single ) {
				$value = maybe_unserialize( $meta_cache[ $meta_key ][0] );
				$value = str_replace( 'https://raw.githubusercontent.com/WordPress/gutenberg/', 'https://github.com/WordPress/gutenberg/edit/', $value );
			} else {
				$value = array_map( 'maybe_unserialize', $meta_cache[ $meta_key ] );
				$value = array_map(
					function( $x ) {
						return str_replace( 'https://raw.githubusercontent.com/WordPress/gutenberg/', 'https://github.com/WordPress/gutenberg/edit/', $x );
					},
					$value
				);
			}
			return $value;
		}

		return $null;
	}

	/**
	 * Modifies the Markdown text prior to being transformed into HTML.
	 *
	 * @param string $markdown  The Markdown text.
	 * @param string $post_type The handbook post type.
	 * @return string
	 */
	public function wporg_markdown_before_transform( $markdown, $post_type ) {
		if ( $this->get_post_type() !== $post_type ) {
			return $markdown;
		}

		// Remove any level 1 headings at the start of the markdown.
		// This also effectively prevents the post title from being the one defined in the markdown doc.
		if ( preg_match( '/^#\s(.+)/', $markdown, $matches ) ) {
			$markdown = preg_replace( '/^#\s(.+)/', '', $markdown );
		}

		// Remove the .md extension from relative links and treat 'readme.md' as an index
		$markdown = preg_replace(
			'@(\[.*?\]\(((\.\./)+docs/|/docs/|/packages/).*?)(((?<=/)readme)?\.md)?(#.*?)?\)@i',
			'$1$6)',
			$markdown
		);

		// Remove the (../)*docs/ path from relative links, and replace it with an absolute URL
		$markdown = preg_replace(
			'@(\[.*?\])\((\.\./)+docs/(.*?)/?(#.*?)?\)@i',
			'$1(https://developer.wordpress.org/block-editor/$3/$4)',
			$markdown
		);

		// Handle /docs/(.+)(/README.md) path for internal links and replace it with an absolute URL
		$markdown = preg_replace(
			'@(\[.*?\])\(/docs/(.*?)/?(#.*?)?\)@i',
			'$1(https://developer.wordpress.org/block-editor/$2/$3)',
			$markdown
		);

		// Handle /packages/compomnents(/README.md)
		$markdown = preg_replace(
			'@(\[.*?\])\(/packages/components/?(#.*?)?\)@i',
			'$1(https://developer.wordpress.org/block-editor/designers-developers/developers/components/$2)',
			$markdown
		);

		// Handle /packages/components/(src/)(.+)(/README.md)
		$markdown = preg_replace(
			'@(\[.*?\])\(/packages/components/(src/)?(.*?)/?(#.*?)?\)@i',
			'$1(https://developer.wordpress.org/block-editor/designers-developers/developers/components/$3/$4)',
			$markdown
		);

		// Handle /packages/(.+)(/README.md)
		$markdown = preg_replace(
			'@(\[.*?\])\(/packages/(.*?)/?(#.*?)?\)@i',
			'$1(https://developer.wordpress.org/block-editor/designers-developers/developers/packages/packages-$2/$3)',
			$markdown
		);

		return $markdown;
	}

	/**
	 * Modifies the HTML resulting from the Markdown transformation.
	 *
	 * @param string $html      The result of converting Markdown to HTML.
	 * @param string $post_type The handbook post type.
	 * @return string
	 */
	public function wporg_markdown_after_transform( $html, $post_type ) {
		if ( $this->get_post_type() !== $post_type ) {
			return $html;
		}

		// Turn the code blocks into tabs
		$html = preg_replace_callback( '/{%\s+codetabs\s+%}(.*?){%\s+end\s+%}/ms', array( __CLASS__, 'parse_code_blocks' ), $html );
		$html = str_replace( 'class="php"', 'class="language-php"', $html );
		$html = str_replace( 'class="js"', 'class="language-javascript"', $html );
		$html = str_replace( 'class="jsx"', 'class="language-jsx"', $html );
		$html = str_replace( 'class="css"', 'class="language-css"', $html );

		return $html;
	}

	/**
	 * Callback for the preg_replace_callback() in wporg_markdown_after_transform()
	 * to transform a block of code tabs into HTML.
	 */
	public static function parse_code_blocks( $matches ) {
		$splitted_tabs = preg_split( '/{%\s+([\w]+)\s+%}/', trim( $matches[1] ), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		$html = '<div class="code-tabs">';
		$code_blocks = '';

		for ( $ii = 0; $ii < count( $splitted_tabs ); $ii += 2 ) {
			$classes = 'code-tab ' . $splitted_tabs[ $ii ];
			$code_classes = 'code-tab-block ' . $splitted_tabs[ $ii ];

			if ( 0 === $ii ) {
				$classes .= ' is-active';
				$code_classes .= ' is-active';
			}

			$html .= "<button data-language='{$splitted_tabs[ $ii ]}' class='$classes'>{$splitted_tabs[ $ii ]}</button>";
			$code_blocks .= "<div class='$code_classes'>{$splitted_tabs[ $ii + 1 ]}</div>";
		}

		$html .= "$code_blocks</div>";

		return $html;
	}

	/**
	 * Add extra tags to the KSES allowed tags list.
	 *
	 * @param array $tags Allowed tags.
	 * @return array
	 */
	public static function allow_extra_tags( $tags ) {
		if ( ! isset( $tags['style'] ) ) {
			$tags['style'] = [];
		}

		return $tags;
	}

}

DevHub_Block_Editor_Importer::instance()->init();
