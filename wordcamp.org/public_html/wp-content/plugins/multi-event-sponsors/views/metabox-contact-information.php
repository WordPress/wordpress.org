<?php /** @var $first_name    string */ ?>
<?php /** @var $last_name     string */ ?>
<?php /** @var $email_address string */ ?>

<table>
	<tbody>
		<tr>
			<th><label for="mes_first_name"><?php _e( 'First Name:', 'wordcamporg' ); ?></label></th>
	
			<td>
				<input id="mes_first_name" name="mes_first_name" type="text" value="<?php echo esc_attr( $first_name ); ?>" class="regular-text" />
			</td>
		</tr>

		<tr>
			<th><label for="mes_last_name"><?php _e( 'Last Name:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_last_name" name="mes_last_name" type="text" value="<?php echo esc_attr( $last_name ); ?>" class="regular-text" />
			</td>
		</tr>

		<tr>
			<th><label for="mes_email_address"><?php _e( 'Email Address:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_email_address" name="mes_email_address" type="email" value="<?php echo esc_attr( $email_address ); ?>" class="regular-text" />
			</td>
		</tr>
	</tbody>
</table>
