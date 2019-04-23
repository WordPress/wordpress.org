<?php

defined( 'WPINC' ) || die();

/** @var string $id */
/** @var string $value */
/** @var string $description */

?>

<input type="text" value="<?php echo esc_attr( $value ); ?>" name="camptix_options[<?php echo esc_html( $id ); ?>]">
<p class="description">
	<?php echo esc_html( $description ); ?>
	<br />
	<a href="<?php esc_attr__( 'https://codex.wordpress.org/Formatting_Date_and_Time', 'wordcamporg' ); ?>">
		<?php echo esc_html__( 'Documentation on date and time formatting', 'wordcamporg' ); ?>
	</a>
</p>
