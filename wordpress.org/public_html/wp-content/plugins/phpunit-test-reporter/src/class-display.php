<?php

namespace PTR;

use WP_Query;

class Display {

	/**
	 * Register the shortcode.
	 */
	public static function action_init_register_shortcode() {
		add_shortcode( 'ptr-results', array( __CLASS__, 'render_results' ) );
	}

	/**
	 * Filter post classes
	 */
	public static function filter_post_class( $classes ) {
		if ( is_singular( 'result' ) ) {
			$classes[] = 'page';
		}
		return $classes;
	}

	/**
	 * Render the data for an individual result within the main content well
	 */
	public static function filter_the_content( $content ) {
		if ( ! is_singular( 'result' ) ) {
			return $content;
		}

		if ( get_queried_object()->post_parent ) {
			$content = ptr_get_template_part( 'single-result', array(
				'report' => get_queried_object(),
			) );
		} else {
			$content = ptr_get_template_part( 'result-set', array(
				'revisions' => array(
					get_queried_object(),
				),
			) );
		}

		return $content;
	}

	/**
	 * Render the test results.
	 */
	public static function render_results( $atts ) {

		$output = '<h2>PHPUnit Test Results</h2>' . PHP_EOL . PHP_EOL;
		$query_args = array(
			'posts_per_page'   => 5,
			'post_type'        => 'result',
			'post_parent'      => 0,
			'orderby'          => 'post_name',
			'order'            => 'DESC',
		);
		$paged = get_query_var( 'paged' );
		if ( $paged ) {
			$query_args['paged'] = $paged;
		}
		$rev_query = new WP_Query( $query_args );
		if ( empty( $rev_query->posts ) ) {
			$output .= '<p>No revisions found</p>';
			return $output;
		}
		$output .= self::get_display_css();
		$output .= ptr_get_template_part( 'result-set', array(
			'revisions' => $rev_query->posts,
		) );
		ob_start();
		self::pagination( $rev_query );
		$output .= ob_get_clean();
		return $output;
	}

	/**
	 * Get the CSS needed for display
	 *
	 * @return string
	 */
	public static function get_display_css() {
		ob_start();
		?>
		<style>
			a.ptr-status-badge {
				color: #FFF;
				display: inline-block;
				padding-left: 8px;
				padding-right: 8px;
				padding-top: 3px;
				padding-bottom: 3px;
				border-radius: 3px;
				font-weight: normal;
			}
			a.ptr-status-badge-passed {
				background-color: #39BC00;
			}
			a.ptr-status-badge-failed {
				background-color: #CD543A;
			}
			a.ptr-status-badge-errored {
				background-color: #909090;
			}
			.pagination-centered {
				text-align: center;
			}
			.pagination-centered ul.pagination {
				list-style-type: none;
			}
			.pagination-centered ul.pagination li {
				display: inline-block;
			}
			.pagination-centered ul.pagination li a {
				cursor: pointer;
			}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the PHP version for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_php_version( $report_id ) {
		$php_version = 'Unknown';
		$env = get_post_meta( $report_id, 'env', true );
		if ( ! empty( $env['php_version'] ) ) {
			$php_version = 'PHP ' . $env['php_version'];
		}
		return $php_version;
	}

	/**
	 * Get the database version for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_mysql_version( $report_id ) {
		$mysql_version = 'Unknown';
		$env = get_post_meta( $report_id, 'env', true );
		if ( ! empty( $env['mysql_version'] ) ) {
			$bits = explode( ',', $env['mysql_version'] );
			$mysql_version = $bits[0];
		}
		return $mysql_version;
	}

	/**
	 * Get the extensions list for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_extensions( $report_id ) {
		$extensions = array();
		$env = get_post_meta( $report_id, 'env', true );
		if ( ! empty( $env['php_modules'] ) ) {
			foreach ( $env['php_modules'] as $module => $version ) {
				if ( ! empty( $version ) ) {
					$extensions[] = $module . ' (' . $version . ')';
				}
			}
		}
		if ( ! empty( $env['system_utils'] ) ) {
			foreach ( $env['system_utils'] as $module => $version ) {
				if ( ! empty( $version ) ) {
					$extensions[] = $module . ' (' . $version . ')';
				}
			}
		}
		return implode( ', ', $extensions );
	}

	private static function pagination( $query ) {
		$bignum = 999999999;
		$base_link = str_replace( $bignum, '%#%', esc_url( get_pagenum_link( $bignum ) ) );
		$max_num_pages = $query->max_num_pages;
		$current_page = max( 1, $query->get( 'paged' ) );
		$prev_page_label = '&lsaquo;';
		$next_page_label = '&rsaquo;';
		$args = array(
			'base'          => $base_link,
			'format'        => '',
			'current'       => $current_page,
			'total'         => $max_num_pages,
			'prev_text'     => $prev_page_label,
			'next_text'     => $next_page_label,
			'type'          => 'array',
			'end_size'      => 1,
			'mid_size'      => 2,
		);

		if ( $max_num_pages <= 1 ) {
			return;
		}

		$pagination_links = paginate_links( $args );

		if ( ! empty( $pagination_links ) ) {

			if ( 1 === $current_page ) {
				array_unshift( $pagination_links, '<span class="prev page-numbers">' . esc_html( $prev_page_label ) . '</span>' );
			} elseif ( $current_page >= $max_num_pages ) {
				array_push( $pagination_links, '<span class="next page-numbers">' . esc_html( $next_page_label ) . '</span>' );
			}

			echo '<nav class="pagination-centered">';

			echo '<ul class="pagination">';
			foreach ( $pagination_links as $paginated_link ) {
				// $paginated_link contains arbitrary HTML
				echo '<li>' . $paginated_link . '</li>';
			}
			echo '</ul>';

			echo '</nav>';
		}

	}

}
