<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Admin;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/** @var array $report_groups */
/** @var array $reports_with_admin */
?>

<div class="wrap">
	<h1>WordCamp Reports</h1>

	<p>Choose a report:</p>

	<?php foreach ( $report_groups as $group_id => $group ) : ?>
		<?php if ( ! empty( $group['classes'] ) ) : ?>
			<h2><?php echo esc_html( $group['label'] ); ?></h2>
			<ul class="ul-disc">
				<?php foreach ( $group['classes'] as $class ) : ?>
					<li>
						<a href="<?php echo esc_attr( Reports\get_page_url( $class::$slug ) ); ?>"><?php echo esc_html( $class::$name ); ?></a>
						&ndash;
						<em><?php echo esc_html( $class::$description ); ?></em>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
