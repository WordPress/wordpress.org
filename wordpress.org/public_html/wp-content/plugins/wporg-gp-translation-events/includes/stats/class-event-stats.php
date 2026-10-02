<?php
/**
 * Value object for the translation statistics of an event.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Stats;

use Exception;

/**
 * Translation statistics for an event, one row per locale plus totals.
 */
class Event_Stats {
	/**
	 * Associative array of rows, with the locale as key.
	 *
	 * @var Stats_Row[]
	 */
	private array $rows = array();

	/**
	 * Totals across all locales.
	 *
	 * @var Stats_Row
	 */
	private Stats_Row $totals;

	/**
	 * Add a stats row for a locale.
	 *
	 * @param string    $locale The locale of the row.
	 * @param Stats_Row $row    The row to add.
	 *
	 * @throws Exception When incorrect locale is passed.
	 */
	public function add_row( string $locale, Stats_Row $row ) {
		if ( ! $locale ) {
			throw new Exception( 'locale must not be empty' );
		}
		$this->rows[ $locale ] = $row;
	}

	/**
	 * Set the totals across all locales.
	 *
	 * @param Stats_Row $totals The totals row.
	 */
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

	/**
	 * Get the totals across all locales.
	 *
	 * @return Stats_Row The totals row.
	 */
	public function totals(): Stats_Row {
		return $this->totals;
	}
}
