<?php
get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<h2 id="post-<?php the_ID(); ?>"><a href="<?php echo get_permalink($post->post_parent); ?>" rev="attachment"><?php echo get_the_title($post->post_parent); ?></a> &raquo; <?php the_title(); ?></h2>
		<p class="attachment"><a href="<?php echo wp_get_attachment_url($post->ID); ?>"><?php echo wp_get_attachment_image( $post->ID, 'medium' ); ?></a></p>
		<p class="wp-caption"><?php if ( !empty($post->post_excerpt) ) the_excerpt(); // this is the "caption" ?></p>
		<?php the_content( esc_html__( 'Read more &laquo;', 'bborg' ) ); ?>
		<dl id="meta">
			<dt><?php esc_html_e( 'Published on', 'bbporg' ); ?></dt>
			<dd>
				<?php
				/* translators: 1: Date; 2: time. */
				printf( __( '%1$s at %2$s' ), get_the_time( 'l, F jS, Y' ), get_the_time() );
				?>
			</dd>
			<dd>
				<?php
				/* translators: author posts link */
				printf( __( 'by <cite>%s</cite>', 'bborg' ), get_the_author_posts_link() );
				?>
			</dd>
			<?php the_tags( "\t\t\t\t\t<dt>" . esc_html__( 'Tagged as', 'bborg' ) . "</dt>\n\t\t\t\t\t<dd>", "</dd>\t\t\t\t\t<dd>", "</dd>\n" ); ?>
			<dt><?php esc_html_e( 'Categorized under', 'bbporg' ); ?></dt>
			<dd><?php the_category(', '); ?></dd>
			<?php comments_popup_link( __( '<dt>Feedback has</dt><dd>not been left</dd>', 'bborg' ), __( '<dt>Feedback has</dt><dd>been left once</dd>', 'bborg' ),  __( '<dt>Feedback has</dt><dd>been left % times</dd>', 'bborg' ), "", __( '<dt>Feedback has</dt><dd>been turned off</dd>', 'bborg' ) ); ?>
			<dt><?php esc_html_e( 'Syndication through', 'bbporg' ); ?></dt>
			<dd><?php comments_rss_link('RSS 2.0'); ?></dd>
			<?php if ('open' == $post->ping_status) : ?>
			<dt><?php esc_html_e( 'Trackback from', 'bbporg' ); ?></dt>
			<dd><a href="<?php trackback_url(); ?>" rel="trackback"><?php esc_html_e( 'your own site', 'bbporg' ); ?></a></dd>
			<?php endif;
				if ( 'open' == $post->comment_status ) :
					_e( '<dt>Respond if</dt><dd><a href="#respond">you&#8217;d like to leave feedback</a></dd>', 'bbporg' );
				endif;
				edit_post_link( esc_html__( 'Edit', 'bbporg' ), "\t\t\t\t\t<dt>" . esc_html__( 'You can', 'bborg' ) . "</dt>\n\t\t\t\t\t<dd>", "</dd>\n");
			?>
		</dl>
		<hr class="hidden" />

		<h3><?php esc_html_e( 'View Older or Newer Images', 'bbporg' ); ?></h3>
		<div class="navigation">
			<div class="alignleft"><?php previous_image_link() ?></div>
			<div class="alignright"><?php next_image_link() ?></div>
		</div>
		<hr class="hidden" />
		<?php
		comments_template();
	endwhile;
else:
	?>
	<h1><?php esc_html_e( 'Whoops!', 'bborg' ); ?></h1>
	<p><?php esc_html_e( 'Sorry, no images matched your criteria.', 'bborg' ); ?></p>
<?php
endif;
get_footer();
