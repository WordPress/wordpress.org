<?php

/*
 * Main controller to handle general functionality
 */

class Multi_Event_Sponsors {
	const VERSION = '0.1';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'multi-event-sponsors', array( $this, 'shortcode_multi_event_sponsors' ) );
	}

	/**
	 * Prepares the site to use the plugin.
	 *
	 * This plugin is only intended to run in single-site mode on central.wordcamp.org.
	 *
	 * @param bool $network_wide
	 */
	public function activate( $network_wide ) {
		/** @var $mes_sponsor           MES_Sponsor */
		/** @var $mes_sponsorship_level MES_Sponsorship_Level */

		global $mes_sponsor, $mes_sponsorship_level;

		$mes_sponsor->create_post_type();
		$mes_sponsorship_level->create_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Render the multi_event_sponsors shortcode output
	 *
	 * @param $parameters
	 * @return string
	 */
	public function shortcode_multi_event_sponsors( $parameters ) {
		$sponsors           = $this->reindex_array_by_object_id( get_posts( array( 'post_type' => MES_Sponsor::POST_TYPE_SLUG, 'numberposts' => -1 ) ) );
		$regions            = $this->reindex_array_by_object_id( get_terms( MES_Sponsor::REGIONS_SLUG, array( 'hide_empty' => false ) ) );
		$sponsorship_levels = $this->reindex_array_by_object_id( get_posts( array( 'post_type' => MES_Sponsorship_Level::POST_TYPE_SLUG, 'numberposts' => -1 ) ) );
		$grouped_sponsors   = $this->group_sponsors_by_region_and_level( $sponsors );

		ob_start();
		require_once( dirname( __DIR__ ) . '/views/shortcode-multi-event-sponsors.php' );
		return ob_get_clean();
	}

	/**
	 * Re-indexes an array of posts or terms by their id.
	 *
	 * This makes for efficient direct access when the ID is known.
	 *
	 * @param $array
	 * @return mixed
	 */
	protected function reindex_array_by_object_id( $old_array ) {
		$new_array = array();

		foreach ( $old_array as $item ) {
			if ( ! empty ( $item->ID ) ) {
				$new_array[ $item->ID ] = $item;
			} elseif ( ! empty( $item->term_id ) ) {
				$new_array[ $item->term_id ] = $item;
			}
		}

		return $new_array;
	}

	/**
	 * Create a multidimensional array that groups sponsors by region and sponsorship level.
	 *
	 * US East
	 *   WordCamp Pillar
	 *     BlueHost
	 *     Wired Tree
	 *   WordCamp Champion
	 *     Dreamhost
	 * US West
	 *   WordCamp Accomplice
	 *     Disqus
	 * etc
	 *
	 * @param array $sponsors
	 * @param array $regions
	 * @return array
	 */
	protected function group_sponsors_by_region_and_level( $sponsors ) {
		$grouped_sponsors = array();

		// Build the grouping
		foreach ( $sponsors as $sponsor ) {
			$regional_sponsorships = get_post_meta( $sponsor->ID, 'mes_regional_sponsorships', true );

			foreach ( $regional_sponsorships as $region_id => $level_id ) {
				if ( 'null' != $level_id ) {
					$grouped_sponsors[ $region_id ][ $level_id ][] = $sponsor->ID;
				}
			}
		}

		// Sort the grouping
		uksort( $grouped_sponsors, array( $this, 'uksort_regions' ) );

		foreach ( $grouped_sponsors as &$region ) {
			uksort( $region, array( $this, 'uksort_sponsorship_levels' ) );
		}

		return $grouped_sponsors;
	}

	/**
	 * Sort regions by their name
	 *
	 * This is a callback for uksort().
	 *
	 * @param int $region_a_id
	 * @param int $region_b_id
	 * @return int
	 */
	protected function uksort_regions( $region_a_id, $region_b_id ) {
		$region_a = get_term( $region_a_id, MES_Sponsor::REGIONS_SLUG );
		$region_b = get_term( $region_b_id, MES_Sponsor::REGIONS_SLUG );

		if ( $region_a->name == $region_b->name ) {
			return 0;
		} else {
			return ( $region_a->name < $region_b->name ) ? -1 : 1;
		}
	}

	/**
	 * Sort sponsorship levels by their contribution amount.
	 *
	 * This is a callback for uksort().
	 *
	 * @param int $level_a_id
	 * @param int $level_b_id
	 * @return int
	 */
	protected function uksort_sponsorship_levels( $level_a_id, $level_b_id ) {
		$level_a_contribution = (float) get_post_meta( $level_a_id, 'mes_contribution_per_attendee', true );
		$level_b_contribution = (float) get_post_meta( $level_b_id, 'mes_contribution_per_attendee', true );

		if ( $level_a_contribution == $level_b_contribution ) {
			return 0;
		} else {
			return ( $level_a_contribution > $level_b_contribution ) ? -1 : 1;
		}
	}
} // end Multi_Event_Sponsors
