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
	 * Use the full width template by default when available
	 */
	public static function filter_get_post_metadata( $check, $object_id, $meta_key, $single ) {
		if ( 'result' !== get_post_type( $object_id )
			|| '_wp_page_template' !== $meta_key ) {
			return $check;
		}
		$template   = 'page-templates/full-width.php';
		$full_width = get_stylesheet_directory() . '/' . $template;
		if ( ! file_exists( $full_width ) ) {
			return $check;
		}
		return $single ? $template : array( $template );
	}

	/**
	 * Filter body classes
	 */
	public static function filter_body_class( $classes ) {
		if ( in_array( 'result-template-full-width', $classes, true ) ) {
			$classes[] = 'page-template-full-width';
		}
		return $classes;
	}

	/**
	 * Filter post classes
	 */
	public static function filter_post_class( $classes ) {
		if ( is_singular( 'result' )
			&& get_the_ID() === get_queried_object_id()
			&& 'result' === get_post_type( get_the_ID() ) ) {
			$classes[] = 'page';
		}
		return $classes;
	}

	/**
	 * Render the data for an individual result within the main content well
	 */
	public static function filter_the_content( $content ) {
		if ( ! is_singular( 'result' )
			|| get_the_ID() !== get_queried_object_id()
			|| 'result' !== get_post_type( get_the_ID() ) ) {
			return $content;
		}

		if ( ! did_action( 'loop_start' ) ) {
			return $content;
		}

		if ( get_queried_object()->post_parent ) {
			$content = ptr_get_template_part(
				'single-result',
				array(
					'report' => get_queried_object(),
				)
			);
		} else {
			$content = ptr_get_template_part(
				'result-set-single',
				array(
					'posts_per_page' => 500,
					'revisions'      => array(
						get_queried_object(),
					),
				)
			);
			$content = '<p><a href="' . esc_url( home_url( 'test-results/' ) ) . '">&larr; Test Results</a></p>' . PHP_EOL . PHP_EOL . $content;
		}

		return $content;
	}

	/**
	 * Render the test results.
	 */
	public static function render_results( $atts ) {
		$current_user = null;
		$output       = '';
		$query_args   = array(
			'posts_per_page' => 20,
			'post_type'      => 'result',
			'post_parent'    => 0,
			'orderby'        => 'post_name',
			'order'          => 'DESC',
		);
		$paged        = isset( $_GET['rpage'] ) ? (int) $_GET['rpage'] : 0;
		if ( $paged ) {
			$query_args['paged'] = $paged;
		}
		if ( isset( $_GET['rper_page'] ) ) {
			$per_page = (int) $_GET['rper_page'];
			if ( $per_page > 1 && $per_page <= 40 ) {
				$query_args['posts_per_page'] = $per_page;
			}
		}
		$rev_query = new WP_Query( $query_args );
		if ( empty( $rev_query->posts ) ) {
			$output .= '<p>No revisions found</p>';
			return $output;
		}
		$output .= self::get_display_css();

		if ($paged <= 1) {
			$output .= self::get_reporter_avatars();
		}

		$output .= ptr_get_template_part(
				'result-set-all',
				array(
						'revisions' => $rev_query->posts,
				)
		);

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
			.ptr-status-badge {
				color: #fff !important;
				text-decoration: none !important;
				display: inline-block;
				padding-left: 8px;
				padding-right: 8px;
				padding-top: 3px;
				padding-bottom: 3px;
				border-radius: 3px;
				font-weight: normal;
			}
			.ptr-status-badge-passed {
				background-color: #39BC00;
			}
			.ptr-status-badge-failed {
				background-color: #CD543A;
			}
			.ptr-status-badge-errored {
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
				margin: 0 5px;
			}
			.pagination-centered ul.pagination li a {
				cursor: pointer;
			}
			.ptr-test-reporter-table th {
				text-align: center;
			}
			.ptr-test-reporter-table td {
				text-align: center;
			}
			.ptr-test-reporter-table td[colspan] {
				text-align: left;
			}
			.ptr-test-reporter-list {
				list-style-type: none;
				margin-left: 0;
				margin-right: 0;
			}
			.ptr-test-reporter-list li {
				display: inline-block;
				vertical-align: top;
				text-align: center;
				margin-left: 10px;
				margin-right: 10px;
				width: 100px;
			}
			.ptr-test-reporter-list.ptr-test-reporter-inactive li {
				width: 75px;
			}
			.ptr-test-reporter-list li h5.avatar-name {
				font-weight: 600;
				margin-top: 6px;
				margin-bottom: 6px;
				text-transform: none;
			}
			.ptr-test-reporter-list.ptr-test-reporter-inactive li h5.avatar-name {
				font-size: 11px;
			}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the avatars who have recently reported.
	 */
	private static function get_reporter_avatars() {
		global $wpdb;

		$output           = '';
		$query_args       = array(
			'posts_per_page'         => 25,
			'post_type'              => 'result',
			'post_parent'            => 0,
			'orderby'                => 'post_name',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
		);
		$query            = new \WP_Query( $query_args );
		$active_reporters = array();
		if ( ! empty( $query->posts ) ) {
			$active_reporters = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type='result' AND post_status='publish' AND post_parent IN (" . implode( ',', $query->posts ) . ')' ); // @codingStandardsIgnoreLine
			$active_reporters = array_map( 'intval', $active_reporters );
			$output          .= '<h3>Active Test Reporters</h3>' . PHP_EOL;
			$users            = get_users(
				array(
					'orderby' => 'display_name',
					'include' => $active_reporters,
				)
			);
			$output          .= self::get_user_list( $users, 'active' );
		}

		$all_time_reporters = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type='result' AND post_status='publish' AND post_parent != 0" ); // @codingStandardsIgnoreLine
		if ( ! empty( $all_time_reporters ) ) {
			$all_time_reporters = array_map( 'intval', $all_time_reporters );
			$users              = get_users(
				array(
					'orderby' => 'display_name',
					'include' => $all_time_reporters,
				)
			);
			foreach ( $users as $i => $user ) {
				if ( in_array( $user->ID, $active_reporters, true ) ) {
					unset( $users[ $i ] );
				}
			}
			if ( ! empty( $users ) ) {
				$output .= '<h4>Registered, but no reports in >25 Revisions</h4>' . PHP_EOL;
			}
			$output .= self::get_user_list( $users, 'inactive' );
		}
		return $output;
	}

	private static function get_user_list( $users, $type ) {
		$output = '<ul class="' . esc_attr( 'ptr-test-reporter-list ptr-test-reporter-' . $type ) . '">';
		foreach ( $users as $user ) {
			$output .= '<li>';
			if ( ! empty( $user->user_url ) ) {
				$output .= '<a target="_blank" rel="nofollow" href="' . esc_url( $user->user_url ) . '">';
			}
			$avatar_size = 'active' === $type ? 82 : 48;
			$output     .= get_avatar( $user->user_email, $avatar_size );
			if ( ! empty( $user->user_url ) ) {
				$output .= '</a>';
			}
			$output .= '<h5 class="avatar-name">';
			if ( ! empty( $user->user_url ) ) {
				$output .= '<a target="_blank" rel="nofollow" href="' . esc_url( $user->user_url ) . '">';
			}
			$output .= $user->display_name;
			if ( ! empty( $user->user_url ) ) {
				$output .= '</a>';
			}
			$output .= '</h5>';
			$output .= '</li>';
		}
		$output .= '</ul>';
		return $output;
	}

	/**
	 * Get the time for display
	 *
	 * @return string
	 */
	public static function get_display_time( $report_id ) {
		$results = get_post_meta( $report_id, 'results', true );
		if ( empty( $results['time'] ) ) {
			return '';
		}
		$minutes = floor( ( (int) ( $results['time'] / 60 ) )% 60 );
		$seconds = ( ( (int) $results['time'] ) % 60 );
		return "{$minutes}m {$seconds}s";
	}

	/**
	 * Get the PHP version for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_php_version( $report_id ) {
		$php_version = 'Unknown';
		$env         = get_post_meta( $report_id, 'env', true );

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
		$env           = get_post_meta( $report_id, 'env', true );
		if ( ! empty( $env['mysql_version'] ) ) {
			$bits          = explode( ',', $env['mysql_version'] );
			$mysql_version = $bits[0];
		}

		return $mysql_version;
	}

	/**
	 * Get the environment name for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_environment_name( $report_id ) {
		$env_name = get_post_meta( $report_id, 'environment_name', true );

		if ( ! empty( $env_name ) ) {
			return esc_html( $env_name );
		}

		return 'Unknown';
	}

	/**
	 * Get the test reporter's display name.
	 *
	 * @param integer $reporter_id Reporter's user ID.
	 * @return string
	 */
	public static function get_display_reporter_name( $reporter_id ) {
		$reporter = new \WP_User( $reporter_id );

		if ( empty( $reporter->display_name ) ) {
			return esc_html( $reporter->display_name );
		}

		return $reporter->user_nicename;
	}

	/**
	 * Get the extensions list for display
	 *
	 * @param integer $report_id Report ID.
	 * @return string
	 */
	public static function get_display_extensions( $report_id ) {
		$extensions = array();
		$env        = get_post_meta( $report_id, 'env', true );
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
		global $wp;
		$bignum    = 999999999;
		$base_link = home_url( trailingslashit( $wp->request ) );
		if ( isset( $_GET['rper_page'] ) ) {
			$base_link = add_query_arg( 'rper_page', (int) $_GET['rper_page'], $base_link );
		}
		$base_link       = add_query_arg( 'rpage', '%#%', $base_link );
		$max_num_pages   = $query->max_num_pages;
		$current_page    = max( 1, $query->get( 'paged' ) );
		$prev_page_label = '&lsaquo;';
		$next_page_label = '&rsaquo;';
		$args            = array(
			'base'      => $base_link,
			'format'    => '',
			'current'   => $current_page,
			'total'     => $max_num_pages,
			'prev_text' => $prev_page_label,
			'next_text' => $next_page_label,
			'type'      => 'array',
			'end_size'  => 1,
			'mid_size'  => 2,
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
