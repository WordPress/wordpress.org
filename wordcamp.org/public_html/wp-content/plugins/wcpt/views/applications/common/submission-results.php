<?php

namespace WordPress_Community\Applications;
defined( 'WPINC' ) or die();

?>

<div class="notice notice-large <?php echo esc_attr( $notice_classes ); ?>">
	<?php echo esc_html( $message ); ?>
</div>
