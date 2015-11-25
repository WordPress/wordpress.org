<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

?>

<p>
	<?php _e( 'Remote CSS gives you a lot more flexibility in how you develop your site than the Jetpack Custom CSS module.
	For instance, you can:', 'wordcamporg' ); ?>
</p>

<ul>
	<li><?php _e( 'Work in a local development environment, like Varying Vagrant Vagrants.', 'wordcamporg' ); ?></li>
	<li><?php _e( 'Use your favorite IDE or text-editor, like PhpStorm or Sublime Text.',    'wordcamporg' ); ?></li>
	<li><?php _e( 'Use SASS or LESS instead of vanilla CSS.',                                'wordcamporg' ); ?></li>
	<li><?php _e( 'Use tools like Grunt to automate your workflow.',                         'wordcamporg' ); ?></li>
	<li><?php _e( 'Manage your CSS in a source control system like Git.',                    'wordcamporg' ); ?></li>
	<li><?php _e( 'Collaborate with others on a social coding platform like GitHub.',        'wordcamporg' ); ?></li>
</ul>

<p>
	<?php _e( "You can use all of those tools, only some of them, or completely different ones.
	It's up to you how you choose to work.", 'wordcamporg' ); ?>
</p>

<p>
	<?php _e( "This tool works by fetching your CSS file from a remote server (like GitHub.com), sanitizing the CSS, minifying it,
	and then storing a local copy on WordCamp.org. The local copy is then enqueued as a stylesheet, either in addition to your theme's stylesheet,
	or as a replacement for it. The local copy of the CSS is synchronized with the remote file whenever you press the <strong>Update</strong> button,
	and you can also setup webhook notifications for automatic synchronization when the remote file changes.", 'wordcamporg' ); ?>
</p>

<p>
	<?php printf(
		__( 'If you\'re looking for something simpler, <a href="%s">Jetpack\'s CSS Editor</a> is a great option.', 'wordcamporg' ),
		esc_url( $jetpack_editor_url )
	); ?>
</p>
