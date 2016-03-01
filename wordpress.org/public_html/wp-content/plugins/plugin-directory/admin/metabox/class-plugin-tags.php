<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * The Plugin Tags metabox
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Plugin_Tags {

	/**
	 * Displays the Publish metabox for plugins.
	 * The HTML here matches what Core uses.
	 */
	static function display( $post, $box ) {
		$taxonomy = get_taxonomy( 'plugin_tag' );
		?>
		<div id="taxonomy-plugin_tag" class="categorydiv">
			<div id="plugin_tag-all" class="tabs-panel">
				<?php
				echo "<input type='hidden' name='tax_input[plugin_tag][]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
				?>
				<ul id="plugin_tagchecklist" data-wp-lists="list:plugin_tag" class="categorychecklist form-no-clear">
					<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => 'plugin_tag' ) ); ?>
				</ul>
			</div>
		</div>
		<?php
	}
}

