<?php
/**
 * Helper showing translations from other locales
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class Helper_Other_Locales extends GP_Translation_Helper {

	/**
	 * Helper priority.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	public $priority = 3;

	/**
	 * Helper title.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	public $title = 'Other locales';

	/**
	 * Indicates whether the helper loads asynchronous content or not.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	public $has_async_content = true;

	/**
	 * Indicates the related locales for each locale, so we will put them in the top list
	 * of "Other locales".
	 *
	 * This array should be sync with
	 * https://github.com/WordPress/wordpress.org/blob/trunk/wordpress.org/public_html/wp-content/plugins/wporg-gp-translation-suggestions/inc/routes/class-other-languages.php
	 *
	 * @since 0.0.1
	 * @var array
	 */
	public array $related_locales = array(
		'ca'  => array( 'ca-val', 'bal', 'es', 'oci', 'an', 'gl', 'fr', 'it', 'pt', 'ro', 'la' ),
		'es'  => array( 'gl', 'ca', 'pt', 'pt-ao', 'pt-br', 'it', 'fr', 'ro' ),
		'gl'  => array( 'es', 'pt', 'pt-ao', 'pt-br', 'ca', 'it', 'fr', 'ro' ),
		'it'  => array( 'ca', 'de', 'es', 'fr', 'pt', 'ro' ),
		'ne'  => array( 'hi', 'mr', 'as' ),
		'oci' => array( 'ca', 'fr', 'it', 'es', 'gl' ),
		'ug'  => array( 'tr', 'uz', 'az', 'zh-cn', 'zh-tw' ),
	);

	/**
	 * Activates the helper.
	 *
	 * @since 0.0.2
	 *
	 * @return bool
	 */
	public function activate(): bool {
		if ( ! $this->data['project'] ) {
			return false;
		}

		if ( ! isset( $this->data['translation_set_slug'] ) || ! isset( $this->data['locale_slug'] ) ) {
			$this->title = 'Translations';
		}

		return true;
	}

	/**
	 * Gets content that is returned asynchronously.
	 *
	 * @since 0.0.1
	 *
	 * @return array|void
	 */
	public function get_async_content() {
		if ( ! $this->data['project'] ) {
			return;
		}
		$translation_set = null;
		if ( isset( $this->data['translation_set_slug'] ) && isset( $this->data['locale_slug'] ) ) {
			$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $this->data['project_id'], $this->data['translation_set_slug'], $this->data['locale_slug'] );
		}

		$translations                           = GP::$translation->find_many_no_map(
			array(
				'status'      => 'current',
				'original_id' => $this->data['original_id'],
			)
		);
		$translations_by_locale                 = array();
		$translations_by_locale_with_preference = array();
		foreach ( $translations as $translation ) {
			$_set = GP::$translation_set->get( $translation->translation_set_id );
			if ( ! $_set || ( $translation_set && intval( $translation->translation_set_id ) === intval( $translation_set->id ) ) ) {
				continue;
			}
			$translations_by_locale[ $_set->locale ] = $translation;
		}

		ksort( $translations_by_locale );

		// Put the variants in the top list.
		foreach ( $translations_by_locale as $key => $translation ) {
			// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
			if ( explode( '-', $key )[0] === explode( '-', $this->data['locale_slug'] )[0] ) {
				$translations_by_locale_with_preference[ $key ] = $translation;
				unset( $translations_by_locale[ $key ] );
			}
		}

		// Put the related locales in the top list, after the variants.
		if ( ! empty( $this->related_locales[ $this->data['locale_slug'] ] ) ) {
			foreach ( $this->related_locales[ $this->data['locale_slug'] ] as $locale ) {
				if ( array_key_exists( $locale, $translations_by_locale ) ) {
					$translations_by_locale_with_preference[ $locale ] = $translations_by_locale[ $locale ];
					unset( $translations_by_locale[ $locale ] );
				}
			}
		}

		return array_merge( $translations_by_locale_with_preference, $translations_by_locale );
	}

	/**
	 * Gets the items that will be rendered by the helper.
	 *
	 * @since 0.0.1
	 *
	 * @param array $translations   Translation history.
	 *
	 * @return string
	 */
	public function async_output_callback( array $translations ): string {
		$output  = '<ul class="other-locales">';
		$project = null;
		foreach ( $translations as $locale => $translation ) {
			$translation_set = GP::$translation_set->get( $translation->translation_set_id );
			if ( is_null( $project ) ) {
				$project = GP::$project->get( $translation_set->project_id );
			}

			$translation_permalink = GP_Route_Translation_Helpers::get_translation_permalink(
				$project,
				$translation_set->locale,
				$translation_set->slug,
				$translation->original_id,
				$translation->id
			);

			if ( ( null === $translation->translation_1 ) && ( null === $translation->translation_2 ) &&
				 ( null === $translation->translation_3 ) && ( null === $translation->translation_4 ) &&
				 ( null === $translation->translation_5 ) ) {
				$output .= sprintf( '<li><span class="locale unique">%s</span>%s</li>', $locale, gp_link_get( $translation_permalink, esc_translation( $translation->translation_0 ) ) );
			} else {
				$output .= sprintf( '<li><span class="locale">%s</span>', $locale );
				$output .= '<ul>';
				for ( $i = 0; $i <= 5; $i ++ ) {
					if ( null !== $translation->{'translation_' . $i} ) {
						$output .= sprintf( '<li>%s</li>', gp_link_get( $translation_permalink, esc_translation( $translation->{'translation_' . $i} ) ) );
					}
				}
				$output .= '</ul>';
				$output .= '</li>';
			}
		}
			$output .= '</ul>';
			return $output;
	}

	/**
	 * Gets the text to display when no other locales have translated this string yet.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function empty_content(): string {
		return esc_html__( 'No other locales have translated this string yet.' );
	}

	/**
	 * Gets the CSS for this helper.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_css(): string {
		return <<<CSS
	.other-locales {
		list-style: none;
	}
	ul.other-locales {
		padding-left: 0;
	}
	.other-locales li {
		clear:both;
	}
	ul.other-locales li {
		display: flex;
	}
	ul.other-locales li ul li {
		display: list-item;
		list-style: disc;
	}
	span.locale.unique {
		margin-right: 26px;
	}
	.other-locales .locale {
		display: inline-block;
		padding: 1px 6px 0 0;
		margin: 1px 6px 1px 0;
		background: #00DA12;
		width: 5em;
		text-align: right;
		float: left;
		color: #fff;
	}
CSS;
	}
}
