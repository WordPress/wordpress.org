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
			<input id="original" type="text" name="search" required value="<?php echo gp_esc_attr_with_entities( $search ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gp_esc_attr_with_entities() escapes the value for an attribute and double-encodes existing entities so they render literally. ?>" class="consistency-form-search" placeholder="Enter original to search for&hellip;">
		</span>

		<span class="consistency-field">
			<label for="set">Locale</label>
			<?php
			$locale_options = [
				'' => 'Select a locale',
			];
			$sets_to_hide   = array(
				'ca/valencia',
				'en/formal',
				'en/default',
				'fr/formal',
				'sr/latin',
			);
			$sets           = array_diff_key( $sets, array_flip( $sets_to_hide ) );
			$locale_options = $locale_options + $sets;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GlotPress escapes select attributes and option labels.
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
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GlotPress escapes select attributes and option labels.
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
	$translations_unique_count  = count( $translations_unique );
	$has_different_translations = $translations_unique_count > 1;
	if ( ! $has_different_translations ) {
		echo '<div class="notice"><p>All originals have the same translations.</p></div>';
	} else {
		echo '<div id="translations-overview" class="notice wporg-notice-warning"><p>There are ' . esc_html( $translations_unique_count ) . ' different translations. <a id="toggle-translations-unique" href="#show">View</a></p>';
		echo '<ul class="translations-unique hidden">';
		foreach ( $translations_unique_counts as $translation => $count ) {
			printf(
				'<li>%s <small>(%s)</small> <a class="anchor-jumper with-tooltip" aria-label="Go to translation" href="#%s">&darr;</a></li>',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
				str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) ),
				esc_html( 1 === $count ? $count . ' time' : $count . ' times' ),
				esc_attr( 't-' . md5( $translation ) )
			);
		}
		echo '</ul>';
		echo '</div>';
	}

	$results_by_translation = [];
	foreach ( $results as $row ) {
		$results_by_translation[ $row->translation ][] = $row;
	}

	$project_cache = [];
	?>
	<table class="gp-table consistency-table">
		<thead>
			<tr>
				<th>Original</th>
				<th>Translation</th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $translations_unique as $translation_index => $translation ) {
			$prev_translation = $translations_unique[ $translation_index - 1 ] ?? false;
			$next_translation = $translations_unique[ $translation_index + 1 ] ?? false;

			$prev_arrow = $prev_translation
				? '<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="' . esc_attr( '#t-' . md5( $prev_translation ) ) . '">&uarr;</a>'
				: '';

			$next_arrow = $next_translation
				? '<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="' . esc_attr( '#t-' . md5( $next_translation ) ) . '">&darr;</a>'
				: '';

			printf(
				'<tr id="%s" class="new-translation"><th colspan="2" scope="rowgroup"><strong>%s</strong> %s %s</th></tr>',
				esc_attr( 't-' . md5( $translation ) ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
				esc_translation( $translation ),
				wp_kses_post( $next_arrow ),
				wp_kses_post( $prev_arrow )
			);

			$matching_results = $results_by_translation[ $translation ] ?? [];

			foreach ( $matching_results as $result ) {
				if ( ! isset( $project_cache[ $result->project_id ] ) ) {
					$p_name      = $result->project_name;
					$p_parent_id = $result->project_parent_id;
					$top_parent  = null;
					$is_active   = true;

					while ( $p_parent_id ) {
						$parent_project = GP::$project->get( $p_parent_id );
						if ( ! $parent_project ) {
							break;
						}

						$top_parent     = $parent_project;
						$p_parent_id    = $parent_project->parent_project_id;
						$p_name         = "{$parent_project->name} - {$p_name}";
						$is_active      = $is_active && $parent_project->active;
					}

					$project_cache[ $result->project_id ] = [
						'name'      => $p_name,
						'is_active' => $is_active,
						'css_class' => isset( $top_parent->name ) ? sanitize_title( 'project-' . $top_parent->name ) : '',
					];
				}

				$cached_project = $project_cache[ $result->project_id ];

				$original_context = '';
				if ( $result->original_context ) {
					$original_context = sprintf(
						' <span class="context">%s</span>',
						esc_translation( $result->original_context )
					);
				}

				if ( $cached_project['is_active'] ) {
					$active_text = '';
				} else {
					$active_text = sprintf(
						' <span class="dashicons dashicons-flag"></span><span class="inactive">%s</span>',
						esc_translation( '(inactive)' )
					);
				}

				$project_url = sprintf( '/projects/%s/%s/', $result->project_path, $set );
				$source_url  = sprintf(
					'/projects/%s/%s/?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d',
					$result->project_path,
					$set,
					(int) $result->original_id,
					(int) $result->translation_id
				);

				printf(
					'<tr class="%s"><td>%s</td><td>%s</td></tr>',
					esc_attr( $cached_project['css_class'] ),
					sprintf(
						'<div class="string">%s%s</div>
						<div class="meta">Project: <a href="%s">%s</a>%s</div>',
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
						esc_translation( $result->original_singular ),
						wp_kses_post( $original_context ),
						esc_url( $project_url ),
						esc_html( $cached_project['name'] ),
						wp_kses_post( $active_text )
					),
					sprintf(
						'<div class="string%s">%s</div>
						<div class="meta">
							<a href="%s">Source</a> |
							Added: %s
						</div>',
						$locale_is_rtl ? ' rtl' : '',
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
						esc_translation( $result->translation ),
						esc_url( $source_url ),
						esc_html( $result->translation_added )
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
			var $list = $( '.translations-unique' );
			$list.toggleClass( 'hidden' );
			$( this ).text( $list.hasClass( 'hidden' ) ? 'View' : 'Hide' );
		});

	});
</script>

<?php gp_tmpl_footer();
