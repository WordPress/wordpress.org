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
			$sets_to_hide = array(
				'ca/valencia',
				'nl/formal/default',
				'en/formal',
				'en/default',
				'fr/formal',
				'de/formal/default',
				'de-ch/info/default',
				'pt/ao90/default',
				'sr/latin',
				'sr/latin/latin',
			);
			$sets = array_diff_key( $sets, array_flip( $sets_to_hide ) );
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
		<button type="submit" class="button is-primary consistency-form-submit">Analyze</button>
	</p>
</form>

<?php
if ( $performed_search && ! $results ) {
	echo '<div class="notice"><p>No results were found.</p></div>';

} elseif ( $performed_search && $results ) {
	$translations_unique_count = count( $translations_unique );
	$has_different_translations = $translations_unique_count > 1;
	if ( ! $has_different_translations ) {
		echo '<div class="notice"><p>All originals have the same translations.</p></div>';
	} else {
		echo '<div id="translations-overview" class="notice wporg-notice-warning"><p>There are ' . $translations_unique_count . ' different translations. <a id="toggle-translations-unique" href="#show">View</a></p>';
		echo '<ul class="translations-unique hidden">';
		foreach ( $translations_unique_counts as $translation => $count ) {
			printf(
				'<li>%s <small>(%s)</small> <a class="anchor-jumper with-tooltip" aria-label="Go to translation" href="#%s">&darr;</a></li>',
				str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) ),
				1 === $count ? $count . ' time' : $count . ' times',
				esc_attr( 't-' . md5( $translation ) )
			);
		}
		echo '</ul>';
		echo '</div>';
	}

	?>
	<table class="gp-table consistency-table">
		<thead>
			<th>Original</th>
			<th>Translation</th>
		</thead>
		<tbody>
		<?php
		$translations = array_keys( $translations_unique_counts );
		foreach ( $translations as $translation_index => $translation ) {
			$prev_arrow = '';
			$next_arrow = '';

			$prev_translation = $translations[ $translation_index - 1 ] ?? false;
			$next_translation = $translations[ $translation_index + 1 ] ?? false;

			if ( ! $prev_translation ) {
				$next_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="' . esc_attr( '#t-' . md5( $next_translation ) ) . '">&darr;</a>';
			} elseif ( ! $next_translation ) {
				$prev_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="' . esc_attr( '#t-' . md5( $prev_translation ) ) . '">&uarr;</a>';
			} else {
				$prev_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="' . esc_attr( '#t-' . md5( $prev_translation ) ) . '">&uarr;</a>';
				$next_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="' . esc_attr( '#t-' . md5( $next_translation ) ) . '">&darr;</a>';
			}

			printf(
				'<tr id="%s" class="new-translation"><th colspan="2"><strong>%s</strong> %s %s</th></tr>',
				esc_attr( 't-' . md5( $translation ) ),
				esc_translation( $translation ),
				$next_arrow,
				$prev_arrow
			);

			foreach ( $results as $result ) {
				if ( $result->translation != $translation ) {
					continue;
				}

				$project_name = $result->project_name;
				$parent_project_id = $result->project_parent_id;
				$is_active = $result->active;
				while ( $parent_project_id ) {
					$parent_project = GP::$project->get( $parent_project_id );
					$parent_project_id = $parent_project->parent_project_id;
					$project_name = "{$parent_project->name} - {$project_name}";
					$is_active = $is_active && $parent_project->active;
				}

				$original_context = '';
				if ( $result->original_context ) {
					$original_context = sprintf(
						' <span class="context">%s</span>',
						esc_translation( $result->original_context )
					);
				}

				if( $is_active ) {
					$active_text = '';
				} else {
					$active_text = sprintf(
						' <span class="dashicons dashicons-flag"></span><span class="inactive">%s</span>',
						esc_translation( '(inactive)' )
					);
				}

				printf(
					'<tr class="%s"><td>%s</td><td>%s</td></tr>',
					isset( $parent_project->name ) ? sanitize_title( 'project-' . $parent_project->name ) : '',
					sprintf(
						'<div class="string">%s%s</div>
						<div class="meta">Project: <a href="/projects/%s/%s/">%s</a>%s</div>',
						esc_translation( $result->original_singular ),
						$original_context,
						$result->project_path,
						$set,
						$project_name,
						$active_text
				),
					sprintf(
						'<div class="string%s">%s</div>
						<div class="meta">
							<a href="/projects/%s/%s/?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d">Source</a> |
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
