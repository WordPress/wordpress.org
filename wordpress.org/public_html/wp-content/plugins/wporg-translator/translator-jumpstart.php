<?php
class Translator_Jumpstart {
	// this is a regex that we output, therefore the backslashes are doubled
	const PLACEHOLDER_REGEX = '%([0-9]\\\\*\\$)?';
	const PLACEHOLDER_MAXLENGTH = 200;

	private $glotpress_project_paths = array();

	private $strings_used = array(), $placeholders_used = array();
	private $blacklisted = array( 'Loading&#8230;' => true );
	private static $instance = array();

	public static function init() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'gettext', array( $this, 'translate' ), 10, 4 );
		add_action( 'gettext_with_context', array( $this, 'translate_with_context' ), 10, 5 );
		add_action( 'ngettext', array( $this, 'ntranslate' ), 10, 5 );
		add_action( 'ngettext_with_context', array( $this, 'ntranslate_with_context' ), 10, 6 );

		if ( defined( 'BB_PATH' ) ) {
			$this->enqueue_scripts();
			add_action( 'bb_foot', array( $this, 'load_translator' ), 1000 );
			//add_action( 'bb_init', array( $this, 'enqueue_scripts' ) ) ;
		} else {
			add_action( 'wp_footer', array( $this, 'load_translator' ), 1000 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) ) ;
			add_action( 'admin_footer', array( $this, 'load_translator' ), 1000 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) ) ;
		}

		add_action( 'wp_ajax_inform_translator', array( $this, 'load_ajax_translator' ), 1000 );
		add_action( 'wp_ajax_inform_admin_translator', array( $this, 'load_ajax_admin_translator' ), 1000 );

		$this->set_glotpress_paths();
	}

	public function enqueue_scripts() {
		//TODO: fix URLS
		wp_enqueue_style( 'dashicons' );
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			wp_enqueue_style( 'translator-jumpstart',   'https://wordpress.org/translator/rtl/translator-jumpstart-rtl.css' );
		} else {
			wp_enqueue_style( 'translator-jumpstart', 'https://wordpress.org/translator/translator-jumpstart.css' );
			wp_enqueue_style( 'translator-jumpstart', 'https://wordpress.org/translator/community-translator.css' );
		}
		wp_enqueue_style( 'translator', 'https://wordpress.org/translator/community-translator.css' );

		wp_enqueue_script('jquery');
		wp_enqueue_script( 'translator', 'https://wordpress.org/translator/community-translator.js' );
		wp_enqueue_script( 'translator-jumpstart', 'https://wordpress.org/translator/translator-jumpstart.js', array( 'translator' ) );
	}

	private function set_glotpress_paths() {

		$this->glotpress_project_paths = apply_filters( 'translator_project_paths', $this->glotpress_project_paths );

		/*$this->glotpress_project_slugs[] = 'meta/forums';
		$this->glotpress_project_slugs[] = 'meta/rosetta';
		$this->glotpress_project_slugs[] = 'meta/wordcamp-theme';
		$this->glotpress_project_slugs[] = 'meta/themes';
		$this->glotpress_project_slugs[] = 'meta/plugins';
*/
	}

	/**
	 * This returns true for text that consists just of placeholders or placeholders + one letter,
	 * for example '%sy': time in years abbreviation
	 * as it leads to lots of translatable text which just matches the regex
	 *
	 * @param  string $text the string to check
	 * @return boolean      true if it contains just placeholders
	 */
	private function contains_just_placeholders( $text ) {
		$placeholderless_text = trim( preg_replace( '#' . self::PLACEHOLDER_REGEX . '[sd]#', '', $text ) );
		return strlen( $text ) !== strlen( $placeholderless_text ) && strlen( $placeholderless_text ) <= 1;
	}

	private function contains_placeholder( $text ) {
		return (bool) preg_match( '#' . self::PLACEHOLDER_REGEX . '[sd]#', $text );
	}

	private function already_checked( $key ) {
		return
			isset( $this->placeholders_used[ $key ] ) ||
			isset( $this->strings_used[ $key ] );
	}

	private function convert_placeholders_to_regex( $text, $string_placeholder = null, $numeric_placeholder = null ) {
		if ( is_null( $string_placeholder ) ) {
			$string_placeholder = '(.{0,' . self::PLACEHOLDER_MAXLENGTH . '}?)';
		}
		if ( is_null( $numeric_placeholder ) ) {
			$numeric_placeholder = '([0-9]{0,15}?)';
		}

		$text = html_entity_decode( $text );
		$text = preg_quote( $text, '/' );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 's#', $string_placeholder, $text );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 'd#', $numeric_placeholder, $text );
		$text = str_replace( '%%', '%', $text );
		return $text;
	}

	private function get_hash_key( $original, $context = null ) {
		if ( ! empty( $context ) && $context !== 'default' ) {
			$context .= "\u0004";
		} else {
			$context = '';
		}

		return $context . html_entity_decode( $original );
	}

	private function add_context( $key, $context = null, $new_entry = false ) {
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

	public function ntranslate_with_context( $translation, $single, $plural, $count, $context, $domain ) {
		return $this->translate_with_context( $translation, $singular, array( $singular, $plural ), $domain );
	}

	public function translate( $translation, $original = null, $domain = null ) {
		return $this->translate_with_context( $translation, $original, null, $domain );
	}

	public function translate_with_context( $translation, $original = null, $context = null, $domain = null ) {
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
				$string_placeholder = null;

				if ( $original_as_string === '%1$s on %2$s' && $context == 'Recent Comments Widget' ) {
					// for this original both variables will be HTML Links
					$string_placeholder = '(<a [^>]+>.{0,' . self::PLACEHOLDER_MAXLENGTH . '}?</a>)';
				}

				$this->placeholders_used[ $key ] = array(
					$original,
					$this->convert_placeholders_to_regex( $translation, $string_placeholder ),
				);

			} else {
				$this->strings_used[ $key ] = array(
					$original,
				);
			}

			$this->add_context( $key, $context, true );
		}


		return $translation;
	}

	public function load_ajax_translator( $locale_code = null ) {
		if ( empty( $locale_code ) ) {
			$locale_code = get_locale();
		}

		if ( $locale_code === 'en_US' ) {
			return false;
		}

		echo '<script type="text','/javascript">';
		echo 'var newTranslatorJumpstart = ';
		echo json_encode( array(
			'stringsUsedOnPage' => $this->strings_used,
			'placeholdersUsedOnPage' => $this->placeholders_used
		) );
		echo ';';
		echo '</script>';

	}

	public function load_translator( $locale_code = null ) {
		if ( empty( $locale_code ) ) {
			$locale_code = get_locale();
		}

		if ( $locale_code === 'en_us' ) {
			return false;
		}

		echo '<script type="text','/javascript">';
		echo 'translatorJumpstart = ', json_encode( $this->get_jumpstart_object( $locale_code ) ), ';';
		echo '</script>';

		?><div id="translator-launcher" class="translator">
		<a href="" title="<?php _e( 'Community Translator' ); ?>">
				<span class="dashicons dashicons-translation">
				</span>
			<div class="text disabled">
				<div class="enable">
					Enable Translator
				</div>
				<div class="disable">
					Disable Translator
				</div>
			</div>
		</a>
		</div><?php
	}

	private function get_jumpstart_object( $locale_code ) {

		if ( ! class_exists( 'GP_Locales' ) ) {
			require_once __DIR__ . '/../../translate/glotpress/locales/locales.php';
		}

		$plural_forms = 'nplurals=2; plural=(n != 1)';

		$gp_locale = GP_Locales::by_slug( $locale_code );
		if( ! $gp_locale ) {
			$gp_locale = GP_Locales::by_field( 'wp_locale', $locale_code );
		}

		if ( property_exists( $gp_locale, 'nplurals' ) || property_exists( $gp_locale, 'plural_expression' ) ) {
			$plural_forms = 'nplurals=' . $gp_locale->nplurals . '; plural='. $gp_locale->plural_expression;
		}

		return array(
			'stringsUsedOnPage' => $this->strings_used,
			'placeholdersUsedOnPage' => $this->placeholders_used,
			'localeCode' => isset( $gp_locale->slug ) ? $gp_locale->slug : $locale_code,
			'languageName' => html_entity_decode( $gp_locale->native_name ),
			'pluralForms' => $plural_forms,
			'glotPress' => array(
				'url' => 'https://translate.wordpress.org',
				'project' => implode( ',', $this->glotpress_project_paths ),
			)
		);
	}
}
