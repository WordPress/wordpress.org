<?php

class DevHub_Block_Editor_Importer extends DevHub_Docs_Importer {
	/**
	 * Initializes object.
	 */
	public function init() {
		$manifest = 'https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/manifest.json';

		parent::do_init(
			'blocks',
			'block-editor',
			$manifest
		);

		add_filter( 'template_redirect',               array( $this, 'redirects' ), 1 );
		add_filter( 'handbook_label', array( $this, 'change_handbook_label' ), 10, 2 );
		add_filter( 'handbook_display_toc',            array( $this, 'disable_toc' ) );
		add_filter( 'get_post_metadata',               array( $this, 'fix_markdown_source_meta' ), 10, 4 );
		add_filter( 'wporg_markdown_before_transform', array( $this, 'wporg_markdown_before_transform' ),  10, 2 );
		add_filter( 'wporg_markdown_after_transform',  array( $this, 'wporg_markdown_after_transform' ), 10, 2 );
		add_filter( 'wporg_markdown_edit_link',        array( $this, 'wporg_markdown_edit_link' ), 10, 2 );

		add_filter( 'syntaxhighlighter_htmlresult',    array( $this, 'fix_code_entity_encoding' ) );

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
	 * Handles redirects for renamed/removed handbook pages.
	 */
	public function redirects() {
		if ( 0 !== strpos( $_SERVER['REQUEST_URI'], '/block-editor/' ) ) {
			return;
		}

	    $handbook_path = explode( '/', trailingslashit( $_SERVER['REQUEST_URI'] ), 3 );
	    $handbook_path = $handbook_path[2] ?? null;

		if ( is_null( $handbook_path ) ) {
			return;
		}

		// Any handbook pages where the slug changes should be listed here.
		$redirects = [
			'tutorials/block-tutorial/block-controls-toolbars-and-inspector' => 'tutorials/block-tutorial/block-controls-toolbar-and-sidebar/',
			'components/server-side-render' => 'packages/packages-server-side-render',

			// After handbook restructuring, March 2021.
			'handbook/versions-in-wordpress/' => 'contributors/versions-in-wordpress',
			'architecture/fse-templates' => 'explanations/architecture/full-site-editing-templates',
			'developers/internationalization' => 'how-to-guides/internationalization',
			'developers/richtext' => 'reference-guides/richtext',
			'developers/accessibility' => 'how-to-guides/accessibility',
			'developers/feature-flags' => 'how-to-guides/feature-flags',
			'tutorials/devenv' => 'handbook/tutorials/devenv',
			'tutorials/devenv/docker-ubuntu' => 'handbook/tutorials/devenv/docker-ubuntu',
			'tutorials/block-based-themes' => 'how-to-guides/block-based-theme',
			'tutorials/block-based-themes/block-based-themes-2-adding-blocks' => 'how-to-guides/block-based-theme/block-based-themes-2-adding-blocks',
		];

		// General path redirects. (More specific path first.)
		$path_redirects = [
			// 'some-path/' => 'new-path/',

			// After handbook restructuring, March 2021.
			'architecture/' => 'explanations/architecture/',
			'contributors/develop/' => 'contributors/code/',
			'contributors/document/' => 'contributors/documentation/',
			'data/' => 'reference-guides/data/',
			'designers/' => 'how-to-guides/designers/',
			'developers/backward-compatibility/' => 'how-to-guides/backward-compatibility/',
			'developers/block-api/' => 'reference-guides/block-api/',
			'developers/filters/' => 'reference-guides/filters/',
			'developers/platform/' => 'how-to-guides/platform/',
			'developers/slotfills/' => 'reference-guides/slotfills/',
			'developers/themes/' => 'how-to-guides/themes/',
			'packages/' => 'reference-guides/packages/',
			'tutorials/create-block/' => 'handbook/tutorials/create-block/',
			'tutorials/plugin-sidebar-0/' => 'how-to-guides/sidebar-tutorial/',
			'tutorials/' => 'how-to-guides/',
		];

		$new_handbook_path = '';
		if ( ! empty( $redirects[ untrailingslashit( $handbook_path ) ] ) ) {
			$new_handbook_path = $redirects[ untrailingslashit( $handbook_path ) ];
		} else {
			foreach ( $path_redirects as $old_path => $new_path ) {
				if ( 0 === strpos( $handbook_path, $old_path ) ) {
					$new_handbook_path = str_replace( $old_path, $new_path, $handbook_path );
					break;
				}
			}
		}

		if ( $new_handbook_path ) {
			$redirect_to = get_post_type_archive_link( $this->get_post_type() ) . $new_handbook_path;

			wp_safe_redirect( $redirect_to, 301 );
			exit;
		}
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
	 * Disables table of contents in-page widget for the Block Editor handbook.
	 *
	 * @param $display Should the table of contents be displayed?
	 * @return bool
	 */
	public function disable_toc( $display ) {
		if ( $this->get_post_type() === get_post_type() ) {
			$display = false;
		}

		return $display;
	}

	/**
	 * Fixes unwarranted HTML entity encoding within code shortcodes.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function fix_code_entity_encoding( $content ) {
		if ( $this->get_post_type() !== get_post_type() ) {
			return $content;
		}

		if ( false !== mb_strpos( $content, '&amp;' ) ) {
			$content = preg_replace_callback(
				'|(<pre class="brush[^>]+)(.+)(</pre)|Us',
				function( $matches ) {
					return $matches[1] . html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML401 ) . $matches[3];
				},
				$content
			);
		}

		return $content;
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
			// Running under cron.
			wp_doing_cron()
		||
			// Running under WP-CLI.
			( defined( 'WP_CLI' ) && WP_CLI )
		||
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
	 * Modifies the GitHub edit URL to point to trunk instead of the imported branch.
	 *
	 * @param string $link    The link to edit the post on GitHub.
	 * @param int    $post_id The post ID.
	 * @return string
	 */
	public function wporg_markdown_edit_link( $link, $post_id ) {
		if ( $this->get_post_type() === get_post_type( $post_id ) ) {
			$link = str_replace( '/wp/' . WP_CORE_STABLE_BRANCH . '/', '/trunk/', $link );
		}

		return $link;
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
