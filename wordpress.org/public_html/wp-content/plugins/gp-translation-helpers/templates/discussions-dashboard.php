<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );
gp_enqueue_script( 'tablesorter' );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = gp_link_get( gp_url_join( '/locale', $locale_slug ), esc_html( $gp_locale->english_name ) );
$breadcrumb[] = 'Discussions';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();

?>
<style>
	html { scroll-behavior: smooth; }
	table td { overflow-wrap: break-word }
</style>

<?php
if ( ! $comments ) {
	echo 'There are no discussions in this locale';
	return;
}

$comments_by_post_id            = array();
$bulk_comments                  = array();
$latest_comment_date_by_post_id = array();

foreach ( $comments as $_comment ) {
	$is_linking_comment = preg_match( '!^' . home_url( gp_url() ) . '[a-z0-9_/#-]+$!i', $_comment->comment_content );
	if ( $is_linking_comment ) {
		$linked_comment = $_comment->comment_content;
		$parts          = wp_parse_url( $linked_comment );
		$parts['path']  = rtrim( $parts['path'], '/' );
		$parts['path']  = rtrim( $parts['path'], '/' );
		$path_parts     = explode( '/', $parts['path'] );

		$linking_comment_original_id = array_pop( $path_parts );

		if ( ! isset( $bulk_comments[ $linking_comment_original_id ] ) ) {
			$bulk_comments[ $linking_comment_original_id ] = array();
		}

		$bulk_comments[ $linking_comment_original_id ][] = $_comment;
		continue;
	}

	if ( ! isset( $comments_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$comments_by_post_id[ $_comment->comment_post_ID ] = array();
	}

	$comments_by_post_id[ $_comment->comment_post_ID ][] = $_comment;

	if ( ! isset( $latest_comment_date_by_post_id[ $_comment->comment_post_ID ] ) ) {
		$latest_comment_date_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	} elseif ( $latest_comment_date_by_post_id[ $_comment->comment_post_ID ] < $_comment->comment_date ) {
		$latest_comment_date_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
	}
}

// If the referenced comment is not in the current batch of comments we need to re-add it.
foreach ( $bulk_comments as $original_id => $_comments ) {
	foreach ( $_comments as $_comment ) {
		if ( ! isset( $comments_by_post_id[ $_comment->comment_post_ID ] ) ) {
			$linked_comment = $_comment->comment_content;
			$parts          = wp_parse_url( $linked_comment );
			$comment_id     = intval( str_replace( 'comment-', '', $parts['fragment'] ) );
			if ( $comment_id ) {
				$comments_by_post_id[ $_comment->comment_post_ID ][] = get_comment( $comment_id );
				if ( ! isset( $latest_comment_date_by_post_id[ $_comment->comment_post_ID ] ) ) {
					$latest_comment_date_by_post_id[ $_comment->comment_post_ID ] = $_comment->comment_date;
				}
			}
		}
	}
}

uksort(
	$comments_by_post_id,
	function( $a, $b ) use ( $latest_comment_date_by_post_id ) {
		return $latest_comment_date_by_post_id[ $b ] <=> $latest_comment_date_by_post_id[ $a ];
	}
);

$args = array(
	'style'            => 'ul',
	'type'             => 'comment',
	'callback'         => 'gth_discussion_callback',
	'reverse_children' => false,
);

?>

<div class="filter-toolbar">
	<a class="<?php echo ( ! $filter ) ? 'filter-current' : ''; ?>" href="<?php echo esc_url( remove_query_arg( array( 'filter', 'page' ) ) ); ?>">All&nbsp;(<?php echo esc_html( count( $all_comments_post_ids ) ); ?>)</a> <span class="separator">•</span>
	<a class="<?php echo ( 'participating' === $filter ) ? 'filter-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'filter', 'participating', $_SERVER['REQUEST_URI'] ) ); ?>">Participating&nbsp;(<?php echo esc_html( count( $participating_post_ids ) ); ?>)</a> <span class="separator">•</span>
	<a class="<?php echo ( 'not_participating' === $filter ) ? 'filter-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'filter', 'not_participating', $_SERVER['REQUEST_URI'] ) ); ?>">Not participating&nbsp;(<?php echo esc_html( count( $not_participating_post_ids ) ); ?>)</a>
</div>
<table id="translations" class="translations clear">
	<thead class="discussions-table-head">
	<tr>
		<th>Original string</th>
		<th>Comment</th>
		<th>Project</th>
		<th>Author</th>
		<th>Submitted on</th>
		<th><?php echo esc_html( apply_filters( 'gp_involved_table_heading', __( 'Validators Involved' ) ) ); ?></th>
	</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $comments_by_post_id as $_post_id => $post_comments ) {
			$original_id = Helper_Translation_Discussion::get_original_from_post_id( $_post_id );
			if ( ! $original_id ) {
				continue;
			}

			$original       = GP::$original->get( $original_id );
			$project        = GP::$project->get( $original->project_id );
			$parent_project = GP::$project->get( $project->parent_project_id );
			$project_name   = ( $parent_project ) ? $parent_project->name : $project->name;
			$project_link   = gp_link_project_get( $project, esc_html( $project_name ) );

			$comment_authors            = array_unique( array_column( $post_comments, 'comment_author_email' ) );
			$validator_emails           = GP_Notifications::get_validators_email_addresses( $project->path );
			$validators_involved_emails = array_intersect( $validator_emails, $comment_authors );

			$validators_involved_emails = apply_filters( 'gp_validators_involved', $validators_involved_emails, $locale_slug, $original_id, $comment_authors );

			$validator_involved_names = array_map(
				function( $validator ) {
					$validator_user = get_user_by( 'email', $validator );
					return '<a href="' . esc_url( gp_url_profile( $validator_user->user_nicename ) ) . '">' . esc_html( $validator_user->display_name ) . '</a>';
				},
				$validators_involved_emails
			);

			$first_comment        = reset( $post_comments );
			$no_of_other_comments = count( $post_comments ) - 1;
			$_translation_set     = GP::$translation_set->by_project_id( $project->id );
			$comment_link         = get_permalink( $first_comment->comment_post_ID ) . $locale_slug . '/' . $_translation_set[0]->slug . '/#comment-' . $first_comment->comment_ID;
			$original_permalink   = gp_url_project_locale(
				$project,
				$locale_slug,
				$_translation_set[0]->slug,
				array(
					'filters[original_id]' => $original_id,
					'filters[status]'      => 'either',
				)
			);

			?>
			<tr>
				<td>
				<a href="<?php echo esc_url( $original_permalink ); ?>">
					<?php echo esc_html( $original->singular ); ?>
				</a>
						<?php if ( isset( $bulk_comments[ $original_id ] ) ) { ?>
						<details>
							<summary class="other-comments">
							<?php
							/* translators: number of other originals in the bulk rejection. */
							printf( '+ ' . _n( 'Thread with %s comment', 'Thread with %s comments', count( $bulk_comments[ $original_id ] ) ), number_format_i18n( count( $bulk_comments[ $original_id ] ) ) );
							?>
						</summary>
						<ul>
							<?php
							foreach ( $bulk_comments[ $original_id ] as $_comment ) {
								$bulk_link_text = $_comment->comment_content;
								$_original_id   = Helper_Translation_Discussion::get_original_from_post_id( $_comment->comment_post_ID );
								if ( $_original_id ) {
									$_original      = GP::$original->get( $_original_id );
									$bulk_link_text = $_original->singular;
								}

								?>
							<li class="bulk-comment-item"><a href="<?php echo esc_attr( $_comment->comment_content ); ?>"><?php echo esc_html( $bulk_link_text ); ?></a></li>
								<?php
							}
							?>
						</ul>
						</details>
							<?php
						}
						?>
				</td>
				 <td>
					 <?php
						if ( ! $first_comment->comment_content ) :
							?>
						
							   <?php
								$comment_reason       = get_comment_meta( $first_comment->comment_ID, 'reject_reason', true );
								$number_of_items      = count( $comment_reason );
								$counter              = 0;
								$all_comment_reasons  = Helper_Translation_Discussion::get_comment_reasons();
								$comment_reasons_text = '';
								foreach ( $comment_reason as $reason ) {
									$comment_reasons_text .= $all_comment_reasons[ $reason ]['name'];
									if ( ++$counter < $number_of_items ) {
										 $comment_reasons_text .= ', ';
									}
								}
								?>
							<a href="<?php echo esc_url( $comment_link ); ?>"><?php echo esc_html( $comment_reasons_text ); ?></a>
						
					   <?php else : ?>
						<a href="<?php echo esc_url( $comment_link ); ?>"><?php echo esc_html( get_comment_excerpt( $first_comment ) ); ?></a>
						   <?php if ( $no_of_other_comments > 0 ) : ?>
							<br>
								<?php /* translators: number of comments. */ ?>
							<a class="other-comments" href="<?php echo esc_url( $comment_link ); ?>"> + <?php printf( _n( '%s Comment', '%s Comments', $no_of_other_comments ), number_format_i18n( $no_of_other_comments ) ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</td>
				<td><?php echo wp_kses( $project_link, array( 'a' => array( 'href' => true ) ) ); ?></td>
				<td><?php echo get_comment_author_link( $first_comment ); ?></td>
				<td><?php echo esc_html( $first_comment->comment_date ); ?></td>
				<td class="gtes-involved">
					<?php
						echo wp_kses(
							implode( ', ', $validator_involved_names ),
							array(
								'a' => array(
									'href'  => array(),
									'class' => array(),
								),
							)
						);
					?>
			</td>
			</tr>
			<?php
		}
		?>
		
	</tbody>
	
</table>
<?php
	$current_page = max( 1, get_query_var( 'page' ) );
	echo wp_kses(
		paginate_links(
			array(
				'base'      => add_query_arg( 'page', '%#%' ),
				'format'    => '?page=%#%',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => __( '« prev' ),
				'next_text' => __( 'next »' ),
			)
		),
		array(
			'span' => array(),
			'a'    => array(
				'href'  => array(),
				'class' => array(),
			),
		)
	);

	?>

	</li>
</ul>
<?php
gp_tmpl_footer();
