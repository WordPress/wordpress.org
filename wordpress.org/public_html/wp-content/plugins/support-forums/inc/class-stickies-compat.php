<?php

namespace WordPressdotorg\Forums;

class Stickies_Compat {

	const META = '_bbp_sticky_topics';

	var $compat   = null;
	var $slug     = null;
	var $taxonomy = null;
	var $object   = null;
	var $term     = null;

	public function __construct( $compat, $slug, $taxonomy, $object, $term ) {
		if ( empty( $compat ) || empty( $slug ) || empty( $taxonomy ) || empty( $object ) || empty( $term ) ) {
			return;
		}

		$this->compat   = $compat;
		$this->slug     = $slug;
		$this->taxonomy = $taxonomy;
		$this->object   = $object;
		$this->term     = $term;

		add_filter( 'bbp_get_stickies', array( $this, 'get_compat_stickies' ), 10, 2 );
	}

	/**
	 * Return compat stickies for a given term.
	 *
	 * @param array $stickies The sticky topic ids
	 * @param int $forum_id The forum id
	 * @return array The sticky topic ids
	 */
	public function get_compat_stickies( $stickies, $forum_id ) {
		if ( $this->term && bbp_is_single_view() && $this->compat == bbp_get_view_id() ) {
			$stickies = self::get_stickies( $this->term->term_id );
		}
		return $stickies;
	}

	/**
	 * Return an array of topic sticky ids for a given term.
	 *
	 * @param int $term_id The term id
	 * @return array The sticky topic ids
	 */
	public static function get_stickies( $term_id ) {
		$retval = array();

		$stickies = get_term_meta( $term_id, self::META, true );
		if ( $stickies ) {
			$retval = explode( ',', $stickies );
		}
		return $retval;
	}
}
