<?php

/**
 * Class Repo_Package
 *
 * Base class for interacting with posts of the repo package type.
 */
class Repo_Package {

	/**
	 * Holds a WP_Post object representing this post.
	 *
	 * @var WP_Post
	 */
	public $wp_post;

	/**
	 * Construct a new Package for the given post ID or object.
	 *
	 * @param WP_Post|int $post
	 */
	public function __construct( $post = 0 ) {
		$this->init( $post );
	}

	/**
	 * Set up the post the for the given ID or object.
	 *
	 * @param WP_Post|int $post
	 */
	public function init( $post = 0 ) {
		if ( $post ) {
			$this->wp_post = get_post( $post );
		}
	}
}
