( function( $ ) {
	/**
	 * Detect whether we are on the blog home.
	 */
	var is_blog = $( 'body' ).hasClass( 'blog' );

	/**
	 * Calculate the index of the post to highlight.
	 *
	 * Uses is_blog to chose the correct calculation.
	 */
	function calculate_post_index( n ) {
		/**
		 * On the blog, the succession of posts to highlight is
		 * 2 3 6 7 10 11 14 15, because of the special styles for
		 * the first post on the page.
		 */
		if ( is_blog ) {
			return 0.5 * ( -1 - Math.pow( -1, n ) + 4 * n );
		}

		/**
		 * On the archives and search pages, the succession of
		 * posts to highlight is 1 2 5 6 9 10 13 14.
		 */
		return 0.5 * ( 4 * n - Math.pow( -1, n ) - 3 );
	}

	/**
	 * Adds the .highlight class to the posts that need to be
	 * highlighted.
	 */
	function highlight_posts() {
		// Get all the posts contained in .site-main.
		$posts = $( '.site-main .hentry' );

		// Count the posts contained in .site-main.
		var number_of_posts = $posts.size();

		// Start calculations with 0.
		var n = 0;

		/**
		 * Calculate the index of the first post to highlight.
		 * Like this we can check if the index is already higher
		 * than the number of total posts.
		 */
		var highlight = calculate_post_index( n );

		/**
		 * Highlight the posts.
		 */
		while ( highlight <= number_of_posts ) {
			highlight = calculate_post_index( n );

			// Make sure we are not using a negative index.
			if ( highlight > 0 ) {
				if ( ! $posts.eq( highlight ).hasClass( 'highlight' ) ) {
					$posts.eq( highlight ).addClass( 'highlight' );
				}
			}
			n++;
		}
	}

	/**
	 * Highlight posts on page load.
	 */
	highlight_posts();

	/**
	 * Highlight posts after Infinite Scroll has loaded new posts.
	 */
	$( document.body ).on( 'post-load', function () {
		highlight_posts();
	} );
} )(jQuery);
