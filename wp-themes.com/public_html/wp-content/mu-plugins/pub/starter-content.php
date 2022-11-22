<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Starter Content previews for wp-themes.com
 * Author: Tung Du
 * Version: 0.1
 */

class Starter_Content {

	private $starter_content_post_id = 10000;

	private $starter_content = array();

	private $mapping = array(
		'nav_menus' => array(),
		'posts'     => array(),
	);

	public function __construct() {
		// This plugin relies upon the object cache being the internal WordPress per-request cache.
		if ( wp_using_ext_object_cache() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		// Some themes require is_customize_preview() before loading starter content.
		add_action( 'setup_theme', array( $this, 'pre_setup_theme' ), 0 );

		// Some themes check to see if the site is fresh through `get_option( 'fresh_site' )` before loading starter content.
		add_filter( 'pre_option_fresh_site', '__return_true' );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_head', array( $this, 'head_debug_info' ), 1 );
	}

	public function pre_setup_theme() {
		if ( class_exists( 'WP_Customize_Manager' ) ) {
			return;
		}

		if ( ! $this->is_supported_theme() ) {
			return;
		}

		// Disable customizer loading parameters.
		unset(
			$_REQUEST['wp_customize'],
			$_REQUEST['customize_changeset_uuid'],
			$_GET['customize_changeset_uuid'],
			$_POST['customize_changeset_uuid']
		);

		// Define a custom `WP_Customize_Manager` class to force `is_customize_preview()` truthful.
		polyfill_wp_customize_manager();

		$GLOBALS['wp_customize'] = new WP_Customize_Manager();

		// Undo this the override after this action is fully run.
		add_action(
			'after_setup_theme',
			function() {
				unset( $GLOBALS['wp_customize'] );
			},
			PHP_INT_MAX
		);
	}

	private function is_supported_theme() {
		// Allow using `?use-starter-content=0` to disable the starter content for a request.
		if (
			isset( $_GET['use-starter-content'] ) &&
			! $_GET['use-starter-content']
		) {
			return false;
		}

		// If a theme causes problems, this can block loading.
		$blocked_themes = array(
			'finedine',  // Customizer polyfill causes E_ERROR: Uncaught Error: Call to a member function add_partial() on bool
		);

		// If a theme authors themes often cause a problem, just block them all.
		$blocked_authors = array(
			'sktthemes',
				// 'posterity', // +child themes - Customizer polyfill causes E_ERROR: Cannot redeclare posterity_get_user_css()
				// 'barter', // E_COMPILE_ERROR: Cannot redeclare barter_get_user_css()
		);

		$theme_author = wp_get_theme()['Author'];
		foreach ( $blocked_authors as $author ) {
			if ( false !== stripos( $theme_author, str_replace( ' ', '', $author ) ) ) {
				$blocked_themes[] = get_stylesheet();
			}
		}

		if (
			in_array( get_stylesheet(), $blocked_themes ) ||
			in_array( get_template(), $blocked_themes )
		) {
			return false;
		}

		return true;
	}

	public function init() {
		if ( ! $this->is_supported_theme() ) {
			return;
		}

		$this->set_starter_content();

		if ( empty( $this->starter_content ) ) {
			return;
		}

		$this->set_options();
		$this->cache_posts();

		$this->filter_posts();
		$this->filter_nav_menus();
		$this->filter_post_thumbnails();
		$this->filter_page_template();
		$this->filter_sidebars();
		$this->filter_theme_mods();
	}

	public function head_debug_info() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( current_theme_supports( 'starter-content' ) && $this->starter_content ) {
			echo "\n<!-- Preview using Starter content -->\n";
		} else {
			echo "\n<!-- Preview is not using Starter content -->\n";
		}
	}

	public function cache_posts() {
		if ( empty( $this->starter_content['posts'] ) ) {
			return;
		}

		foreach ( $this->starter_content['posts'] as $post ) {

			wp_cache_add( $post['ID'], (object) $post, 'posts' );
			if ( 'page' === $post['post_type'] ) {
				$last_changed = wp_cache_get_last_changed( 'posts' );
				$hash         = md5( $post['post_name'] . serialize( $post['post_type'] ) );
				$cache_key    = "get_page_by_path:$hash:$last_changed";
				wp_cache_set( $cache_key, $post['ID'], 'posts' );
			}
		}
	}

	public function filter_theme_mods() {
		if ( empty( $this->starter_content['theme_mods'] ) ) {
			return;
		}
		foreach ( $this->starter_content['theme_mods'] as $key => $theme_mod ) {
			add_filter(
				"theme_mod_$key",
				function() use ( $theme_mod ) {
					return $theme_mod;
				}
			);
		}
	}

	public function filter_posts() {
		add_filter( 'posts_pre_query', array( $this, 'filter_page_query' ), 10, 2 );
	}

	public function filter_page_template() {

		add_filter(
			'get_post_metadata',
			function( $value, $post_id, $meta_key ) {
				if ( '_wp_page_template' !== $meta_key ) {
					return $value;
				}

				$post_data = $this->find_data_by_id( $post_id );

				return $post_data['template'] ?? $value;
			},
			10,
			3
		);
	}

	public function filter_post_thumbnails() {

		add_filter(
			'get_post_metadata',
			function ( $value, $post_id, $meta_key ) {
				if ( ! in_array( $post_id, array_values( $this->mapping['posts'] ) )
					|| '_thumbnail_id' !== $meta_key
				) {
					return $value;
				}

				$post_data = $this->find_data_by_id( $post_id );
				if ( empty( $post_data['thumbnail'] ) ) {
					return $value;
				}
				return $post_data['thumbnail'];
			},
			10,
			3
		);

		add_filter(
			'wp_get_attachment_image_src',
			function ( $image, $attachment_id, $size ) {
				$image_data = $this->find_data_by_id( $attachment_id, 'attachments' );
				if ( empty( $image_data ) ) {
					return $image;
				}
				$image_url = sprintf(
					'%1$s/%2$s',
					untrailingslashit( get_template_directory_uri() ),
					ltrim( $image_data['file'], '/' )
				);

				$image_sizes = wp_get_registered_image_subsizes();
				$width       = 0;
				$height      = 0;
				if ( ! empty( $image_sizes[ $size ] ) ) {
					$width  = $image_sizes[ $size ]['width'];
					$height = $image_sizes[ $size ]['height'];
				}

				return array( $image_url, $width, $height );
			},
			10,
			3
		);
	}

	public function filter_nav_menus() {
		if ( empty( $this->starter_content['nav_menus'] ) ) {
			return;
		}

		add_filter(
			'has_nav_menu',
			function ( $has_nav_menu, $location ) {
				if ( empty( $this->starter_content['nav_menus'][ $location ] ) ) {
					return $has_nav_menu;
				}
				return true;
			},
			10,
			2
		);

		add_filter(
			'theme_mod_nav_menu_locations',
			function () {
				return $this->mapping['nav_menus'];
			}
		);

		add_filter(
			'wp_get_nav_menu_object',
			function ( $menu_objects, $menu ) {
				foreach ( $this->mapping['nav_menus'] as $location => $menu_id ) {
					if ( $menu_id != $menu ) {
						continue;
					}
					$menu_objects = new \WP_Term(
						(object) array(
							'taxonomy'         => 'nav_menu',
							'term_id'          => $menu_id,
							'slug'             => $location,
							'name'             => $this->starter_content['nav_menus'][ $location ]['name'],
							'term_taxonomy_id' => $menu_id,
							'count'            => count( $this->starter_content['nav_menus'][ $location ]['items'] ),
						)
					);
				}

				return $menu_objects;
			},
			10,
			2
		);

		add_filter(
			'wp_get_nav_menu_items',
			function ( $items, $menu, $args ) {
				foreach ( $this->mapping['nav_menus'] as $location => $menu_id ) {
					if ( $menu_id != $menu->term_id ) {
						continue;
					}

					$menu_items = array();
					foreach ( $this->starter_content['nav_menus'][ $location ]['items'] as $index => $item ) {
						$item = wp_parse_args(
							$item,
							array(
								'db_id'            => 0,
								'object_id'        => 0,
								'object'           => '',
								'parent_id'        => 0,
								'position'         => 0,
								'type'             => 'custom',
								'title'            => '',
								'url'              => '',
								'description'      => '',
								'attr-title'       => '',
								'target'           => '',
								'classes'          => '',
								'xfn'              => '',
								'status'           => '',
								'menu_order'       => $index,
								'menu_item_parent' => 0,
								'post_parent'      => 0,
								'ID'               => $this->generate_id(),
							)
						);

						if ( 'custom' === $item['type'] ) {
							$item['object'] = 'custom';
						}

						if ( ! empty( $item['title'] ) && ! empty( $item['url'] ) ) {
							$menu_items[] = (object) $item;
							continue;
						}

						if ( ! empty( $item['object_id'] ) && 'post_type' === $item['type'] ) {
							foreach ( $this->mapping['posts'] as $name => $id ) {
								if ( $id !== $item['object_id'] ) {
									continue;
								}
								$post_data = $this->find_data_by_id( $item['object_id'] );
								if ( empty( $post_data ) ) {
									continue;
								}

								$item['url']   = get_permalink( $id );
								$item['title'] = $post_data['post_title'];

								$menu_items[] = (object) $item;
								continue;
							}
						}
					}
				}

				if ( empty( $menu_items ) ) {
					return $items;
				}
				return $menu_items;
			},
			10,
			3
		);
	}

	public function filter_sidebars() {
		if ( empty( $this->starter_content['widgets'] ) ) {
			return;
		}

		$widgets         = array();
		$widgets_options = array();

		$number = 1;
		foreach ( $this->starter_content['widgets'] as $sidebar => $sidebar_widgets ) {
			foreach ( $sidebar_widgets as $widget ) {
				$widgets[]                                = array(
					'number'   => $number,
					'type'     => $widget[0],
					'sidebar'  => $sidebar,
					'settings' => $widget[1],
				);
				$widgets_options[ $widget[0] ][ $number ] = $widget[1];
				$number++;
			}
		}

		foreach ( $widgets_options as $type => $options ) {
			add_filter(
				"pre_option_widget_$type",
				function () use ( $options ) {
					return $options;
				}
			);
		}

		foreach ( $this->starter_content['widgets'] as $sidebar => $sidebar_widgets ) {
			add_filter(
				'is_active_sidebar',
				function ( $is_active_sidebar, $index ) use ( $sidebar ) {
					if ( $index == $sidebar ) {
						return true;
					}
					return $is_active_sidebar;
				},
				10,
				2
			);
		}
		add_filter(
			'sidebars_widgets',
			function () use ( $widgets ) {
				$sidebars_widgets = array();
				foreach ( $widgets as $widget ) {
					list('type' => $type, 'number' => $number) = $widget;
					$sidebars_widgets[ $widget['sidebar'] ][]  = "$type-$number";
				}
				return $sidebars_widgets;
			}
		);

		foreach ( $widgets as $widget ) {
			list('type' => $type, 'number' => $number) = $widget;
			$widget_class                              = $this->get_widget_class_from_type( $type );
			if ( ! $widget_class ) {
				continue;
			}
			wp_register_sidebar_widget(
				"$type-$number",
				$widget_class->id_base,
				array( $widget_class, 'display_callback' ),
				array( 'classname' => "widget_$type" ),
				array( 'number' => $number )
			);
		}
	}

	public function set_starter_content() {
		$starter_content = get_theme_starter_content();

		if ( ! empty( $starter_content['posts'] ) ) {
			foreach ( $starter_content['posts'] as $name => &$data ) {
				$this->mapping['posts'][ $name ] = $this->generate_id( $name );
				$data['ID']                      = $this->mapping['posts'][ $name ];
				$data['post_name']               = $name;
				if ( 'page' === $data['post_type'] ) {
					$data['comment_status'] = 'closed';
				}
			}
		}

		if ( ! empty( $starter_content['attachments'] ) ) {
			foreach ( $starter_content['attachments'] as $name => &$data ) {
				$this->mapping['attachments'][ $name ] = $this->generate_id();
				$data['ID']                            = $this->mapping['attachments'][ $name ];
			}
		}

		if ( ! empty( $starter_content['nav_menus'] ) ) {
			foreach ( $starter_content['nav_menus'] as $name => &$data ) {
				$nav_menu_id                         = $this->generate_id();
				$this->mapping['nav_menus'][ $name ] = $nav_menu_id;
				$data['ID']                          = $this->mapping['nav_menus'][ $name ];
			}
		}

		array_walk_recursive(
			$starter_content,
			function ( &$value ) {
				if ( preg_match( '/^{{(?P<symbol>.+)}}$/', $value, $matches ) ) {
					foreach ( array( 'posts', 'attachments', 'theme_mods' ) as $type ) {
						if ( isset( $this->mapping[ $type ][ $matches['symbol'] ] ) ) {
							$value = $this->mapping[ $type ][ $matches['symbol'] ];
						}
					}
				}
			}
		);

		$this->starter_content = $starter_content;
	}

	public function set_options() {
		if ( empty( $this->starter_content['options'] ) ) {
			return;
		}
		foreach ( $this->starter_content['options'] as $option => $value ) {
			add_filter(
				"pre_option_$option",
				function () use ( $value ) {
					return $value;
				}
			);
		}
	}

	public function filter_page_query( $posts, $query ) {
		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		$page_for_posts = $this->starter_content['options']['page_for_posts'];
		$blog_post_name = $this->get_blog_post_name();

		if ( ! empty( $query->query_vars['page_id'] ) && $query->query_vars['page_id'] != $page_for_posts ) {
			$data = $this->find_data_by_id( $query->query_vars['page_id'], 'posts' );
			if ( $data ) {
				return array( get_post( (object) $data ) );
			}
		}

		if ( ! empty( $query->query_vars['p'] ) ) {
			$data = $this->find_data_by_id( $query->query_vars['p'], 'posts' );
			if ( $data ) {
				return array( get_post( (object) $data ) );
			}
		}

		/* // Theme Preview doesn't currently use name-based lookups.

		if ( ! empty( $query->query['name'] ) && ! empty( $this->starter_content['posts'][ $query->query['name'] ] ) ) {
			return array( get_post( (object) $this->starter_content['posts'][ $query->query['name'] ] ) );
		}

		if ( ! empty( $query->query['pagename'] ) && ! empty( $this->starter_content['posts'][ $query->query['pagename'] ] ) && $blog_post_name !== $query->query['pagename'] ) {
			return array( get_post( (object) $this->starter_content['posts'][ $query->query['pagename'] ] ) );
		}
		*/

		return $posts;
	}

	private function get_blog_post_name() {
		if ( empty( $this->starter_content['options']['page_for_posts'] ) ) {
			return false;
		}

		$blog_page_id = $this->starter_content['options']['page_for_posts'];
		$post_data    = $this->find_data_by_id( $blog_page_id, 'posts' );

		return $post_data['post_name'];
	}

	private function find_data_by_id( $id, $type = 'posts' ) {
		if ( empty( $this->starter_content[ $type ] ) ) {
			return array();
		}

		foreach ( $this->starter_content[ $type ] as $name => $data ) {
			if ( $id === $data['ID'] ) {
				return $data;
			}
		}

		return array();
	}

	private function generate_id( $page_name = '' ) {
		if ( $page_name && $page = get_page_by_path( $page_name ) ) {
			return $page->ID;
		}

		return $this->starter_content_post_id++;
	}

	private function get_widget_class_from_type( $type ) {
		global $wp_widget_factory;
		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( $widget->id_base === $type ) {
				return $widget;
			}
		}
		return false;
	}

}

new Starter_Content();

/**
 * Define a custom WP_Customize_Manager class that claims this request is a customizer preview request.
 *
 * This is needed as many themes (including 2020/2021) limit starter content to customizer preview requests.
 *
 * As PHP cannot handle nested classes, this is defined in a function outside of the above class.
 */
function polyfill_wp_customize_manager() {
	class WP_Customize_Manager {
		// Yes, this is a theme preview.
		function is_preview() {
			return true;
		}

		// Define a constructor, for components that call `parent::__construct()`.
		function __construct() {}

		// Ensure that any calls return false too.
		function __get( $property ) {
			return false;
		}
		function __call( $method, $args ) {
			return false;
		}
	}

	// This needs to be defined in the global namespace.
	class_alias( __NAMESPACE__ . '\WP_Customize_Manager', 'WP_Customize_Manager', false );

	// Some themes assume the customizer is loaded, and attempt to use other customize classes.
	// Polyfill them as needed.
	spl_autoload_register( function( $class ) {
		if (
			// WP_Customize_*
			'WP_Customize_' === substr( $class, 0, 13 ) ||
			// Some customizer classes don't follow that naming.
			in_array(
				$class,
				[
					'WP_Widget_Area_Customize_Control',
				]
			)
		) {
			class_alias( __NAMESPACE__ . '\WP_Customize_Manager', $class, false );	
		}
	} );
}
