<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;

use WordPressdotorg\Plugin_Directory\Admin\Metabox\Elasticsearch;

/**
 * Admin tool to check which published plugins are missing from the ElasticSearch index.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */
class Elasticsearch_Status {

	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new self();
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
		add_action( 'wp_ajax_es-index-check-batch', array( $this, 'ajax_check_batch' ) );
		add_action( 'wp_ajax_es-index-reindex', array( $this, 'ajax_reindex' ) );
	}

	public function add_to_menu() {
		add_submenu_page(
			'plugin-tools',
			'ES Index Status',
			'ES Index Status',
			'plugin_approve',
			'es-index-status',
			array( $this, 'render' )
		);
	}

	public function render() {
		if ( ! current_user_can( 'plugin_approve' ) ) {
			return;
		}

		$post_ids = $this->get_published_plugin_ids();
		$total    = count( $post_ids );
		?>
		<div class="wrap">
			<h1>ElasticSearch Index Status</h1>
			<p>Checks that all published plugins exist in the ElasticSearch index.</p>
			<p>Total plugins to check: <strong><?php echo number_format_i18n( $total ); ?></strong></p>

			<p>
				<button id="es-check-start" class="button button-primary">Start Check</button>
				<button id="es-check-stop" class="button button-secondary" disabled>Stop</button>
			</p>

			<div id="es-progress" style="display:none;">
				<progress id="es-progress-bar" max="100" value="0" style="width: 100%;"></progress>
				<p id="es-progress-text"></p>
			</div>

			<div id="es-results" style="display:none;">
				<h2>Results</h2>
				<p id="es-results-summary"></p>
				<table id="es-missing-table" class="widefat" style="display:none;">
					<thead>
						<tr>
							<th>Post ID</th>
							<th>Plugin</th>
							<th>Status</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>

		<script>
		jQuery( function( $ ) {
			var allPostIds   = <?php echo wp_json_encode( array_values( $post_ids ) ); ?>,
			    batchSize    = 500,
			    concurrency  = 5,
			    nonce        = <?php echo wp_json_encode( wp_create_nonce( 'es-index-check-batch' ) ); ?>,
			    editBaseUrl  = <?php echo wp_json_encode( admin_url( 'post.php?action=edit&post=' ) ); ?>,
			    stopped, missing, checked, totalBatches;

			function esPost( data ) {
				data._ajax_nonce = nonce;
				return $.post( ajaxurl, data );
			}

			$( '#es-check-start' ).on( 'click', function() {
				stopped = false; missing = []; checked = 0;
				$( '#es-check-start' ).prop( 'disabled', true );
				$( '#es-check-stop' ).prop( 'disabled', false );
				$( '#es-progress' ).show();
				$( '#es-results' ).show();
				$( '#es-results-summary' ).text( '' );
				$( '#es-missing-table tbody' ).empty().end().hide();
				startCheck();
			} );

			$( '#es-check-stop' ).on( 'click', function() {
				stopped = true;
				$( this ).prop( 'disabled', true );
			} );

			function startCheck() {
				var batches = [], queue, running = 0;
				for ( var i = 0; i < allPostIds.length; i += batchSize ) {
					batches.push( allPostIds.slice( i, i + batchSize ) );
				}
				totalBatches = batches.length;
				queue = batches.slice();
				updateStats();

				function next() {
					while ( running < concurrency && queue.length && ! stopped ) {
						running++;
						processBatch( queue.shift() ).always( function() {
							running--;
							if ( ! running && ! queue.length ) onDone();
							else next();
						} );
					}
					if ( ! running && ! queue.length ) onDone();
				}
				next();
			}

			function processBatch( batch ) {
				return esPost( { action: 'es-index-check-batch', post_ids: batch } ).done( function( r ) {
					checked++;
					if ( r.success && r.data.found < r.data.expected ) {
						var foundSet = {};
						r.data.found_ids.forEach( function( id ) { foundSet[ id ] = true; } );
						var candidates = batch.filter( function( id ) { return ! foundSet[ id ]; } );
						if ( candidates.length ) verifyMissing( candidates );
					}
					updateStats();
				} ).fail( function() {
					checked++;
					batch.forEach( function( id ) { addMissing( id, true ); } );
					updateStats();
				} );
			}

			function verifyMissing( candidates ) {
				candidates.forEach( function( postId ) {
					esPost( { action: 'es-index-check-batch', post_ids: [ postId ] } ).done( function( r ) {
						if ( r.success && r.data.found === 0 ) addMissing( postId );
					} ).fail( function() {
						addMissing( postId, true );
					} );
				} );
			}

			function addMissing( postId, error ) {
				missing.push( { id: postId } );
				$( '#es-missing-table' ).show();
				$( '#es-missing-table tbody' ).append(
					'<tr data-id="' + postId + '">' +
						'<td>' + postId + '</td>' +
						'<td><a href="' + editBaseUrl + postId + '">' + postId + '</a>' + ( error ? ' <em>(query failed)</em>' : '' ) + '</td>' +
						'<td class="es-status">-</td>' +
						'<td><button class="button button-small es-reindex-single" data-id="' + postId + '">Reindex</button></td>' +
					'</tr>'
				);
				// Fetch title/status.
				esPost( { action: 'es-index-check-batch', get_post_details: [ postId ] } ).done( function( r ) {
					if ( ! r.success || ! r.data.details[ postId ] ) return;
					var d = r.data.details[ postId ],
					    $row = $( '#es-missing-table tbody tr[data-id="' + postId + '"]' );
					$row.find( 'td:eq(1)' ).html( '<a href="' + d.edit_url + '">' + d.title + '</a>' );
					$row.find( '.es-status' ).text( d.status );
				} );
				updateStats();
			}

			function updateStats() {
				var pct = totalBatches ? Math.round( checked / totalBatches * 100 ) : 0;
				$( '#es-progress-bar' ).val( pct );
				$( '#es-progress-text' ).text(
					'Checked ' + checked + '/' + totalBatches + ' batches (' +
					Math.min( checked * batchSize, allPostIds.length ) + '/' + allPostIds.length + ' plugins). ' +
					missing.length + ' missing.'
				);
				$( '#es-results-summary' ).html(
					missing.length
						? '<strong style="color:#d63638;">' + missing.length + ' plugin(s) missing from the index.</strong>'
						: ( checked >= totalBatches ? 'All ' + allPostIds.length + ' plugins are indexed.' : '' )
				);
			}

			function onDone() {
				$( '#es-progress-bar' ).val( 100 );
				$( '#es-progress-text' ).text( stopped
					? 'Stopped.'
					: 'Done. Checked ' + allPostIds.length + ' plugins. ' + missing.length + ' missing.'
				);
				$( '#es-check-start' ).prop( 'disabled', false );
				$( '#es-check-stop' ).prop( 'disabled', true );
			}

			$( document ).on( 'click', '.es-reindex-single', function() {
				var $btn = $( this ).prop( 'disabled', true ).text( 'Queued...' ),
				    postId = $btn.data( 'id' );

				esPost( { action: 'es-index-reindex', post_id: postId } ).done( function() {
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
						esPost( { action: 'es-index-check-batch', post_ids: [ postId ] } ).done( function( r ) {
							if ( r.success && r.data.found > 0 ) {
								clearInterval( poll );
								clearInterval( countdown );
								$btn.text( 'Indexed' ).removeClass( 'button-secondary' ).addClass( 'button-primary' );
								$( '#es-missing-table tbody tr[data-id="' + postId + '"]' ).css( 'opacity', 0.5 );
								missing = missing.filter( function( m ) { return m.id !== postId; } );
								updateStats();
							} else {
								delay = 15;
								tick();
							}
						} );
					}, 15000 );
				} ).fail( function() {
					$btn.text( 'Failed' ).prop( 'disabled', false );
				} );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * AJAX handler to check a batch of post IDs against the ES index.
	 */
	public function ajax_check_batch() {
		check_ajax_referer( 'es-index-check-batch' );

		if ( ! current_user_can( 'plugin_approve' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		// Sub-action: return post details.
		if ( ! empty( $_POST['get_post_details'] ) ) {
			$details = [];
			foreach ( array_map( 'absint', (array) $_POST['get_post_details'] ) as $id ) {
				$post = get_post( $id );
				if ( $post ) {
					$details[ $id ] = [
						'title'    => get_the_title( $post ),
						'status'   => $post->post_status,
						'edit_url' => get_edit_post_link( $post, 'raw' ),
					];
				}
			}
			wp_send_json_success( [ 'details' => $details ] );
		}

		$post_ids = array_filter( array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) ) );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( 'No post IDs provided.' );
		}

		$found_ids = Elasticsearch::check_post_ids( $post_ids );

		if ( is_wp_error( $found_ids ) ) {
			wp_send_json_error( $found_ids->get_error_message() );
		}

		wp_send_json_success( [
			'found'     => count( $found_ids ),
			'expected'  => count( $post_ids ),
			'found_ids' => $found_ids,
		] );
	}

	/**
	 * AJAX handler to reindex a single post.
	 */
	public function ajax_reindex() {
		check_ajax_referer( 'es-index-check-batch' );

		if ( ! current_user_can( 'plugin_approve' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || 'plugin' !== $post->post_type ) {
			wp_send_json_error( 'Invalid post.' );
		}

		Elasticsearch::reindex_post( $post_id );

		wp_send_json_success( 'Reindex queued.' );
	}

	private function get_published_plugin_ids() {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type = 'plugin'
			AND post_status = 'publish'
			ORDER BY ID ASC"
		) );
	}
}
