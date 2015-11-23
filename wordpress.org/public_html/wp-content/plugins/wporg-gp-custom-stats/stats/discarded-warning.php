<?php

/**
 * This plugin logs discared warnings into a database table.
 *
 * @author Dominik Schilling (ocean90)
 */
class WPorg_GP_Discarded_Warning_Stats {

	/**
	 * Holds the table name for logging warnings.
	 *
	 * @var string
	 */
	private $table_name = 'translate_dotorg_warnings';

	public function __construct() {
		add_action( 'warning_discarded', array( $this, 'log_discarded_warning' ), 10, 5 );
	}

	/**
	 * Logs discared warnings into a table.
	 *
	 * @param  int    $project_id      ID of the project.
	 * @param  int    $translation_set ID if the translation set.
	 * @param  int    $translation     ID of the translation.
	 * @param  string $warning         Key of the warning. (length, tags, placeholders, both_begin_end_on_newlines)
	 * @param  int    $user            ID of the user.
	 */
	public function log_discarded_warning( $project_id, $translation_set, $translation, $warning, $user ) {
		global $wpdb;

		$warning = array(
			'project_id'      => $project_id,
			'translation_set' => $translation_set,
			'translation'     => $translation,
			'warning'         => $warning,
			'user'            => $user,
			'status'          => 'needs-review'
		);
		$format = array( '%d', '%d', '%d', '%s', '%d', '%s' );
		$wpdb->insert(
			$this->table_name,
			$warning,
			$format
		);
	}
}

/*
Required Table:

CREATE TABLE `gp_dotorg_warnings` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `translation_set` bigint(20) unsigned NOT NULL DEFAULT '0',
  `translation` bigint(20) unsigned NOT NULL DEFAULT '0',
  `warning` varchar(20) NOT NULL DEFAULT '',
  `user` bigint(20) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

*/
