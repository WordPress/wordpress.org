/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { _x }      from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Post } from './post';

/*
 * todo switch all files to BEMish class names
 */

export const LatestPosts = ( { archiveUrl, isFetching, posts } ) => {
	// todo test when there aren't any posts

	return (
		<div className="day-of-event-latest-posts">
			<h2>
				{ _x( 'Latest Posts', 'title', 'wordcamporg' ) }
			</h2>

			{/* If we already have some posts, then continue showing them while we fetch new ones. */}
			{ isFetching && 0 === posts.length &&
				<Spinner />
			}
			{/* todo not seeing spinner while posts are loading initially */}

			{ ( ! isFetching || 0 < posts.length ) &&
				<>
					{
						posts.filter( ( post ) => !! post ).map(
							( post ) => <Post key={ post.id } post={ post } />
						)
					}
				</>
			}

			<a href={ archiveUrl } className="all-posts">
				{ _x( 'View all Posts', 'title', 'wordcamporg' ) }
				{/* todo figure out good solution for this: https://github.com/wceu/wordcamp-pwa-page/issues/6#issuecomment-504268038 */}
			</a>
		</div>
	);
};
