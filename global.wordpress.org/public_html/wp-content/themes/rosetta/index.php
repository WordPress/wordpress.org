<?php get_header(); ?>
	<div class="outer" id="mid-wrapper">
<?php
$latest_release = $rosetta->rosetta->get_latest_public_release();
if ( false === $latest_release && $rosetta->rosetta->get_latest_release() ) :
?>
		<div class="wrapper">
			<div class="section">
				<h3>The <?php bloginfo( 'name' ); ?> translation of WordPress is inactive</h3>
				<p><a href="https://wordpress.org/download/">Download the English version instead</a>.</p>
				<p>If you&#8217;re interested in translating WordPress to <?php bloginfo( 'name' ); ?>,
				join <a href="https://make.wordpress.org/polyglots/">the Polyglots team</a> and find out how.</p>
			</div>
		</div>
<?php endif; ?>

		<div class="wrapper">
			<div class="section">
<?php
	query_posts('pagename=txt-welcome');
	while(have_posts()):
		the_post();
?>
				<h3><?php the_title(); ?></h3>

<?php
	$hw = image_hwstring( HEADER_IMAGE_WIDTH, HEADER_IMAGE_HEIGHT );
	if ( ! $header_image = get_header_image() ) {
		$header_image = content_url( '/languages/shots/shot-' . $rosetta->locale . '.png' );
		$hw = '';
	}
?>
				<img class="shot" <?php echo $hw; ?>src="<?php echo esc_url( $header_image ); ?>" alt="Localized version screenshot" />
					<?php the_content(); ?>
<?php endwhile; ?>
			</div>
		</div>
	</div>
<?php
	$latest_release = $rosetta->rosetta->get_latest_public_release();
	if (false !== $latest_release):
?>
		<div class="wrapper">
			<div class="section">
				<div class="main">
<?php
	query_posts('pagename=txt-download');
	while(have_posts()):
		the_post();
?>
				<h3><?php the_title(); ?></h3>
					<?php the_content(); ?>
<?php endwhile; ?>
				</div>
				<div class="sidebar">
<?php
	require_once dirname( __FILE__ ) . '/download-sidebar.php';
?>
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
			<h3><?php _e( 'Showcase', 'rosetta' ); ?></h3>
			<ul id="showcase">
<?php
	foreach ( $showcase as $item ) :
		$url = get_permalink( $item->ID );
?>
	<li>
		<a class="shot" href="<?php echo esc_url( $url ); ?>">
			<img src="<?php echo esc_url( $rosetta->screenshot_url( $url, 230 ) ); ?>" width="230" alt="screenshot" />
		</a>
		<?php echo esc_html( $item->post_title ); ?>
		<br />
		<a href="<?php echo esc_url( $url ); ?>"><?php _e( 'Visit the site &rarr;', 'rosetta' ); ?></a>
	</li>
<?php
	endforeach;
?>
			</ul>
		</div>
	</div>
<?php
	elseif( current_user_can('edit_posts') ):
?>
		<div class="wrapper">
		<div class="section">
			<h3><?php _e('Showcase', 'rosetta'); ?></h3>
			<span id="showcase-front-slate">You can <a href="<?php echo admin_url('edit.php?post_type=showcase'); ?>">add notable sites</a> in your language and screenshot and description of random three of them will show here.</span>
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
			<h3><?php the_title(); ?></h3>
				<?php the_content(); ?>
		</div>
	</div>
<?php endwhile; ?>
		<div class="wrapper">
			<div id="blog" class="section">
				<div class="main">
					<h3><?php _e('Blog', 'rosetta'); ?></h3>
<?php
	wp_reset_query();
	while (have_posts()) : the_post();
?>
							<div class="post" id="post-<?php the_ID(); ?>">
								<h4><a href="<?php the_permalink() ?>" rel="bookmark" title="<?php printf( esc_attr__('Permanent Link to %s', 'rosetta'), get_the_title()); ?>"><?php the_title(); ?></a></h4>
								<h6><?php the_time(__('F j, Y', 'rosetta')); ?></h6>
								<div class="entry">
									<?php the_excerpt(); ?>
								</div>
							</div>
<?php endwhile; ?>
				</div>

				<div class="sidebar">
					<h5><?php _e('Blog Archives', 'rosetta'); ?></h5>
					<ul>
						<?php wp_get_archives('type=monthly&limit=12'); ?>
					</ul>
				</div>
			</div>
		</div>

<?php get_footer(); ?>
