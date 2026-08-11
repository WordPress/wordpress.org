<?php
/**
 * Project page for the Meta project family.
 *
 * Mirrors GlotPress's project template, except that the locale name links to
 * the locale's contributors page rather than into the editor, matching what
 * the wp-plugins and wp-themes project pages already do.
 *
 * @see https://meta.trac.wordpress.org/ticket/8396
 *
 * @package wporg-gp-customizations
 */

gp_title(
	sprintf(
		/* translators: %s: Project name. */
		__( '%s project < GlotPress', 'glotpress' ),
		esc_html( $project->name )
	)
);
gp_breadcrumb_project( $project );
gp_enqueue_scripts( array( 'gp-common', 'tablesorter' ) );
gp_tmpl_header();
?>

<h2 class="project-name"><?php echo esc_html( $project->name ); ?></h2>

<?php if ( $project->description ) : ?>
	<div class="project-description"><?php echo $project->description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Project descriptions allow markup. ?></div>
<?php endif; ?>

<?php if ( $translation_sets ) : ?>
<div id="translation-sets">
	<table class="gp-table translation-sets">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Locale', 'glotpress' ); ?></th>
				<th class="stats percent"><?php esc_html_e( 'Percent', 'glotpress' ); ?></th>
				<th class="stats translated"><?php esc_html_e( 'Translated', 'glotpress' ); ?></th>
				<th class="stats fuzzy"><?php esc_html_e( 'Fuzzy', 'glotpress' ); ?></th>
				<th class="stats untranslated"><?php esc_html_e( 'Untranslated', 'glotpress' ); ?></th>
				<th class="stats waiting"><?php esc_html_e( 'Waiting', 'glotpress' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $translation_sets as $set ) :
			// The contributors page for this locale. It is generated for every
			// project path, Meta included, and is what this template exists to
			// surface -- see #8396.
			$contributors_url = gp_url( gp_url_join( 'locale', $set->locale, $set->slug, $project->path ) );
			$editor_url       = gp_url_project( $project, gp_url_join( $set->locale, $set->slug ) );
			?>
			<tr>
				<td>
					<strong><?php gp_link( $contributors_url, $set->name_with_locale ); ?></strong>
					<?php if ( $set->current_count && $set->all_count && $set->current_count >= $set->all_count * 0.9 ) : ?>
						<span class="bubble morethan90"><?php echo esc_html( number_format_i18n( floor( $set->current_count / $set->all_count * 100 ) ) ); ?>%</span>
					<?php endif; ?>
				</td>
				<td class="stats percent"><?php echo esc_html( number_format_i18n( $set->percent_translated ) ); ?>%</td>
				<td class="stats translated" title="<?php esc_attr_e( 'translated', 'glotpress' ); ?>">
					<?php gp_link( gp_url_join( $editor_url, array( 'filters[status]' => 'current' ) ), number_format_i18n( $set->current_count ) ); ?>
				</td>
				<td class="stats fuzzy" title="<?php esc_attr_e( 'fuzzy', 'glotpress' ); ?>">
					<?php gp_link( gp_url_join( $editor_url, array( 'filters[status]' => 'fuzzy' ) ), number_format_i18n( $set->fuzzy_count ) ); ?>
				</td>
				<td class="stats untranslated" title="<?php esc_attr_e( 'untranslated', 'glotpress' ); ?>">
					<?php gp_link( gp_url_join( $editor_url, array( 'filters[status]' => 'untranslated' ) ), number_format_i18n( $set->untranslated_count ) ); ?>
				</td>
				<td class="stats waiting">
					<?php gp_link( gp_url_join( $editor_url, array( 'filters[status]' => 'waiting' ) ), number_format_i18n( $set->waiting_count ) ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php if ( $sub_projects ) : ?>
<div id="sub-projects">
	<h3><?php esc_html_e( 'Projects', 'glotpress' ); ?></h3>
	<ul>
		<?php foreach ( $sub_projects as $sub_project ) : ?>
			<li>
				<?php gp_link_project( $sub_project, esc_html( $sub_project->name ) ); ?>
				<?php if ( $sub_project->description ) : ?>
					<span class="description"><?php echo esc_html( gp_html_excerpt( $sub_project->description, 111 ) ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<script type="text/javascript">
	jQuery( function( $ ) {
		$( '.translation-sets' ).tablesorter( { sortList: [ [ 1, 1 ] ] } );
	} );
</script>

<?php gp_tmpl_footer(); ?>
