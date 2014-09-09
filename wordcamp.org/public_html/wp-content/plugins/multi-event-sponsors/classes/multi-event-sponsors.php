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

	/**
	 * Retrieve all of the Multi-Event Sponsors for the given WordCamp.
	 *
	 * @param int $wordcamp_id
	 * @param string $grouped_by
	 *     'ungrouped' will return a one-dimensional array;
	 *     'sponsor_level' will return an associative array with sponsors grouped by their level and indexed by level ID
	 * @return array
	 */
	public function get_wordcamp_me_sponsors( $wordcamp_id, $grouped_by = 'ungrouped' ) {
		$wordcamp_sponsors = array();

		if ( ! empty( $_POST[ wcpt_key_to_str( 'Multi-Event Sponsor Region', 'wcpt_' ) ] ) ) {
			$wordcamp_region = absint( $_POST[ wcpt_key_to_str( 'Multi-Event Sponsor Region', 'wcpt_' ) ] );
		} else {
			$wordcamp_region = get_post_meta( $wordcamp_id, 'Multi-Event Sponsor Region', true );
		}

		$all_me_sponsors = get_posts( array(
			'post_type'   => MES_Sponsor::POST_TYPE_SLUG,
			'numberposts' => -1
		) );

		foreach ( $all_me_sponsors as $sponsor ) {
			$regional_sponsorships = get_post_meta( $sponsor->ID, 'mes_regional_sponsorships', true );

			if ( ! empty( $regional_sponsorships[ $wordcamp_region ] ) && is_numeric( $regional_sponsorships[ $wordcamp_region ] ) ) {
				if ( 'sponsor_level' == $grouped_by ) {
					$sponsorship_level = get_post( $regional_sponsorships[ $wordcamp_region ] );
					$wordcamp_sponsors[ $sponsorship_level->ID ][] = $sponsor;
				} else {
					$wordcamp_sponsors[] = $sponsor;
				}
			}
		}

		return $wordcamp_sponsors;
	}

	/**
	 * Retrieve all of the e-mail addresses for the given sponsors.
	 *
	 * @param array $sponsors
	 * @return array
	 */
	public function get_sponsor_emails( $sponsors ) {
		$addresses = array();

		foreach ( $sponsors as $sponsor ) {
			$address = get_post_meta( $sponsor->ID, 'mes_email_address', true );

			if ( $address ) {
				$addresses[] = $address;
			}
		}

		return $addresses;
	}

	/**
	 * Retrieve the names of the given sponsors in a sentence format.
	 *
	 * @param array $sponsors
	 * @return string
	 */
	public function get_sponsor_names( $sponsors ) {
		$names = wp_list_pluck( $sponsors, 'post_title' );
		$count = count( $names );

		if ( 0 === $count ) {
			$names = '';
		} else if ( 1 === $count ) {
			$names = $names[0];
		} else {
			$names = implode( ', ', array_slice( $names, 0, $count - 1 ) ) . ' and ' . $names[ $count - 1 ];
		}

		return $names;
	}


	/**
	 * Get the excerpts for the given sponsors in HTML paragraphs.
	 *
	 * @param array $sponsors
	 * @return string
	 */
	public function get_sponsor_excerpts( $sponsors ) {
		$excerpts = wp_list_pluck( $sponsors, 'post_excerpt' );

		foreach ( $excerpts as & $excerpt ) {
			$excerpt = '<p>' . $excerpt . '</p>';
		}

		return implode( ' ', $excerpts );
	}
} // end Multi_Event_Sponsors
