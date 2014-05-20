<?php /** @var $regions               array */ ?>
<?php /** @var $sponsorship_levels    array */ ?>
<?php /** @var $regional_sponsorships array */ ?>

<table>
	<thead>
		<tr>
			<th>Region</th>
			<th>Sponsorship Level</th>
		</tr>
	</thead>

	<tbody>
		<?php foreach ( $regions as $region ) : ?>
			<tr>
				<td>
					<label for="mes_regional_sponsorships-<?php echo esc_attr( $region->term_id ); ?>">
						<?php echo esc_html( $region->name ); ?>
					</label>
				</td>

				<td>
					<select id="mes_regional_sponsorships-<?php echo esc_attr( $region->term_id ); ?>" name="mes_regional_sponsorships[<?php echo esc_attr( $region->term_id ); ?>]">
						<option value="null">None</option>

						<?php foreach ( $sponsorship_levels as $level ) : ?>
							<option value="<?php echo esc_attr( $level->ID ); ?>" <?php selected( $regional_sponsorships[ $region->term_id ], $level->ID ); ?>>
								<?php echo esc_html( $level->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
