<div class="wrap">
	<h2>
		<?php _e( 'Cross-Locale PTEs', 'rosetta' ); ?>
	</h2>

	<?php echo $feedback_message; ?>

	<p><?php _e( 'This is the list of our current Cross-Locale PTEs.', 'rosetta' ); ?></p>
	<table class="wp-list-table widefat fixed striped translation-editors">
		<thead>
		<tr>
			<th scope="col" id="username" class="column-username column-primary">Username</th>
			<th scope="col" id="name" class="column-name">Name</th>
			<th scope="col" id="email" class="column-email">E-mail</th>
			<th scope="col" id="projects" class="column-projects">Projects</th></tr>
		</thead>

		<tbody id="the-list">
		<?php $url = menu_page_url( 'cross-locale-pte', false );  ?>
		<?php foreach ( $cross_locale_pte_users as $user_id => $user ) : ?>
			<tr>
				<td class="username column-username column-primary">
					<?php echo get_avatar( $user_id, 32 ); ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'user_id' => $user_id ), $url ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a>
				</td>
				<td class="name column-name">
					<?php echo esc_html( $user->display_name ); ?>
				</td>
				<td class="email column-email">
					<a href="mailto:<?php echo esc_attr( $user->email ); ?>"><?php echo esc_html( $user->email ); ?></a>
				</td>
				<td class="projects column-projects"><?php
					asort( $user->projects );
					echo implode( ', ', array_map( 'esc_html', $user->projects ) );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( current_user_can( Rosetta_Roles::MANAGE_TRANSLATION_EDITORS_CAP ) ) : ?>
		<h3><?php _e( 'Add Cross-Locale PTE', 'rosetta' ); ?></h3>
		<p><?php _e( 'Enter the email address or username of an existing user on wordpress.org.', 'rosetta' ); ?></p>
		<form action="" method="post">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="user"><?php _e( 'E-mail or Username', 'rosetta' ); ?></label></th>
					<td><input type="text" class="regular-text" name="user" id="user"></td>
				</tr>
			</table>
			<input type="hidden" name="action" value="cross-locale-pte">
			<?php wp_nonce_field( 'cross-locale-pte', '_nonce_cross-locale-pte' ) ?>
			<?php submit_button( __( 'Add Cross-Locale PTE', 'rosetta' ) ); ?>
		</form>
	<?php endif; ?>
</div>
