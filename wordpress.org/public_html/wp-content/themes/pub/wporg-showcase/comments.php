<?php if ( !empty($post->post_password) && $_COOKIE['wp-postpass_' . COOKIEHASH] != $post->post_password) : ?>
<p><?php _e('Enter your password to view comments.'); ?></p>
<?php return; endif; ?>

<?php if ( is_single() ) : ?>
<div class="nextprev"><span class="prev"><?php previous_post('&laquo; %', '');?></span><span class="next"><?php next_post('% &raquo;', ''); ?></span><div class="clear"></div></div>
<?php endif; ?>

<?php if ( $comments ) : ?>
<h2 class="fancy"><?php comments_number(__('No Pings'), __('1 Ping'), __('% Pings')); ?></h2>
<ol id="comments">

<?php foreach ($comments as $comment) : $i++; ?>
	<li id="comment-<?php comment_ID() ?>" <?php if ($i % 2) echo "class='altc'"; ?>>
	<?php comment_text() ?>
	<p><cite><?php comment_type(__('Comment'), __('Trackback'), __('Pingback')); ?> from <?php comment_author_link() ?> on <?php comment_date() ?></cite> <?php edit_comment_link(__("Edit This"), ' |'); ?></p>
	</li>

<?php endforeach; ?>

</ol>

<?php endif; ?>
