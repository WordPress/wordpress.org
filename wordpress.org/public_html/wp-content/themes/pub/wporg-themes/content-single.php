<?php
global $post;
$theme = new Repo_Theme_Package($post);
?>
<div <?php post_class('single-theme'); ?> id="post-<?php the_ID(); ?>">
	<div class="theme-overlay">
		<div class="theme-about">
			<div class="theme-screenshots">
				<div class="screenshot"><?php the_post_thumbnail(); ?></div>
			</div>
			<div class="theme-info">
				<h3 class="theme-name"><?php the_title(); ?>
					<span class="theme-version">Version: <?php echo $theme->version; ?></span>
				</h3>
				<h4 class="theme-author">By <a href="<?php echo $theme->authorurl; ?>"> TODO the WordPress team</a></h4>
				<p class="theme-description">
					<?php the_content(); ?>
				</p>
				<p class="theme-tags"><span>TODO Tags:</span> black, brown, orange, tan, white, yellow, light, one-column, two-columns, right-sidebar, fluid-layout, responsive-layout, custom-header, custom-menu, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, translation-ready, accessibility-ready</p>
				
			</div>
		</div>
	</div>
</div>
