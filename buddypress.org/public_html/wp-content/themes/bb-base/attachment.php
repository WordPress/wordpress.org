<?php
get_header();
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		// This also populates the iconsize for the next line
		$attachment_link = get_the_attachment_link($post->ID, true, array(450, 800));
		$_post = &get_post($post->ID);
		$classname = ($_post->iconsize[0] <= 128 ? 'small' : '') . 'attachment'; // This lets us style narrow icons specially
		?>

		<div class="post" id="post-<?php the_ID(); ?>">
			<h1><a href="<?php the_permalink() ?>" rel="bookmark" title="<?php printf( esc_attr__( 'Permanent Link to %s', 'bborg' ), the_title_attribute( [ 'echo' => false ] ) ); ?>"><?php the_title(); ?></a></h1>
			<label class="date"><?php the_time( 'F jS, Y' ); ?></label>
			<p class="<?php echo esc_attr( $classname ); ?>"><?php echo $attachment_link; ?><br /><?php echo basename($post->guid); ?></p>
			<?php
				the_content( __( 'Check it out!', 'bborg' ) );
				wp_link_pages( array(
					'before' => __( 'Pages: ', 'bborg' ),
					'after' => '',
					'next_or_number' => 'number',
				) );
			?>
			<p class="postmeta">
				<?php
				printf(
					/* translators: 1: Date; 2: Time; 3: Category list; 4: Comment feed URL. */
					wp_kses_post( __( 'This entry was posted on %1$s at %2$s and is filed under %3$s. You can follow any responses to this entry through the <a href="%4$s">RSS 2.0</a> feed.', 'bborg' ) ),
					get_the_time( 'l, F jS, Y' ),
					get_the_time(),
					get_the_category_list(', '),
					get_post_comments_feed_link()
				);

				if ( 'open' == $post->comment_status && 'open' == $post->ping_status ) :
					// Both Comments and Pings are open.
					printf( wp_kses_post( __( 'You can <a href="#respond">leave a response</a>, or <a href="%s" rel="trackback">trackback</a> from your own site.', 'bborg' ) ), esc_url( get_trackback_url() ) );
				elseif ( 'open' != $post->comment_status && 'open' == $post->ping_status ) :
					// Only Pings are Open
					printf( wp_kses_post( __( 'Responses are currently closed, but you can <a href="%s" rel="trackback">trackback</a> from your own site.', 'bborg' ) ), esc_url( get_trackback_url() ) );
				elseif ( 'open' == $post-> comment_status && 'open' != $post->ping_status ) :
					// Comments are open, Pings are not
					esc_html_e( 'You can skip to the end and leave a response. Pinging is currently not allowed.', 'bborg' );
				elseif ( 'open' != $post-> comment_status && 'open' != $post->ping_status ) :
					// Neither Comments, nor Pings are open
					esc_html_e( 'Both comments and pings are currently closed.', 'bborg' );
				endif;
				edit_post_link('Edit this entry.','','');
				?>
			</p>
		</div>

	<?php
	comments_template();
	endwhile;
else:
?>
	<h1><?php esc_html_e('Whoops!', 'bborg' ); ?></h1>
	<p class="error"><?php esc_html_e('Sorry friend, there&#8217;s no attachments for you to see here.', 'bborg' ); ?></p>
<?php
endif;
get_footer();
