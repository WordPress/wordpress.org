			<form id="searchform" method="get" action="<?php echo get_settings('home'); ?>">
				<fieldset>
					<legend><span><?php esc_html_e( 'Search this website', 'bborg' ); ?></span></legend>
					<label for="search"><?php esc_html_e( 'for:', 'bborg' ); ?></label>
					<input type="text" value="" id="search" name="s" class="searchtext" />
					<input type="submit" value="<?php esc_attr_e( 'Search', 'bborg' ); ?>" class="search button" />
				</fieldset>
			</form>
			<hr class="hidden" />
