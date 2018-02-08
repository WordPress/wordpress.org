<?php
gp_title( 'Translation Consistency &lt; GlotPress' );
$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', 'Locales' );
$breadcrumb[] = 'Translation Consistency';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();
?>

<p>Analyze translation consistency across projects. The result is limited to 500 translations.</p>


<form action="/consistency" method="get" class="consistency-form">
	<p class="consistency-fields">
		<span class="consistency-field">
			<label for="original">Original</label>
			<input id="original" type="text" name="search" required value="<?php echo gp_esc_attr_with_entities( $search ); ?>" class="consistency-form-search" placeholder="Enter original to search for&hellip;">
		</span>

		<span class="consistency-field">
			<label for="set">Locale</label>
			<?php
			$locale_options = [
				'' => 'Select a locale',
			];
			$locale_options = array_merge( $locale_options, $sets );
			echo gp_select(
				'set',
				$locale_options,
				$set,
				[
					'class'    => 'consistency-form-locale',
					'required' => 'required',
				]
			);
			?>
		</span>

		<span class="consistency-field">
			<label for="project">Project</label>
			<?php
			$project_options = [
				'' => 'All Projects',
			];
			$project_options = $project_options + $projects;
			echo gp_select(
				'project',
				$project_options,
				$project,
				[
					'class' => 'consistency-form-project',
				]
			);
			?>
		</span>
	</p>

	<p>
		<label>
			<input type="checkbox" name="search_case_sensitive" value="1"<?php checked( $search_case_sensitive ); ?>>
			Case Sensitive
		</label>
	</p>

	<p>
		<button type="submit" class="consistency-form-submit">Analyze</button>
	</p>
</form>

<?php
if ( $performed_search && ! $results ) {
	echo '<p class="notice">No results were found.</p>';

} elseif ( $performed_search && $results ) {
	$translations_unique_count = count( $translations_unique );
	$has_different_translations = $translations_unique_count > 1;
	if ( ! $has_different_translations ) {
		echo '<p class="notice">All originals have the same translations</p>';
	} else {
		echo '<div id="translations-overview" class="notice wporg-notice-warning"><p>There are ' . $translations_unique_count . ' different translations. <a id="toggle-translations-unique" href="#show">View</a></p>';
		echo '<ul class="translations-unique hidden">';
		foreach ( $translations_unique as $translation ) {
			printf(
				'<li>%s <small>(%s)</small> <a href="#%s">&darr;</a></li>',
				str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) ),
				1 === $translations_unique_counts[ $translation ] ? $translations_unique_counts[ $translation ] . ' time' : $translations_unique_counts[ $translation ] . ' times',
				esc_attr( 't-' . md5( $translation ) )
			);
		}
		echo '</ul>';
		echo '</div>';
	}

	?>
	<table class="consistency-table">
		<thead>
			<th>Original</th>
			<th>Translation</th>
		</thead>
		<tbody>
			<?php
			$previous_translation = '';

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

				if ( $has_different_translations && $previous_translation !== $result->translation ) {
					$previous_translation = $result->translation;
					printf(
						'<tr id="%s" class="new-translation"><td colspan="2"><strong>%s</strong> <a href="#translations-overview">&uarr;</a></td></tr>',
						esc_attr( 't-' . md5( $result->translation ) ),
						esc_translation( $result->translation )
					);
				}

				printf(
					'<tr class="%s"><td>%s</td><td>%s</td></tr>',
					isset( $parent_project->name ) ? sanitize_title( 'project-' . $parent_project->name ) : '',
					sprintf(
						'<div class="string">%s%s</div>
						<div class="meta">Project: <a href="/projects/%s">%s</a></div>',
						esc_translation( $result->original_singular ),
						$original_context,
						$result->project_path,
						$project_name
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
					)
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
