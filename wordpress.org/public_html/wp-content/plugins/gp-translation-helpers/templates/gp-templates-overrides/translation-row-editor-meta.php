<?php
/**
 * Template for the meta section of the editor row in a translation set display
 *
 * @package    GlotPress
 * @subpackage Templates
 */

$more_links = array();
if ( $translation->translation_status ) {
	$translation_permalink = gp_url_project_locale(
		$project,
		$locale->slug,
		$translation_set->slug,
		array(
			'filters[status]'         => 'either',
			'filters[original_id]'    => $translation->original_id,
			'filters[translation_id]' => $translation->id,
		)
	);

	$more_links['translation-permalink'] = '<a tabindex="-1" href="' . esc_url( $translation_permalink ) . '">' . __( 'Permalink to this translation', 'glotpress' ) . '</a>';
} else {
	$original_permalink = gp_url_project_locale( $project, $locale->slug, $translation_set->slug, array( 'filters[original_id]' => $translation->original_id ) );

	$more_links['original-permalink'] = '<a tabindex="-1" href="' . esc_url( $original_permalink ) . '">' . __( 'Permalink to this original', 'glotpress' ) . '</a>';
}

$original_history = gp_url_project_locale(
	$project,
	$locale->slug,
	$translation_set->slug,
	array(
		'filters[status]'      => 'either',
		'filters[original_id]' => $translation->original_id,
		'sort[by]'             => 'translation_date_added',
		'sort[how]'            => 'asc',
	)
);

$more_links['history'] = '<a tabindex="-1" href="' . esc_url( $original_history ) . '">' . __( 'All translations of this original', 'glotpress' ) . '</a>';

/**
 * Allows to modify the more links in the translation editor.
 *
 * @since 2.3.0
 *
 * @param array $more_links The links to be output.
 * @param GP_Project $project Project object.
 * @param GP_Locale $locale Locale object.
 * @param GP_Translation_Set $translation_set Translation Set object.
 * @param GP_Translation $translation Translation object.
 */
$more_links = apply_filters( 'gp_translation_row_template_more_links', $more_links, $project, $locale, $translation_set, $translation );
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div class="meta" id="sidebar-div-meta-' . $translation->row_id . '"  data-row-id="' . $translation->row_id . '">';
?>
		<h3><?php _e( 'Meta', 'glotpress' ); ?></h3>

		<?php gp_tmpl_load( 'translation-row-editor-meta-status', get_defined_vars() ); ?>

		<?php if ( $translation->context ) : ?>
			<dl>
				<dt><?php _e( 'Context:', 'glotpress' ); ?></dt>
				<dd><?php echo esc_translation( $translation->context ); ?></dd>
			</dl>
		<?php endif; ?>
		<?php if ( $translation->extracted_comments ) : ?>
			<dl>
				<dt><?php _e( 'Comment:', 'glotpress' ); ?></dt>
				<dd>
					<?php
					/**
					 * Filters the extracted comments of an original.
					 *
					 * @param string         $extracted_comments Extracted comments of an original.
					 * @param GP_Translation $translation        Translation object.
					 */
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo apply_filters( 'gp_original_extracted_comments', $translation->extracted_comments, $translation );
					?>
				</dd>
			</dl>
		<?php endif; ?>
		<?php if ( $translation->translation_added && '0000-00-00 00:00:00' !== $translation->translation_added ) : ?>
			<dl>
				<dt><?php _e( 'Date added (GMT):', 'glotpress' ); ?></dt>
				<dd id="gmt-date-added-<?php echo esc_attr( $translation->row_id ); ?>"><?php echo esc_html( $translation->translation_added ); ?></dd>
			</dl>
			<dl>
				<dt><?php _e( 'Date added (local):', 'glotpress' ); ?></dt>
				<dd id="local-date-added-<?php echo esc_attr( $translation->row_id ); ?>"><?php _e( 'Calculating...', 'glotpress' ); ?></dd>
			</dl>
		<?php endif; ?>
		<?php if ( $translation->user ) : ?>
			<dl>
				<dt><?php _e( 'Translated by:', 'glotpress' ); ?></dt>
				<dd><?php gp_link_user( $translation->user ); ?></dd>
			</dl>
		<?php endif; ?>
		<?php if ( $translation->user_last_modified && ( ! $translation->user || $translation->user->ID !== $translation->user_last_modified->ID ) ) : ?>
			<dl>
				<dt>
					<?php
					if ( 'current' === $translation->translation_status ) {
						_e( 'Approved by:', 'glotpress' );
					} elseif ( 'rejected' === $translation->translation_status ) {
						_e( 'Rejected by:', 'glotpress' );
					} else {
						_e( 'Last updated by:', 'glotpress' );
					}
					?>
				</dt>
				<dd><?php gp_link_user( $translation->user_last_modified ); ?></dd>
			</dl>
		<?php endif; ?>
		<?php references( $project, $translation ); ?>

		<dl>
			<dt><?php _e( 'Priority:', 'glotpress' ); ?></dt>
			<?php if ( $can_write ) : ?>
				<dd>
					<?php
					echo gp_select(
						'priority-' . $translation->original_id,
						GP::$original->get_static( 'priorities' ),
						$translation->priority,
						array(
							'class'      => 'priority',
							'tabindex'   => '-1',
							'data-nonce' => wp_create_nonce( 'set-priority_' . $translation->original_id ),
						)
					);
					?>
				</dd>
			<?php else : ?>
				<dd>
					<?php
					echo esc_html(
						gp_array_get(
							GP::$original->get_static( 'priorities' ),
							$translation->priority,
							_x( 'Unknown', 'priority', 'glotpress' )
						)
					);
					?>
				</dd>
			<?php endif; ?>
		</dl>

		<dl>
			<dt><?php _e( 'More links:', 'glotpress' ); ?>
				<ul>
					<?php foreach ( $more_links as $more_link ) : ?>
						<li>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $more_link;
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</dt>
		</dl>
	</div>
<?php
$meta_sidebar = ob_get_clean();

$sidebar_tabs  = '<nav class="nav-sidebar">';
$sidebar_tabs .= '<ul class="sidebar-tabs">';
$sidebar_tabs .= '	<li class="current tab-meta" data-tab="sidebar-tab-meta-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Meta</li>';
$sidebar_tabs .= '	<li class="tab-discussion" data-tab="sidebar-tab-discussion-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Discussion&nbsp;<span class="count"></span></li>';
$sidebar_tabs .= '	<li class="tab-others" data-tab="sidebar-tab-others-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Others&nbsp;<span class="count"></span></li>';
$sidebar_tabs .= '</ul>';
$sidebar_tabs .= $meta_sidebar;
$sidebar_tabs .= '<div class="meta discussion" id="sidebar-div-discussion-' . $translation->row_id . '"  data-row-id="' . $translation->row_id . '" style="display: none;"></div>';
$sidebar_tabs .= '<div class="meta others" id="sidebar-div-others-' . $translation->row_id . '"  data-row-id="' . $translation->row_id . '" style="display: none;">';
$sidebar_tabs .= '	<details class="details-other-locales" open="">';
$sidebar_tabs .= '		<summary class="summary-other-locales" id="summary-other-locales-' . $translation->row_id . '">Other locales';
$sidebar_tabs .= '			<span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>';
$sidebar_tabs .= '		</summary>';
$sidebar_tabs .= '		<div class="sidebar-div-others-other-locales-content" id="sidebar-div-others-other-locales-content-' . $translation->row_id . '"></div>';
$sidebar_tabs .= '	</details>';
$sidebar_tabs .= '	<details class="details-history" open="">';
$sidebar_tabs .= '		<summary class="summary-history" id="summary-history-' . $translation->row_id . '">History';
$sidebar_tabs .= '			<span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>';
$sidebar_tabs .= '		</summary>';
$sidebar_tabs .= '		<div class="sidebar-div-others-history-content" id="sidebar-div-others-history-content-' . $translation->row_id . '"></div>';
$sidebar_tabs .= '	</details>';
$sidebar_tabs .= '</div>'; /* meta others */
$sidebar_tabs .= '</nav>';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $sidebar_tabs;
