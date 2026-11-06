<?php
/**
 * Imports videos, their terms, and pages from WordPress.tv's public APIs.
 *
 * Posts embed their video with the `[wpvideo]` shortcode, as most production posts do.
 *
 * @package WordPressdotorg\Environments\WordPressTV
 */

namespace WordPressdotorg\Environments\WordPressTV;

use WP_CLI;

use const WordPressdotorg\Environments\WordPressTV_Local\SUBMISSION_GUID;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SITE_API  = 'https://wordpress.tv/api/';
const PAGES_API = 'https://public-api.wordpress.com/wp/v2/sites/wordpress.tv/pages';

/**
 * Fetches and decodes a JSON document.
 *
 * @param string $url URL to fetch.
 * @return array|null Decoded body, or null on failure.
 */
function fetch_json( string $url ): ?array {
	$response = wp_remote_get( $url, array( 'timeout' => 60 ) );
	$code     = wp_remote_retrieve_response_code( $response );
	if ( is_wp_error( $response ) || 200 !== $code ) {
		WP_CLI::warning( sprintf( 'Could not fetch %s (%s).', $url, is_wp_error( $response ) ? $response->get_error_message() : "HTTP $code" ) );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * Fetches the names of all production categories, keyed by slug.
 *
 * Videos only carry their own categories, so the parents' names come from here.
 *
 * @return string[] Category names keyed by slug.
 */
function fetch_category_names(): array {
	$names = array();
	$page  = 1;
	do {
		$data = fetch_json( SITE_API . 'categories.json?paged=' . $page );
		foreach ( $data['categories'] ?? array() as $category ) {
			$slug = category_path( $category['link'] );
			if ( $slug ) {
				$names[ end( $slug ) ] = $category['name'];
			}
		}
		++$page;
	} while ( $data && $page <= (int) ( $data['query']['pages'] ?? 0 ) );

	return $names;
}

/**
 * Splits a category archive link into its slug hierarchy.
 *
 * @param string $link Category link, like https://wordpress.tv/category/year/2024/.
 * @return string[] Slugs from the top-level category down.
 */
function category_path( string $link ): array {
	if ( ! preg_match( '#/category/(.+?)/?$#', (string) wp_parse_url( $link, PHP_URL_PATH ), $matches ) ) {
		return array();
	}

	return explode( '/', $matches[1] );
}

/**
 * Returns the ID of a category, creating it and its parents as needed.
 *
 * @param string   $link  Category link.
 * @param string[] $names Category names keyed by slug.
 * @return int Term ID, or 0 on failure.
 */
function ensure_category( string $link, array $names ): int {
	$parent = 0;
	foreach ( category_path( $link ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term(
				$names[ $slug ] ?? ucfirst( $slug ),
				'category',
				array(
					'slug'   => $slug,
					'parent' => $parent,
				)
			);
			if ( is_wp_error( $term ) ) {
				WP_CLI::warning( "Could not create category $slug: " . $term->get_error_message() );
				return 0;
			}
			$term = get_term( $term['term_id'], 'category' );
		}
		$parent = (int) $term->term_id;
	}

	return $parent;
}

/**
 * Returns the IDs of terms, creating the missing ones.
 *
 * @param array[] $items    Terms as listed by the videos API, with `slug` and `name`.
 * @param string  $taxonomy Taxonomy.
 * @return int[] Term IDs.
 */
function ensure_terms( array $items, string $taxonomy ): array {
	$ids = array();
	foreach ( $items as $item ) {
		$term = get_term_by( 'slug', $item['slug'], $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $item['name'], $taxonomy, array( 'slug' => $item['slug'] ) );
			if ( is_wp_error( $term ) ) {
				WP_CLI::warning( "Could not create $taxonomy {$item['slug']}: " . $term->get_error_message() );
				continue;
			}
			$term = get_term( $term['term_id'], $taxonomy );
		}
		$ids[] = (int) $term->term_id;
	}

	return $ids;
}

/**
 * Finds the VideoPress GUID of a video in its file or thumbnail URLs.
 *
 * @param array $video Video from the videos API.
 * @return string GUID, or an empty string.
 */
function video_guid( array $video ): string {
	$urls = array_merge( array( $video['thumbnail'] ?? '' ), (array) ( $video['video']['mp4'] ?? array() ) );
	foreach ( $urls as $url ) {
		if ( is_string( $url ) && preg_match( '#^https://videos\.files\.wordpress\.com/([A-Za-z0-9]{8})/#', $url, $matches ) ) {
			return $matches[1];
		}
	}

	return '';
}

/**
 * Creates a post for a video unless one with its slug exists, then tags it.
 *
 * @param array    $video Video from the videos API.
 * @param string[] $names Category names keyed by slug.
 * @param string[] $tags  Tag slugs to add, even to an existing post.
 * @return bool Whether the post was created.
 */
function import_video( array $video, array $names, array $tags ): bool {
	$guid = video_guid( $video );
	$slug = basename( (string) wp_parse_url( $video['permalink'], PHP_URL_PATH ) );
	if ( ! $guid || ! $slug ) {
		return false;
	}

	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existing ) {
		wp_set_object_terms( $existing->ID, $tags, 'post_tag', true );
		return false;
	}

	$categories = array();
	foreach ( array_merge( $video['category'], array_filter( array( $video['year'], $video['location'] ) ) ) as $category ) {
		$categories[] = ensure_category( $category['link'], $names );
	}

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_name'     => $slug,
				'post_title'    => $video['title'],
				'post_excerpt'  => $video['description'],
				'post_content'  => "[wpvideo $guid]",
				'post_date_gmt' => $video['date'],
				'post_date'     => get_date_from_gmt( $video['date'] ),
				'post_category' => array_filter( $categories ),
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "Could not import $slug: " . $post_id->get_error_message() );
		return false;
	}

	wp_set_object_terms( $post_id, ensure_terms( $video['speakers'], 'speakers' ), 'speakers' );
	wp_set_object_terms( $post_id, ensure_terms( $video['event'], 'event' ), 'event' );
	wp_set_object_terms( $post_id, ensure_terms( $video['language'], 'language' ), 'language' );
	wp_set_object_terms( $post_id, $tags, 'post_tag' );
	if ( ! empty( $video['producer']['username'] ) ) {
		wp_set_object_terms( $post_id, array( $video['producer']['username'] ), 'producer-username' );
	}
	if ( ! empty( $video['producer']['name'] ) ) {
		wp_set_object_terms( $post_id, array( $video['producer']['name'] ), 'producer' );
	}
	if ( $video['slides'] ) {
		update_post_meta( $post_id, '_wptv_slides_url', esc_url_raw( $video['slides'] ) );
	}

	return true;
}

/**
 * Imports the published pages, keeping their slugs and rendered content.
 *
 * @return int Number of pages created.
 */
function import_pages(): int {
	$pages = fetch_json( PAGES_API . '?per_page=50&_fields=slug,title,content' ) ?? array();
	$count = 0;
	foreach ( $pages as $page ) {
		if ( get_page_by_path( $page['slug'] ) ) {
			continue;
		}
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_author'  => 1,
					'post_name'    => $page['slug'],
					'post_title'   => html_entity_decode( $page['title']['rendered'], ENT_QUOTES, 'UTF-8' ),
					'post_content' => $page['content']['rendered'],
				)
			)
		);
		if ( $post_id ) {
			++$count;
		}
	}

	return $count;
}

/**
 * Adds a pending submission, as the submission form would, for the moderation tools.
 *
 * @return bool Whether the submission was added.
 */
function seed_submission(): bool {
	$pending = get_posts(
		array(
			'post_status' => 'pending',
			'meta_key'    => '_wptv_submitted_video',
			'fields'      => 'ids',
		)
	);
	if ( $pending ) {
		return false;
	}

	$author  = get_user_by( 'login', 'anonvideoupload' );
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'pending',
			'post_author'  => $author ? $author->ID : 1,
			'post_title'   => 'Jane Doe: Building Block Themes',
			'post_content' => serialize_block(
				array(
					'blockName'    => 'videopress/video',
					'attrs'        => array( 'guid' => SUBMISSION_GUID ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			),
		)
	);
	if ( ! $post_id ) {
		return false;
	}

	update_post_meta(
		$post_id,
		'_wptv_submitted_video',
		array(
			'attachment_id'     => 0,
			'submitted_by'      => 'Jane Doe',
			'submitted_email'   => 'jane@example.org',
			'title'             => 'Building Block Themes',
			'producer_username' => '',
			'speakers'          => 'Jane Doe',
			'event'             => 'WordCamp Example 2026',
			'language'          => 'English',
			'categories'        => '',
			'description'       => "A walk through building a block theme.\n\nWith a second paragraph.",
			'slides'            => 'https://example.org/slides/',
			'ip'                => '127.0.0.1',
			'recorded'          => '2026-05-01',
			'video_guid'        => SUBMISSION_GUID,
		)
	);

	return true;
}

/**
 * Replaces the sample content with WordPress.tv's, unless that was done before.
 *
 * @return void
 */
function run(): void {
	if ( get_option( 'wptv_env_imported' ) ) {
		WP_CLI::log( 'Already imported, skipping. Run `npm run wptv:refresh` to import again.' );
		return;
	}

	$samples = array(
		'hello-world'    => 'post',
		'sample-page'    => 'page',
		'privacy-policy' => 'page',
	);
	foreach ( $samples as $slug => $post_type ) {
		$sample = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $sample ) {
			wp_delete_post( $sample->ID, true );
		}
	}

	$names = fetch_category_names();

	// The API leaves every video's tags empty, so the featured query adds its tag.
	$queries = array(
		'posts_per_page=40'                        => array(),
		'tag=featured&posts_per_page=8'            => array( 'featured' ),
		'category_name=education&posts_per_page=6' => array(),
		'category_name=how-to&posts_per_page=6'    => array(),
	);

	$fetched  = 0;
	$imported = 0;
	foreach ( $queries as $query => $tags ) {
		$data = fetch_json( SITE_API . 'videos.json?' . $query );
		foreach ( $data['videos'] ?? array() as $video ) {
			++$fetched;
			if ( import_video( $video, $names, $tags ) ) {
				++$imported;
			}
		}
	}

	if ( ! $fetched ) {
		WP_CLI::warning( 'No videos were fetched; wordpress.tv may be unreachable. Run `npm run wptv:refresh` to try again.' );
		return;
	}

	$pages = import_pages();
	seed_submission();
	update_option( 'wptv_env_imported', time() );

	WP_CLI::success( sprintf( 'Imported %d new videos and %d new pages.', $imported, $pages ) );
}

run();
