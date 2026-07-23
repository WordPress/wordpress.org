<?php

namespace Wporg\TranslationEvents\Stats;

use GP_Locale;

class Stats_Row {
	public int $created;
	public int $reviewed;
	public int $users;
	public ?GP_Locale $language = null;

	public function __construct( $created, $reviewed, $users, ?GP_Locale $language = null ) {
		$this->created  = $created;
		$this->reviewed = $reviewed;
		$this->users    = $users;
		$this->language = $language;
	}
}
