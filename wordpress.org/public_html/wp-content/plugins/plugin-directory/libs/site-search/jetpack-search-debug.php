<?php

add_filter('debug_bar_panels', function($panels) {
	class Debug_Bar_Search extends Debug_Bar_Panel {
		function init() {
			$this->title( __('Search', 'debug-bar') );
		}

		function prerender() {
			$this->set_visible( is_search() && class_exists( 'Jetpack_Search' ) );
		}

		function render() {

			$search = Jetpack_Search::instance();

			echo "<div id='debug-bar-jetpack-search'>";


			echo '<h3>', __( 'Elasticsearch Query:', 'debug-bar' ), '</h3>';
			echo '<pre>' . esc_html( var_export( $search->get_search_query(), true ) ) . '</pre>';

			echo '<h3>', __( 'Elasticsearch Result:', 'debug-bar' ), '</h3>';
			echo '<pre>' . esc_html( var_export( $search->get_search_result(), true ) ) . '</pre>';

			echo '</div>';
		}
	}

	$panels[] = new Debug_Bar_Search();
	return $panels;
});
