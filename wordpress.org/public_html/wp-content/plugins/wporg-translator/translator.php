<?php
namespace WordPressdotorg\Translator;
use GP_Locales;

/**
 * Plugin Name: WordPress.org Translator - Community Translator
 */

class Plugin {
	// this is a regex that we output, therefore the backslashes are doubled
	const PLACEHOLDER_REGEX = '%([0-9]\\\\*\\$)?';
	const PLACEHOLDER_MAXLENGTH = 200;

	const COOKIE = 'translator';

	// The GlotPress projects & WordPress textdomains we're interested in.
	protected $glotpress_projects = [];
	protected $textdomains        = [];

	// The strings which we see during page execution.
	protected $strings_used      = [];
	protected $placeholders_used = [];
	protected $blacklisted       = [];

	public static function instance() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new self;
		}

		return $instance;
	}

	public function __construct() {
		// Only logged in users need the translation tools, and we're not supporting wp-admin for now.
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}

		// This isn't for English (US)
		if ( 'en_US' === get_locale() ) {
			return;
		}

		$this->handle_toggle();

		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 1000 );

		if ( ! $this->enabled() ) {
			return;
		}

		include __DIR__ . '/wporg-projects.php';

		$this->glotpress_projects = apply_filters( 'translator_projects', $this->glotpress_projects );
		$this->textdomains        = apply_filters( 'translator_textdomains', $this->textdomains );

		add_action( 'wp_footer', array( $this, 'load_translator' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) ) ;

		// Watch for translations.
		add_action( 'gettext', array( $this, 'translate' ), 10, 4 );
		add_action( 'gettext_with_context', array( $this, 'translate_with_context' ), 10, 5 );
		add_action( 'ngettext', array( $this, 'ntranslate' ), 10, 5 );
		add_action( 'ngettext_with_context', array( $this, 'ntranslate_with_context' ), 10, 6 );
	}

	/**
	 * Simple getter to determine if the tool is enabled for the current request.
	 */
	public function enabled() {
		return ! empty( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Add a Admin bar entry to enable/disable the Translator tool.
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		$args = [
			'id'    => 'toggle_translator',
			'title' => '<span class="ab-icon dashicons-translation"></span> ' . __( 'Translate', 'wporg' ),
			'href'  => add_query_arg( 'toggle-translator', (int)( ! $this->enabled() ) ),
			'meta'  => [
				'class' => 'toggle-translator',
				'title' => ( $this->enabled() ? __( 'Disable Translator', 'wporg' ) : __( 'Enable Translator', 'wporg' ) )
			]
		];
		$wp_admin_bar->add_node( $args );

		// Add a descriptive sub-child menu.
		$args['title'] = $args['meta']['title'];
		$args['parent'] = $args['id'];
		$args['id'] .= '-child';
		$wp_admin_bar->add_node( $args );
	}

	/**
	 * Handle the Admin Bar toggle.
	 */
	protected function handle_toggle() {
		if ( ! isset( $_REQUEST['toggle-translator'] ) ) {
			 return;
		}

		$enable = (bool)( (int)$_REQUEST['toggle-translator'] );

		if ( $enable ) {
			setcookie( self::COOKIE, 1, [
				'expires' => time() + 6 * HOUR_IN_SECONDS,
				'path' => '/',
				'domain' => '.wordpress.org',
				'secure' => true,
			] );
			$_COOKIE[ self::COOKIE ] = 1;
		} else {
			setcookie( self::COOKIE, 0, [
				'expires' => time() - DAY_IN_SECONDS,
				'path' => '/',
				'domain' => '.wordpress.org',
				'secure' => true,
			] );
			unset( $_COOKIE[ self::COOKIE ] );
		}

		wp_safe_redirect( remove_query_arg( 'toggle-translator' ) );
		exit;
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'translator', plugins_url( 'community-translator/community-translator.css', __FILE__ ), [ 'dashicons' ], 1 );
		wp_enqueue_script( 'translator', plugins_url( 'community-translator/community-translator.js', __FILE__ ), [ 'jquery' ], 2 );
	}

	/**
	 * Watch a WordPress textdomain for translation usage, and load it into the Translator.
	 *
	 * @param string $textdomain The Textdomain to watch for.
	 */
	public function watch_textdomain( $textdomain ) {
		if ( ! in_array( $textdomain, $this->textdomains ) ) {
			$this->textdomains[] = $textdomain;
		}
	}

	/**
	 * Register a GlotPress project for fetching strings from.
	 *
	 * @param string $project The GlotPress project.
	 * @param bool   $add_at_front The most specific project should be listed first, set to bump it up the list.
	 */
	public function register_glotpress_project( $project, $add_at_front = false ) {
		if ( ! in_array( $project, $this->glotpress_projects ) ) {
			if ( $add_at_front ) {
				array_unshift( $this->glotpress_projects, $project );
			} else {
				$this->glotpress_projects[] = $project;
			}
		}
	}

	/**
	 * This returns true for text that consists just of placeholders or placeholders + one letter,
	 * for example '%sy': time in years abbreviation
	 * as it leads to lots of translatable text which just matches the regex
	 *
	 * @param  string $text the string to check
	 * @return boolean      true if it contains just placeholders
	 */
	protected function contains_just_placeholders( $text ) {
		$placeholderless_text = trim( preg_replace( '#' . self::PLACEHOLDER_REGEX . '[sd]#', '', $text ) );
		return strlen( $text ) !== strlen( $placeholderless_text ) && strlen( $placeholderless_text ) <= 1;
	}

	protected function contains_placeholder( $text ) {
		return (bool) preg_match( '#' . self::PLACEHOLDER_REGEX . '[sd]#', $text );
	}

	protected function already_checked( $key ) {
		return
			isset( $this->placeholders_used[ $key ] ) ||
			isset( $this->strings_used[ $key ] );
	}

	protected function convert_placeholders_to_regex( $text ) {
		$string_placeholder  = '(.{0,' . self::PLACEHOLDER_MAXLENGTH . '}?)';
		$numeric_placeholder = '([0-9]{0,15}?)';

		$text = html_entity_decode( $text );
		$text = preg_quote( $text, '/' );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 's#', $string_placeholder, $text );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 'd#', $numeric_placeholder, $text );
		$text = str_replace( '%%', '%', $text );
		return $text;
	}

	protected function convert_links_to_regex( $text ) {
		// Standardise links.
		$text = preg_replace( '#<a [^>]+>#', '<a ([^>]{0,' . self::PLACEHOLDER_MAXLENGTH . '}?)>', $text );

		return $text;
	}

	protected function get_hash_key( $original, $context = null ) {
		if ( ! empty( $context ) && $context !== 'default' ) {
			$context .= "\u0004";
		} else {
			$context = '';
		}

		return $context . html_entity_decode( $original );
	}

	protected function add_context( $key, $context = null, $new_entry = false ) {
		if ( ! $context ) {
			return;
		}

		if ( isset( $this->strings_used[ $key ] ) ) {
			if ( ! isset( $this->strings_used[ $key ][ 1 ] ) ) {
				$this->strings_used[ $key ][ 1 ] = array();

				if ( ! $new_entry ) {
					// the first entry had an empty context, so add it now
					$this->strings_used[ $key ][ 1 ][] = '';
				}
			}

			if ( ! in_array( $context, $this->strings_used[ $key ][ 1 ] ) ) {
				$this->strings_used[ $key ][ 1 ][] = $context;
			}

		} elseif ( isset( $this->placeholders_used[ $key ] ) ) {
			if ( ! isset( $this->placeholders_used[ $key ][ 2 ] ) ) {
				$this->placeholders_used[ $key ][ 2 ] = array();

				if ( ! $new_entry ) {
					// the first entry had an empty context, so add it now
					$this->placeholders_used[ $key ][ 2 ][] = '';
				}
			}

			if ( ! in_array( $context, $this->placeholders_used[ $key ][ 2 ] ) ) {
				$this->placeholders_used[ $key ][ 2 ][] = $context;
			}
		}
	}

	public function ntranslate( $translation, $singular, $plural, $count, $domain ) {
		return $this->translate_with_context( $translation, array( $singular, $plural ), null, $domain );
	}

	public function ntranslate_with_context( $translation, $singular, $plural, $count, $context, $domain ) {
		return $this->translate_with_context( $translation, $singular, array( $singular, $plural ), $domain );
	}

	public function translate( $translation, $original = null, $domain = null ) {
		return $this->translate_with_context( $translation, $original, null, $domain );
	}

	public function translate_with_context( $translation, $original = null, $context = null, $domain = null ) {
		if ( ! in_array( $domain, $this->textdomains ) ) {
			return $translation;
		}

		if ( ! $original ) {
			$original = $translation;
		}
		$original_as_string = $original;
		if ( is_array( $original_as_string ) ) {
			$original_as_string = implode( ' ', $original_as_string );
		}

		if ( isset( $this->blacklisted[ $original_as_string ] ) )  {
			return $translation;
		}

		if ( $this->contains_just_placeholders( $original_as_string ) ) {
			$this->blacklisted[ $original_as_string ] = true;
			return $translation;
		}
		$key = $this->get_hash_key( $translation );

		if ( $this->already_checked( $key ) ) {

			$this->add_context( $key, $context );

		} else {

			if ( $this->contains_placeholder( $translation ) ) {
				$this->placeholders_used[ $key ] = array(
					$original,
					$this->convert_links_to_regex(
						$this->convert_placeholders_to_regex( $translation )
					),
				);
			} elseif ( false !== stripos( $translation, '<a ' ) ) {
				$this->placeholders_used[ $key ] = array(
					$original,
					$this->convert_links_to_regex( $translation ),
				);

				// The translation might be run through content filters, texturize this string and add that too.
				$original_texturize = wptexturize( $original );
				if ( $original_texturize != $original ) {
					$this->placeholders_used[ html_entity_decode( $original_texturize ) ] = array(
						$original,
						$this->convert_links_to_regex( $translation ),
					);
				}
			} else {
				// The translation might be run through content filters, texturize this string and add that too.
				$original_texturize = wptexturize( $original );
				if ( $original_texturize != $original ) {
					$this->strings_used[ html_entity_decode( $original_texturize ) ] = array(
						$original,
					);
				}

				$this->strings_used[ $key ] = array(
					$original,
				);
			}

			$this->add_context( $key, $context, true );
		}


		return $translation;
	}

	public function load_translator() {
		echo '<script type="text/javascript">';
		echo 'var translatorJumpstart = ', wp_json_encode( $this->get_jumpstart_object( get_locale() ) ), ';';
		echo 'communityTranslator.load();';
		echo '</script>';
	}

	protected function get_jumpstart_object( $locale_code ) {
		if ( ! class_exists( 'GP_Locales' ) ) {
			require_once GLOTPRESS_LOCALES_PATH;
		}

		$gp_locale = GP_Locales::by_field( 'wp_locale', $locale_code );
		if ( ! $gp_locale ) {
			$gp_locale = GP_Locales::by_slug( $locale_code );
		}
		if ( ! $gp_locale ) {
			return false;
		}

		$plural_forms = 'nplurals=2; plural=(n != 1)';
		if ( $gp_locale->plural_expression ) {
			$plural_forms = 'nplurals=' . $gp_locale->nplurals . '; plural='. $gp_locale->plural_expression;
		}

		return array(
			'stringsUsedOnPage'      => $this->strings_used,
			'placeholdersUsedOnPage' => $this->placeholders_used,
			'localeCode'             => $gp_locale->slug ?: $locale_code,
			'languageName'           => html_entity_decode( $gp_locale->native_name ),
			'pluralForms'            => $plural_forms,
			'glotPress'              => array(
				'url'     => 'https://translate.wordpress.org',
				'project' => implode( ',', $this->glotpress_projects ),
			)
		);
	}
}

function get_translator() {
	return \WordPressdotorg\Translator\Plugin::instance();
}

// Load the plugin early.
add_action( 'init', [ __NAMESPACE__ . '\\Plugin', 'instance' ], 1 );
