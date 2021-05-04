<?php
/**
 * Table_Of_Contents_Lite Class
 *
 * @package WordPress
 * @author Carl Alberto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helphub Table of Content Class
 *
 * Functionalities needed to generate the table of contents.
 *
 * @package    WordPress
 * @subpackage HelpHub_Post_Types
 * @category   Plugin
 * @author     Carl Alberto
 * @since      1.0.0
 */
class Table_Of_Contents_Lite {

	/**
	 * The single instance of Table_Of_Contents_Lite.
	 *
	 * @var    object
	 * @access private
	 * @since  1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 *
	 * @access public
	 *
	 * @param    string $file filename of the plugin.
	 * @param    string $version version number of plugin.
	 *
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'table_of_contents_lite';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		add_action( 'init', array( $this, 'plugin_init' ), 99 );

		register_activation_hook( $this->file, array( $this, 'install' ) );

		add_filter( 'the_content', array( $this, 'add_toc' ) );
	} // End __construct ()

	/**
	 * Load plugin initialization
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function plugin_init() {
		$plugin_text_domain = 'table-of-contents-lite';
		load_plugin_textdomain( $plugin_text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	/**
	 * Load style
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'table-of-contents', TABLE_OF_CONTENTS_URL . 'assets/css/style.css', array(), 4 );
	}

	/**
	 * Main Table_Of_Contents_Lite Instance. This ensures only one instance of Table_Of_Contents_Lite is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 *
	 * @param    string $file filename of the plugin.
	 * @param    string $version version number of plugin.
	 *
	 * @see    Table_Of_Contents_Lite()
	 * @return    Main Table_Of_Contents_Lite instance
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html_e( 'Cheatin&#8217; huh?', 'wporg-forums' ) );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html_e( 'Cheatin&#8217; huh?', 'wporg-forums' ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install() {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number() {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	/**
	 * Main function that adds the TOC to the regular content of each post.
	 *
	 * @param    longtext $content contains the post content.
	 *
	 * @return    longtext generatod TOC based from the h tags in the $content plus the $content at the end.
	 */
	public function add_toc( $content ) {

		if ( ! is_singular() || is_embed() ) {
			return $content;
		}

		$toc = '';

		$items = $this->get_tags_in_content( 'h([1-4])', $content ); // returns the h1-h4 tags inside the_content.
		if ( count( $items ) < 2 ) {
			return $content;
		}

		for ( $i = 1; $i <= 4; $i ++ ) {
			$content = $this->add_ids_and_jumpto_links( "h$i", $content );
		}

		if ( $items ) {
			$contents_header = sprintf( 'h%s', $items[0][2] );
			$toc            .= '<div class="table-of-contents">';
			$toc            .= '<h2 class="toc-title">' . __( 'Topics', 'wporg-forums' ) . '</h2>';
			$toc            .= '<ul class="items">';
			$last_item       = false;
			foreach ( $items as $item ) {
				if ( $last_item ) {
					if ( $last_item < $item[2] ) {
						$toc .= '<ul>';
					} elseif ( $last_item > $item[2] ) {
						$toc .= '</ul></li>';
					} else {
						$toc .= '</li>';
					}
				}
				$last_item = $item[2];
				$toc .= sprintf( '<li><a href="#%1s">%2s</a>', sanitize_title( $item[3] ), $item[3] );
			}
			$toc .= '</ul></div>';
		}

		return $toc . $content;
	}

	/**
	 * Filters all header tags in the current content.
	 *
	 * @param    string $tag header tags to be included, default h1-h4.
	 * @param    string $content content to be filtered.
	 *
	 * @return    array $matches all filtered header tags.
	 */
	public function get_tags_in_content( $tag, $content = '' ) {
		if ( empty( $content ) ) {
			$content = get_the_content();
		}
		preg_match_all( "/(<{$tag}>)(.*)(<\/{$tag}>)/", $content, $matches, PREG_SET_ORDER );

		return $matches;
	}

	/**
	 * Appends the filtered header tags on the start fo the $content.
	 *
	 * @param string $tag depending on the tag, it will place the TOC link deeper in the ul - li tag.
	 * @param longtext $content content to be filtered.
	 *
	 * @return array $content    returns the content with the partial TOC on top.
	 */
	public function add_ids_and_jumpto_links( $tag, $content ) {
		$items        = $this->get_tags_in_content( $tag, $content );
		$first        = true;
		$matches      = array();
		$replacements = array();

		foreach ( $items as $item ) {
			$replacement = '';
			$matches[]   = $item[0];
			$id          = sanitize_title( $item[2] );
			if ( ! $first ) {
				$replacement .= '<p class="toc-jump"><a href="#top">' . __( 'Top &uarr;', 'wporg-forums' ) . '</a></p>';
			} else {
				$first = false;
			}
			$a11y_text      = sprintf( '<span class="screen-reader-text">%s</span>', $item[2] );
			$hash           = __( '<span aria-hidden="true">&#35;</span>', 'wporg-forums' );
			$anchor         = sprintf( '<a href="#%1$s" class="anchor">%2$s %3$s</a>', $id, $hash, $a11y_text );
			$replacement   .= sprintf( '<%1$s class="toc-heading" id="%2$s" tabindex="-1">%3$s %4$s</%1$s>', $tag, $id, $item[2], $anchor );
			$replacements[] = $replacement;
		}

		if ( $replacements ) {
			$content = str_replace( $matches, $replacements, $content );
		}

		return $content;
	}

}
