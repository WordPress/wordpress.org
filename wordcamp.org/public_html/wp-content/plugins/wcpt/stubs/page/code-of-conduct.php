<?php /** @var WordCamp_New_Site $this */ ?>
<!-- wp:paragraph {"customBackgroundColor":"#eeeeee"} -->
<p style="background-color:#eeeeee" class="has-background"><?php printf(
	// translators: %s: URL for code of conduct policy
	__( '<em>Organizers note:</em> Below is a boilerplate code of conduct that you can customize; another great example is the Ada Initiative <a href="%s">anti-harassment policy.</a>', 'wordcamporg' ),
	'http://geekfeminism.wikia.com/wiki/Conference_anti-harassment/Policy'
); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"customBackgroundColor":"#eeeeee"} -->
<p style="background-color:#eeeeee" class="has-background"><?php printf(
	// translators: %s: URL for article about harassment reports
	__( 'We also recommend the organizing team read this article on <a href="%s">how to take a harassment report</a>', 'wordcamporg' ),
	'http://geekfeminism.wikia.com/wiki/Conference_anti-harassment/Responding_to_reports'
); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"customBackgroundColor":"#eeeeee"} -->
<p style="background-color:#eeeeee" class="has-background"><?php _e( 'Please update the portions <span style="color: red; text-decoration: underline;">with red text</span>. You can use the "Remove Formatting" button on the toolbar (the eraser icon on the second line) to remove the color and underline.', 'wordcamporg' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list {"ordered":true} -->
<?php echo $this->get_code_of_conduct(); ?>
<!-- /wp:list -->
