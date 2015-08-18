<?php

/**
 * This plugin records the submitted/accepted counts of translations offered by users.
 *
 * Only translations with an attached user_id are counted.
 *
 * @author dd32
 */
class GP_WPorg_User_Stats extends GP_Plugin {
	var $id = 'wporg-user-stats';

	function __construct() {
		global $gpdb;

		parent::__construct();
		$this->add_action( 'translation_created' );
		$this->add_action( 'translation_saved' );

		$gpdb->user_translations_count = $gpdb->prefix . 'user_translations_count';
	}

	function translation_created( $translation ) {
		return $this->translation_saved( $translation, 'created' );
	}

	function translation_saved( $translation, $action = 'saved' ) {
		if ( ! $translation->user_id ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );

		if ( 'waiting' === $translation->status ) {
			// New translation suggested
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 0 );

		} elseif ( 'current' === $translation->status && 'created' === $action ) {
			// New translation suggested & approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 1 );

		} elseif ( 'current' === $translation->status ) {
			// Translation approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 0, 1 );

		}

	}

	function bump_user_stat( $user_id, $locale, $locale_slug, $suggested = 0, $accepted = 0 ) {
		global $gpdb;
		$gpdb->query( $gpdb->prepare(
			"INSERT INTO {$gpdb->user_translations_count} (`user_id`, `locale`, `locale_slug`, `suggested`, `accepted`) VALUES (%d, %s, %s, %d, %d)
			ON DUPLICATE KEY UPDATE `suggested`=`suggested` + VALUES(`suggested`), `accepted`=`accepted` + VALUES(`accepted`)",
			$user_id, $locale, $locale_slug, $suggested, $accepted
		) );
	}

}
GP::$plugins->wporg_user_stats = new GP_WPorg_User_Stats;

/*
Table:

Note: WordPress uses BIGINT(20) for user_id; GlotPress uses int(10)

CREATE TABLE `gp_user_translations_count` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `locale` varchar(255) NOT NULL DEFAULT '',
  `locale_slug` varchar(255) NOT NULL DEFAULT '',
  `suggested` int(10) unsigned NOT NULL DEFAULT '0',
  `accepted` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`locale`,`locale_slug`),
  KEY `locale` (`locale`,`locale_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

*/
