<?php

// Used within the global footer
__('Code is Poetry.', 'rosetta');

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
