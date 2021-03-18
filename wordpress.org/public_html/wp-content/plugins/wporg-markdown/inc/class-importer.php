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
		// Account for potential handbook landing page, which results in an empty $key.
		if ( ! $key ) {
			if ( in_array( $post->post_name, [ 'handbook', $post->post_type, "{$post->post_type}-handbook", 'welcome' ] ) ) {
				$key = $post->post_name;
			}
		}
		if ( ! $key ) {
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
		} else {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::log( "Importing manifest from " . $this->get_manifest_url() );
			}	
		}
		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $manifest ) {
			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::error( 'Invalid manifest' );
			}
			return new WP_Error( 'invalid-manifest', 'Manifest did not unfurl properly.' );
		}

		// A numeric key suggests the manifest did not explicitly specify keys for each item, so define one.
		// Note: not explicitly specifying a key means the slugs defined must be unique.
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
			$existing = $this->existing[ $key ]
				?? $this->existing['slug_only'][ $key ]
				?? $this->existing[ $doc['slug'] ]
				?? $this->existing['slug_only'][ $doc['slug'] ]
				?? false;
			if ( ! $existing && 'index' === $key ) {
				$key = $this->get_post_type();
				$existing = $this->existing[ $key ] ?? $this->existing['slug_only'][ $key ] ?? false;
			}
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
			if ( 0 === $created && 0 === $updated ) {
				WP_CLI::success( "No updates detected for any handbook page." );
			} else {
				WP_CLI::success( "Successfully created {$created} and updated {$updated} handbook pages." );
			}
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

		/**
		 * Filters the post data used to create a post from the manifest.
		 *
		 * @param array $post_data Post data.
		 */
		$post_data = apply_filters( 'wporg_markdown_post_data_pre_insert', $post_data );

		$post_id = wp_insert_post( $post_data );
		if ( ! $post_id ) {
			return false;
		}
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::log( "Created post {$post_id} for {$doc['slug']}." );
		}
		update_post_meta( $post_id, $this->meta_key, esc_url_raw( $this->generate_markdown_source_url( $doc['markdown_source'] ) ) );
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
		update_post_meta( $post_id, $this->manifest_entry_meta_key, $doc );

		$did_update = update_post_meta( $post_id, $this->meta_key, esc_url_raw( $this->generate_markdown_source_url( $doc['markdown_source'] ) ) );
		if ( ! $did_update ) {
			return false;
		}

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
	 * Updates a post for fields that are derived from the manifest.
	 *
	 * A manifest specifies post-related values that may be updated even if the
	 * post's markdown page is not.
	 *
	 * A manifest can set or affect the following post fields:
	 * - menu_order
	 * - post_parent
	 * - post_title
	 *
	 * @param int    $post_id   Page ID to update.
	 * @param mixed  $title     Page title from markdown. If false, then no title
	 *                          handling will occur. If null, then title defined in
	 *                          manifest will be used. If string, then that's the
	 *                          title and its value will be compared to manifest value.
	 * @param bool   $do_update Optional. Should the post actually be updated?
	 *                          Default true.
	 * @return array The array of post data that would be used for an update.
	 */
	protected function update_post_from_manifest( $post_id, $title = false, $do_update = true ) {
		$post_data = [];

		$manifest_entry = get_post_meta( $post_id, $this->manifest_entry_meta_key, true );

		// Determine value for 'menu_order'.
		if ( ! empty( $manifest_entry['order'] ) ) {
			$post_data['menu_order'] = $manifest_entry['order'];
		}

		// If no title was extracted from markdown doc, use the value defined in manifest.
		if ( is_null( $title ) ) {
			if ( ! empty( $manifest_entry['title'] ) ) {
				$post_data['post_title'] = sanitize_text_field( wp_slash( $manifest_entry['title'] ) );
			}
		} elseif ( $title ) {
			$post_data['post_title'] = sanitize_text_field( wp_slash( $title ) );
		}

		$parent_id = wp_get_post_parent_id( $post_id );

		// Determine value for 'post_parent'.
		if ( ! $manifest_entry ) {
			// Do nothing with regards to possibly changing post parent as we know
			// nothing about previous import.
		}
		// If post had a parent...
		elseif ( $parent_id ) {
			$parent = $parent_id ? get_post( $parent_id ) : null;
			// ...but no parent is now defined, unset parent.
			if ( empty( $manifest_entry['parent'] ) ) {
				$post_data['post_parent'] = '';
			}
			// ...and it appears to differ from parent now defined, find new parent.
			elseif ( $manifest_entry['parent'] !== $parent->post_name ) {
				$find_parent = get_page_by_path( $manifest_entry['parent'], OBJECT, $this->get_post_type() );
				if ( $find_parent ) {
					$post_data['post_parent'] = $find_parent->ID;
				}
			}
		}
		// Does this parentless post now have one newly defined?
		elseif ( ! empty( $manifest_entry['parent'] ) ) {
			$find_parent = get_page_by_path( $manifest_entry['parent'], OBJECT, $this->get_post_type() );
			if ( $find_parent ) {
				$post_data['post_parent'] = $find_parent->ID;
			}
		}

		if ( $do_update && $post_data ) {
			$post_data['ID'] = $post_id;

			if ( class_exists( 'WP_CLI' ) ) {
				WP_CLI::log( "Updated {$post_id} from manifest source" );
			}
			wp_update_post( $post_data );
		}

		return $post_data;
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
		/**
		 * Filters if HTTP ETags should be included in request for remote Markdown
		 * source update.
		 *
		 * @param bool $check_etags Should HTTP ETags be checcked? Default true.
		 */
		$last_etag = apply_filters( 'wporg_markdown_check_etags', true ) ? get_post_meta( $post_id, $this->etag_meta_key, true ) : false;
		if ( ! empty( $last_etag ) ) {
			$args['headers']['If-None-Match'] = $last_etag;
		}

		$response = wp_remote_get( $markdown_source, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 304 === wp_remote_retrieve_response_code( $response ) ) {
			// No content update required. Though certain meta fields that are defined
			// in the manifest may have been updated.
			$this->update_post_from_manifest( $post_id );
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
			$markdown = preg_replace( '/^#\s(.+)/', '', $markdown );
		}
		$markdown = trim( $markdown );

		// Use the first sentence as the excerpt.
		$excerpt = '';
		if ( preg_match( '/^(.+)/', $markdown, $matches ) ) {
			$excerpt = $matches[1];
		}

		// Transform to HTML and save the post
		jetpack_require_lib( 'markdown' );
		$parser = new WPCom_GHF_Markdown_Parser();
		$parser->preserve_shortcodes = false;
		$html = $parser->transform( $markdown );

		$html = apply_filters( 'wporg_markdown_after_transform', $html, $this->get_post_type() );

		add_filter( 'wp_kses_allowed_html', [ $this, 'wp_kses_allow_links' ], 10, 2 );

		$post_data = array(
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
			'post_excerpt' => sanitize_text_field( wp_slash( $excerpt ) ),
		);

		remove_filter( 'wp_kses_allowed_html', [ $this, 'wp_kses_allow_links' ], 10 );

		$fields_from_manifest = $this->update_post_from_manifest( $post_id, $title, false );
		if ( $fields_from_manifest ) {
			$post_data = array_merge( $post_data, $fields_from_manifest );
		}

		wp_update_post( $post_data );

		// Set ETag for future updates.
		update_post_meta( $post_id, $this->etag_meta_key, wp_slash( $etag ) );

		return true;
	}

	/**
	 * Ensures that the 'a' tag and certain of its attributes are allowed in
	 * posts if not already.
	 *
	 * Supported 'a' attributes are those defined for `$allowedposttags` by default.
	 *
	 * This is necessary since the 'a' tag is being removed somewhere along the way.
	 *
	 * @param array[]|string $allowed_tags Allowed HTML tags and their attributes
	 *                                     or the context to judge allowed tags by.
	 * @param string         $context      Context name.
	 * @return array[]|string
	 */
	public function wp_kses_allow_links( $allowed_tags, $context ) {
		if ( 'post' === $context && is_array( $allowed_tags ) && empty( $allowed_tags[ 'a' ] ) ) {
			$allowed_tags['a'] = [
				'href'     => true,
				'rel'      => true,
				'rev'      => true,
				'name'     => true,
				'target'   => true,
				'download' => [
						'valueless' => 'y',
				],
			];
		}

		return $allowed_tags;
	}

	/**
	 * Generates a fully qualified markdown source URL in the event a relative
	 * path was defined.
	 *
	 * @param string $markdown_source The markdwon_source value defined for a
	 *                                document in the manifest.
	 * @return string
	 */
	public function generate_markdown_source_url( $markdown_source ) {
		// If source is not explicit URL, then it is relative.
		if ( false === strpos( $markdown_source, 'https://' ) ) {
			// Base URL is the location of the manifest.
			$base = $this->get_manifest_url();
			$base = rtrim( dirname( $base ), '/' );

			// Markdown source can relatively refer to manifest's parent directory,
			// but no higher.
			if ( false !== strpos( $markdown_source, '../' ) ) {
				$base = rtrim( dirname( $base ), '/' );
				$markdown_source = str_replace( '../', '/', $markdown_source );
			}

			$markdown_source = $base . '/' . ltrim( $markdown_source, '/' );
		}

		return $markdown_source;
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
