<?php

/**
 * This plugin records the submitted/accepted counts of translations offered by users.
 *
 * Only translations with an attached user_id are counted.
 *
 * @author dd32
 */
class WPorg_GP_User_Stats {

	private $user_stats = array();

	public function __construct() {
		global $wpdb, $gp_table_prefix;

		add_action( 'gp_translation_created', array( $this, 'translation_updated' ) );
		add_action( 'gp_translation_saved', array( $this, 'translation_updated' ) );

		// DB Writes are delayed until shutdown to bulk-update the stats during imports.
		add_action( 'shutdown', array( $this, 'write_stats_to_database' ) );

		$wpdb->user_translations_count = $gp_table_prefix . 'user_translations_count';
	}

	public function translation_updated( $translation ) {
		if ( ! $translation->user_id ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );

		if ( 'waiting' === $translation->status ) {
			// New translation suggested
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 0 );

		} elseif ( 'current' === $translation->status && 'gp_translation_created' === current_filter() ) {
			// New translation suggested & approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 1 );

		} elseif ( 'current' === $translation->status ) {
			// Translation approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 0, 1 );

		}
	}

	private function bump_user_stat( $user_id, $locale, $locale_slug, $suggested = 0, $accepted = 0 ) {
		$key = "$user_id,$locale,$locale_slug";

		if ( isset( $this->user_stats[ $key ] ) ) {
			$this->user_stats[ $key ]->suggested += $suggested;
			$this->user_stats[ $key ]->accepted  += $accepted;
		} else {
			$this->user_stats[ $key ] = (object) array(
				'suggested' => $suggested,
				'accepted'  => $accepted,
			);
		}
	}

	public function write_stats_to_database() {
		global $wpdb;

		$values = array();
		foreach ( $this->user_stats as $key => $stats ) {
			list( $user_id, $locale, $locale_slug ) = explode( ',', $key );

			$values[] = $wpdb->prepare( '(%d, %s, %s, %d, %d)',
				$user_id,
				$locale,
				$locale_slug,
				$stats->suggested,
				$stats->accepted
			);

			// If we're processing a large batch, add them as we go to avoid query lengths & memory limits.
			if ( count( $values ) > 50 ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->user_translations_count} (`user_id`, `locale`, `locale_slug`, `suggested`, `accepted`)
					VALUES " . implode( ', ', $values ) . "
					ON DUPLICATE KEY UPDATE `suggested`=`suggested` + VALUES(`suggested`), `accepted`=`accepted` + VALUES(`accepted`)"
				);
				$values = array();
			}
		}

		if ( $values ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->user_translations_count} (`user_id`, `locale`, `locale_slug`, `suggested`, `accepted`)
				VALUES " . implode( ', ', $values ) . "
				ON DUPLICATE KEY UPDATE `suggested`=`suggested` + VALUES(`suggested`), `accepted`=`accepted` + VALUES(`accepted`)"
			);
		}
	}
}

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
