<?php
$edit_link = gp_link_project_edit_get( $project, __( '(edit)' ) );
gp_title( sprintf( __( '%s &lt; GlotPress' ), esc_html( $project->name ) ) );
gp_breadcrumb( array(
	gp_project_links_from_root( $project ),
	'Language Packs',
) );


gp_enqueue_script( 'common' );

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
				<li><span>Language Packs</span>
					<ul>
						<li><a href="<?php echo gp_url_project( $project ); ?>">Projects</a></li>
						<li><a href="<?php echo gp_url_join( gp_url_project( $project ), 'contributors' ); ?>">Contributors</a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</div>

<div class="project-sub-page">
	<h3>Language Packs</h3>

	<p>Language packs are installed automatically if they are available. Once a locale has reached the threshold for a package build it will be listed here. It also means that you don&#8217;t have to include this language in your theme anymore.</p>

	<?php
	if ( isset( $language_packs->translations ) && $language_packs->translations ) {
		echo '<ul class="language-packs-list">';
		foreach ( $language_packs->translations as $language_pack ) {
			printf(
				'<li><strong>%s <span class="locale-code">(%s)</span>:</strong> Last updated %s for version %s (<a href="%s">zip</a>)</li>',
				$language_pack->english_name,
				$language_pack->language,
				$language_pack->updated,
				$language_pack->version,
				$language_pack->package
			);
		}
		echo '</ul>';
	} else {
		echo '<p>The are no language packs yet.</p>';
	}
	?>
</div>

<script type="text/javascript">
jQuery( function( $ ) {
	$( '.projects-dropdown > li' ).on( 'click', function() {
		$( this ).parent( '.projects-dropdown' ).toggleClass( 'open' );
	});
});
</script>

<?php gp_tmpl_footer();
