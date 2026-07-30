<?php

namespace WPOrg_Cli;

use WP_Error;
use WP_Query;

class Markdown_Import {

	private static $handbook_manifest = 'https://raw.githubusercontent.com/wp-cli/handbook/main/bin/handbook-manifest.json';
	private static $input_name = 'wporg-cli-markdown-source';
	private static $meta_key = 'wporg_cli_markdown_source';
	private static $nonce_name = 'wporg-cli-markdown-source-nonce';
	private static $submit_name = 'wporg-cli-markdown-import';
	private static $supported_post_types = array( 'handbook' );
	private static $posts_per_page = -1;

	/**
	 * Hosts a Markdown source may be fetched from.
	 *
	 * @var string[]
	 */
	private static $allowed_hosts = array( 'github.com', 'raw.githubusercontent.com' );

	/**
	 * Register our cron task if it doesn't already exist
	 */
	public static function action_init() {
		if ( ! wp_next_scheduled( 'wporg_cli_manifest_import' ) ) {
			wp_schedule_event( time(), '15_minutes', 'wporg_cli_manifest_import' );
		}
		if ( ! wp_next_scheduled( 'wporg_cli_markdown_import' ) ) {
			wp_schedule_event( time(), '15_minutes', 'wporg_cli_markdown_import' );
		}
	}

	/**
	 * Register the Markdown source meta, so that every write is checked.
	 */
	public static function action_register_meta() {
		foreach ( self::$supported_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( __CLASS__, 'validate_markdown_source' ),
				)
			);
		}
	}

	public static function action_wporg_cli_manifest_import() {
		$response = wp_remote_get( self::$handbook_manifest );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}
		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! $manifest ) {
			return new WP_Error( 'invalid-manifest', 'Manifest did not unfurl properly.' );;
		}
		// Fetch all handbook posts for comparison
		$q = new WP_Query( array(
			'post_type'      => self::$supported_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::$posts_per_page,
		) );
		$existing = $q->posts;
		$created = 0;
		foreach( $manifest as $doc ) {
			// Already exists
			if ( wp_filter_object_list( $existing, array( 'post_name' => $doc['slug'] ) ) ) {
				continue;
			}
			$post_parent = null;
			if ( ! empty( $doc['parent'] ) ) {
				// Find the parent in the existing set
				$parents = wp_filter_object_list( $existing, array( 'post_name' => $doc['parent'] ) );
				if ( ! empty( $parents ) ) {
					$parent = array_shift( $parents );
				} else {
					// Create the parent and add it to the stack
					if ( isset( $manifest[ $doc['parent'] ] ) ) {
						$parent_doc = $manifest[ $doc['parent'] ];
						$parent = self::create_post_from_manifest_doc( $parent_doc );
						if ( $parent ) {
							$created++;
							$existing[] = $parent;
						} else {
							continue;
						}
					} else {
						continue;
					}
				}
				$post_parent = $parent->ID;
			}
			$post = self::create_post_from_manifest_doc( $doc, $post_parent );
			if ( $post ) {
				$created++;
				$existing[] = $post;
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::success( "Successfully created {$created} handbook pages." );
		}
	}

	/**
	 * Create a new handbook page from the manifest document
	 */
	private static function create_post_from_manifest_doc( $doc, $post_parent = null ) {
		// Checked before the insert, so a bad entry doesn't leave behind a page that only ever fails to import.
		$markdown_source = self::validate_markdown_source( $doc['markdown_source'] ?? '' );
		if ( ! $markdown_source ) {
			if ( class_exists( 'WP_CLI' ) ) {
				\WP_CLI::warning( sprintf( 'Skipped %s: markdown source is not an allowed URL.', $doc['slug'] ?? 'manifest doc' ) );
			}
			return false;
		}

		$post_data = array(
			'post_type'   => 'handbook',
			'post_status' => 'publish',
			'post_parent' => $post_parent,
			'post_title'  => sanitize_text_field( wp_slash( $doc['title'] ) ),
			'post_name'   => sanitize_title_with_dashes( $doc['slug'] ),
		);
		$post_id = wp_insert_post( $post_data );
		if ( ! $post_id ) {
			return false;
		}
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( "Created post {$post_id} for {$doc['title']}." );
		}
		update_post_meta( $post_id, self::$meta_key, $markdown_source );
		return get_post( $post_id );
	}

	public static function action_wporg_cli_markdown_import() {
		$q = new WP_Query( array(
			'post_type'      => self::$supported_post_types,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => self::$posts_per_page,
		) );
		$ids = $q->posts;
		$success = 0;
		foreach( $ids as $id ) {
			$ret = self::update_post_from_markdown_source( $id );
			if ( class_exists( 'WP_CLI' ) ) {
				if ( is_wp_error( $ret ) ) {
					\WP_CLI::warning( $ret->get_error_message() );
				} else {
					\WP_CLI::log( "Updated {$id} from markdown source" );
					$success++;
				}
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			$total = count( $ids );
			\WP_CLI::success( "Successfully updated {$success} of {$total} handbook pages." );
		}
	}

	/**
	 * Handle a request to import from the markdown source
	 */
	public static function action_load_post_php() {
		if ( ! isset( $_GET[ self::$submit_name ] )
			|| ! isset( $_GET[ self::$nonce_name ] )
			|| ! isset( $_GET['post'] ) ) {
			return;
		}
		$post_id = (int) $_GET['post'];
		if ( ! current_user_can( 'edit_post', $post_id )
			|| ! wp_verify_nonce( $_GET[ self::$nonce_name ], self::$input_name )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}

		$response = self::update_post_from_markdown_source( $post_id );
		if ( is_wp_error( $response ) ) {
			wp_die( $response->get_error_message() );
		}

		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Add an input field for specifying Markdown source
 	 */
	public static function action_edit_form_after_title( $post ) {
		if ( ! in_array( $post->post_type, self::$supported_post_types, true ) ) {
			return;
		}
		$markdown_source = get_post_meta( $post->ID, self::$meta_key, true );
		?>
		<label>Markdown source: <input
			type="text"
			name="<?php echo esc_attr( self::$input_name ); ?>"
			value="<?php echo esc_attr( $markdown_source ); ?>"
			placeholder="Enter a URL representing a markdown file to import"
			size="50" />
		</label> <?php
			if ( $markdown_source ) :
				$update_link = add_query_arg( array(
					self::$submit_name => 'import',
					self::$nonce_name  => wp_create_nonce( self::$input_name ),
				), get_edit_post_link( $post->ID, 'raw' ) );
				?>
				<a class="button button-small button-primary" href="<?php echo esc_url( $update_link ); ?>">Import</a>
			<?php endif; ?>
		<?php wp_nonce_field( self::$input_name, self::$nonce_name ); ?>
		<?php
	}

	/**
	 * Save the Markdown source input field
	 */
	public static function action_save_post( $post_id ) {

		if ( ! isset( $_POST[ self::$input_name ] )
			|| ! is_string( $_POST[ self::$input_name ] )
			|| ! isset( $_POST[ self::$nonce_name ] )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST[ self::$nonce_name ], self::$input_name )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = wp_unslash( $_POST[ self::$input_name ] );
		if ( '' === $submitted ) {
			update_post_meta( $post_id, self::$meta_key, '' );
			return;
		}

		$markdown_source = self::validate_markdown_source( $submitted );
		if ( ! $markdown_source ) {
			// The stored source is left alone, since the prefilled field posts it back on every save.
			return;
		}

		update_post_meta( $post_id, self::$meta_key, $markdown_source );
	}

	/**
	 * Filter cron schedules to add a 15 minute schedule
	 */
	public static function filter_cron_schedules( $schedules ) {
		$schedules['15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => '15 minutes'
		);
		return $schedules;
	}

	/**
	 * Update a post from its Markdown source
	 */
	private static function update_post_from_markdown_source( $post_id ) {
		$markdown_source = self::get_markdown_source( $post_id );
		if ( is_wp_error( $markdown_source ) ) {
			return $markdown_source;
		}

		/*
		 * Sources are also checked on save, but stored values predate that check
		 * and the scheduled import reuses whatever is already in post meta.
		 */
		$markdown_source = self::validate_markdown_source( $markdown_source );
		if ( ! $markdown_source ) {
			return new WP_Error( 'invalid-markdown-source', 'Markdown source is not an allowed URL.' );
		}

		if ( ! class_exists( 'WPCom_GHF_Markdown_Parser' ) && defined( 'JETPACK__PLUGIN_DIR' ) ) {
			include JETPACK__PLUGIN_DIR . '/_inc/lib/markdown.php';
		}
		if ( ! class_exists( 'WPCom_GHF_Markdown_Parser' ) ) {
			return new WP_Error( 'missing-jetpack-markdown', 'Jetpack Markdown is missing on system.' );
		}

		// Transform GitHub repo HTML pages into their raw equivalents, matching the host as case insensitively as it was validated.
		$markdown_source = preg_replace( '#https?://github\.com/([^/]+/[^/]+)/blob/(.+)#i', 'https://raw.githubusercontent.com/$1/$2', $markdown_source );
		$markdown_source = add_query_arg( 'v', time(), $markdown_source );
		$response = wp_safe_remote_get( $markdown_source );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}

		$markdown = wp_remote_retrieve_body( $response );
		// Strip YAML doc from the header
		$markdown = preg_replace( '#^---(.+)---#Us', '', $markdown );

		$title = null;
		if ( preg_match( '/^#\s(.+)/', $markdown, $matches ) ) {
			$title = $matches[1];
			$markdown = preg_replace( '/^#\s(.+)/', '', $markdown );
		}

		// Transform to HTML and save the post
		$parser = new \WPCom_GHF_Markdown_Parser;
		$html = $parser->transform( $markdown );
		$post_data = array(
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
		);
		if ( ! is_null( $title ) ) {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $title ) );
		}
		wp_update_post( $post_data );
		return true;
	}

	/**
	 * Check a Markdown source URL against the list of allowed hosts.
	 *
	 * @param string $markdown_source URL to check.
	 * @return string The URL, or an empty string if it isn't allowed.
	 */
	public static function validate_markdown_source( $markdown_source ) {
		$markdown_source = esc_url_raw( $markdown_source, array( 'http', 'https' ) );
		if ( ! $markdown_source ) {
			return '';
		}

		$parts = wp_parse_url( $markdown_source );
		if ( ! $parts ) {
			return '';
		}

		// A protocol relative URL parses to an allowed host, but the HTTP API refuses to request it.
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host   = strtolower( $parts['host'] ?? '' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true )
			|| ! in_array( $host, self::$allowed_hosts, true ) ) {
			return '';
		}

		return $markdown_source;
	}

	/**
	 * Retrieve the markdown source URL for a given post.
	 */
	public static function get_markdown_source( $post_id ) {
		$markdown_source = get_post_meta( $post_id, self::$meta_key, true );
		if ( ! $markdown_source ) {
			return new WP_Error( 'missing-markdown-source', 'Markdown source is missing for post.' );
		}

		return $markdown_source;
	}
}
