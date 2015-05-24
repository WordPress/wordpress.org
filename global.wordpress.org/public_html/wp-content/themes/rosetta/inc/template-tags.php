<?php
/**
 * Prints HTML with meta information for the date, author, categories.
 */
function rosetta_entry_meta() {
	if ( is_sticky() && is_home() && ! is_paged() ) {
		printf( '<span class="sticky-post">%s</span> ', __( 'Featured', 'rosetta' ) );
	}

	$time_string = sprintf(
		'<time class="entry-date published" datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( 'c' ) ),
		get_the_date()
	);

	$author_string = sprintf(
		'<span class="entry-author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
		esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
		get_the_author()
	);

	printf(
		/* translators: 1: post date 2: post author */
		__( 'Posted on %1$s by %2$s.', 'rosetta' ),
		$time_string,
		$author_string
	);
	echo ' ';

	$categories_list = get_the_category_list( _x( ', ', 'Used between list items, there is a space after the comma.', 'rosetta' ) );
	if ( $categories_list ) {
		$categories_string = sprintf(
			'<span class="entry-cat-links">%s</span>',
			$categories_list
		);
		echo ' ';
		printf(
			/* translators: %s: list of categories */
			__( 'Filed under %s.', 'rosetta' ),
			$categories_string
		);
	}

	edit_post_link( null, ' ' );
}

function rosetta_release_row( $release, $alt_class=false, $first_of_branch_class=false, $reset = false) {
	static $even = true;
	static $last_branch='';

	if ($reset) {
		$even = true;
		$last_branch = '';
		return;
	}
	$classes = array();
	if (!$even && $alt_class) {
		$classes[] = $alt_class;
	}
	$even = !$even;
	if ($release['branch'] != $last_branch && $first_of_branch_class) {
		$classes[] = $first_of_branch_class;
	}
	$last_branch = $release['branch'];
	$classes_str = implode(' ', $classes);
	print "<tr class='$classes_str'>";
	print "\t<td>".$release['version']."</td>";
	print "\t<td>".date_i18n(__('Y-M-d', 'rosetta'), $release['builton'])."</td>";
	print "\t<td><a href='".$release['zip_url']."'>zip</a> <small>(<a href='".$release['zip_url'].".md5'>md5</a>)</small></td>";
	print "\t<td><a href='".$release['targz_url']."'>tar.gz</a> <small>(<a href='".$release['targz_url'].".md5'>md5</a>)</small></td>";
	print "</tr>";

}
