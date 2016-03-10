<h4><?php _e( 'Contact Information', 'wordcamporg' ); ?></h4>

<table>
	<tbody>
		<tr>
			<th><label for="mes_company_name"><?php _e( 'Company Name:', 'wordcamporg' ); ?></label></th>
	
			<td>
				<input id="mes_company_name" name="mes_company_name" type="text" value="<?php echo esc_attr( $company_name ); ?>" required class="regular-text" />
			</td>
		</tr>
		
		<tr>
			<th><label for="mes_website"><?php _e( 'Website:', 'wordcamporg' ); ?></label></th>
	
			<td>
				<input id="mes_website" name="mes_website" type="url" value="<?php echo esc_attr( $website ); ?>" required class="regular-text" />
			</td>
		</tr>
		
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
	
		<tr>
			<th><label for="mes_phone_number"><?php _e( 'Phone Number:', 'wordcamporg' ); ?></label></th>
	
			<td>
				<input id="mes_phone_number" name="mes_phone_number" type="text" value="<?php echo esc_attr( $phone_number ); ?>" required class="regular-text" />
			</td>
		</tr>
	</tbody>
</table>

<h4><?php _e( 'Address', 'wordcamporg' ); ?></h4>

<table>
	<tbody>
		<tr>
			<th><label for="mes_street_address1"><?php _e( 'Street Address 1:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_street_address1" name="mes_street_address1" type="text" value="<?php echo esc_attr( $street_address1 ); ?>" required class="regular-text" />
			</td>
		</tr>
	
		<tr>
			<th><label for="mes_street_address2"><?php _e( 'Street Address 2:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_street_address2" name="mes_street_address2" type="text" value="<?php echo esc_attr( $street_address2 ); ?>" class="regular-text" />
			</td>
		</tr>
	
		<tr>
			<th><label for="mes_city"><?php _e( 'City:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_city" name="mes_city" type="text" value="<?php echo esc_attr( $city ); ?>" required class="regular-text" />
			</td>
		</tr>
	
		<tr>
			<th><label for="mes_state"><?php _e( 'State:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_state" name="mes_state" type="text" value="<?php echo esc_attr( $state ); ?>" required class="regular-text" />
			</td>
		</tr>
	
		<tr>
			<th><label for="mes_zip_code"><?php _e( 'Zip Code:', 'wordcamporg' ); ?></label></th>

			<td>
				<input id="mes_zip_code" name="mes_zip_code" type="text" value="<?php echo esc_attr( $zip_code ); ?>" required class="regular-text" />
			</td>
		</tr>
	
		<tr>
			<th><label for="mes_country"><?php _e( 'Country:', 'wordcamporg' ); ?></label></th>

			<td>
				<select id="mes_country" name="mes_country">
					<option value="" <?php selected( $country, '' ); ?>>
						<?php _e( '-- Select a Country --', 'wordcamporg' ); ?>
					</option>

					<?php foreach ( $available_countries as $available_country ) : ?>
						<option value="<?php echo esc_attr( $available_country ); ?>" <?php selected( $available_country, $country ); ?>>
							<?php echo esc_html( $available_country ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</tbody>
</table>
