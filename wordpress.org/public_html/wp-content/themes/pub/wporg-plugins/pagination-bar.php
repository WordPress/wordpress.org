<?php
namespace WordPressdotorg\Plugin_Directory\Theme;

?>
<div class="wrapper">
	<div class="col-12 filter-bar">
		<div class="plugin-pagination">
			<?php
				the_posts_pagination( array(
					    'mid_size' => 5,
						'prev_text' => __( 'Back', 'wporg-plugins' ),
						'next_text' => __( 'Onward', 'wporg-plugins' ),
				) ); 
			?>
		</div>
	</div>
</div>
