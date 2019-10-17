<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="profile" href="http://gmpg.org/xfn/11">
		<?php wp_head(); ?>
	</head>

	<body <?php body_class( 'folded' ); ?>>
		<?php wp_body_open(); ?>
		<div class="gutenberg">
			<div id="editor" class="gutenberg__editor"></div>
		</div>
		<?php wp_footer();?>
	</body>
</html>
