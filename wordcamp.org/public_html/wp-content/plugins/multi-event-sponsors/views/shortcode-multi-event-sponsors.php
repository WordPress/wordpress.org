<?php /** @var $grouped_sponsors   array */ ?>
<?php /** @var $sponsors           array */ ?>
<?php /** @var $regions            array */ ?>
<?php /** @var $sponsorship_levels array */ ?>

<?php foreach ( $grouped_sponsors as $region_id => $levels ) : ?>

	If your WordCamp is in <?php echo esc_html( $regions[ $region_id ]->name ); ?>:

	<ul>
		<?php foreach( $levels as $level_id => $sponsor_ids ) : ?>
			<?php foreach( $sponsor_ids as $sponsor_id ) : ?>

				<li>
					<a href="<?php echo esc_attr( esc_url( get_permalink( $sponsor_id ) ) ); ?>"><?php echo esc_html( $sponsors[ $sponsor_id ]->post_title ); ?></a> is a
					<a href="<?php echo esc_attr( esc_url( get_permalink( $level_id ) ) ); ?>"><?php echo esc_html( $sponsorship_levels[ $level_id ]->post_title ); ?></a> for your event.
				</li>

			<?php endforeach; ?>
		<?php endforeach; ?>
	</ul>

<?php endforeach; ?>
