<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $usernames */

?>

<h2>Manage Mentor List</h2>

<p class="description">Configure the list of currently active mentors. Enter WordPress.org usernames, separated by commas.</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<textarea class="widefat" name="wcpt-mentors-usernames"><?php echo implode( ',', $usernames ); ?></textarea>

	<input type="hidden" name="action" value="wcpt-mentors-update-usernames" />
	<?php wp_nonce_field( 'wcpt-mentors-update-usernames', 'wcpt-mentors-nonce' ); ?>

	<p><input type="submit" class="button button-primary" value="Update" /></p>
</form>