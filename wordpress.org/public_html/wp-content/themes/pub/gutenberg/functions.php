<?php
/**
 * gutenbergtheme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Gutenbergtheme
 */

if ( ! defined( 'WPORGPATH' ) ) {
	define( 'WPORGPATH', get_theme_file_path( '/inc/' ) );
}

/**
 * Prevent errors resulting from change to Gutenberg plugin in 4.9 that adds call to
 * `get_current_screen()`.
 */
if ( ! function_exists( 'get_current_screen' ) && ! is_admin() && ! wp_doing_cron() && ! wp_doing_ajax() && ! ( defined( 'WP_CLI' ) && WP_CLI ) &&  ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
	function get_current_screen() {
		return null;
	}
}

/**
 * This function was removed from the Gutenberg plugin in v5.3.
 */
if ( ! function_exists( 'gutenberg_editor_scripts_and_styles' ) ) {
/**
 * Scripts & Styles.
 *
 * Enqueues the needed scripts and styles when visiting the top-level page of
 * the Gutenberg editor.
 *
 * @since 0.1.0
 *
 * @param string $hook Screen name.
 */
function gutenberg_editor_scripts_and_styles( $hook ) {
	global $wp_meta_boxes;

	// Enqueue heartbeat separately as an "optional" dependency of the editor.
	// Heartbeat is used for automatic nonce refreshing, but some hosts choose
	// to disable it outright.
	wp_enqueue_script( 'heartbeat' );

	wp_enqueue_script( 'wp-edit-post' );
	wp_enqueue_script( 'wp-format-library' );
	wp_enqueue_style( 'wp-format-library' );

	global $post;

	// Set initial title to empty string for auto draft for duration of edit.
	// Otherwise, title defaults to and displays as "Auto Draft".
	$is_new_post = 'auto-draft' === $post->post_status;

	// Set the post type name.
	$post_type        = get_post_type( $post );
	$post_type_object = get_post_type_object( $post_type );
	$rest_base        = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;

	$preload_paths = array(
		'/',
		'/wp/v2/types?context=edit',
		'/wp/v2/taxonomies?per_page=-1&context=edit',
		'/wp/v2/themes?status=active',
		sprintf( '/wp/v2/%s/%s?context=edit', $rest_base, $post->ID ),
		sprintf( '/wp/v2/types/%s?context=edit', $post_type ),
		sprintf( '/wp/v2/users/me?post_type=%s&context=edit', $post_type ),
		array( '/wp/v2/media', 'OPTIONS' ),
		array( '/wp/v2/blocks', 'OPTIONS' ),
	);

	/**
	 * Preload common data by specifying an array of REST API paths that will be preloaded.
	 *
	 * Filters the array of paths that will be preloaded.
	 *
	 * @param array $preload_paths Array of paths to preload
	 * @param object $post         The post resource data.
	 */
	$preload_paths = apply_filters( 'block_editor_preload_paths', $preload_paths, $post );

	// Ensure the global $post remains the same after
	// API data is preloaded. Because API preloading
	// can call the_content and other filters, callbacks
	// can unexpectedly modify $post resulting in issues
	// like https://github.com/WordPress/gutenberg/issues/7468.
	$backup_global_post = $post;

	$preload_data = array_reduce(
		$preload_paths,
		'rest_preload_api_request',
		array()
	);

	// Restore the global $post as it was before API preloading.
	$post = $backup_global_post;

	wp_add_inline_script(
		'wp-api-fetch',
		sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preload_data ) ),
		'after'
	);

	wp_add_inline_script(
		'wp-blocks',
		sprintf( 'wp.blocks.setCategories( %s );', wp_json_encode( get_block_categories( $post ) ) ),
		'after'
	);

	// Assign initial edits, if applicable. These are not initially assigned
	// to the persisted post, but should be included in its save payload.
	if ( $is_new_post ) {
		// Override "(Auto Draft)" new post default title with empty string,
		// or filtered value.
		$initial_edits = array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
		);
	} else {
		$initial_edits = null;
	}

	// Preload server-registered block schemas.
	wp_add_inline_script(
		'wp-blocks',
		'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . json_encode( get_block_editor_server_block_settings() ) . ');'
	);

	// Get admin url for handling meta boxes.
	$meta_box_url = admin_url( 'post.php' );
	$meta_box_url = add_query_arg(
		array(
			'post'            => $post->ID,
			'action'          => 'edit',
			'meta-box-loader' => true,
			'_wpnonce'        => wp_create_nonce( 'meta-box-loader' ),
		),
		$meta_box_url
	);
	wp_localize_script( 'wp-editor', '_wpMetaBoxUrl', $meta_box_url );

	// Initialize the editor.
	$align_wide    = get_theme_support( 'align-wide' );
	$color_palette = current( (array) get_theme_support( 'editor-color-palette' ) );
	$font_sizes    = current( (array) get_theme_support( 'editor-font-sizes' ) );

	/**
	 * Filters the allowed block types for the editor, defaulting to true (all
	 * block types supported).
	 *
	 * @param bool|array $allowed_block_types Array of block type slugs, or
	 *                                        boolean to enable/disable all.
	 * @param object $post                    The post resource data.
	 */
	$allowed_block_types = apply_filters( 'allowed_block_types', true, $post );

	// Get all available templates for the post/page attributes meta-box.
	// The "Default template" array element should only be added if the array is
	// not empty so we do not trigger the template select element without any options
	// besides the default value.
	$available_templates = wp_get_theme()->get_page_templates( get_post( $post->ID ) );
	$available_templates = ! empty( $available_templates ) ? array_merge(
		array(
			'' => apply_filters( 'default_page_template_title', __( 'Default template', 'gutenberg' ), 'rest-api' ),
		),
		$available_templates
	) : $available_templates;

	// Media settings.
	$max_upload_size = wp_max_upload_size();
	if ( ! $max_upload_size ) {
		$max_upload_size = 0;
	}

	// Editor Styles.
	global $editor_styles;
	$styles = array(
		array(
			'css' => file_get_contents(
				ABSPATH . WPINC . '/css/dist/editor/editor-styles.css'
			),
		),
	);

	/* Translators: Use this to specify the CSS font family for the default font */
	$locale_font_family = esc_html_x( 'Noto Serif', 'CSS Font Family for Editor Font', 'gutenberg' );
	$styles[]           = array(
		'css' => "body { font-family: '$locale_font_family' }",
	);

	if ( $editor_styles && current_theme_supports( 'editor-styles' ) ) {
		foreach ( $editor_styles as $style ) {
			if ( filter_var( $style, FILTER_VALIDATE_URL ) ) {
				$styles[] = array(
					'css' => file_get_contents( $style ),
				);
			} else {
				$file = get_theme_file_path( $style );
				if ( file_exists( $file ) ) {
					$styles[] = array(
						'css'     => file_get_contents( $file ),
						'baseURL' => get_theme_file_uri( $style ),
					);
				}
			}
		}
	}

	// Lock settings.
	$user_id = wp_check_post_lock( $post->ID );
	if ( $user_id ) {
		/**
		 * Filters whether to show the post locked dialog.
		 *
		 * Returning a falsey value to the filter will short-circuit displaying the dialog.
		 *
		 * @since 3.6.0
		 *
		 * @param bool         $display Whether to display the dialog. Default true.
		 * @param WP_Post      $post    Post object.
		 * @param WP_User|bool $user    The user id currently editing the post.
		 */
		if ( apply_filters( 'show_post_locked_dialog', true, $post, $user_id ) ) {
			$locked = true;
		} else {
			$locked = false;
		}

		$user_details = null;
		if ( $locked ) {
			$user         = get_userdata( $user_id );
			$user_details = array(
				'name' => $user->display_name,
			);
			$avatar       = get_avatar( $user_id, 64 );
			if ( $avatar ) {
				if ( preg_match( "|src='([^']+)'|", $avatar, $matches ) ) {
					$user_details['avatar'] = $matches[1];
				}
			}
		}

		$lock_details = array(
			'isLocked' => $locked,
			'user'     => $user_details,
		);
	} else {

		// Lock the post.
		$active_post_lock = wp_set_post_lock( $post->ID );
		$lock_details     = array(
			'isLocked'       => false,
			'activePostLock' => esc_attr( implode( ':', $active_post_lock ) ),
		);
	}

	$editor_settings = array(
		'alignWide'              => $align_wide,
		'availableTemplates'     => $available_templates,
		'allowedBlockTypes'      => $allowed_block_types,
		'disableCustomColors'    => get_theme_support( 'disable-custom-colors' ),
		'disableCustomFontSizes' => get_theme_support( 'disable-custom-font-sizes' ),
		'disablePostFormats'     => ! current_theme_supports( 'post-formats' ),
		'titlePlaceholder'       => apply_filters( 'enter_title_here', __( 'Add title', 'gutenberg' ), $post ),
		'bodyPlaceholder'        => apply_filters( 'write_your_story', __( 'Start writing or type / to choose a block', 'gutenberg' ), $post ),
		'isRTL'                  => is_rtl(),
		'autosaveInterval'       => 10,
		'maxUploadFileSize'      => $max_upload_size,
		'allowedMimeTypes'       => get_allowed_mime_types(),
		'styles'                 => $styles,
		'imageSizes'             => gutenberg_get_available_image_sizes(),
		'richEditingEnabled'     => user_can_richedit(),

		// Ideally, we'd remove this and rely on a REST API endpoint.
		'postLock'               => $lock_details,
		'postLockUtils'          => array(
			'nonce'       => wp_create_nonce( 'lock-post_' . $post->ID ),
			'unlockNonce' => wp_create_nonce( 'update-post_' . $post->ID ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		),

		// Whether or not to load the 'postcustom' meta box is stored as a user meta
		// field so that we're not always loading its assets.
		'enableCustomFields'     => (bool) get_user_meta( get_current_user_id(), 'enable_custom_fields', true ),
	);

	$post_autosave = gutenberg_get_autosave_newer_than_post_save( $post );
	if ( $post_autosave ) {
		$editor_settings['autosave'] = array(
			'editLink' => get_edit_post_link( $post_autosave->ID ),
		);
	}

	if ( false !== $color_palette ) {
		$editor_settings['colors'] = $color_palette;
	}

	if ( false !== $font_sizes ) {
		$editor_settings['fontSizes'] = $font_sizes;
	}

	if ( ! empty( $post_type_object->template ) ) {
		$editor_settings['template']     = $post_type_object->template;
		$editor_settings['templateLock'] = ! empty( $post_type_object->template_lock ) ? $post_type_object->template_lock : false;
	}

	$current_screen  = get_current_screen();
	$core_meta_boxes = array();

	// Make sure the current screen is set as well as the normal core metaboxes.
	if ( isset( $current_screen->id ) && isset( $wp_meta_boxes[ $current_screen->id ]['normal']['core'] ) ) {
		$core_meta_boxes = $wp_meta_boxes[ $current_screen->id ]['normal']['core'];
	}

	// Check if the Custom Fields meta box has been removed at some point.
	if ( ! isset( $core_meta_boxes['postcustom'] ) || ! $core_meta_boxes['postcustom'] ) {
		unset( $editor_settings['enableCustomFields'] );
	}

	/**
	 * Filters the settings to pass to the block editor.
	 *
	 * @since 3.7.0
	 *
	 * @param array   $editor_settings Default editor settings.
	 * @param WP_Post $post            Post being edited.
	 */
	$editor_settings = apply_filters( 'block_editor_settings', $editor_settings, $post );

	$init_script = <<<JS
( function() {
	window._wpLoadBlockEditor = new Promise( function( resolve ) {
		wp.domReady( function() {
			resolve( wp.editPost.initializeEditor( 'editor', "%s", %d, %s, %s ) );
		} );
	} );
} )();
JS;

	$script = sprintf(
		$init_script,
		$post->post_type,
		$post->ID,
		wp_json_encode( $editor_settings ),
		wp_json_encode( $initial_edits )
	);
	wp_add_inline_script( 'wp-edit-post', $script );

	/**
	 * Scripts
	 */
	wp_enqueue_media(
		array(
			'post' => $post->ID,
		)
	);
	wp_tinymce_inline_scripts();
	wp_enqueue_editor();

	/**
	 * Styles
	 */
	wp_enqueue_style( 'wp-edit-post' );

	/**
	 * Fires after block assets have been enqueued for the editing interface.
	 *
	 * Call `add_action` on any hook before 'admin_enqueue_scripts'.
	 *
	 * In the function call you supply, simply use `wp_enqueue_script` and
	 * `wp_enqueue_style` to add your functionality to the Gutenberg editor.
	 *
	 * @since 0.4.0
	 */
	do_action( 'enqueue_block_editor_assets' );
}

}


/**
 * This function was removed from the Gutenberg plugin in v5.4.
 */
if ( ! function_exists( 'gutenberg_get_available_image_sizes' ) ) {
/**
 * Retrieve The available image sizes for a post
 *
 * @return array
 */
function gutenberg_get_available_image_sizes() {
	$size_names = apply_filters(
		'image_size_names_choose',
		array(
			'thumbnail' => __( 'Thumbnail', 'gutenberg' ),
			'medium'    => __( 'Medium', 'gutenberg' ),
			'large'     => __( 'Large', 'gutenberg' ),
			'full'      => __( 'Full Size', 'gutenberg' ),
		)
	);
	$all_sizes = array();
	foreach ( $size_names as $size_slug => $size_name ) {
		$all_sizes[] = array(
			'slug' => $size_slug,
			'name' => $size_name,
		);
	}
	return $all_sizes;
}
} // /function_exists()

/**
 * This function was removed from the Gutenberg plugin in v5.4.
 */
if ( ! function_exists( 'gutenberg_get_autosave_newer_than_post_save' ) ) {
/**
 * Retrieve a stored autosave that is newer than the post save.
 *
 * Deletes autosaves that are older than the post save.
 *
 * @param  WP_Post $post Post object.
 * @return WP_Post|boolean The post autosave. False if none found.
 */
function gutenberg_get_autosave_newer_than_post_save( $post ) {
	// Add autosave data if it is newer and changed.
	$autosave = wp_get_post_autosave( $post->ID );
	if ( ! $autosave ) {
		return false;
	}
	// Check if the autosave is newer than the current post.
	if (
		mysql2date( 'U', $autosave->post_modified_gmt, false ) > mysql2date( 'U', $post->post_modified_gmt, false )
	) {
		return $autosave;
	}
	// If the autosave isn't newer, remove it.
	wp_delete_post_revision( $autosave->ID );
	return false;
}
} // /function_exists()

add_action( 'template_redirect', function() {
	if ( ! is_page( 'test' ) ) {
		return;
	}

	show_admin_bar( true );

	add_action( 'wp_enqueue_scripts', function() {
		wp_enqueue_script( 'postbox', admin_url( 'js/postbox.min.js' ),array( 'jquery-ui-sortable' ), false, 1 );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'common' );
		wp_enqueue_style( 'forms' );
		wp_enqueue_style( 'dashboard' );
		wp_enqueue_style( 'media' );
		wp_enqueue_style( 'admin-menu' );
		wp_enqueue_style( 'admin-bar' );
		wp_enqueue_style( 'nav-menus' );
		wp_enqueue_style( 'l10n' );
		wp_enqueue_style( 'buttons' );

		// Use a middleware provider to intercept and modify API calls. Short-circuit POST requests, bound queries, allow media, etc.
		wp_add_inline_script( 'wp-api-fetch',
			'wp.apiFetch.use( function( options, next ) {
				var isWhitelistedEndpoint = (
					lodash.startsWith( options.path, "/oembed/1.0/proxy" ) ||
					lodash.startsWith( options.path, "/gutenberg/v1/block-renderer" )
				);

				// Prevent non-whitelisted non-GET requests (ie. POST) to prevent errors
				if ( options.method && options.method !== "GET" && ! isWhitelistedEndpoint ) {
					// This works in enough cases to be the default return value.
					return Promise.resolve( options.data );
				}

				// Add limits to all GET queries which attempt unbound queries
				options.path = options.path.replace( "per_page=-1", "per_page=10" );

				// Load images with the view context, seems to work
				if ( lodash.startsWith( options.path, "/wp/v2/media/" ) ) {
					options.path = options.path.replace( "context=edit", "context=view" );
				}

				return next( options );
			} );',
			'after'
		);

		// Use a middleware preloader to handle the "types" API endpoints with minimal data needed
		wp_add_inline_script( 'wp-api-fetch',
			'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( {
				"/wp/v2/types?context=edit": { "body": {
					"page": {
						"rest_base": "pages",
						"supports": {},
						"labels": {
							"singular_name": "Page",
						}
					},
					"wp_block": {
						"rest_base": "blocks",
						"supports": {},
						"labels": {
							"singular_name": "Block",
						}
					}
				} },
				"/wp/v2/types/page?context=edit": { "body": {
					"rest_base": "pages",
					"supports": {},
					"labels": {
						"singular_name": "Page",
					}
				} },
				"/wp/v2/types/wp_block?context=edit": { "body": {
					"rest_base": "blocks",
					"supports": {},
					"labels": {
						"singular_name": "Block",
					}
				} }
			} ) );',
			'after'
		);

		// Use a middleware preloader to load the custom post content:
		$frontendburg_content = include __DIR__ . '/gutenfront-content.php';
		wp_add_inline_script( 'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
				wp_json_encode( array(
					"/wp/v2/pages/" . get_post()->ID . "?context=edit" => [ 'body' => [
						'id' => get_post()->ID,
						'title' => [ 'raw' => $frontendburg_content['title'] ],
						'content' => [ 'block_format' => 1, 'raw' => $frontendburg_content['content'] ],
						'excerpt' => [ 'raw' => '' ],
						'date' => '', 'date_gmt' => '', 'modified' => '', 'modified_gmt' => '',
						'link' => home_url('/'), 'guid' => [],
						'parent' => 0, 'menu_order' => 0, 'author' => 0, 'featured_media' => 0,
						'comment_status' => 'closed', 'ping_status' => 'closed', 'template' => '', 'meta' => [], '_links' => [],
						'type' => 'page', 'status' => 'draft',
						'slug' => '', 'generated_slug' => '', 'permalink_template' => home_url('/'),
					] ]
				) )
			),
			'after'
		);

		// Add a middleware provider which intercepts all uploads and stores them within the browser
		wp_add_inline_script( 'wp-api-fetch',
			'wp.apiFetch.use( function( options, next ) {
				if ( options.method == "POST" && options.path == "/wp/v2/media" ) {
					var file = options.body.get("file");

					window.fakeUploadedMedia = window.fakeUploadedMedia || [];
					if ( ! window.fakeUploadedMedia.length ) {
						window.fakeUploadedMedia[9999000] = {};
					}
					var id = window.fakeUploadedMedia.length;

					window.fakeUploadedMedia[ id ] = {
						"id": id,
						"date": "", "date_gmt": "", "modified": "", "modified_gmt": "",
						"guid": {}, "title": { "rendered": file.name, "raw": file.name },
						"description": {}, "caption": {}, "alt_text": "",
						"slug": file.name, "status": "inherit", "type": "attachment", "link": "",
						"author": 0, "comment_status": "open", "ping_status": "closed",
						"media_details": {}, "media_type": file.type.split("/")[0], "mime_type": file.type,
						"source_url": "", // This gets filled below with a data uri
						"_links": {}
					};

						return new Promise( function( resolve ) {
						var a = new FileReader();
    						a.onload = function(e) {
    							window.fakeUploadedMedia[ id ].source_url = e.target.result;
    							resolve( window.fakeUploadedMedia[ id ] );
    						}
    						a.readAsDataURL( file );
    					} );
				}

				// Drag droped media of ID 9999xxx is stored within the Browser
				var path_id_match = options.path.match( "^/wp/v2/media/(9999\\\\d+)" );
				if ( path_id_match ) {
					return Promise.resolve( window.fakeUploadedMedia[ path_id_match[1] ] );
				}

				return next( options );
			} );',
			'after'
		);
		wp_add_inline_script(
			'wp-edit-post',
			'wp.data.dispatch( "core/edit-post" ).closeGeneralSidebar();' .
			'if ( wp.data.select( "core/edit-post" ).isFeatureActive( "welcomeGuide" )) { wp.data.dispatch( "core/edit-post" ).toggleFeature( "welcomeGuide" )};' .
			'_wpLoadBlockEditor.then( function() { wp.blocks.unregisterBlockType( "core/shortcode" ); } );'
		);

	}, 11 );

	add_action( 'wp_enqueue_scripts', function( $hook ) {
		// Gutenberg requires the post-locking functions defined within:
		// See `show_post_locked_dialog` and `get_post_metadata` filters below.
		include_once ABSPATH . 'wp-admin/includes/post.php';

		gutenberg_editor_scripts_and_styles( $hook );
	} );

	add_action( 'enqueue_block_editor_assets', function() {
		wp_enqueue_script( 'button-readonly', get_template_directory_uri() . '/js/button-readonly.js', array( 'wp-blocks', 'wp-element' ), null );
	} );

	// Disable post locking dialogue.
	add_filter( 'show_post_locked_dialog', '__return_false' );

	// Everyone can richedit! This avoids a case where a page can be cached where a user can't richedit.
	$GLOBALS['wp_rich_edit'] = true;
	add_filter( 'user_can_richedit', '__return_true', 1000 );

	// Homepage is always locked by @wordpressdotorg
	// This prevents other logged-in users taking a lock of the post on the front-end.
	add_filter( 'get_post_metadata', function( $value, $post_id, $meta_key ) {
		if ( $meta_key !== '_edit_lock' ) {
			return $value;
		}

		// This filter is only added on a front-page view of the homepage for this site, no other checks are needed here.

		return time() . ':5911429'; // WordPressdotorg user ID
	}, 10, 3 );

	// Disable use XML-RPC
	add_filter( 'xmlrpc_enabled', '__return_false' );

	// Disable X-Pingback to header
	function disable_x_pingback( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}
	add_filter( 'wp_headers', 'disable_x_pingback' );

	function frontenberg_site_title() {
		return esc_html__( 'The new Gutenberg editing experience', 'wporg' );
	}
	add_filter( 'the_title', 'frontenberg_site_title' );
	add_filter( 'option_blogname', 'frontenberg_site_title' );

	function frontenberg_site_description() {
		return esc_html__( 'A new editing experience for WordPress is in the works, code name Gutenberg. Read more about it and test it!', 'wporg' );
	}
	add_filter( 'option_blogdescription', 'frontenberg_site_description' );

	// Disable Jetpack Blocks for now.
	add_filter( 'jetpack_gutenberg', '__return_false' );
});

/**
 * Let unauthenticated users embed media in Frontenberg.
 */
function frontenberg_enable_oembed( $all_caps ) {
	if (
		0 === strpos( $_SERVER['REQUEST_URI'], '/gutenberg/wp-json/oembed/1.0/proxy' )  ||
		0 === strpos( $_SERVER['REQUEST_URI'], '/gutenberg/wp-json/gutenberg/v1/block-renderer/core/archives' ) ||
		0 === strpos( $_SERVER['REQUEST_URI'], '/gutenberg/wp-json/gutenberg/v1/block-renderer/core/latest-comments' )
	) {
		$all_caps['edit_posts'] = true;
	}

	return $all_caps;
}
add_filter( 'user_has_cap', 'frontenberg_enable_oembed' );

/**
 * Ajax handler for querying attachments for logged-out users.
 *
 * @since 3.5.0
 */
function frontenberg_wp_ajax_nopriv_query_attachments() {
	if ( 97589 !== absint( $_REQUEST['post_id'] ) ) {
		wp_send_json_error();
	}
	$query = isset( $_REQUEST['query'] ) ? (array) $_REQUEST['query'] : array();
	$keys = array(
		's', 'order', 'orderby', 'posts_per_page', 'paged', 'post_mime_type',
		'post_parent', 'post__in', 'post__not_in', 'year', 'monthnum'
	);
	foreach ( get_taxonomies_for_attachments( 'objects' ) as $t ) {
		if ( $t->query_var && isset( $query[ $t->query_var ] ) ) {
			$keys[] = $t->query_var;
		}
	}

	$query = array_intersect_key( $query, array_flip( $keys ) );
	$query['post_type'] = 'attachment';
	if ( MEDIA_TRASH
		&& ! empty( $_REQUEST['query']['post_status'] )
		&& 'trash' === $_REQUEST['query']['post_status'] ) {
		$query['post_status'] = 'trash';
	} else {
		$query['post_status'] = 'inherit';
	}

	// Filter query clauses to include filenames.
	if ( isset( $query['s'] ) ) {
		add_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
	}

	if ( empty( $query['post__in'] ) ) {
		$query['post__in'] = range( 97654, 97659 );
	}

	/**
	 * Filters the arguments passed to WP_Query during an Ajax
	 * call for querying attachments.
	 *
	 * @since 3.7.0
	 *
	 * @see WP_Query::parse_query()
	 *
	 * @param array $query An array of query variables.
	 */
	$query = apply_filters( 'ajax_query_attachments_args', $query );
	$query = new WP_Query( $query );

	$posts = array_map( 'wp_prepare_attachment_for_js', $query->posts );
	$posts = array_filter( $posts );

	wp_send_json_success( $posts );
}
add_action( 'wp_ajax_nopriv_query-attachments', 'frontenberg_wp_ajax_nopriv_query_attachments' );

/**
 * Removes tagline, which is used more as a description on this site.
 *
 * @param array $title {
 *     The document title parts.
 *
 *     @type string $title   Title of the viewed page.
 *     @type string $page    Optional. Page number if paginated.
 *     @type string $tagline Optional. Site description when on home page.
 *     @type string $site    Optional. Site title when not on home page.
 * }
 */
function gutenberg_title_parts( $title ) {
	unset( $title['tagline'] );

	return $title;
}
add_filter( 'document_title_parts', 'gutenberg_title_parts' );

if ( ! function_exists( 'gutenbergtheme_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function gutenbergtheme_setup() {
		/*
		 * Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on gutenbergtheme, use a find and replace
		 * to change 'gutenbergtheme' to the name of your theme in all the template files.
		 */
		load_theme_textdomain( 'gutenbergtheme', get_template_directory() . '/languages' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );

		// This theme uses wp_nav_menu() in one location.
		register_nav_menus( array(
			'menu-1' => esc_html__( 'Primary', 'gutenbergtheme' ),
		) );

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		add_theme_support( 'align-wide' );

		add_theme_support( 'editor-color-palette', [
			[
				'name'  => esc_html__( 'Dark Blue', 'gutenbergtheme' ),
				'slug' => 'dark-blue',
				'color' => '#0073aa',
			],
			[

				'name'  => esc_html__( 'Light Blue', 'gutenbergtheme' ),
				'slug' => 'light-blue',
				'color' => '#229fd8',
			],
			[

				'name'  => esc_html__( 'Dark Gray', 'gutenbergtheme' ),
				'slug' => 'dark-gray',
				'color' => '#444',
			],
			[

				'name'  => esc_html__( 'Light Gray', 'gutenbergtheme' ),
				'slug' => 'light-gray',
				'color' => '#eee',
			],
		] );
	}
endif;
add_action( 'after_setup_theme', 'gutenbergtheme_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function gutenbergtheme_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'gutenbergtheme_content_width', 640 );
}
add_action( 'after_setup_theme', 'gutenbergtheme_content_width', 0 );

/**
 * Register Google Fonts
 */
function gutenbergtheme_fonts_url() {
    $fonts_url = '';

    /* Translators: If there are characters in your language that are not
	 * supported by Karla, translate this to 'off'. Do not translate
	 * into your own language.
	 */
	$notoserif = esc_html_x( 'on', 'Noto Serif font: on or off', 'gutenbergtheme' );

	if ( 'off' !== $notoserif ) {
		$font_families = array();
		$font_families[] = 'Noto Serif:400,400italic,700,700italic';

		$query_args = array(
			'family' => urlencode( implode( '|', $font_families ) ),
			'subset' => urlencode( 'latin,latin-ext' ),
		);

		$fonts_url = add_query_arg( $query_args, 'https://fonts.googleapis.com/css' );
	}

	return $fonts_url;

}

/**
 * Enqueue scripts and styles.
 */
function gutenbergtheme_scripts() {
	wp_enqueue_style( 'gutenbergtheme-style', get_stylesheet_uri(), [], 13 );

	wp_enqueue_style( 'gutenbergthemeblocks-style', get_template_directory_uri() . '/blocks.css');

	wp_enqueue_style( 'gutenbergtheme-fonts', gutenbergtheme_fonts_url(), array(), null );

	wp_enqueue_script( 'gutenbergtheme-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20181209', true );

	wp_enqueue_script( 'gutenbergtheme-prism', get_template_directory_uri() . '/js/prism.js', array(), '1.15.0' );

	wp_enqueue_script( 'gutenbergtheme-handbook', get_template_directory_uri() . '/js/handbook.js', array( 'jquery' ), null );

	wp_enqueue_script( 'gutenbergtheme-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'gutenbergtheme_scripts' );

function gutenbergtheme_adjacent_post_order( $order_by, $post, $order ) {
	if ( 'handbook' !== $post->post_type ) {
		return $order_by;
	}

	return "ORDER BY p.menu_order $order LIMIT 1";
}
add_filter( 'get_previous_post_sort', 'gutenbergtheme_adjacent_post_order', 10, 3 );
add_filter( 'get_next_post_sort', 'gutenbergtheme_adjacent_post_order', 10, 3 );

function gutenbergtheme_previous_post_where( $where, $in_same_term, $excluded_term, $taxonomy, $post ) {
	if ( 'handbook' !== $post->post_type ) {
		return $where;
	}

	return "WHERE p.post_type='handbook' AND p.post_status='publish' AND p.menu_order < {$post->menu_order}";
}
add_filter( 'get_previous_post_where', 'gutenbergtheme_previous_post_where', 10, 5 );

function gutenbergtheme_next_post_where( $where, $in_same_term, $excluded_term, $taxonomy, $post ) {
	if ( 'handbook' !== $post->post_type ) {
		return $where;
	}

	return "WHERE p.post_type='handbook' AND p.post_status='publish' AND p.menu_order > {$post->menu_order}";
}
add_filter( 'get_next_post_where', 'gutenbergtheme_next_post_where', 10, 5 );

/**
 * Add redirects for any handbook pages that have been renamed.
 */
function gutenbergtheme_handbook_redirects() {
	if ( 0 !== strpos( $_SERVER['REQUEST_URI'], '/gutenberg/handbook' ) ) {
		return;
	}

	$handbook_path = explode( '/', trailingslashit( $_SERVER['REQUEST_URI'] ), 4 );
	$handbook_path = $handbook_path[3] ?? null;

	if ( is_null( $handbook_path ) ) {
		return;
	}

	// Any handbook pages where the filename changes should be listed here.
	$redirects = [
		'designers-developers/developers/backwards-compatibility' => 'developers/backward-compatibility/',
		'reference/deprecated'           => 'developers/backward-compatibility/deprecations/',
		'reference/design-principles'    => 'contributors/design/',
		'extensibility/annotations'      => 'developers/block-api/block-annotations/',
		'block-api/attributes'           => 'developers/block-api/block-attributes/',
		'block-api/deprecated-blocks'    => 'developers/block-api/block-deprecation/',
		'block-api/rich-text-api'        => 'developers/tutorials/format-api/',
		'block-api'                      => 'developers/block-api/block-registration/',
		'templates'                      => 'developers/block-api/block-templates/',
		'extensibility'                  => 'developers/filters/',
		'extensibility/autocomplete'     => 'developers/filters/autocomplete-filters/',
		'extensibility/extending-blocks' => 'developers/filters/block-filters/',
		'extensibility/parser'           => 'developers/filters/parser-filters/',
		'packages/packages-api-request'  => 'packages/packages-api-fetch/',
		'blocks'                         => 'tutorials/block-tutorial/',
		'language'                       => 'key-concepts/',

		// Redirects for index pages where needed
		'designers-developers/developers/tutorials/block-tutorial'       => 'tutorials/block-tutorial/',
		'designers-developers/developers/tutorials/block-tutorial/intro' => 'tutorials/block-tutorial/',
	];

	// General path redirects. (More specific path first.)
	$path_redirects = [
		'block-api/'                                  => 'developers/block-api/',
		'designers-developers/data/'                  => 'data/',
		'designers-developers/designers/'             => 'designers/',
		'designers-developers/developers/components/' => 'components/',
		'designers-developers/developers/packages/'   => 'packages/',
		'designers-developers/developers/tutorials/'  => 'tutorials/',
		'designers-developers/developers/'            => 'developers/',
	];

	if ( ! empty( $redirects[ untrailingslashit( $handbook_path ) ] ) ) {
		$handbook_path = $redirects[ untrailingslashit( $handbook_path ) ];
	} else {
		foreach ( $path_redirects as $old_path => $new_path ) {
			if ( 0 === strpos( $handbook_path, $old_path ) ) {
				$handbook_path = str_replace( $old_path, $new_path, $handbook_path );
				break;
			}
		}
	}

	$redirect_to = 'https://developer.wordpress.org/block-editor/' . $handbook_path ;

	wp_redirect( $redirect_to );
	exit;
}
add_action( 'template_redirect', 'gutenbergtheme_handbook_redirects', 1 );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}

/**
 * Gutenberg documentation
 */
require __DIR__ . '/inc/docs-importer.php';
require __DIR__ . '/inc/class-gutenberg-handbook.php';

add_action( 'load-post.php', array( 'Import_Gutendocs', 'action_load_post_php' ) );
add_action( 'edit_form_after_title', array( 'Import_Gutendocs', 'action_edit_form_after_title' ) );
add_action( 'save_post', array( 'Import_Gutendocs', 'action_save_post' ) );
add_filter( 'cron_schedules', array( 'Import_Gutendocs', 'filter_cron_schedules' ) );
//add_action( 'init', array( 'Import_Gutendocs', 'action_init' ) );
//add_action( 'wporg_gutenberg_manifest_import', array( 'Import_Gutendocs', 'action_wporg_gutenberg_manifest_import' ) );
//add_action( 'wporg_gutenberg_markdown_import', array( 'Import_Gutendocs', 'action_wporg_gutenberg_markdown_import' ) );

add_filter( 'the_title', array( 'Gutenberg_Handbook', 'filter_the_title_edit_link' ), 10, 2 );
add_filter( 'get_edit_post_link', array( 'Gutenberg_Handbook', 'redirect_edit_link_to_github' ), 10, 3 );
add_filter( 'o2_filter_post_actions', array( 'Gutenberg_Handbook', 'redirect_o2_edit_link_to_github' ), 11, 2 );

add_filter( 'handbook_display_toc', '__return_false' );
