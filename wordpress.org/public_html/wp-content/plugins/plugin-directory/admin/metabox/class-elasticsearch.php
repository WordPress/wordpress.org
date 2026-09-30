<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Displays the ElasticSearch index status and document data for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Elasticsearch {

	/**
	 * Fields to request from the ES index.
	 */
	const ES_FIELDS = [
		'post_id', 'blog_id', 'site_id', 'slug', 'post_type', 'status', 'disabled',
		'title', 'content', 'excerpt', 'description', 'all_content', 'slug_text',
		'title_en', 'content_en', 'excerpt_en', 'description_en', 'all_content_en',
		'author', 'author_login', 'author_login_slash_name', 'author_id', 'contributors',
		'date', 'date_gmt', 'modified', 'plugin_modified',
		'version', 'stable_tag', 'tested', 'requires', 'requires_php',
		'active_installs', 'downloaded', 'rating', 'num_ratings',
		'support_threads', 'support_threads_resolved',
		'tag', 'tag.name', 'tag.slug', 'category', 'category.name', 'category.slug',
		'taxonomy', 'taxonomy.plugin_tags.name', 'taxonomy.plugin_tags.slug',
		'taxonomy.plugin_section.name', 'taxonomy.plugin_section.slug',
		'taxonomy.plugin_category.name', 'taxonomy.plugin_category.slug',
		'taxonomy.plugin_contributors.slug',
		'shortcode_types', 'embed_types', 'features', 'has',
		'link', 'url', 'comment_count', 'menu_order', 'sticky',
	];

	/**
	 * Display the metabox placeholder, content is loaded via AJAX.
	 */
	public static function display() {
		$post  = get_post();
		$nonce = wp_create_nonce( 'plugin-elasticsearch-' . $post->ID );
		?>
		<div id="elasticsearch-data">
			<p>Loading ElasticSearch data&hellip;</p>
		</div>
		<script>
		jQuery( function( $ ) {
			var postId     = <?php echo (int) $post->ID; ?>,
			    nonce      = <?php echo wp_json_encode( $nonce ); ?>,
			    storageKey = 'es-details-open';

			function loadData() {
				$( '#elasticsearch-data' ).html( '<p>Loading ElasticSearch data&hellip;</p>' );
				$.post( ajaxurl, { action: 'plugin-elasticsearch', post_id: postId, _ajax_nonce: nonce }, function( response ) {
					$( '#elasticsearch-data' ).html( response );
					if ( localStorage.getItem( storageKey ) === '1' ) {
						$( '#es-details' ).attr( 'open', '' );
					}
				} ).fail( function() {
					$( '#elasticsearch-data' ).html( '<p>Failed to load ElasticSearch data.</p>' );
				} );
			}

			new IntersectionObserver( function( entries, obs ) {
				if ( entries[0].isIntersecting ) {
					obs.disconnect();
					loadData();
				}
			} ).observe( document.getElementById( 'elasticsearch-data' ) );

			$( document ).on( 'toggle', '#es-details', function() {
				localStorage.setItem( storageKey, this.open ? '1' : '0' );
			} );

			$( document ).on( 'click', '#es-reindex', function( e ) {
				e.preventDefault();
				var $btn = $( this ).prop( 'disabled', true ).text( 'Reindexing\u2026' );
				$.post( ajaxurl, { action: 'plugin-elasticsearch-reindex', post_id: postId, _ajax_nonce: nonce } ).done( function() {
					var delay = 15, countdown, poll;
					function tick() {
						$btn.text( 'Checking in ' + delay + 's\u2026' );
						countdown = setInterval( function() {
							delay--;
							if ( delay > 0 ) {
								$btn.text( 'Checking in ' + delay + 's\u2026' );
							} else {
								clearInterval( countdown );
								$btn.text( 'Checking\u2026' );
							}
						}, 1000 );
					}
					tick();
					poll = setInterval( function() {
						$.post( ajaxurl, { action: 'plugin-elasticsearch', post_id: postId, _ajax_nonce: nonce }, function( response ) {
							if ( response.indexOf( 'Not indexed' ) === -1 ) {
								clearInterval( poll );
								clearInterval( countdown );
								$( '#elasticsearch-data' ).html( response );
								if ( localStorage.getItem( storageKey ) === '1' ) {
									$( '#es-details' ).attr( 'open', '' );
								}
							} else {
								delay = 15;
								tick();
							}
						} );
					}, 15000 );
				} ).fail( function() {
					loadData();
				} );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * AJAX handler to query ElasticSearch and return the document data.
	 */
	public static function ajax_response() {
		$post_id = absint( $_POST['post_id'] ?? 0 );

		check_ajax_referer( 'plugin-elasticsearch-' . $post_id );

		if ( ! current_user_can( 'plugin_edit', $post_id ) ) {
			wp_die( '<p>You do not have permission to view this data.</p>' );
		}

		$hit = self::get_es_document( $post_id );

		if ( is_wp_error( $hit ) ) {
			printf( '<p>Error querying ElasticSearch: %s</p>', esc_html( $hit->get_error_message() ) );
			wp_die();
		}

		if ( ! $hit ) {
			echo '<p><strong style="color: #d63638;">Not indexed.</strong> This plugin is not present in the ElasticSearch index.</p>';
			echo '<p><button id="es-reindex" class="button button-secondary">Reindex</button></p>';
			wp_die();
		}

		echo '<p><strong style="color: #00a32a;">Indexed</strong> <button id="es-reindex" class="button button-secondary button-small">Reindex</button></p>';
		echo '<details id="es-details">';
		echo '<summary style="cursor: pointer; user-select: none;">View document data</summary>';
		echo '<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 600px; overflow: auto; margin-top: 0.5em;">';
		echo esc_html( wp_json_encode( $hit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		echo '</pre>';
		echo '</details>';

		wp_die();
	}

	/**
	 * AJAX handler to trigger a reindex of the plugin in Jetpack Sync.
	 */
	public static function ajax_reindex() {
		$post_id = absint( $_POST['post_id'] ?? 0 );

		check_ajax_referer( 'plugin-elasticsearch-' . $post_id );

		if ( ! current_user_can( 'plugin_edit', $post_id ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post = get_post( $post_id );

		if ( ! $post || 'plugin' !== $post->post_type ) {
			wp_send_json_error( 'Invalid post.' );
		}

		self::reindex_post( $post_id );

		wp_send_json_success( 'Reindex queued.' );
	}

	/**
	 * Trigger a reindex of a post via metadata update and action.
	 *
	 * @param int $post_id The post ID to reindex.
	 */
	public static function reindex_post( $post_id ) {
		update_post_meta( $post_id, 'jp_plugins_reindex_post', time() );

		// Fire the Jetpack Sync action directly to queue this post for reindexing
		// without running the full wp_update_post pipeline.
		$post = get_post( $post_id );
		if ( $post ) {
			do_action( 'jetpack_sync_save_post', $post_id, $post, false, [] );
		}
	}

	/**
	 * Query ES for a single post document.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $fields  Fields to request. Defaults to self::ES_FIELDS.
	 * @return array|false|WP_Error The hit array, false if not found, or WP_Error.
	 */
	public static function get_es_document( $post_id, $fields = null ) {
		if ( ! class_exists( '\Automattic\Jetpack\Search\Classic_Search' ) ) {
			return new \WP_Error( 'no_jetpack', 'Jetpack Search is not available.' );
		}

		$response = \Automattic\Jetpack\Search\Classic_Search::instance()->search( [
			'size'   => 1,
			'from'   => 0,
			'fields' => $fields ?? self::ES_FIELDS,
			'filter' => [
				'and' => [
					[ 'term' => [ 'post_id' => $post_id ] ],
				],
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['results']['total'] ) ) {
			return false;
		}

		return $response['results']['hits'][0] ?? false;
	}

	/**
	 * Check whether a set of post IDs exist in the ES index.
	 *
	 * @param int[] $post_ids Post IDs to check.
	 * @return int[]|WP_Error Array of found post IDs, or WP_Error.
	 */
	public static function check_post_ids( $post_ids ) {
		if ( ! class_exists( '\Automattic\Jetpack\Search\Classic_Search' ) ) {
			return new \WP_Error( 'no_jetpack', 'Jetpack Search is not available.' );
		}

		$response = \Automattic\Jetpack\Search\Classic_Search::instance()->search( [
			'size'   => count( $post_ids ),
			'from'   => 0,
			'fields' => [ 'post_id' ],
			'filter' => [
				'and' => [
					[ 'terms' => [ 'post_id' => $post_ids ] ],
				],
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$found = [];
		foreach ( $response['results']['hits'] ?? [] as $hit ) {
			if ( isset( $hit['fields']['post_id'] ) ) {
				$found[] = (int) $hit['fields']['post_id'];
			}
		}

		return $found;
	}
}
