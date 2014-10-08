<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package wporg-themes
 */

get_header(); ?>

<div id="pagebody">
        <div class="wrapper">
		<div class="col-12">
			<p class="intro">Looking for awesome WordPress themes?<br />Welcome to the Free WordPress Themes directory.</p>
		<div class="theme-browser">
		
		TODO menu bar
		<div class="wp-filter">
				<div class="filter-count">
					<span class="count theme-count">2,675</span>
				</div>
		
				<ul class="filter-links">
					<li><a href="/" data-sort="featured" class="current">Featured</a></li>
					<li><a href="/browse/popular/" data-sort="popular">Popular</a></li>
					<li><a href="/browse/new/" data-sort="new">Latest</a></li>
				</ul>
		
				<a class="drawer-toggle" href="#">Feature Filter</a>
		
				<div class="search-form"><input placeholder="Search themes..." type="search" id="wp-filter-search-input" class="wp-filter-search"></div>
		
				<div class="filter-drawer">
					<div class="buttons">
						<a class="apply-filters button button-secondary" href="#">Apply Filters<span></span></a>
						<a class="clear-filters button button-secondary" href="#">Clear</a>
					</div>
				<div class="filter-group"><h4>Colors</h4><ol class="feature-group"><li><input type="checkbox" id="filter-id-black" value="black"> <label for="filter-id-black">Black</label></li><li><input type="checkbox" id="filter-id-blue" value="blue"> <label for="filter-id-blue">Blue</label></li><li><input type="checkbox" id="filter-id-brown" value="brown"> <label for="filter-id-brown">Brown</label></li><li><input type="checkbox" id="filter-id-gray" value="gray"> <label for="filter-id-gray">Gray</label></li><li><input type="checkbox" id="filter-id-green" value="green"> <label for="filter-id-green">Green</label></li><li><input type="checkbox" id="filter-id-orange" value="orange"> <label for="filter-id-orange">Orange</label></li><li><input type="checkbox" id="filter-id-pink" value="pink"> <label for="filter-id-pink">Pink</label></li><li><input type="checkbox" id="filter-id-purple" value="purple"> <label for="filter-id-purple">Purple</label></li><li><input type="checkbox" id="filter-id-red" value="red"> <label for="filter-id-red">Red</label></li><li><input type="checkbox" id="filter-id-silver" value="silver"> <label for="filter-id-silver">Silver</label></li><li><input type="checkbox" id="filter-id-tan" value="tan"> <label for="filter-id-tan">Tan</label></li><li><input type="checkbox" id="filter-id-white" value="white"> <label for="filter-id-white">White</label></li><li><input type="checkbox" id="filter-id-yellow" value="yellow"> <label for="filter-id-yellow">Yellow</label></li><li><input type="checkbox" id="filter-id-dark" value="dark"> <label for="filter-id-dark">Dark</label></li><li><input type="checkbox" id="filter-id-light" value="light"> <label for="filter-id-light">Light</label></li></ol></div><div class="filter-group"><h4>Layout</h4><ol class="feature-group"><li><input type="checkbox" id="filter-id-fixed-layout" value="fixed-layout"> <label for="filter-id-fixed-layout">Fixed Layout</label></li><li><input type="checkbox" id="filter-id-fluid-layout" value="fluid-layout"> <label for="filter-id-fluid-layout">Fluid Layout</label></li><li><input type="checkbox" id="filter-id-responsive-layout" value="responsive-layout"> <label for="filter-id-responsive-layout">Responsive Layout</label></li><li><input type="checkbox" id="filter-id-one-column" value="one-column"> <label for="filter-id-one-column">One Column</label></li><li><input type="checkbox" id="filter-id-two-columns" value="two-columns"> <label for="filter-id-two-columns">Two Columns</label></li><li><input type="checkbox" id="filter-id-three-columns" value="three-columns"> <label for="filter-id-three-columns">Three Columns</label></li><li><input type="checkbox" id="filter-id-four-columns" value="four-columns"> <label for="filter-id-four-columns">Four Columns</label></li><li><input type="checkbox" id="filter-id-left-sidebar" value="left-sidebar"> <label for="filter-id-left-sidebar">Left Sidebar</label></li><li><input type="checkbox" id="filter-id-right-sidebar" value="right-sidebar"> <label for="filter-id-right-sidebar">Right Sidebar</label></li></ol></div><div class="filter-group wide"><h4>Features</h4><ol class="feature-group"><li><input type="checkbox" id="filter-id-accessibility-ready" value="accessibility-ready"> <label for="filter-id-accessibility-ready">Accessibility Ready</label></li><li><input type="checkbox" id="filter-id-blavatar" value="blavatar"> <label for="filter-id-blavatar">Blavatar</label></li><li><input type="checkbox" id="filter-id-buddypress" value="buddypress"> <label for="filter-id-buddypress">BuddyPress</label></li><li><input type="checkbox" id="filter-id-custom-background" value="custom-background"> <label for="filter-id-custom-background">Custom Background</label></li><li><input type="checkbox" id="filter-id-custom-colors" value="custom-colors"> <label for="filter-id-custom-colors">Custom Colors</label></li><li><input type="checkbox" id="filter-id-custom-header" value="custom-header"> <label for="filter-id-custom-header">Custom Header</label></li><li><input type="checkbox" id="filter-id-custom-menu" value="custom-menu"> <label for="filter-id-custom-menu">Custom Menu</label></li><li><input type="checkbox" id="filter-id-editor-style" value="editor-style"> <label for="filter-id-editor-style">Editor Style</label></li><li><input type="checkbox" id="filter-id-featured-image-header" value="featured-image-header"> <label for="filter-id-featured-image-header">Featured Image Header</label></li><li><input type="checkbox" id="filter-id-featured-images" value="featured-images"> <label for="filter-id-featured-images">Featured Images</label></li><li><input type="checkbox" id="filter-id-flexible-header" value="flexible-header"> <label for="filter-id-flexible-header">Flexible Header</label></li><li><input type="checkbox" id="filter-id-front-page-post-form" value="front-page-post-form"> <label for="filter-id-front-page-post-form">Front Page Posting</label></li><li><input type="checkbox" id="filter-id-full-width-template" value="full-width-template"> <label for="filter-id-full-width-template">Full Width Template</label></li><li><input type="checkbox" id="filter-id-microformats" value="microformats"> <label for="filter-id-microformats">Microformats</label></li><li><input type="checkbox" id="filter-id-post-formats" value="post-formats"> <label for="filter-id-post-formats">Post Formats</label></li><li><input type="checkbox" id="filter-id-rtl-language-support" value="rtl-language-support"> <label for="filter-id-rtl-language-support">RTL Language Support</label></li><li><input type="checkbox" id="filter-id-sticky-post" value="sticky-post"> <label for="filter-id-sticky-post">Sticky Post</label></li><li><input type="checkbox" id="filter-id-theme-options" value="theme-options"> <label for="filter-id-theme-options">Theme Options</label></li><li><input type="checkbox" id="filter-id-threaded-comments" value="threaded-comments"> <label for="filter-id-threaded-comments">Threaded Comments</label></li><li><input type="checkbox" id="filter-id-translation-ready" value="translation-ready"> <label for="filter-id-translation-ready">Translation Ready</label></li></ol></div><div class="filter-group"><h4>Subject</h4><ol class="feature-group"><li><input type="checkbox" id="filter-id-holiday" value="holiday"> <label for="filter-id-holiday">Holiday</label></li><li><input type="checkbox" id="filter-id-photoblogging" value="photoblogging"> <label for="filter-id-photoblogging">Photoblogging</label></li><li><input type="checkbox" id="filter-id-seasonal" value="seasonal"> <label for="filter-id-seasonal">Seasonal</label></li></ol></div>			<div class="filtered-by">
						<span>Filtering by:</span>
						<div class="tags"></div>
						<a href="#">Edit</a>
					</div>
				</div>
	</div>
		<div class="themes">
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'content', get_post_format() ); ?>
			<?php endwhile; ?>
		<?php else : ?>
			<?php get_template_part( 'content', 'none' ); ?>
		<?php endif; ?>
		</div>
		</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>
