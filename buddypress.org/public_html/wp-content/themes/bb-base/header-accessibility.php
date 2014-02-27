	<dl id="accessibility">
		<dt>Skip to:</dt>
		<dd><a href="#content" title="Skip to content"><?php _e( 'Content', 'bborg' ); ?></a></dd>
		<dd><a href="#pages" title="Skip to pages"><?php _e( 'Pages', 'bborg' ); ?></a></dd>
		<dd><a href="#categories" title="Skip to categories"><?php _e( 'Categories', 'bborg' ); ?></a></dd>
		<dd><a href="#search" title="Skip to search"><?php _e( 'Search', 'bborg' ); ?></a></dd>
<?php if ( ( is_page() || is_single() || is_attachment() ) && comments_open() ) : ?>
		<dd class="separator"><a href="#comments" title="Skip to comments"><?php _e( 'Comments', 'bborg' ); ?></a></dd>
		<dd><a href="#commentform" title="Skip to comment form"><?php _e( 'Respond', 'bborg' ); ?></a></dd>
<?php endif; ?>
		<dd class="separator"><a href="#top" title="Skip to top"><?php _e( 'Top', 'bborg' ); ?></a></dd>
<?php if ( is_category() || is_post_type_archive( 'post' ) || is_singular( 'post' ) ) : ?>
		<?php previous_post_link( "\t\t\t\t<dd>%link</dd>\n", __( 'Previous Post', 'bbporg' ) ); ?>
		<?php next_post_link( "\t\t\t\t<dd>%link</dd>\n", __( 'Next Post', 'bbporg' ) ); ?>
<?php endif; ?>
		<dd><a href="#bottom" title="Skip to bottom"><?php _e( 'Bottom', 'bborg' ); ?></a></dd>
	</dl>
	<hr class="hidden" />
