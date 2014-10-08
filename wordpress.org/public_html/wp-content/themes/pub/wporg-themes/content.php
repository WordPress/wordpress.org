<?php
global $post;
$theme = new Repo_Theme_Package($post);
?>
<div <?php post_class('theme'); ?> id="post-<?php the_ID(); ?>">
	<div class="theme-screenshot">
		<img src="<?php echo $theme->screenshot_url(); ?>" alt="">
	</div>
	<a href="<?php the_permalink(); ?>"><span class="more-details">Theme Details</span></a>
	<div class="theme-author">By the TODO Author</div>	
	<h3 class="theme-name"><?php the_title(); ?></h3>	
</div>
