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

		// Add block patterns.
		add_filter( 'init', [ $this, 'register_predefs' ] );

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

	public function after_setup_theme() {
		// This will make embeds resize properly.
		add_theme_support( 'responsive-embeds' );
	}

	public function allowed_blocks( $blocks ) {
		// See ::editor_settings();
		$blocks[] = 'core/image';
		$blocks[] = 'core/embed';

		return array_unique( $blocks );
	}

	public function editor_settings( $settings ) {
		// This adds the image block, but only with 'add from url' as an option.
		$settings['iso']['blocks']['allowBlocks'][] = 'core/image';

		// Allows embeds and might fix pasting links sometimes not working.
		$settings['iso']['blocks']['allowBlocks'][] = 'core/embed';

		// Adds a table of contents button in the toolbar.
		$settings['toolbar']['toc'] = true;

		// Adds a navigation button in the toolbar.
		$settings['toolbar']['navigation'] = true;

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

		// Add patterns.
		$settings['editor']['__experimentalBlockPatterns'] = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$settings['editor']['__experimentalBlockPatternCategories'] = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();

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
			$reply_id = bbp_is_reply_edit() ? bbp_get_reply_id() : ( ( bbp_is_post_request() && ! empty( $_POST['action'] ) && 'bbp-edit-reply' === $_POST['action'] ) ? $_POST['bbp_reply_id'] : 0 );
			$topic_id = bbp_is_topic_edit() ? bbp_get_topic_id() : ( ( bbp_is_post_request() && ! empty( $_POST['action'] ) && 'bbp-edit-topic' === $_POST['action'] ) ? $_POST['bbp_topic_id'] : 0 );

			// If we're editing a post made without the editor, let's respect that.
			if ( $reply_id ) {
				$reply = bbp_get_reply( $reply_id );

				if ( $reply && ! has_blocks( $reply->post_content ) ) {
					$use_it = false;
				}
			} elseif ( $topic_id ) {
				if ( ! has_blocks( get_post_field( 'post_content', $topic_id ) ) ) {
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

	/**
	 * Register pre-defs for the forums.
	 */
	public function register_predefs() {
		$registered = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		foreach ( $registered as $pattern ) {
			unregister_block_pattern( $pattern['name'] );
		}

		$registered = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
		foreach ( $registered as $pattern ) {
			unregister_block_pattern_category( $pattern['name'] );
		}

		register_block_pattern_category( 'predef', [ 'label' => 'Pre-defined Replies' ] );

		register_block_pattern( 'wordpress-org/no-dashboard', [
			'title'      => 'Cannot Access Dashboard',
			'categories' => [ 'predef' ],
			'content'    => '
				<!-- wp:paragraph -->
				<p>Try <a href="https://wordpress.org/support/article/faq-troubleshooting/#how-to-deactivate-all-plugins-when-not-able-to-access-the-administrative-menus">manually resetting your plugins</a> (no Dashboard access required). If that resolves the issue, reactivate each one individually until you find the cause.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>If that does not resolve the issue, access your server via <a href="https://wordpress.org/support/article/ftp-clients/">SFTP or FTP</a>, or a file manager in your hosting account\'s control panel, navigate to <code>/wp-content/themes/</code> and rename the directory of your currently active theme. This will force the default theme to activate and hopefully rule-out a theme-specific issue (theme functions can interfere like plugins).</p>
				<!-- /wp:paragraph -->',
		] );

		register_block_pattern( 'wordpress-org/theme-conflict', [
			'title'      => 'Error Related to Plugin or Theme Conflict',
			'categories' => [ 'predef' ],
			'content'    => '
				<!-- wp:paragraph -->
				<p>This may be a plugin or theme conflict. Please attempt to disable all plugins, and use one of the default (Twenty*) themes. If the problem goes away, enable them one by one to identify the source of your troubles.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>If you can install plugins, install and activate "Health Check": <a href="https://wordpress.org/plugins/health-check/">https://wordpress.org/plugins/health-check/</a></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>It will add some additional features under the menu item under Tools &gt; Site Health.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>On its troubleshooting tab, you can Enable Troubleshooting Mode. This will disable all plugins, switch to a standard WordPress theme (if available), allow you to turn your plugins on and off and switch between themes, <strong>without affecting normal visitors to your site</strong>. This allows you to test for various compatibility issues.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>There’s a more detailed description about how to use the Health Check plugin and its Troubleshooting Mode at <a href="https://make.wordpress.org/support/handbook/appendix/troubleshooting-using-the-health-check/">https://make.wordpress.org/support/handbook/appendix/troubleshooting-using-the-health-check/</a></p>
				<!-- /wp:paragraph -->',
		] );

		register_block_pattern( 'wordpress-org/missing-files', [
			'title'      => 'Error Related to Missing or Damaged Core Files',
			'categories' => [ 'predef' ],
			'content'    => '
				<!-- wp:paragraph -->
				<p>Try <a href="https://wordpress.org/download/">downloading WordPress</a> again, access your server via <a href="https://wordpress.org/support/article/ftp-clients/">SFTP or FTP</a>, or a file manager in your hosting account\'s control panel, and delete then replace your copies of everything <strong>except</strong> the `wp-config.php` file and the <code>/wp-content/</code> directory with fresh copies from the download. This will effectively replace all of your core files without damaging your content and settings.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>Some uploaders tend to be unreliable when overwriting files, so don\'t forget to delete the original files before replacing them.</p>
				<!-- /wp:paragraph -->',
		] );

		register_block_pattern( 'wordpress-org/oom', [
			'title'      => 'Out of Memory Errors',
			'categories' => [ 'predef' ],
			'content'    => '
				<!-- wp:paragraph -->
				<p>If you\'re seeing this error either suddenly (no specific task was done to cause the error) or frequently, try deactivating all plugins to rule-out a plugin-specific issue and try switching themes to rule-out a theme-specific issue.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>Otherwise, here are three ways to increase PHP\'s memory allocation:</p>
				<!-- /wp:paragraph -->

				<!-- wp:list {"ordered":true} -->
				<ol><!-- wp:list-item -->
				<li>If you can edit or override the system <code>php.ini</code> file, increase the memory limit. For example, <code>memory_limit = 128M</code></li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li>If you cannot edit or override the system <code>php.ini</code> file, add <code>php_value memory_limit 128M</code> to your <code>.htaccess</code> file.</li>
				<!-- /wp:list-item -->

				<!-- wp:list-item -->
				<li>If neither of these work, it\'s time to ask your hosting provider to temporarily increase PHP\'s memory allocation on your account.</li>
				<!-- /wp:list-item --></ol>
				<!-- /wp:list -->

				<!-- wp:paragraph -->
				<p>(in the above examples, the limit is set to 128MB)</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p><a href="https://make.wordpress.org/support/handbook/giving-good-support/pre-defined-replies/#error-500-internal-server-error"><strong>Error 500: Internal Server Error</strong></a></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>Internal server errors (error 500) are often caused by plugin or theme function conflicts, so if you have access to your admin panel, try deactivating all plugins. If you don\'t have access to your admin panel, try <a href="https://wordpress.org/support/article/faq-troubleshooting/#how-to-deactivate-all-plugins-when-not-able-to-access-the-administrative-menus">manually resetting your plugins</a> (no Dashboard access required). If that resolves the issue, reactivate each one individually until you find the cause.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>If that does not resolve the issue, try switching to the default theme for your version of WordPress to rule-out a theme-specific issue. If you don\'t have access to your admin panel, access your server via <a href="https://wordpress.org/support/article/ftp-clients/">SFTP or FTP</a>, or a file manager in your hosting account\'s control panel, navigate to <code>/wp-content/themes/</code> and rename the directory of your currently active theme. This will force the default theme to activate and hopefully rule-out a theme-specific issue.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>If that does not resolve the issue, it\'s possible that a <code>.htaccess</code> rule could be the source of the problem. To check for this, access your server via SFTP or FTP, or a file manager in your hosting account\'s control panel, and rename the <code>.htaccess</code> file. If you can\'t find a <code>.htaccess</code> file, make sure that you have set your SFTP or FTP client to view invisible files.</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph -->
				<p>If you weren’t able to resolve the issue by either resetting your plugins and theme or renaming your <code>.htaccess</code> file, we may be able to help, but we\'ll need a more detailed error message. Internal server errors are usually described in more detail in the server error log. If you have access to your server error log, generate the error again, note the date and time, then immediately check your server error log for any errors that occurred during that time period. If you don’t have access to your server error log, ask your hosting provider to look for you.</p>
				<!-- /wp:paragraph -->',
		] );
	}
}
