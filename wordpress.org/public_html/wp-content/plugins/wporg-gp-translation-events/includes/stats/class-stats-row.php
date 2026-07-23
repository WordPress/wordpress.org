<?php
/**
 * Value object for one row of event statistics.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Stats;

use GP_Locale;

/**
 * Translation statistics for one locale (or the totals across all locales).
 */
class Stats_Row {
	/**
	 * Number of translations created.
	 *
	 * @var int
	 */
	public int $created;

	/**
	 * Number of translations reviewed.
	 *
	 * @var int
	 */
	public int $reviewed;

	/**
	 * Number of users who contributed.
	 *
	 * @var int
	 */
	public int $users;

	/**
	 * The locale for this row, or null for the totals row.
	 *
	 * @var GP_Locale|null
	 */
	public ?GP_Locale $language = null;

	/**
	 * Stats_Row constructor.
	 *
	 * @param int            $created  Number of translations created.
	 * @param int            $reviewed Number of translations reviewed.
	 * @param int            $users    Number of users who contributed.
	 * @param GP_Locale|null $language The locale for this row, or null for the totals row.
	 */
	public function __construct( $created, $reviewed, $users, ?GP_Locale $language = null ) {
		$this->created  = $created;
		$this->reviewed = $reviewed;
		$this->users    = $users;
		$this->language = $language;
	}
}
