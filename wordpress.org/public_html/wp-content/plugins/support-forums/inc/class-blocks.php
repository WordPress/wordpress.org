<?php
namespace WordPressdotorg\Forums;
use WP_Block_Patterns_Registry, WP_Block_Pattern_Categories_Registry;

/**
 * Customizations for the Support Forum and the Blocks Everywhere plugin.
 *
 * To enable this file to be loaded on a bbPress install, activate the Blocks Everywhere plugin.
 */
class Blocks {

	public $forum_enabled_by_default = true;

	public function __construct() {
		if ( null !== get_option( 'forum_block_editor_enabled', null ) ) {
			$this->forum_enabled_by_default = get_option( 'forum_block_editor_enabled' );
		}

		// Enable bbPress support.
		add_filter( 'blocks_everywhere_bbpress', '__return_true' );

		// Enable block processing in emails.
		add_filter( 'blocks_everywhere_email', '__return_true' );

		// Enable theme compatibility CSS.
		add_filter( 'blocks_everywhere_theme_compat', '__return_true' );
		add_action( 'wp_head', [ $this, 'expand_theme_compat' ], 1 );

		// Theme Tweaks, these should be moved to the theme.
		add_filter( 'after_setup_theme', [ $this, 'after_setup_theme' ] );

		// Customize blocks for the Support Forums.
		add_filter( 'blocks_everywhere_editor_settings', [ $this, 'editor_settings' ] );

		// Enable the blocks on the server-side.
		add_filter( 'blocks_everywhere_allowed_blocks', [ $this, 'allowed_blocks' ] );

		// Allow the oEmbed proxy endpoint for any user who can publish a thread/reply..
		add_filter( 'rest_api_init', [ $this, 'overwrite_oembed_10_proxy_permission' ], 20 );

		// Hack to make Imgur embeds work. This should be fixed by Imgur.
		add_filter( 'oembed_remote_get_args', [ $this, 'oembed_remote_get_args' ], 10, 2 );

		// Add user opt-out.
		add_action( 'bbp_user_edit_after', [ $this, 'bbp_user_edit_after' ], 11 );
		add_action( 'bbp_profile_update', [ $this, 'bbp_profile_update' ], 10, 1 );
		add_filter( 'blocks_everywhere_bbpress_editor', [ $this, 'blocks_everywhere_bbpress_editor' ] );

		// Add forum opt-in/out.
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'save_post', [ $this, 'metabox_forum_optin_save_handler' ] );

		// Reverse twemoji replacements. Before bbPress sanitization gets to it.
		add_filter( 'bbp_new_reply_pre_content',  [ $this, 'reverse_twemoji_upon_save' ], 5 );
		add_filter( 'bbp_edit_reply_pre_content', [ $this, 'reverse_twemoji_upon_save' ], 5 );
		add_filter( 'bbp_new_topic_pre_content',  [ $this, 'reverse_twemoji_upon_save' ], 5 );
		add_filter( 'bbp_edit_topic_pre_content', [ $this, 'reverse_twemoji_upon_save' ], 5 );
	}

	/**
	 * CSS to expand on Blocks Everywhere support
	 */
	public function expand_theme_compat() {
		$back_compat_css_vars = '';
		// The old support theme doesn't have the parent colors defined.
		if ( 'pub/wporg-support' === get_stylesheet() ) {
			$back_compat_css_vars = <<<CSS
				:root {
					--wp--preset--color--blueberry-1: #3858e9;
					--wp--preset--color--charcoal-1: #1e1e1e;
					--wp--preset--color--charcoal-3: #40464d;
					--wp--preset--color--deep-blueberry: #213fd4;
					--wp--custom--button--color--background: var(--wp--preset--color--blueberry-1);
					--wp--custom--button--hover--color--background: var(--wp--preset--color--deep-blueberry);
				}
			CSS;
		}

		wp_add_inline_style(
			'blocks-everywhere-compat',
			<<<CSS
				{$back_compat_css_vars}
				/* Fix the primary block inserter button */
				.blocks-everywhere .components-button.is-primary {
					--wp-components-color-accent: var(--wp--custom--button--color--background);
					--wp-components-color-accent-darker-10: var(--wp--custom--button--hover--color--background);
					--wp-components-color-accent-darker-20: var(--wp--custom--button--hover--color--background);
				}
				.gutenberg-support .iso-editor .edit-post-header__toolbar button.components-button.is-primary:hover svg {
					fill: #fff;
				}
				.blocks-everywhere .components-button.is-primary {
					--wp-admin-theme-color: #fff;
				}
				.editor-document-tools .editor-document-tools__left>.components-button.is-primary.has-icon.is-pressed {
					background: var(--wp--custom--button--color--background);
				}
				/* Fix the inline new-block button */
				.block-editor-default-block-appender .block-editor-inserter__toggle.components-button.has-icon:hover {
					background: var( --wp--preset--color--charcoal-3 );
				}
				/* Fix the button selected state */
				.gutenberg-support .edit-post-header button:not(:hover):not(:active):not(.has-background):not(.is-primary),
				.gutenberg-support .edit-post-header button.is-pressed:not(.has-background):not(.is-primary) {
					color: #fff;
				}
				/* Editor toolbar buttons */
				.gutenberg-support .iso-editor .block-editor-block-types-list__list-item:hover span {
					fill: inherit;
					color: inherit;
				}
				.gutenberg-support .iso-editor .edit-post-header__toolbar button.is-pressed:hover svg {
					fill: #fff;
				}
				/* Fix the accessibility navigation block styles */
				.block-editor-list-view-leaf.is-selected .block-editor-list-view-block-contents,
				.block-editor-list-view-leaf.is-selected .components-button.has-icon,
				.gutenberg-support #bbpress-forums fieldset.bbp-form .block-editor-list-view-tree button:focus,
				.gutenberg-support #bbpress-forums fieldset.bbp-form .block-editor-list-view-tree button:hover {
					color: var( --wp--preset--color--charcoal-1 );
				}
				/* Fix the accessibility block drag handles */
				button.components-button.block-selection-button_drag-handle,
				button.components-button.block-selection-button_select-button {
					color: #fff !important;
				}
				/* Reset the link editor padding in BE theme-compat. https://meta.trac.wordpress.org/ticket/7606 + https://github.com/Automattic/blocks-everywhere/issues/206 */
				.gutenberg-support #bbpress-forums fieldset.bbp-form .blocks-everywhere .block-editor-link-control button {
					padding: inherit;
				}
			CSS
		);
	}

	public function after_setup_theme() {
		// This will make embeds resize properly.
		add_theme_support( 'responsive-embeds' );
	}

	/**
	 * Whether the current pageload appears to be related to the reviews forum.
	 */
	protected function is_review_related() {
		if ( bbp_is_single_forum() ) {
			return ( bbp_get_forum_id() == Plugin::REVIEWS_FORUM_ID );
		}

		if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {
			return ( bbp_get_topic_forum_id() == Plugin::REVIEWS_FORUM_ID );
		}

		if ( bbp_is_single_view() ) {
			return ( bbp_get_view_id() == 'reviews' );
		}

		return false;
	}

	public function allowed_blocks( $blocks ) {
		if ( ! $this->is_review_related() ) {
			// See ::editor_settings();
			$blocks[] = 'core/image';
			$blocks[] = 'core/embed';
		}

		return array_unique( $blocks );
	}

	public function editor_settings( $settings ) {
		if ( ! $this->is_review_related() ) {
			// This adds the image block, but only with 'add from url' as an option.
			$settings['iso']['blocks']['allowBlocks'][] = 'core/image';

			// Allows embeds and might fix pasting links sometimes not working.
			$settings['iso']['blocks']['allowBlocks'][] = 'core/embed';
		}

		// We don't need these on the forums.
		$settings['unregisterFormatType'][] = 'core/keyboard';
		$settings['unregisterFormatType'][] = 'core/language';
		$settings['unregisterFormatType'][] = 'core/non-breaking-space';
		$settings['unregisterFormatType'][] = 'core/subscript';
		$settings['unregisterFormatType'][] = 'core/superscript';
		$settings['unregisterFormatType'][] = 'core/strikethrough';
		$settings['unregisterFormatType'][] = 'core/underline';

		// WP Calypso editor adds some too.
		$settings['unregisterFormatType'][] = 'wpcom/justify';
		$settings['unregisterFormatType'][] = 'wpcom/underline';

		// This will display a support link in an ellipsis menu in the top right of the editor.
		$settings['iso']['moreMenu'] = true;
		$settings['iso']['linkMenu'] = [
			[
				/* translators: Link title to the WordPress Editor support article. */
				'title' => __( 'Help & Support', 'wporg-forums' ),
				/* translators: Link to the WordPress Editor article, used as the forum 'Help & Support' destination. */
				'url'   => __( 'https://wordpress.org/support/article/wordpress-editor/', 'wporg-forums' ),
			]
		];

		$settings['iso']['allowEmbeds'] = array_values( array_diff(
			$settings['iso']['allowEmbeds'],
			[
				// Disable screencast, it seems not to work.
				'screencast',
			]
		) );

		// Enable the custom paragraph that converts HTML and PHP code into a code block.
		$settings['replaceParagraphCode'] = true;

		return $settings;
	}

	public function overwrite_oembed_10_proxy_permission() {
		// A register_route_args filter would be handy here... See https://core.trac.wordpress.org/ticket/54087
		$oembed_proxy_route_args = rest_get_server()->get_routes( 'oembed/1.0' )['/oembed/1.0/proxy'] ?? false;
		if ( ! $oembed_proxy_route_args ) {
			return;
		}

		// Flip it from [ GET => true ] to [ GET ]
		$oembed_proxy_route_args[0]['methods'] = array_keys( $oembed_proxy_route_args[0]['methods'] );

		// Overwrite the permission_callback, allow any user who can create replies to use embeds.
		$oembed_proxy_route_args[0]['permission_callback'] = function() {
			return bbp_current_user_can_publish_topics() || bbp_current_user_can_publish_replies();
		};

		register_rest_route(
			'oembed/1.0',
			'/proxy',
			$oembed_proxy_route_args,
			true
		);
	}

	/**
	 * Imgur oEmbed API appears to block any request with a lowercase 'wordpress' in the user-agent.
	 *
	 * @param array  $http_args    The args to pass to wp_safe_remote_get().
	 * @param string $provider_url The URL of the oEmbed provider to be requested.
	 * @return array The modified $http_args.
	 */
	public function oembed_remote_get_args( $http_args, $provider_url ) {
		if ( str_contains( $provider_url, 'imgur.com' ) ) {
			$http_args['user-agent'] = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ), $provider_url );
			$http_args['user-agent'] = str_replace( 'wordpress', 'WordPress', $http_args['user-agent'] );
		}

		return $http_args;
	}

	/**
	 * Add an option to the user profile to disable the block editor.
	 */
	public function bbp_user_edit_after() {
		$user_id = bbp_get_displayed_user_id();

		printf(
			'<p>
				<input type="hidden" name="can_update_block_editor_preference" value="true">
				<input name="block_editor" id="block_editor" type="checkbox" value="disabled" %s />
				<label for="block_editor">%s</label>
			</p>',
			checked( get_user_option( 'block_editor', $user_id ), 'disabled', false ),
			sprintf(
				__( 'Disable the <a href="%s">Block Editor</a> for new topics and replies.', 'wporg-forums' ),
				'https://wordpress.org/support/article/wordpress-editor/'
			)
		);
	}

	/**
	 * Save the user option to enable/disable.
	 */
	public function bbp_profile_update( $user_id ) {
		// Catch profile updates that should not be able to include the "Disable Block Editor" preference, and return early.
		if ( ! isset( $_REQUEST['can_update_block_editor_preference'] ) ) {
			return;
		}

		$disabled = ! empty( $_REQUEST['block_editor'] ) && 'disabled' === $_REQUEST['block_editor'];

		if ( $disabled ) {
			update_user_option( $user_id, 'block_editor', 'disabled', false );
		} else {
			delete_user_option( $user_id, 'block_editor' );
		}
	}

	/**
	 * Add an admin interface to enable/disable the Block Editor for a forum.
	 */
	public function admin_init() {
		add_meta_box( 'block_editor', 'Block Editor for Topics/Replies', [ $this, 'metabox_forum_optin' ], 'forum', 'side' );
	}

	/**
	 * Display the forum opt-in for the Block Editor.
	 */
	function metabox_forum_optin() {
		global $post;

		$forum_status = $post->block_editor ?: 'default';
		$default      = $this->forum_enabled_by_default ? 'enabled' : 'disabled';

		printf(
			'<p>
				<select name="block_editor" id="block_editor">
					<option value="default" %s>Default (%s)</option>
					<option value="enabled" %s>Enabled</option>
					<option value="disabled" %s>Disabled</option>
				</select>
			</p>',
			selected( $forum_status, 'default', false ),
			esc_html( $default ),
			selected( $forum_status, 'enabled', false ),
			selected( $forum_status, 'disabled', false ),
		);
	}

	/**
	 * Save the values for ::metabox_forum_optin().
	 *
	 * @param WP_Post $post The post being edited.
	 */
	function metabox_forum_optin_save_handler( $post_id ) {
		$post = get_post( $post_id );
		if (
			! $post ||
			'forum' !== $post->post_type ||
			! current_user_can( 'edit_post', $post->ID ) ||
			! isset( $_REQUEST['block_editor'] )
		) {
			return;
		}

		$value = sanitize_key( wp_unslash( $_REQUEST['block_editor'] ) );
		if ( 'default' === $value ) {
			delete_post_meta( $post->ID, 'block_editor' );
		} else {
			update_post_meta( $post->ID, 'block_editor', $value );
		}
	}

	/**
	 * Conditionally disable the Block Editor under certain circumstances.
	 *
	 * Those circumstances are:
	 *  - The page is an article.
	 *  - The user has disabled the editor.
	 *  - The default is forum opt-in, and the forum has the block_editor not enabled.
	 *  - The topic/reply being edited was not created in the Block Editor.
	 */
	public function blocks_everywhere_bbpress_editor( $use_it ) {
		if ( ! $use_it ) {
			return $use_it;
		}

		if ( is_singular( 'helphub_article' ) ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Respect the user option.
		$user_option = get_user_option( 'block_editor', $user_id ) ?: 'enabled';

		// Determine if the forum has the editor enabled.
		$forum             = bbp_get_forum( bbp_get_forum_id() );
		$enabled_for_forum = $forum ? ( 'enabled' === $forum->block_editor || ( ! $forum->block_editor && $this->forum_enabled_by_default ) ) : $this->forum_enabled_by_default;
		$enabled_for_user  = ( 'enabled' === $user_option );
		$use_it            = ( $enabled_for_user && $enabled_for_forum );

		if ( $use_it ) {
			$content = false;

			if ( bbp_is_reply_edit() ) {
				$content = bbp_get_reply( bbp_get_reply_id() )->post_content;
			} elseif ( bbp_is_topic_edit() ) {
				$content = get_post_field( 'post_content', bbp_get_topic_id() );
			} elseif ( 'bbp-edit-reply' === ( $_POST['action'] ?? '' ) ) {
				$content = wp_unslash( $_POST['bbp_reply_content'] ?? '' ) ?: bbp_get_reply( $_POST['bbp_reply_id'] ?? 0 )->post_content;
			} elseif ( 'bbp-edit-topic' === ( $_POST['action'] ?? '' ) ) {
				$content = wp_unslash( $_POST['bbp_topic_content'] ?? '' ) ?: get_post_field( 'post_content', $_POST['bbp_topic_id'] );
			}

			if ( $content ) {
				// Similar to has_blocks(), but optimized for forum use.
				$content = trim( $content );
				if ( ! str_starts_with( $content, '<!-- wp:' ) || ! str_ends_with( $content, '-->' ) ) {
					$use_it = false;
				}
			}
		}

		return $use_it;
	}

	/**
	 * Reverse twemoji upon save.
	 *
	 * @see https://core.trac.wordpress.org/ticket/52219
	 */
	public function reverse_twemoji_upon_save( $content ) {
		// <img ... class="emoji" alt="emojihere" ... />
		if ( ! str_contains( $content, 'emoji' ) ) {
			return $content;
		}

		$content = wp_unslash( $content );

		// Replace all img.emoji with the alt text.
		$content = preg_replace(
			'~<img[^>]+class="emoji"[^>]+alt="(.*?)"+[^>]+>(\s*</img>)?~iu',
			'$1',
			$content
		);

		// Replace all emoji svgs with the alt text, for when the emoji class has been stripped.
		$content = preg_replace(
			'~<img[^>]+alt="(.*?)"+[^>]+s\.w\.org/images/core/emoji[^>]+>(\s*</img>)?~iu',
			'$1',
			$content
		);

		return wp_slash( $content ); // Expect slashed.
	}

}
