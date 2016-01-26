<?php if ( !empty($post->post_password) && $_COOKIE['wp-postpass_' . COOKIEHASH] != $post->post_password) : ?>
<p><?php _e( 'Enter your password to view comments.', 'wporg-showcase' ); ?></p>
<?php return; endif; ?>

<?php if ( is_single() ) : ?>
<div class="nextprev"><span class="prev"><?php previous_post( __( '&laquo; %', 'wporg-showcase' ), '');?></span><span class="next"><?php next_post( __( '% &raquo;', 'wporg-showcase' ), ''); ?></span><div class="clear"></div></div>
<?php endif; ?>

<?php if ( $comments ) : ?>
<h2 class="fancy"><?php comments_number( __( 'No Pings', 'wporg-showcase' ), __( '1 Ping', 'wporg-showcase' ), __( '% Pings', 'wporg-showcase' ) ); ?></h2>
<ol id="comments">

<?php foreach ($comments as $comment) : $i++; ?>
	<li id="comment-<?php comment_ID() ?>" <?php if ($i % 2) echo "class='altc'"; ?>>
	<?php comment_text() ?>
	<p><cite><?php printf(
		_x( '%1$s from %2$s on %3$s', '{comment type} from {comment author link} on {comment date}', 'wporg-showcase' ),
		comment_type( __( 'Comment', 'wporg-showcase' ), __( 'Trackback', 'wporg-showcase' ), __( 'Pingback', 'wporg-showcase' ) ),
		comment_author_link(),
		comment_date()
	); ?></cite> <?php edit_comment_link( __( 'Edit This', 'wporg-showcase' ), ' |' ); ?></p>
	</li>

<?php endforeach; ?>

</ol>

<?php endif; ?>
