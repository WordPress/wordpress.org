<?php

$menu_items = array(
	array(
		'id' => 'download',
		'title' => __( 'Download', 'wporg-plugins' ),
	),
	array(
		'id' => 'playground',
		'title' => __( 'Test in Playground', 'wporg-plugins' ),
	),
);

?>
 <div data-wp-interactive="wporg-release-menu-options">
	<details 
		class="wporg-release-menu-options"
		data-wp-on--focusout="actions.handleFocusOut"
	>   
		<summary>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
				<path d="M13 19h-2v-2h2v2zm0-6h-2v-2h2v2zm0-6h-2V5h2v2z"></path>
			</svg>
			<span class="screen-reader-text">Toggle menu</span>
		</summary>
		<div class="wporg-release-menu-options-content">
			<?php foreach ( $menu_items as $item ) : ?>
				<button
					data-wp-on--click="actions.handleMenuItemClick"
					data-item-id="<?php echo esc_attr( $item['id'] ); ?>"
				>
					<?php echo esc_html( $item['title'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</details>
</div>
