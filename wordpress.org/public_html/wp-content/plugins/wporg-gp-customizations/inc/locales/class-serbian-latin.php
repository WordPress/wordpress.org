<?php

namespace WordPressdotorg\GlotPress\Customizations\Locales;

use GP;
use GP_Locales;
use GP_Translation;

class Serbian_Latin {

	const REPLACE_PAIRS = [
		'А' => 'A',
		'Б' => 'B',
		'В' => 'V',
		'Г' => 'G',
		'Д' => 'D',
		'Ђ' => 'Đ',
		'Е' => 'E',
		'Ж' => 'Ž',
		'З' => 'Z',
		'И' => 'I',
		'Ј' => 'J',
		'К' => 'K',
		'Л' => 'L',
		'Љ' => 'Lj',
		'М' => 'M',
		'Н' => 'N',
		'Њ' => 'Nj',
		'О' => 'O',
		'П' => 'P',
		'Р' => 'R',
		'С' => 'S',
		'Т' => 'T',
		'Ћ' => 'Ć',
		'У' => 'U',
		'Ф' => 'F',
		'Х' => 'H',
		'Ц' => 'C',
		'Ч' => 'Č',
		'Џ' => 'Dž',
		'Ш' => 'Š',
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'ђ' => 'đ',
		'е' => 'e',
		'ж' => 'ž',
		'з' => 'z',
		'и' => 'i',
		'ј' => 'j',
		'к' => 'k',
		'л' => 'l',
		'љ' => 'lj',
		'м' => 'm',
		'н' => 'n',
		'њ' => 'nj',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'ћ' => 'ć',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'c',
		'ч' => 'č',
		'џ' => 'dž',
		'ш' => 'š',
	];

	/**
	 * Registers actions.
	 */
	public static function init() {
		add_action( 'gp_translation_created', [ self::class, 'queue_translation_for_transliteration' ], 5 );
		add_action( 'gp_translation_saved', [ self::class, 'queue_translation_for_transliteration' ], 5 );
	}

	/**
	 * Inserts a transliteration of a cyrillic translation into the latin set.
	 *
	 * @param \GP_Translation $translation Created/updated translation.
	 */
	public static function queue_translation_for_transliteration( $translation ) {
		// Only process current translations without warnings.
		if ( 'current' !== $translation->status || ! empty( $translation->warnings ) ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );
		if ( ! $translation_set || 'sr' !== $translation_set->locale || 'default' !== $translation_set->slug ) {
			return;
		}

		$original = GP::$original->get( $translation->original_id );
		if ( ! $original ) {
			return;
		}

		$translation_set_latin = GP::$translation_set->by_project_id_slug_and_locale( $original->project_id, 'latin', 'sr' );
		if ( ! $translation_set_latin ) {
			return;
		}

		$translation_latin                     = new GP_Translation( $translation->fields() );
		$translation_latin->translation_set_id = $translation_set_latin->id;
		$translation_latin->status             = 'current';

		$locale = GP_Locales::by_slug( $translation_set_latin->locale );
		for ( $i = 0; $i < $locale->nplurals; $i++ ) {
			$translation_latin->{"translation_{$i}"} = strtr( $translation->{"translation_{$i}"}, self::REPLACE_PAIRS );
		}

		$translation_latin = GP::$translation->create( $translation_latin );
		if ( ! $translation_latin ) {
			return;
		}

		$translation_latin->set_as_current();
		gp_clean_translation_set_cache( $translation_set_latin->id );
	}
}
