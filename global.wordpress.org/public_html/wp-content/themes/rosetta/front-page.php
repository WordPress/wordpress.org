<?php get_header(); ?>
<div id="pagebody">
	<div class="outer" id="mid-wrapper">
<?php
$latest_release = $rosetta->rosetta->get_latest_public_release();
if ( false === $latest_release && $rosetta->rosetta->get_latest_release() ) :
?>
		<div class="wrapper">
			<div class="section">
				<div class="col-12" role="main">
					<h3>The <?php echo $rosetta->rosetta->get_glotpress_locale()->english_name; ?> translation of WordPress is inactive</h3>
					<p><a href="https://wordpress.org/download/">Download the English version instead</a>.</p>
					<p>If you&#8217;re interested in translating WordPress to <?php echo $rosetta->rosetta->get_glotpress_locale()->english_name; ?>,
					join <a href="https://make.wordpress.org/polyglots/">the Polyglots team</a> and find out how.</p>
				</div>
			</div>
		</div>
<?php endif; ?>

		<div class="wrapper">
			<div class="section">
				<div class="col-12" role="main">
<?php
	query_posts('pagename=txt-welcome');
	while(have_posts()):
		the_post();
?>
				<h3><?php the_title(); ?></h3>

				<?php
				if ( $header_image = get_header_image() ) {
					$hw = image_hwstring( HEADER_IMAGE_WIDTH, HEADER_IMAGE_HEIGHT );
					printf(
						'<img class="shot" %ssrc="%s" alt="" />',
						$hw,
						esc_url( $header_image )
					);
				}

				the_content();
				?>
<?php endwhile; ?>
				</div>
			</div>
		</div>
	</div>
<?php
	$latest_release = $rosetta->rosetta->get_latest_public_release();
	if (false !== $latest_release):
?>
		<div class="wrapper">
			<div class="section">
				<div class="col-9">
<?php
	query_posts('pagename=txt-download');
	while(have_posts()):
		the_post();
?>
				<h3><?php the_title(); ?></h3>
					<?php the_content(); ?>
<?php endwhile; ?>
				</div>
				<div class="col-3" role="complementary">
					<?php get_sidebar( 'page' ); ?>
				</div>
			</div>
		</div>
<?php
	endif; # at least one no-beta release
	$showcase = $rosetta->showcase->front();
	if ( $showcase ) :
?>
	<div class="wrapper">
		<div class="section">
			<div class="col-12">
				<h3><?php _e( 'Showcase', 'rosetta' ); ?></h3>
				<ul id="showcase">
<?php
	foreach ( $showcase as $item ) :
		$url = get_permalink( $item->ID );
?>
	<li>
		<a class="shot" href="<?php echo esc_url( $url ); ?>">
			<?php
			if ( has_post_thumbnail( $item->ID ) ) {
				echo get_the_post_thumbnail( $item->ID, 'showcase-thumbnail' );
			} else {
				?>
				<img src="<?php echo esc_url( $rosetta->screenshot_url( $url, 220 ) ); ?>" width="220" alt="" />
				<?php
			}
			?>
		</a>
		<?php echo esc_html( $item->post_title ); ?>
		<br />
		<a class="showcase-url" href="<?php echo esc_url( $url ); ?>" rel="nofollow"><?php _e( 'Visit the site &rarr;', 'rosetta' ); ?></a>
	</li>
<?php
	endforeach;
?>
				</ul>
			</div>
		</div>
	</div>
<?php
	elseif( current_user_can('edit_posts') ):
?>
		<div class="wrapper">
			<div class="section">
				<div class="col-12">
					<h3><?php _e('Showcase', 'rosetta'); ?></h3>
					<span id="showcase-front-slate">You can <a href="<?php echo admin_url('edit.php?post_type=showcase'); ?>">add notable sites</a> in your language and screenshot and description of random three of them will show here.</span>
				</div>
			</div>
		</div>
<?php
	endif;
?>
<?php
	query_posts('pagename=txt-install');
	while(have_posts()):
		the_post();
?>
	<div class="wrapper">
		<div class="section">
			<div class="col-12">
				<h3><?php the_title(); ?></h3>
				<?php the_content(); ?>
			</div>
		</div>
	</div>
<?php endwhile; ?>
<?php if ( 'posts' === get_option( 'show_on_front' ) ) : ?>
		<div class="wrapper">
			<div id="blog" class="section">
				<div class="col-9">
					<h3><?php _e('Blog', 'rosetta'); ?></h3>
<?php
	query_posts( 'showposts=5' );
	while (have_posts()) : the_post();
?>
							<div class="post" id="post-<?php the_ID(); ?>">
								<h4><a href="<?php the_permalink() ?>" rel="bookmark" title="<?php printf( esc_attr__('Permanent Link to %s', 'rosetta'), get_the_title()); ?>"><?php the_title(); ?></a></h4>
								<div class="entry">
									<?php the_excerpt(); ?>
								</div>
							</div>
<?php endwhile; ?>
				</div>

				<div class="col-3" role="complementary">
					<?php get_sidebar( 'blog' ); ?>
				</div>
			</div>
		</div>
<?php endif; ?>
</div>
<?php get_footer();
