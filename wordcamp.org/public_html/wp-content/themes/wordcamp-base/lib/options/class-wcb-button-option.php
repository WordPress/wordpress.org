<?php

class WCB_Button_Option extends WCB_Array_Option {
	var $keys = array('visible', 'text', 'url');

	function validate_visible( $input ) {
		return $input == 'on';
	}

	function validate_text( $input ) {
		return esc_html( $input );
	}

	function validate_url( $url ) {
		return esc_url( $url );
	}

	function render() {
		$ids = array(
			'visible' => 'featured-button-visible',
			'text'    => 'featured-button-text',
			'url'     => 'featured-button-url',
		);
		$class = 'featured-button';
		if ( $this->get_option('visible') )
			$class .= " visible";
		?>
		<tr>
			<th><?php esc_html_e( 'Featured Button', 'wordcamporg' ); ?></th>
			<td class="<?php echo $class; ?>">
				<label class="description checkbox-field" for="<?php echo $ids['visible']; ?>">
					<input type="checkbox" id="<?php echo $ids['visible']; ?>" <?php
						$this->name('visible');
						checked( $this->get_option('visible') );
						?> />
					<?php echo esc_html_e( 'Show a featured button in the menu.', 'wordcamporg' ); ?>
				</label><br />
				<label class="description text-field" for="<?php echo $ids['text']; ?>">
					<span><?php esc_html_e( 'Text:', 'wordcamporg' ); ?></span>
					<input type="text" id="<?php echo $ids['text']; ?>"
						<?php $this->name('text'); ?>
						value="<?php echo esc_attr( $this->get_option('text') ); ?>" />
				</label><br />
				<label class="description text-field" for="<?php echo $ids['url']; ?>">
					<span><?php esc_html_e( 'URL:', 'wordcamporg' ); ?></span>
					<input type="text" id="<?php echo $ids['url']; ?>"
						<?php $this->name('url'); ?>
						value="<?php echo esc_attr( $this->get_option('url') ); ?>" />
				</label>
			</td>
		</tr>
		<?php
	}
}

?>