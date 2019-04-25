<?php

namespace WordPressdotorg\Markdown;

use WP_CLI;
use WP_Error;
use WP_Post;
use WP_Query;
use WPCom_GHF_Markdown_Parser;

abstract class Importer {
	/**
	 * Meta key to store source in.
	 *
	 * @var string
	 */
	protected $meta_key = 'wporg_markdown_source';

	/**
	 * Meta key to store request ETag in.
	 *
	 * @var string
	 */
	protected $etag_meta_key = 'wporg_markdown_etag';

	/**
	 * Meta key to store source manifest entry in.
	 *
	 * @var string
	 */
	protected $manifest_entry_meta_key = 'wporg_markdown_manifest_entry';

	/**
	 * Posts per page to query for.
	 *
	 * This needs to be set at least as high as the number of pages being
	 * imported, but should not be unbounded (-1).
	 *
	 * @var int
	 */
	protected $posts_per_page = 500;

	/**
	 * Data about existing handbook pages.
	 *
	 * @var array
	 */
	protected $existing = [];

	/**
	 * Get base URL for all pages.
	 *
	 * This is used for generating the keys for the existing pages.
	 *
	 * @see static::get_existing_for_post()
	 *
	 * @return string Base URL to strip from page permalink.
	 */
	abstract protected function get_base();

	/**
	 * Get manifest URL.
	 *
	 * This URL should point to a JSON file containing the manifest for the
	 * site's content. (Typically raw.githubusercontent.com)
	 *
	 * @return string URL for the manifest file.
	 */
	abstract protected function get_manifest_url();

	/**
	 * Get post type for the type being imported.
	 *
	 * @return string Post type slug to import as.
	 */
	abstract public function get_post_type();

	/**
	 * Get existing data for a given post.
	 *
	 * @param WP_Post $post Post to get existing data for.
	 * @return array 2-tuple of array key and data.
	 */
	protected function get_existing_for_post( WP_Post $post ) {
		$key = rtrim( str_replace( $this->get_base(), '', get_permalink( $post->ID ) ), '/' );
		if ( empty( $key ) ) {
			$key = 'index';
		}

		$data = array(
			'post_id' => $post->ID,
		);
		return array( $key, $data );
	}

	/**
	 * Import the manifest.
	 *
	 * Fetches the manifest, parses, and creates pages as needed.
	 */
	public function import_manifest() {
		$response = wp_remote_get( $this->get_manifest_url() );
		if ( is_wp_error( $response ) ) {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::error( $response->get_error_message() );
			}
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::error( 'Non-200 from Markdown source' );
			}
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}
		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $manifest ) {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::error( 'Invalid manifest' );
			}
			return new WP_Error( 'invalid-manifest', 'Manifest did not unfurl properly.' );;
		}

		// A numeric key suggests the manifest did not explicitly specify keys for each item, so define one.
		$keys = array_keys( $manifest );
		if ( is_int( array_shift( $keys ) ) ) {
			$newdata = [];
			foreach ( $manifest as $key => $item ) {
				$item['order'] = $key;
				$key = $item['slug'];
				$newdata[ $key ] = $item;
			}
			$manifest = $newdata;
		}

		// Fetch all handbook posts for comparison
		$q = new WP_Query( array(
			'post_type'      => $this->get_post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => $this->posts_per_page,
		) );
		$this->existing = [];
		$this->existing['slug_only'] = [];
		foreach ( $q->posts as $post ) {
			list( $key, $data ) = $this->get_existing_for_post( $post );
			$this->existing[ $key ] = $data;

			// Also store a secondary entry for the post associated
			// with its slug and not its relative path if the key
			// looks like a path.
			if ( false !== strpos( $key, '/' ) ) {
				$this->existing['slug_only'][ $post->post_name ] = $data;
			}
		}

		$created = $updated = 0;
		foreach ( $manifest as $key => $doc ) {
			// Already exists, update.
			$existing = $this->existing[ $key ] ?? $this->existing['slug_only'][ $key ] ?? false;
			if ( $existing ) {
				$existing_id = $existing['post_id'];
				if ( $this->update_post_from_manifest_doc( $existing_id, $doc ) ) {
					$updated++;
				}

				continue;
			}
			if ( $this->process_manifest_doc( $doc, $manifest ) ) {
				$created++;
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::success( "Successfully created {$created} and updated {$updated} handbook pages." );
		}
	}

	/**
	 * Process a document from the manifest.
	 *
	 * @param array $doc Document to process.
	 * @param array $manifest Manifest data.
	 * @return boolean True if processing succeeded, false otherwise.
	 */
	protected function process_manifest_doc( $doc, $manifest ) {
		$post_parent = null;
		if ( ! empty( $doc['parent'] ) ) {
			// Find the parent in the existing set
			$parent = $this->existing[ $doc['parent'] ] ?? $this->existing['slug_only'][ $doc['parent'] ] ?? false;

			if ( ! $parent ) {
				if ( ! $this->process_manifest_doc( $manifest[ $doc['parent'] ], $manifest ) ) {
					return false;
				}
			}
			if ( $parent ) {
				$post_parent = $parent['post_id'];
			}
		}

		$post = $this->create_post_from_manifest_doc( $doc, $post_parent );
		if ( $post ) {
			list( $key, $data ) = $this->get_existing_for_post( $post );
			$this->existing[ $key ] = $data;
			$this->existing['slug_only'][ $post->post_name ] = $data;
			return true;
		}
		return false;
	}

	/**
	 * Create a new handbook page from the manifest document
	 */
	protected function create_post_from_manifest_doc( $doc, $post_parent = null ) {
		if ( $doc['slug'] === 'index' ) {
			$doc['slug'] = $this->get_post_type();
		}
		$post_data = array(
			'post_type'   => $this->get_post_type(),
			'post_status' => 'publish',
			'post_parent' => $post_parent,
			'post_title'  => wp_slash( $doc['slug'] ),
			'post_name'   => sanitize_title_with_dashes( $doc['slug'] ),
		);
		if ( isset( $doc['order'] ) ) {
			$post_data['menu_order'] = $doc['order'];
		}
		if ( isset( $doc['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $doc['title'] ) );
		}
		$post_id = wp_insert_post( $post_data );
		if ( ! $post_id ) {
			return false;
		}
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::log( "Created post {$post_id} for {$doc['slug']}." );
		}
		update_post_meta( $post_id, $this->meta_key, esc_url_raw( $doc['markdown_source'] ) );
		update_post_meta( $post_id, $this->manifest_entry_meta_key, $doc );
		return get_post( $post_id );
	}

	/**
	 * Update an existing post from the manifest.
	 *
	 * @param int $post_id Existing post ID.
	 * @param array $doc Document details from the manifest.
	 * @return boolean True if updated, false otherwise.
	 */
	protected function update_post_from_manifest_doc( $post_id, $doc ) {
		$did_update = update_post_meta( $post_id, $this->meta_key, esc_url_raw( $doc['markdown_source'] ) );
		if ( ! $did_update ) {
			return false;
		}

		update_post_meta( $post_id, $this->manifest_entry_meta_key, $doc );

		if ( isset( $doc['meta'] ) ) {
			foreach ( $doc['meta'] as $key => $value ) {
				$did_update = update_post_meta( $post_id, wp_slash( $key ), wp_slash( $value ) );
			}
		}

		return true;
	}

	/**
	 * Update existing posts from Markdown source.
	 *
	 * Reparses the Markdown for every page.
	 */
	public function import_all_markdown() {
		$q = new WP_Query( array(
			'post_type'      => $this->get_post_type(),
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => $this->posts_per_page,
		) );
		$ids = $q->posts;
		$success = 0;
		foreach( $ids as $id ) {
			$ret = $this->update_post_from_markdown_source( $id );
			if ( class_exists( 'WP_CLI' ) ) {
				if ( is_wp_error( $ret ) ) {
					WP_CLI::warning( $ret->get_error_message() );
				} elseif ( false === $ret ) {
					WP_CLI::log( "No updates for {$id}" );
					$success++;
				} else {
					WP_CLI::log( "Updated {$id} from markdown source" );
					$success++;
				}
			}
		}
		if ( class_exists( 'WP_CLI' ) ) {
			$total = count( $ids );
			WP_CLI::success( "Successfully updated {$success} of {$total} pages." );
		}
	}

	/**
	 * Update a post from its Markdown source.
	 *
	 * @param int $post_id Post ID to update.
	 * @return boolean|WP_Error True if updated, false if no update needed, error otherwise.
	 */
	protected function update_post_from_markdown_source( $post_id ) {
		$markdown_source = $this->get_markdown_source( $post_id );
		if ( is_wp_error( $markdown_source ) ) {
			return $markdown_source;
		}
		if ( ! function_exists( 'jetpack_require_lib' ) ) {
			return new WP_Error( 'missing-jetpack-require-lib', 'jetpack_require_lib() is missing on system.' );
		}

		// Transform GitHub repo HTML pages into their raw equivalents
		$markdown_source = preg_replace( '#https?://github\.com/([^/]+/[^/]+)/blob/(.+)#', 'https://raw.githubusercontent.com/$1/$2', $markdown_source );
		$markdown_source = add_query_arg( 'v', time(), $markdown_source );

		// Grab the stored ETag, and use it to deduplicate.
		$args = array(
			'headers' => array(),
		);
		$last_etag = get_post_meta( $post_id, $this->etag_meta_key, true );
		if ( ! empty( $last_etag ) ) {
			$args['headers']['If-None-Match'] = $last_etag;
		}

		$response = wp_remote_get( $markdown_source, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 304 === wp_remote_retrieve_response_code( $response ) ) {
			// No update required!
			return false;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}

		$etag = wp_remote_retrieve_header( $response, 'etag' );

		$markdown = wp_remote_retrieve_body( $response );
		// Strip YAML doc from the header
		$markdown = preg_replace( '#^---(.+)---#Us', '', $markdown );

		$markdown = trim( $markdown );

		$markdown = apply_filters( 'wporg_markdown_before_transform', $markdown, $this->get_post_type() );

		$title = null;
		if ( preg_match( '/^#\s(.+)/', $markdown, $matches ) ) {
			$title = $matches[1];
			$markdown = preg_replace( '/^#\swp\s(.+)/', '', $markdown );
		}
		$markdown = trim( $markdown );

		// Steal the first sentence as the excerpt
		$excerpt = '';
		if ( preg_match( '/^(.+)/', $markdown, $matches ) ) {
			$excerpt = $matches[1];
			$markdown = preg_replace( '/^(.+)/', '', $markdown );
		}

		// Transform to HTML and save the post
		jetpack_require_lib( 'markdown' );
		$parser = new WPCom_GHF_Markdown_Parser();
		$parser->preserve_shortcodes = false;
		$html = $parser->transform( $markdown );

		$html = apply_filters( 'wporg_markdown_after_transform', $html, $this->get_post_type() );

		$post_data = array(
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
			'post_excerpt' => sanitize_text_field( wp_slash( $excerpt ) ),
		);
		// If no title was extracted from markdown doc, use the value defined in manifest.
		if ( is_null( $title ) ) {
			$markdown_entry = get_post_meta( $post_id, $this->manifest_entry_meta_key, true );
			if ( ! empty( $markdown_entry['title'] ) ) {
				$post_data['post_title'] = sanitize_text_field( wp_slash( $markdown_entry['title'] ) );
			}
		} else {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $title ) );
		}

		wp_update_post( $post_data );

		// Set ETag for future updates.
		update_post_meta( $post_id, $this->etag_meta_key, wp_slash( $etag ) );

		return true;
	}

	/**
	 * Retrieve the markdown source URL for a given post.
	 */
	public function get_markdown_source( $post_id ) {
		$markdown_source = get_post_meta( $post_id, $this->meta_key, true );
		if ( ! $markdown_source ) {
			return new WP_Error( 'missing-markdown-source', 'Markdown source is missing for post.' );
		}

		return $markdown_source;
	}
}
