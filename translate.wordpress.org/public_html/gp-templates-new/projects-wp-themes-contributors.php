<?php
$edit_link = gp_link_project_edit_get( $project, __( '(edit)' ) );
gp_title( sprintf( __( '%s &lt; GlotPress' ), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	'Editors &amp; Contributors',
) );


gp_enqueue_script( 'common' );
gp_enqueue_style( 'chartist' );
gp_enqueue_script( 'chartist' );

gp_tmpl_header();
?>
<div class="project-header">
	<p class="project-description"><?php echo apply_filters( 'project_description', $project->description, $project ); ?></p>

	<div class="project-box">
		<div class="project-box-header">
			<div class="project-icon">
				<?php echo $project->icon; ?>
			</div>

			<ul class="project-meta">
				<li class="project-name"><?php echo $project->name; ?> <?php echo $edit_link; ?></li>
			</ul>
		</div>

		<div class="project-box-footer">
			<ul class="projects-dropdown">
				<li><span>Contributors</span>
					<ul>
						<li><a href="<?php echo gp_url_project( $project ); ?>">Projects</a></li>
						<li><a href="<?php echo gp_url_join( gp_url_project( $project ), 'language-packs' ); ?>">Language Packs</a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</div>

<div class="project-sub-page">

	<h3>Activity</h3>
	<p>The graph shows the recent activity of your contributors. It&#8217;s updated once per day.</p>

	<div class="ct-chart ct-chart-contributors"></div>

	<h3>Teams</h3>
	<p>For each locale a theme can have translation editors and contributors. If a locale has no editor yet then you should probably <a href="https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/#translating-themes-plugins">make a request</a>.</p>
	<?php
	if ( $contributors_by_locale ) {
		?>
		<div class="contributors-list-filter">
			<button type="button" class="button-link filter active" data-filter="all">All</button> |
			<button type="button" class="button-link filter" data-filter="has-editors">With Editors</button> |
			<button type="button" class="button-link filter" data-filter="no-editors">Without Editors</button>

			<input type="search" class="search" placeholder="Filter teams&hellip;" id="contributors-list-search">
		</div>
		<?php
		echo '<div id="contributors-list" class="contributors-list">';
		foreach ( $contributors_by_locale as $locale_slug => $data ) {
			$locale = GP_Locales::by_slug( $locale_slug );
			$has_editors = ! empty ( $data['editors'] );

			$editors_list = array();
			foreach ( $data['editors'] as $editor ) {
				$editors_list[] = sprintf(
					'<a href="https://profiles.wordpress.org/%s/">%s</a>',
					$editor->nicename,
					$editor->display_name ? $editor->display_name : $editor->nicename
				);
			}

			if ( ! $editors_list ) {
				$editors_list[] = 'None';
			}

			$contributor_list = array();
			foreach ( $data['contributors'] as $contributor ) {
				$contributor_list[] = sprintf(
					'<a href="https://profiles.wordpress.org/%s/">%s</a>',
					$contributor->nicename,
					$contributor->display_name ? $contributor->display_name : $contributor->nicename
				);
			}

			if ( ! $contributor_list ) {
				$contributor_list[] = 'None';
			}

			printf(
				'<div class="contributors-list-box%s">
					<h4><span class="locale-name">%s<span> <span class="contributors-count">%s</span> <span class="locale-code">#%s</span></h4>
					<p><strong>Editors:</strong> %s</p>
					<p><strong>Contributors:</strong> %s</p>
				</div>',
				$has_editors ? ' has-editors' : ' no-editors',
				$locale->english_name,
				sprintf( _n( '%s person', '%s persons', $data['count'] ), number_format_i18n( $data['count'] ) ),
				$locale->wp_locale,
				wp_sprintf( '%l', $editors_list ),
				wp_sprintf( '%l', $contributor_list )
			);
		}
		echo '</div>';
	} else {
		echo '<p>The plugin has no contributors, yet.</p>';
	}
	?>
</div>

<script type="text/javascript">
jQuery( function( $ ) {
	$( '.projects-dropdown > li' ).on( 'click', function() {
		$( this ).parent( '.projects-dropdown' ).toggleClass( 'open' );
	});

	$rows = $( '#contributors-list' ).find( '.contributors-list-box' );
	$( '#contributors-list-search' ).on( 'input keyup',function() {
		var words = this.value.toLowerCase().split( ' ' );

		if ( '' === this.value.trim() ) {
			$rows.show();
		} else {
			$rows.hide();
			$rows.filter( function( i, v ) {
				var $t = $(this).find( '.locale-name' );
				for ( var d = 0; d < words.length; ++d ) {
					if ( $t.text().toLowerCase().indexOf( words[d] ) != -1 ) {
						return true;
					}
				}
				return false;
			}).show();
		}
	});

	$( '.contributors-list-filter .filter' ).on( 'click', function() {
		var $el = $( this ), filter = $el.data( 'filter' );

		$el.siblings( '.active' ).removeClass( 'active' );
		$el.addClass( 'active' );
		$( '#contributors-list' ).attr( 'data-current-filter', filter );
	});
});

new Chartist.Line('.ct-chart-contributors', {
	labels: <?php echo json_encode( $chart_data['labels'] ); ?>,
	series: <?php echo json_encode( $chart_data['series'] ); ?>
}, {
	lineSmooth: Chartist.Interpolation.simple({
		divisor: 2
	}),
	low: 0,
	showPoint: false,
	showLine: false,
	showArea: true,
	fullWidth: true,
	axisX: {
		showGrid: false,
	},
	axisY: {
		onlyInteger: true,
		offset: 30
	},
	chartPadding: {
		right: 0,
		left: 0
	},
	plugins: [
		Chartist.plugins.legend()
	]
}, [
	['screen and (max-width: 500px)', {
		axisX: {
			labelInterpolationFnc: function( value, index ) {
				return index % 2 === 0 ? value : null;
			}
		}
	}]
]);
</script>

<?php gp_tmpl_footer();
