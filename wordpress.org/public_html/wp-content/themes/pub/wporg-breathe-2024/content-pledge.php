<?php

namespace WordPressdotorg\Make\Breathe;

defined( 'WPINC' ) || die();

/**
 * Note: There will be a large number of contributors on every page, so don't call any expensive functions like
 * `get_avatar_url( $user_id )` here (which creates a database lookup for the email address). Instead, add the
 * data to `get_team_contributors()` in a performant way.
 *
 * @var array $contributor
 */

?>

<div class="team-contributor">
	<?php echo wp_kses_post( get_avatar( $contributor['email'] ) ); ?>

	<p class="contributor-name">
		<?php echo esc_html( $contributor['name'] ); ?>

		<?php echo wp_kses_data( sprintf(
			"(<a href='%s'>@%s</a>)",
			'https://profiles.wordpress.org/' . $contributor['username'] . '/',
			$contributor['username']
		) ); ?>
	</p>

	<p class="contributor-bio">
		<?php echo wp_kses_data( $contributor['bio'] ); ?>
	</p>
</div>
