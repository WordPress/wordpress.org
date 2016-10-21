<?php

/**
 * Template for the site filters
 */

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

?>

<script id="tmpl-wcsc-site-filters" type="text/html">
	<div class="wcsc-filter">
		<label for="wcsc-filter-search-input">
			<span class="customize-control-title">
				<?php esc_html_e( 'Search', 'wordcamporg' ); ?>
			</span>

			<div class="customize-control-content">
				<input type="search" id="wcsc-filter-search-input" class="wcsc-filter-search" />
			</div>
		</label>
	</div>

	<div class="wcsc-filter">
		<label for="wcsc-filter-theme_slug">
			<span class="customize-control-title">
				<?php esc_html_e( 'Theme', 'wordcamporg' ); ?>
			</span>

			<div class="customize-control-content">
				<select id="wcsc-filter-theme_slug" data-filter="theme_slug">
					<option value="">Any</option>

					<# _.each( data.themeOptions, function( themeOption ) { #>
						<option value="{{themeOption.slug}}">{{themeOption.name}}</option>
					<# }); #>
				</select>
			</div>
		</label>
	</div>

	<div class="wcsc-filter">
		<label for="wcsc-filter-year">
			<span class="customize-control-title">
				<?php esc_html_e( 'WordCamp Year', 'wordcamporg' ); ?>
			</span>

			<div class="customize-control-content">
				<select id="wcsc-filter-year" data-filter="year">
					<option value="">Any</option>

					<# _.each( data.yearOptions, function( yearOption ) { #>
						<option value="{{yearOption}}">{{yearOption}}</option>
					<# }); #>
				</select>
			</div>
		</label>
	</div>

	<div class="wcsc-filter">
		<label for="wcsc-filter-css_preprocessor">
			<span class="customize-control-title">
				<?php esc_html_e( 'CSS Preprocessor', 'wordcamporg' ); ?>
			</span>

			<div class="customize-control-content">
				<select id="wcsc-filter-css_preprocessor" data-filter="css_preprocessor">
					<option value="">Any</option>

					<# _.each( data.preprocessorOptions, function( preprocessorOption ) { #>
						<option value="{{preprocessorOption}}">{{preprocessorOption}}</option>
					<# }); #>
				</select>
			</div>
		</label>
	</div>
</script>
