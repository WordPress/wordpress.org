<?php
namespace Wporg\TranslationEvents\Templates;

use GP;
use Wporg\TranslationEvents\Event\Event;

/** @var Event  $event */
?>
<div id="translations_<?php echo esc_attr( $translation_set->id ); ?>">
<div class="gp-heading">
	<h3>
		<?php
		printf(
			/* translators: 1: Project name. 2: Translation set name. */
			esc_html__( 'Translation of %1$s: %2$s', 'glotpress' ),
			wp_kses(
				gp_link_get(
					gp_url_project_locale( $project, $translation_set->locale, $translation_set->slug ),
					esc_html(
						gp_project_names_from_root( $project )
					)
				),
				array(
					'a' => array(
						'href'  => array(),
						'title' => array(),
					),
				)
			),
			esc_html( $locale->name )
		);
		?>
	</h3>
</div>
<div class="gp-table-actions top">
	<?php
	if ( $can_approve ) {
		gp_translations_bulk_actions_toolbar( $bulk_action, $can_write, $translation_set, 'top' );
	}
	?>
</div>

<?php $class_rtl = 'rtl' === $locale->text_direction ? ' translation-sets-rtl' : ''; ?>
<?php
/**
 * Fires before the translation table has been displayed.
 *
 * @since 4.0.0
 *
 * @param array $def_vars Variables defined in the template.
 */
do_action( 'gp_before_translation_table', get_defined_vars() );
?>
<table data-translation-set="<?php echo esc_attr( $translation_set->id ); ?>" class="<?php echo esc_attr( apply_filters( 'gp_translation_table_classes', 'gp-table translations ' . $class_rtl, get_defined_vars() ) ); ?>">
	<thead>
	<tr>
		<?php
		if ( $can_approve ) :
			?>
			<th class="gp-column-checkbox checkbox" scope="row"><input type="checkbox" /></th>
			<?php
		endif;
		?>
		<th class="gp-column-priority"><?php /* Translators: Priority */ esc_html_e( 'Prio', 'glotpress' ); ?></th>
		<th class="gp-column-original"><?php esc_html_e( 'Original string', 'glotpress' ); ?></th>
		<th class="gp-column-translation"><?php esc_html_e( 'Translation', 'glotpress' ); ?></th>
		<th class="gp-column-actions">&mdash;</th>
	</tr>
	</thead>
<?php
foreach ( $translations as $translation ) {
	if ( ! $translation->translation_set_id ) {
		$translation->translation_set_id = $translation_set->id;
	}

	$can_approve_translation = GP::$permission->current_user_can( 'approve', 'translation', $translation->id, array( 'translation' => $translation ) );
	gp_tmpl_load( 'translation-row', get_defined_vars() );
}
?>
<tr class="preview" style="display: none"></tr>
<?php
if ( ! $translations ) :
	?>
	<tr><td colspan="<?php echo $can_approve ? 5 : 4; ?>"><?php esc_html_e( 'No translations were found!', 'glotpress' ); ?></td></tr>
	<?php
	endif;
?>
</table>
<?php
/**
 * Fires after the translation table has been displayed.
 *
 * @since 4.0.0
 *
 * @param array $def_vars Variables defined in the template.
 */
do_action( 'gp_after_translation_table', get_defined_vars() );
?>

<div class="gp-table-actions bottom">
	<?php
	if ( $can_approve ) {
		gp_translations_bulk_actions_toolbar( $bulk_action, $can_write, $translation_set, 'bottom' );
	}
	?>
	<div id="legend">
		<div><strong><?php esc_html_e( 'Legend:', 'glotpress' ); ?></strong></div>
		<?php
		foreach ( GP::$translation->get_static( 'statuses' ) as $legend_status ) :
			if ( ( 'changesrequested' === $legend_status ) && ( ! apply_filters( 'gp_enable_changesrequested_status', false ) ) ) { // todo: delete when we merge the gp-translation-helpers in GlotPress.
				continue;
			}
			?>
			<div class="box status-<?php echo esc_attr( $legend_status ); ?>"></div>
			<div>
				<?php
				switch ( $legend_status ) {
					case 'current':
						esc_html_e( 'Current', 'glotpress' );
						break;
					case 'waiting':
						esc_html_e( 'Waiting', 'glotpress' );
						break;
					case 'fuzzy':
						esc_html_e( 'Fuzzy', 'glotpress' );
						break;
					case 'old':
						esc_html_e( 'Old', 'glotpress' );
						break;
					case 'rejected':
						esc_html_e( 'Rejected', 'glotpress' );
						break;
					case 'changesrequested':
						if ( apply_filters( 'gp_enable_changesrequested_status', false ) ) { // todo: delete when we merge the gp-translation-helpers in GlotPress.
							esc_html_e( 'Changes requested', 'glotpress' );
						} else {
							esc_html_e( 'Rejected', 'glotpress' );
						}
						break;
					default:
						echo esc_html( $legend_status );
				}
				?>
			</div>
			<?php
		endforeach;
		?>
		<div class="box has-warnings"></div>
		<div><?php esc_html_e( 'With warnings', 'glotpress' ); ?></div>
	</div>
</div>
</div>
<hr>
