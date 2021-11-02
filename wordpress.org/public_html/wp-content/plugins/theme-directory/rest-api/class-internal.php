<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_REST_Server;
use WP_Error;
use WP_Http;

class Internal {

	function __construct() {
		register_rest_route( 'themes/v1', 'update-stats', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_update_stats' ),
			'permission_callback' => array( $this, 'permission_check_bearer' ),
		) );

		register_rest_route( 'themes/v1', 'svn-auth', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'svn_auth' ),
			'permission_callback' => function( $request ) {
				return $this->permission_check_bearer( $request, 'THEME_SVN_AUTH_BEARER_TOKEN' );
			}
		) );
	}

	/**
	 * A Permission Check callback which validates the request with a Bearer token.
	 *
	 * @param \WP_REST_Request $request  The Rest API Request.
	 * @param string           $constant The constant to check.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_bearer( $request, $constant = 'THEME_API_INTERNAL_BEARER_TOKEN' ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! defined( $constant ) ||
			! hash_equals( constant( $constant ), $authorization_header )
		) {
			return new WP_Error(
				'not_authorized',
				__( 'Sorry! You cannot do that.', 'wporg-themes' ),
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}

	/**
	 * Generates a SVN auth file.
	 *
	 * The SVN auth file is printed directly to the output, designed to be consumed by a cron task.
	 */
	function svn_auth() {
		global $wpdb;

		header( 'Content-Type: text/plain' );

		// Raw SQL to avoid loading every WP_Post / WP_User object causing OOM errors.
		$themes = $wpdb->get_results(
			"SELECT p.post_name as slug, u.user_login as user
			FROM {$wpdb->posts} p
			JOIN {$wpdb->users} u ON p.post_author = u.ID
			WHERE p.post_type = 'repopackage' AND p.post_status IN( 'publish', 'delist' )
			ORDER BY p.post_name ASC"
		);

		// Some users need write access to all themes, such as the dropbox user.
		$all_access_users   = get_option( 'svn_all_access', array() );
		$all_access_users[] = 'themedropbox';
		echo "[/]\n";
		echo "* = r\n";
		foreach ( array_unique( $all_access_users ) as $u ) {
			echo "{$u} = rw\n";
		}
		echo "\n";

		// Theme Authors.
		foreach ( $themes as $r ) {
			if ( ! $r->slug ) {
				// This should never occur, but just to ensure this never produces [/].
				continue;
			}

			printf(
				"[%s]\n%s = rw\n\n",
				'/' . $r->slug,
				$r->user
			);

		}

		exit();		
	}

	/**
	 * Endpoint to update a whitelisted set of postmeta fields for a bunch of theme slugs.
	 *
	 * Data is in the format of
	 * themes: {
	 *    theme-slug: {
	 *      active_installs: 1000
	 *    },
	 *    theme-slug-2: {
	 *       active_instals: 1000000
	 *    }
	 * }
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool true
	 */
	function bulk_update_stats( $request ) {
		$data = $request['themes'];

		foreach ( $data as $theme_slug => $stats ) {
			$theme = get_posts( array(
				'name'             => $theme_slug,
				'posts_per_page'   => 1,
				'post_type'        => 'repopackage',
				'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'trash', 'suspend' ),
				'suppress_filters' => false,
			) );
			if ( ! $theme ) {
				continue;
			}
			$theme = current( $theme );


			foreach ( $stats as $stat_name => $value ) {
				if ( 'active_installs' == $stat_name ) {
					$value = $this->sanitize_active_installs( $value );
					$meta_key = '_active_installs';
				} elseif ( 'popularity' == $stat_name ) {
					$value = (float) $value;
					$meta_key = '_popularity';
				} else {
					continue; // Unknown key
				}

				update_post_meta( $theme->ID, $meta_key, wp_slash( $value ) );
			}
		}

		return true;
	}

	/**
	 * Sanitizes the Active Install count number to a rounded display value.
	 *
	 * @param int $active_installs The raw active install number.
	 * @return int The sanitized version for display.
	 */
	protected function sanitize_active_installs( $active_installs ) {
		if ( $active_installs > 1000000 ) {
			// 1 million +;
			return 1000000;
		} elseif ( $active_installs > 100000 ) {
			$round = 100000;
		} elseif ( $active_installs > 10000 ) {
			$round = 10000;
		} elseif ( $active_installs > 1000 ) {
			$round = 1000;
		} elseif ( $active_installs > 100 ) {
			$round = 100;
		} else {
			// Rounded to ten, else 0
			$round = 10;
		}

		return floor( $active_installs / $round ) * $round;
	}

}
new Internal();
