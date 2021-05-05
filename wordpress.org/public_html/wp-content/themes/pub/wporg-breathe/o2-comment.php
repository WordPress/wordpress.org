<?php
/**
 * Modify the o2 comment template to adjust the output to suit our needs.
 * Alterations:
 *  - Removes the `rel="external nofollow"` attribute from logged in profiles.
 */

global $o2;

ob_start();
include $o2->templates->template_dir . 'comment.php';
$comment_template = ob_get_clean();

// Remove `rel` attributes on comment urls.
echo str_replace( 'rel="external nofollow" ', '', $comment_template );