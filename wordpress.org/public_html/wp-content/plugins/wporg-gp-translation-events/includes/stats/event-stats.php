<?php

namespace Wporg\TranslationEvents\Stats;

use Exception;

class Event_Stats {
	/**
	 * Associative array of rows, with the locale as key.
	 *
	 * @var Stats_Row[]
	 */
	private array $rows = array();

	private Stats_Row $totals;

	/**
	 * Add a stats row.
	 *
	 * @throws Exception When incorrect locale is passed.
	 */
	public function add_row( string $locale, Stats_Row $row ) {
		if ( ! $locale ) {
			throw new Exception( 'locale must not be empty' );
		}
		$this->rows[ $locale ] = $row;
	}

	public function set_totals( Stats_Row $totals ) {
		$this->totals = $totals;
	}

	/**
	 * Get an associative array of rows, with the locale as key.
	 *
	 * @return Stats_Row[]
	 */
	public function rows(): array {
		uasort(
			$this->rows,
			function ( $a, $b ) {
				if ( ! $a->language && ! $b->language ) {
					return 0;
				}
				if ( ! $a->language ) {
					return -1;
				}
				if ( ! $b->language ) {
					return 1;
				}

				return strcasecmp( $a->language->english_name, $b->language->english_name );
			}
		);

		return $this->rows;
	}

	public function totals(): Stats_Row {
		return $this->totals;
	}
}
