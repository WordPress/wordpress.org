<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

?>

<ol>
	<li>
		<p>
			<?php _e( '<strong>Publish your CSS file</strong> to one of our supported platforms.', 'wordcamporg' ); ?>
		</p>

		<p>
			<?php printf(
				__( 'Due to security constraints, only certain third-party platforms can be used.
				We currently only support GitHub, but more platforms can be added if there\'s interest from organizers.
				To request an additional platform, please <a href="%s">create a ticket</a> on Meta Trac.', 'wordcamporg' ),
				'https://meta.trac.wordpress.org/newticket'
			); ?>
		</p>

		<p>
			<?php _e( "If you're using SASS or LESS, you'll need to compile it into vanilla CSS and publish that file.", 'wordcamporg' ); ?>
		</p>
	</li>

	<li>
		<p>
			<?php _e( '<strong>Enter the URL</strong> for the CSS file into the input box below.', 'wordcamporg' ); ?>
		</p>

		<p>
			<?php _e( "If you're using GitHub, you can enter the URL in any of the following formats,
			but we'll convert them to use the GitHub API.", 'wordcamporg' ); ?>
		</p>

		<ul>
			<li>
				<?php _e( 'Web-based file browser:', 'wordcamporg' ); ?>
				<code>https://github.com/WordPressSeattle/seattle.wordcamp.org-<?php echo esc_html( date( 'Y' ) ); ?>/blob/master/style.css</code>
			</li>

			<li>
				<?php _e( 'Raw file:', 'wordcamporg' ); ?>
				<code>https://raw.githubusercontent.com/WordPressSeattle/seattle.wordcamp.org-<?php echo esc_html( date( 'Y' ) ); ?>/master/style.css</code>
			</li>

			<li>
				<?php _e( 'API:', 'wordcamporg' ); ?>
				<code>https://api.github.com/repos/WordPressSeattle/seattle.wordcamp.org-<?php echo esc_html( date( 'Y' ) ); ?>/contents/style.css</code>
			</li>
		</ul>
	</li>

	<li>
		<p><?php _e( 'Click the <strong>Update</strong> button.', 'wordcamporg' ); ?></p>

		<p>
			<?php _e( "WordCamp.org will download the file, sanitize it, minify it, and store a local copy,
			then enqueue the local copy as a stylesheet alongside your theme's default stylesheet.", 'wordcamporg' ); ?>
		</p>
	</li>

	<li>
		<?php _e( 'The local copy will need to be <strong>synchronized</strong> whenever you make a change to the file.
		You can either update manually by pushing the <strong>Update</strong> button again, or update automatically by setting up a webhook.
		For instructions on setting up a webhook, open the <strong>Automated Synchronization</strong> tab.', 'wordcamporg' ); ?>
	</li>
</ol>

<p>
	<?php printf(
		__( 'If you run into any problems, you can ask for help in the <code>#meta-wordcamp</code> channel on <a href="%s">Slack</a>.', 'wordcamporg' ),
		'https://chat.wordpress.org'
	); ?>
</p>
