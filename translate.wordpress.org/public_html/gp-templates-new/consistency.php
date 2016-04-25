<?php
gp_title( 'Translation Consistency &lt; GlotPress' );
$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', 'Locales' );
$breadcrumb[] = 'Translation Consistency';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();
?>

<p>Analyze translation consistency across projects.</p>


<form action="" method="get" class="consistency-form">
	<p>
		<input type="text" name="search" required value="<?php echo esc_attr( $search ); ?>" class="consistency-form-search" placeholder="Enter original to search for&hellip;">
		<?php
		$options     = [];
		$options[''] = 'Select a locale';
		$options     = array_merge( $options, $sets );
		echo gp_select( 'set', $options, $set, [ 'class' => 'consistency-form-locale', 'required' => 'required' ] );
		?>
		<button type="submit" class="consistency-form-submit">Search</button>
	</p>

	<p class="consistency-form-filters">
		<label>
			<input type="checkbox" name="search_case_senstive" value="1"<?php checked( $search_case_senstive ); ?>>
			Case Sensitive
		</label>
		<label>
			<input type="checkbox" name="search_fuzzy" value="1"<?php checked( $search_fuzzy ); ?>>
			Fuzzy Search (<em>term*</em>)
		</label>
	</p>

</form>

<?php
if ( $performed_search && ! $results ) {
	echo '<p class="notice">No results were found.</p>';

} elseif ( $performed_search && $results ) {
	if ( ! $search_fuzzy && $translations_unique ) {
		$translations_unique_count = count( $translations_unique );
		if ( 1 === $translations_unique_count ) {
			echo '<p class="notice">All originals have the same translations</p>';
		} else {
			echo '<div class="notice wporg-notice-warning"><p>There are ' . $translations_unique_count . ' different translations. <a id="toggle-translations-unique" href="#show">View</a></p>';
			echo '<ul class="translations-unique hidden">';
			foreach ( $translations_unique as $translation ) {
				printf(
					'<li data-id="%s">%s</li>',
					md5( $translation ),
					str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) )
				);
			}
			echo '</ul>';
			echo '</div>';
		}
	}

	?>
	<table class="consistency-table">
		<thead>
			<th>Original</th>
			<th>Translation</th>
		</thead>
		<tbody>
			<?php
			foreach ( $results as $result ) {
				$project_name = $result->project_name;
				$parent_project_id = $result->project_parent_id;
				while ( $parent_project_id ) {
					$parent_project = GP::$project->get( $parent_project_id );
					$parent_project_id = $parent_project->parent_project_id;
					$project_name = "{$parent_project->name} - {$project_name}";
				}

				$original_context = '';
				if ( $result->original_context ) {
					$original_context = sprintf(
						' <span class="context">%s</span>',
						esc_translation( $result->original_context )
					);
				}

				$search_link = '';
				if ( $search_fuzzy ) {
					$search_link = sprintf(
						' | <a href="%s">Search</a>',
						esc_url( add_query_arg( [
							'search'               => urlencode( $result->original_singular ),
							'search_fuzzy'         => 0,
							'search_case_senstive' => 1,
						] ) )
					);
				}

				printf(
					'<tr><td>%s</td><td>%s</td></tr>',
					sprintf(
						'<div class="string">%s%s</div>
						<div class="meta">Project: <a href="/projects/%s">%s</a>%s</div>',
						esc_translation( $result->original_singular ),
						$original_context,
						$result->project_path,
						$project_name,
						$search_link
					),
					sprintf(
						'<div class="string%s">%s</div>
						<div class="meta">
							<a href="/projects/%s/%s?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d">Source</a> |
							Added: %s
						</div>',
						$locale_is_rtl ? ' rtl' : '',
						esc_translation( $result->translation ),
						$result->project_path,
						$set,
						$result->original_id,
						$result->translation_id,
						$result->translation_added
					),
					esc_translation( $result->translation )
				);
			}
			?>
		</tbody>
	</table>
	<?php
}
?>

<script>
	jQuery( document ).ready( function( $ ) {
		$( '#toggle-translations-unique' ).on( 'click', function( event ) {
			event.preventDefault();
			$( '.translations-unique' ).toggleClass( 'hidden' );
		});

	});
</script>

<?php gp_tmpl_footer();
