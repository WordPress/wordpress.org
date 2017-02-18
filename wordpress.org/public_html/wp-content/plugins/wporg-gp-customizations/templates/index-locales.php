<?php
gp_title( __('Locales &lt; GlotPress') );
gp_enqueue_script('common');
gp_tmpl_header();
?>

	<div class="filter-header">
		<ul class="filter-header-links">
			<li><span class="current"><?php _e( 'Find your locale' ); ?></span></li>
			<li><a href="/stats"><?php _e( 'Stats' ); ?></a></li>
			<li><a href="/consistency"><?php _e( 'Consistency' ); ?></a></li>
		</ul>
		<div class="search-form">
			<label class="screen-reader-text" for="locales-filter"><?php esc_attr_e( 'Search locales...' ); ?></label>
			<input placeholder="<?php esc_attr_e( 'Search locales...' ); ?>" type="search" id="locales-filter" class="filter-search">
		</div>
	</div>

	<p class="intro">If your locale isn’t below, follow the steps in the <a href="https://make.wordpress.org/polyglots/handbook/translating/requesting-a-new-locale/">Translator Handbook</a> to contribute a new locale.</p>

	<div id="locales" class="locales">
		<?php foreach ( $locales as $locale ) :
			$percent_complete = 0;
			if ( isset( $translation_status[ $locale->slug ] ) ) {
				$status = $translation_status[ $locale->slug ];
				$percent_complete = floor( $status->current_count / $status->all_count * 100 );
			}

			$wp_locale = ( isset( $locale->wp_locale ) ) ? $locale->wp_locale : $locale->slug;
			?>
			<div class="locale <?php echo 'percent-' . $percent_complete; ?>">
				<ul class="name">
					<li class="english"><?php echo gp_link_get( gp_url_join( '/locale', $locale->slug ), $locale->english_name ) ?></li>
					<li class="native"><?php echo gp_link_get( gp_url_join( '/locale', $locale->slug ), $locale->native_name ) ?></li>
					<li class="code"><?php echo gp_link_get( gp_url_join( '/locale', $locale->slug ), $wp_locale ) ?></li>
				</ul>
				<div class="contributors">
					<?php
					$contributors = sprintf(
						'<span class="dashicons dashicons-admin-users"></span><br />%s',
						isset( $contributors_count[ $locale->slug ] ) ? $contributors_count[ $locale->slug ] : 0
					);
					echo gp_link_get( 'https://make.wordpress.org/polyglots/teams/?locale=' . $locale->wp_locale, $contributors );
					?>
				</div>
				<div class="percent">
					<div class="percent-complete" style="width:<?php echo $percent_complete; ?>%;"></div>
				</div>
				<div class="locale-button">
					<div class="button contribute-button">
						<?php echo gp_link_get( gp_url_join( '/locale', $locale->slug ), 'Contribute Translation' ) ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<script>
		jQuery( document ).ready( function( $ ) {
			$rows = $( '#locales' ).find( '.locale' );
			$( '#locales-filter' ).on( 'input keyup',function() {
				var words = this.value.toLowerCase().split( ' ' );

				if ( '' === this.value.trim() ) {
					$rows.show();
				} else {
					$rows.hide();
					$rows.filter( function( i, v ) {
						var $t = $(this).find( '.name' );
						for ( var d = 0; d < words.length; ++d ) {
							if ( $t.text().toLowerCase().indexOf( words[d] ) != -1 ) {
								return true;
							}
						}
						return false;
					}).show();
				}
			});
		});
	</script>

<?php gp_tmpl_footer();
