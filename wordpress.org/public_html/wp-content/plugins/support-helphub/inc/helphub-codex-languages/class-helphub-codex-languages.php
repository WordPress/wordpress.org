<?php
/**
 * Plugin Name: Helphub Codex Languages
 * Plugin URI: http://www.wordpress.org
 * Description: Short code to link codex translated articles from HelpHub
 * Version: 1.0.0
 * Author: Akira Tachibana
 * Author URI: http://www.helphubcommunications.com/
 * Requires at least: 4.0.0
 * Tested up to: 4.0.0
 *
 * Text Domain: helphub
 * Domain Path: /languages/
 *
 * @package HelpHub_Codex_Languages
 * @category Core
 * @author Akira Tachibana
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns the main instance of HelpHub_Codex_Languages to prevent the need to
 * use globals.
 *
 * @since  1.0.0
 * @return object HelpHub_Codex_Languages
 */
function helphub_codex_languages() {
	return HelpHub_Codex_Languages::instance();
} // End HelpHub_Codex_Languages()

add_action( 'plugins_loaded', 'helphub_codex_languages' );

/**
 * Main HelpHub_Codex_Languages Class
 *
 * @class HelpHub_Codex_Languages
 * @version 1.0.0
 * @since   1.0.0
 * @package HelpHub_Codex_Languages
 * @author  Akira Tachibana
 */
final class HelpHub_Codex_Languages {
	/**
	 * HelpHub_Codex_Languages The single instance of HelpHub_Codex_Languages.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $version;

	/**
	 * The plugin directory URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $plugin_url;

	/**
	 * Constructor function.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function __construct() {
		$this->token      = 'helphub';
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->version    = '1.0.0';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_shortcode( 'codex_languages', array( $this, 'codex_languages_func' ) );
	} // End __construct()

	/**
	 * Short code for Codex Language link.
	 *
	 * Note for pt-br, zh-cn and zh-tw: In short codes, hypen causes many troubles
	 * so it have to be removed.
	 *
	 * @example     [languages en="Version 4.6" ja="version 4.6"]
	 * @param       string $atts language indicator. Refer Multilingual_Codex.
	 * @access  public
	 * @since   1.0.0
	 */
	public function codex_languages_func( $atts ) {
		wp_enqueue_style( 'helphub-codex-languages-style', $this->plugin_url . 'assets/css/codex-languages.css', array(), '1.0.0' );
		$str              = '<p class="language-links"><a href="https://codex.wordpress.org/Multilingual_Codex" title="Multilingual Codex" class="mw-redirect">Languages</a>: <strong class="selflink">English</strong>';
		$lang_table       = array(
			array( 'Arabic', 'العربية', 'ar_codex', 'https://codex.wordpress.org/ar:%1s' ),
			array( 'Azerbaijani', 'Azərbaycanca', 'azr_codex', 'https://codex.wordpress.org/azr:%1s' ),
			array( 'Azeri', 'آذری', 'azb_codex', 'https://codex.wordpress.org/azb:%1s' ),
			array( 'Bulgarian', 'Български', 'bg_codex', 'https://codex.wordpress.org/bg:%1s' ),
			array( 'Bengali', 'বাংলা', 'bn_codex', 'https://codex.wordpress.org/bn:%1s' ),
			array( 'Bosnian', 'Bosanski', 'bs_codex', 'https://codex.wordpress.org/bs:%1s' ),
			array( 'Catalan', 'Catalan', 'ca_codex', 'https://codex.wordpress.org/ca:%1s' ),
			array( 'Czech', 'Čeština', 'cs_codex', 'https://codex.wordpress.org/cs:%1s' ),
			array( 'Danish', 'Dansk', 'da_codex', 'https://codex.wordpress.org/da:%1s' ),
			array( 'German', 'Deutsch', 'de_codex', 'https://codex.wordpress.org/de:%1s' ),
			array( 'Greek', 'Greek', 'el_codex', 'http://wpgreece.org/%1s' ),
			array( 'Spanish', 'Español', 'es_codex', 'https://codex.wordpress.org/es:%1s' ),
			array( 'Finnish', 'suomi', 'fi_codex', 'https://codex.wordpress.org/fi:%1s' ),
			array( 'French', 'Français', 'fr_codex', 'https://codex.wordpress.org/fr:%1s' ),
			array( 'Croatian', 'Hrvatski', 'hr_codex', 'https://codex.wordpress.org/hr:%1s' ),
			array( 'Hebrew', 'עברית', 'he_codex', 'https://codex.wordpress.org/he:%1s' ),
			array( 'Hindi', 'हिन्दी', 'hi_codex', 'https://codex.wordpress.org/hi:%1s' ),
			array( 'Hungarian', 'Magyar', 'hu_codex', 'https://codex.wordpress.org/hu%1s' ),
			array( 'Indonesian', 'Bahasa Indonesia', 'id_codex', 'http://id.wordpress.net/codex/%1s' ),
			array( 'Italian', 'Italiano', 'it_codex', 'https://codex.wordpress.org/it:%1s' ),
			array( 'Japanese', '日本語', 'ja_codex', 'http://wpdocs.sourceforge.jp/%1s' ),
			array( 'Georgian', 'ქართული', 'ka_codex', 'https://codex.wordpress.org/ka:%1s' ),
			array( 'Khmer', 'ភាសា​ខ្មែរ', 'km_codex', 'http://khmerwp.com/%1s' ),
			array( 'Korean', '한국어', 'ko_codex', 'http://wordpress.co.kr/codex/%1s' ),
			array( 'Lao', 'ລາວ', 'lo_codex', 'http://www.laowordpress.com/%1s' ),
			array( 'Macedonian', 'Македонски', 'mk_codex', 'https://codex.wordpress.org/mk:%1s' ),
			array( 'Moldavian', 'Română', 'md_codex', 'https://codex.wordpress.org/md:%1s' ),
			array( 'Mongolian', 'Mongolian', 'mn_codex', 'https://codex.wordpress.org/mn:%1s' ),
			array( 'Myanmar', 'myanmar', 'mya_codex', 'http://www.myanmarwp.com/%1s' ),
			array( 'Dutch', 'Nederlands', 'nl_codex', 'https://codex.wordpress.org/nl:%1s' ),
			array( 'Persian', 'فارسی', 'fa_codex', 'http://codex.wp-persian.com/%1s' ),
			array( 'Polish', 'Polski', 'pl_codex', 'https://codex.wordpress.org/pl:%1s' ),
			array( 'Portuguese_Português', 'Português', 'pt_codex', 'https://codex.wordpress.org/pt:%1s' ),
			array( 'Brazilian Portuguese', 'Português do Brasil', 'ptbr_codex', 'https://codex.wordpress.org/pt-br:%1s' ),
			array( 'Romanian', 'Română', 'ro_codex', 'https://codex.wordpress.org/ro:%1s' ),
			array( 'Russian', 'Русский', 'ru_codex', 'https://codex.wordpress.org/ru:%1s' ),
			array( 'Serbian', 'Српски', 'sr_codex', 'https://codex.wordpress.org/sr:%1s' ),
			array( 'Slovak', 'Slovenčina', 'sk_codex', 'https://codex.wordpress.org/sk:%1s' ),
			array( 'Slovenian', 'Slovenščina', 'sl_codex', 'https://codex.wordpress.org/sl:%1s' ),
			array( 'Albanian', 'Shqip', 'sq_codex', 'https://codex.wordpress.org/al:%1s' ),
			array( 'Swedish', 'Svenska', 'sv_codex', 'http://wp-support.se/dokumentation/%1s' ),
			array( 'Tamil', 'Tamil', 'ta_codex', 'http://codex.wordpress.com/ta:%1s' ),
			array( 'Telugu', 'తెలుగు', 'te_codex', 'https://codex.wordpress.org/te:%1s' ),
			array( 'Thai', 'ไทย', 'th_codex', 'http://codex.wordthai.com/%1s' ),
			array( 'Turkish', 'Türkçe', 'tr_codex', 'https://codex.wordpress.org/tr:%1s' ),
			array( 'Ukrainian', 'Українська', 'uk_codex', 'https://codex.wordpress.org/uk:%1s' ),
			array( 'Vietnamese', 'Tiếng Việt', 'vi_codex', 'https://codex.wordpress.org/vi:%1s' ),
			array( 'Chinese', '中文(简体)', 'zhcn_codex', 'https://codex.wordpress.org/zh-cn:%1s' ),
			array( 'Chinese (Taiwan)', '中文(繁體)', 'zhtw_codex', 'https://codex.wordpress.org/zh-tw:%1s' ),
			array( 'Kannada', 'ಕನ್ನಡ', 'kn_codex', 'https://codex.wordpress.org/kn:%1s' ),
		);
		$shortcode_params = array();
		foreach ( $lang_table as $lang ) {
			$shortcode_params[ $lang[2] ] = null;
		}
		$args = shortcode_atts( $shortcode_params, $atts );
		$i    = 0;
		foreach ( $args as $key => $value ) {
			if ( null != $value ) {
				$str .= sprintf( ' &bull; <a class="external text" href="' . $lang_table[ $i ][3] . '">' . $lang_table[ $i ][1] . '</a>', $value );
			}
			$i++;
		}
		$str .= '</p>';
		return $str;
	} // End codex_languages_func()

	/**
	 * Main HelpHub_Codex_Languages Instance
	 *
	 * Ensures only one instance of HelpHub_Codex_Languages is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see HelpHub_Codex_Languages()
	 * @return Main HelpHub_Codex_Languages instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Load the localisation file.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wporg-forums', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	} // End load_plugin_textdomain()

	/**
	 * Cloning is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wporg-forums' ), '1.0.0' );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wporg-forums' ), '1.0.0' );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 *
	 * @access  private
	 * @since   1.0.0
	 */
	private function _log_version_number() {
		// Log the version number.
		update_option( $this->token . '-version', $this->version );
	} // End _log_version_number()
} // End Class
