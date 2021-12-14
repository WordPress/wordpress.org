<?php
namespace WordPressdotorg\Trac\Watcher;

function display_reports_page( $details ) {
	global $wpdb;
	$url       = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'admin.php' ) );
	$what      = $_REQUEST['what'] ?? '';
	$version   = $_REQUEST['version'] ?? (WP_CORE_LATEST_RELEASE+0.1);
	$revisions = $_REQUEST['revisions'] ?? '';
	$branch    = $_REQUEST['branch'] ?? '';

	$url = add_query_arg( 'version', $version, $url );
	?>
	<div class="wrap">
		<h2>Reports: <?php echo esc_html( $details['name'] ); ?></h2>
		<ol>
			<li><a href="<?php echo $url; ?>&what=contributors">All props matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=committers">All Committers matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=cloud">Cloud of Props matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=typos">Props typos matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=raw-contributors-and-committers">All Props+Committers matching filter grouped together</a></li>
			
			<li><a href="<?php echo $url; ?>&what=versions-contributed">Versions which users have contributed to. Ignores filter.</a></li>
		</ol>

		<form>
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
			<input type="hidden" name="what" value="<?php echo esc_attr( $what ); ?>">

		<?php
		if ( 'core' === $details['slug'] ) {
			echo '<select name="version"><option value="">Version</option>';
			foreach ( get_wordpress_versions() as $v ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $v ),
					selected( $version ?? '', $v ),
					esc_html( $v )
				);
			}
			echo '</select>';
		}

		$branches = get_branches_for( $details );
		if ( $branches ) {
			echo '<select name="branch"><option value="">Branch</option>';
			foreach ( $branches as $b ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $b ),
					selected( $branch, $b ),
					esc_html( $b )
				);
			}
			echo '</select>';
		}

		// Revision range.
		echo '<input type="text" name="revisions" placeholder="Revs: 1:50 or 1,2,4,5" value="' . esc_attr( $revisions ) .'">';

		echo '<input type="submit" class="button button-primary" value="Filter">';

		echo '</form>';

		$where = '1=1';
		if ( ! empty( $version ) ) {
			$where .= $wpdb->prepare( ' AND r.version LIKE %s', $wpdb->esc_like( $version ) . '%' );
		}

		// Revisions.
		if ( ! empty( $revisions ) ) {
			if ( false !== strpos( $revisions, ':' ) || false !== strpos( $revisions, '-' )  ) {
				$where .= $wpdb->prepare( ' AND r.id BETWEEN %d AND %d', preg_split( '![-:]!', $revisions ) );
			} elseif ( false !== strpos( $revisions, ',' ) ) {
				$ids = implode( ',', array_map( 'intval', explode( ',', $revisions ) ) );
				$where .= " AND r.id IN({$ids})";
			}
		}

		switch( $what ) {
			case 'cloud':
				// Get all contributors by Name & Count.
				$details = $wpdb->get_results(
					"SELECT u.user_nicename, u.display_name, u.ID, count(*) as count
					FROM {$details['props_table']} p
					LEFT JOIN {$details['rev_table']} r ON p.revision = r.id
					JOIN $wpdb->users u ON p.user_id = u.ID
					WHERE $where
					GROUP BY p.user_id"
				);

				$counts = [];
				foreach ( $details as $r ) {
					$counts[] = (object)[
						'id' => $r->id,
						'name' => $r->display_name ?: $r->user_nicename,
						'link' => 'https://profiles.wordpress.org/' . $r->user_nicename,
						'count' => $r->count
					];
				}

				echo wp_generate_tag_cloud( $counts );
				break;
			case 'committers':
				$details = $wpdb->get_results(
					"SELECT ifnull(u.user_login,r.author) as user_login, ifnull(u.user_nicename,r.author) as user_nicename, u.display_name, u.ID, count(*) as count
					FROM {$details['rev_table']} r 
						LEFT JOIN $wpdb->users u ON r.author = u.user_login
					WHERE $where
					GROUP BY r.author
					ORDER BY count DESC"
				);

				echo '<table class="widefat striped">';
				echo '<thead><tr><th>Comitter</th><th>Count</th></tr></thead>';
				foreach ( $details as $c ) {
					$link = add_query_arg(
						[
							'page' => str_replace( 'reports', 'edit', $_REQUEST['page'] ),
							'author' => $c->user_login,
						],
						admin_url( 'admin.php' )
					);

					printf(
						'<tr><td><a href="%s">%s</a></td><td><a href="%s">%s</a></td></tr>',
						'https://profiles.wordpress.org/' . $c->user_nicename . '/',
						get_avatar( $c->ID, 32 ) . ' ' . ( $c->display_name ?: $c->user_nicename ),
						$link,
						$c->count,
					);
				}
				echo '</table>';


				break;
			case 'contributors':
				$details = $wpdb->get_results(
					"SELECT p.prop_name, u.user_nicename, u.display_name, u.ID, count(*) as count,
						GROUP_CONCAT( p.revision ORDER BY p.revision ASC ) as revisions,
						IFNULL(p.user_id,p.prop_name) as _groupby
					FROM {$details['props_table']} p
						LEFT JOIN {$details['rev_table']} r ON p.revision = r.id
						LEFT JOIN $wpdb->users u ON p.user_id = u.ID
					WHERE $where
					GROUP BY _groupby
					ORDER BY count DESC"
				);

				echo '<table class="widefat striped">';
				echo '<thead><tr><th>Contributor</th><th>Count</th><th>Revisions</th></tr></thead>';
				foreach ( $details as $c ) {
					$link = add_query_arg(
						[
							'page' => str_replace( 'reports', 'edit', $_REQUEST['page'] ),
							'revisions' => $c->revisions
						],
						admin_url( 'admin.php' )
					);
					if ( ! $c->ID ) {
						printf(
							'<tr><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
							$c->prop_name,
							$c->count,
							$link,
							'[' . str_replace( ',', '] [', $c->revisions ) . ']'
						);
						continue;
					}
					printf(
						'<tr><td><a href="%s">%s</a></td><td>%s</td><td><a href="%s">%s</a></td></tr>',
						'https://profiles.wordpress.org/' . $c->user_nicename . '/',
						get_avatar( $c->ID, 32 ) . ' ' . ($c->display_name ?: $c->user_nicename),
						$c->count,
						$link,
						'[' . str_replace( ',', '] [', $c->revisions ) . ']'
					);
				}
				echo '</table>';

				break;
			case 'versions-contributed':
					$details = $wpdb->get_results(
						"SELECT prop_name, user_id, COUNT( distinct version ) as count,
							group_concat( distinct version ORDER BY version ASC  SEPARATOR ', ' ) as versions,
							ifnull(user_id,prop_name) as _groupby
						FROM (
							SELECT distinct LEFT(version, 3) as version, prop_name, p.user_id
							FROM {$details['props_table']} p
								JOIN {$details['rev_table']} r ON p.revision = r.id
							GROUP BY prop_name, version
							UNION
							SELECT distinct LEFT(version, 3) as version, r.author as prop_name, u.ID as user_id
							FROM {$details['rev_table']} r
								JOIN {$wpdb->users} u ON r.author = u.user_login
							GROUP BY r.author, version
						) a
						WHERE version != ''
						group by _groupby HAVING COUNT(*) > 1
						ORDER BY `count` DESC"
					);

					echo '<table class="widefat striped">';
					echo '<thead><tr><th>Prop</th><th>Count</th><th>Versions</th></tr></thead>';
					foreach ( $details as $p ) {
						$link = add_query_arg(
							[
								'page' => str_replace( 'reports', 'edit', $_REQUEST['page'] ),
								's' => $p->prop_name,
							],
							admin_url( 'admin.php' )
						);
	
						$profile = $p->prop_name;
						if ( $p->user_id ) {
							$u = get_user_by( 'ID', $p->user_id );
							$profile = "<A href='https://profiles.wordpress.org/{$u->user_nicename}/'>" . ( $u->display_name ?: $u->user_login ) . "</a>";
						}

						printf(
							'<tr><td>%s</td><td>%s</td><td title="%s">%s</td></tr>',
							$profile,
							$p->count,
							esc_attr( $p->versions ),
							$p->versions
						);
					}
					echo '</table>';

					break;
			case 'typos':
				$details = $wpdb->get_results(
					"SELECT u.user_login, u.user_nicename,
						COUNT( * ) as count,
						group_concat( distinct p.prop_name SEPARATOR ', ' ) as typos,
						group_concat( p.revision ORDER BY p.revision ASC ) as revisions
					FROM {$details['props_table']} p
						LEFT JOIN {$details['rev_table']} r ON p.revision = r.id
						LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
					WHERE $where AND p.prop_name != u.user_login AND p.prop_name != u.user_nicename AND p.prop_name != u.display_name
					GROUP BY p.user_id
					ORDER BY count DESC"
				);

				echo "<p>Props where what's in the commit doesn't match the user Login, Slug, or Display Name.</p>";

				echo '<table class="widefat striped">';
				echo '<thead><tr><th>Contributor</th><th>Typos</th><th>Count</th><th>Revisions</th></tr></thead>';
				foreach ( $details as $c ) {
					$link = add_query_arg(
						[
							'page' => str_replace( 'reports', 'edit', $_REQUEST['page'] ),
							'revisions' => $c->revisions
						],
						admin_url( 'admin.php' )
					);
					printf(
						'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
						'https://profiles.wordpress.org/' . $c->user_nicename . '/',
						$c->display_name ?: $c->user_nicename,
						$c->typos,
						$c->count,
						$link,
						'[' . str_replace( ',', '] [', $c->revisions ) . ']'
					);
				}
				echo '</table>';

				break;
			case 'raw-contributors-and-committers':
				$details = $wpdb->get_results(
					"SELECT prop_name, ID, user_nicename, display_name, _groupby, SUM(_count) as count FROM (
						SELECT p.prop_name, u.ID, u.user_nicename, u.display_name,
							IFNULL(p.user_id,p.prop_name) as _groupby, COUNT(*) as _count
						FROM {$details['props_table']} p
							LEFT JOIN {$details['rev_table']} r ON p.revision = r.id
							LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
						WHERE $where AND p.prop_name != r.author
						GROUP BY _groupby

						UNION

						SELECT r.author as prop_name, u.ID, u.user_nicename, u.display_name,
							u.ID as _groupby, COUNT(*) as _count
						FROM {$details['rev_table']} r
							LEFT JOIN {$wpdb->users} u ON r.author = u.user_login
						WHERE $where
						GROUP BY _groupby
					) results
					GROUP BY _groupby
					ORDER BY count DESC"
				);


				echo "<p>Props (Contributors + Committers bunched together) designed to be copy-pasted elsewhere.<br>
				Set gravatar size via adding <a href='$url&size=96'>&size=96</a> to this URL.<br>
				Note: A committer prop'ing themselves only counts for 1 here.</p>";

				echo '<table class="widefat striped">';
				echo '<thead><tr><th>ID</th><th>Name</th><th>DisplayName</th><th>Count</th><th>Profile URL</th><th>Gravatar</th><th>GravURL</th></tr></thead>';
				foreach ( $details as $c ) {
					printf(
						'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
						$c->ID,
						$c->prop_name,
						$c->display_name,
						$c->count,
						make_clickable( $c->user_nicename ? 'https://profile.wordpress.org/' . $c->user_nicename . '/' : '' ),
						$c->ID ? get_avatar( $c->ID, min( 96, $_GET['size'] ?? 32 ) ) : '',
						make_clickable( $c->ID ? get_avatar_url( $c->ID, [ 'size' => $_GET['size'] ?? 64 ] ) : '' )
					);
				}
				echo '</table>';

				break;
			default:
				echo '<p>Nothing but fishies here today.</p>';
				break;
		}

		?>
	</div>
	<?php
}