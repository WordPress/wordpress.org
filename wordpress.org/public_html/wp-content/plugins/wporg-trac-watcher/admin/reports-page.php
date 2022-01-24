<?php
namespace WordPressdotorg\Trac\Watcher;

function display_reports_page( $details ) {
	global $wpdb;
	$url       = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'admin.php' ) );
	$what      = $_REQUEST['what'] ?? '';
	$version   = $_REQUEST['version'] ?? null;
	$revisions = $_REQUEST['revisions'] ?? '';
	$branch    = $_REQUEST['branch'] ?? '';
	$is_core   = ( 'core' === $details['slug'] );

	// Default to the latest version for core.
	if ( $is_core && is_null( $version ) ) {
		$version = sprintf( '%.1f', floatval( WP_CORE_LATEST_RELEASE ) + 0.1 );
	}

	$url = add_query_arg( 'version', $version, $url );
	?>
	<div class="wrap">
		<h2>Reports: <?php echo esc_html( $details['name'] ); ?></h2>
		<ol>
			<li><a href="<?php echo $url; ?>&what=contributors">All props matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=committers">All Committers matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=cloud">Cloud of Props matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=typos">Props typos matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=unknown-props">Unknown Props/typos matching filter</a></li>
			<li><a href="<?php echo $url; ?>&what=raw-contributors-and-committers">All Props+Committers matching filter grouped together</a></li>
			
			<?php if ( $is_core ) { ?>
			<li><a href="<?php echo $url; ?>&what=versions-contributed">Versions which users have contributed to. Ignores filter.</a></li>
			<?php } ?>
		</ol>

		<form>
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
			<input type="hidden" name="what" value="<?php echo esc_attr( $what ); ?>">

		<?php
		if ( $is_core ) {
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
		echo '<input type="text" name="revisions" placeholder="Revs: 1:HEAD or 1,2,4,5" value="' . esc_attr( $revisions ) .'">';

		echo '<input type="submit" class="button button-primary" value="Filter">';

		echo '</form>';

		$where = '1=1';
		if ( ! empty( $version ) ) {
			$where .= $wpdb->prepare( ' AND r.version LIKE %s', $wpdb->esc_like( $version ) . '%' );
		}

		// Revisions.
		if ( ! empty( $revisions ) ) {
			if (  preg_match( '!(?P<start>\d+)[:-](?P<end>(HEAD|\d+))!', $args['revisions'], $m ) ) {
				if ( 'HEAD' === $m['end'] ) {
					$where .= $wpdb->prepare( ' AND r.id > %d', $m['start'] );
				} else {
					$where .= $wpdb->prepare( ' AND r.id BETWEEN %d AND %d', $m['start'], $m['end'] );
				}
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
						'link' => 'https://profiles.wordpress.org/' . $r->user_nicename . '/',
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

					// Compress the versions list down to a smaller range.
					$compress = function( $versions ) {
						$in = $versions;
						$out = [];
						if ( ! is_array( $versions ) ) {
							$versions = preg_split( '![,\s]+!', $versions );
						}

						// Don't try to compress only a few versions.
						if ( count( $versions ) <= 2 ) {
							return $in;
						}

						// [ X.Y => 0, X.Y+1 => 1, ... ]
						$versions = array_flip( $versions );

						$not_a_version = [
							'0.9', '1.1', '1.2', '1.4', '1.7', '1.8', '1.9',
						];

						// 2.4 + 2.5 are special. 2.4 was skipped, and many users only have a 2.4 or a 2.5 prop. Give them both versions if they have either.
						if ( isset( $versions['2.5'] ) || isset( $versions['2.4'] ) ) {
							$versions['2.5'] = $version['2.4'] = 1;
						}

						$i = 0; // This counter is just here to protect against infinite loops should something go wrong.
						while ( $versions && $i++ < 40 ) {
							$min   = sprintf( '%.1f', min( array_keys( $versions ) ) );
							$max   = sprintf( '%.1f', max( array_keys( $versions ) ) );
							if ( $min === $max ) {
								$out[] = $min;
								break;
							} elseif ( $max - $min < 0.2 ) {
								$out[] = "{$min}-{$max}";
								break;
							}

							foreach ( range( $min, $max+0.1, 0.1 ) as $v ) {
								$v = sprintf( '%.1f', $v );

								if ( in_array( $v, $not_a_version ) ) {
									unset( $versions[ $v ] );
									continue;
								}

								if ( ! isset( $versions[ $v ] ) ) {
									$last = sprintf( '%.1f', $v - 0.1 );

									if ( $last == $min ) {
										$out[] = $last;
										unset( $versions[ $v ] );
										break;
									} elseif ( $last - $min < 0.15 ) {
										// No point doing 1-2, just do 1, 2
										$out[] = $min;
										$out[] = $last;
									} else {
										$out[] = "{$min}-{$last}";
									}
									break;
								} elseif ( $v == $max ) {
									$out[] = "{$min}-{$max}"; // (5)";
									unset( $versions[ $v ] );
									break;
								} else {
									// no break. We're between a start, and end version.
								}
								unset( $versions[ $v ] );
							}
						}

						return implode( ', ', $out );
					};

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
							$compress( $p->versions )
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
			case 'unknown-props':
				$details = $wpdb->get_results(
					"SELECT p.prop_name,
						COUNT( * ) as count,
						GROUP_CONCAT( p.revision ORDER BY p.revision ASC ) as revisions
					FROM {$details['props_table']} p
						LEFT JOIN {$details['rev_table']} r ON p.revision = r.id
					WHERE $where AND p.user_id IS NULL
					GROUP BY p.prop_name
					ORDER BY r.id DESC"
				);

				echo '<table class="widefat striped">';
				echo '<thead><tr><th>Prop</th><th>Count</th><th>Revisions</th></tr></thead>';
				foreach ( $details as $c ) {
					$link = add_query_arg(
						[
							'page' => str_replace( 'reports', 'edit', $_REQUEST['page'] ),
							'revisions' => $c->revisions
						],
						admin_url( 'admin.php' )
					);
					printf(
						'<tr><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
						esc_html( $c->prop_name ),
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
