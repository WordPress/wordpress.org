<?php
/**
 * Outputs the site footer and closes the HTML document opened by the header block.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

?>

			<?php echo do_blocks( '<!-- wp:wporg/global-footer /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php wp_footer(); ?>
		</div><?php // Close the wp-site-blocks div, opened by the header block. ?>
	</body>
</html>
