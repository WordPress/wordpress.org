<?php
/**
 * The user settings block
 *
 * A single table that contains all of the user settings, which is included as part of gp-templates/settings.php.
 *
 * @link http://glotpress.org
 *
 * @package GlotPress
 * @since 2.0.0
 */

$gp_per_page = (int) get_user_option( 'gp_per_page' );
if ( 0 === $gp_per_page ) {
	$gp_per_page = 15;
}

$gp_default_sort = get_user_option( 'gp_default_sort' );
if ( ! is_array( $gp_default_sort ) ) {
	$gp_default_sort = array(
		'by'  => 'priority',
		'how' => 'desc',
	);
}
?>

<table class="form-table">
	<tr>
		<th><label for="per_page"><?php _e( 'Number of items per page:', 'glotpress' ); ?></label></th>
		<td><input type="number" id="per_page" name="per_page" value="<?php echo esc_attr( $gp_per_page ); ?>"/></td>
	</tr>
	<tr>
		<th><label for="default_sort[by]"><?php _e( 'Default Sort By:', 'glotpress' ); ?></label></th>
		<td>
			<?php
			$sort_bys = wp_list_pluck( gp_get_sort_by_fields(), 'title' );

			echo gp_radio_buttons( 'default_sort[by]', $sort_bys, gp_array_get( $gp_default_sort, 'by', 'priority' ) );
			?>
		</td>
	</tr>
	<tr>
		<th><label for="default_sort[how]"><?php _e( 'Default Sort Order:', 'glotpress' ); ?></label></th>
		<td>
			<?php
			echo gp_radio_buttons(
				'default_sort[how]',
				array(
					'asc'  => __( 'Ascending', 'glotpress' ),
					'desc' => __( 'Descending', 'glotpress' ),
				),
				gp_array_get( $gp_default_sort, 'how', 'desc' )
			);
			?>
		</td>
	</tr>
	<!-- Including the "notifications_optin" in the "default_sort" array is a hack.
		 If we include it in the future in the GlotPress core, it would be interesting to put
		 this value in it own option item.
		 I do this because the post values are processed in the GP_Route_Settings->settings_post,
		 and I have to modify the GlotPress core to add a new configuration item. -->
	<tr>
		<th><label for="default_sort[notifications_optin]"><?php _e( 'I want to receive notifications of discussions:', 'glotpress' ); ?></label></th>
		<td><input type="checkbox" id="default_sort[notifications_optin]" name="default_sort[notifications_optin]" <?php gp_checked( 'on' == gp_array_get( $gp_default_sort, 'notifications_optin', 'off' ) ); ?> /></td>
	</tr>
</table>
