/**
 * External dependencies
 */
import { keyBy, flatten } from 'lodash';

/**
 * WordPress dependencies
 */
import { stripTagsAndEncodeText } from '@wordpress/sanitize';

export const Post = ( { post } ) => {
	const {
		link,
		date_gmt: date,
		title: {
			rendered: title,
		},
		excerpt: {
			rendered: excerpt,
		},
		_embedded: {
			'wp:term': embeddedTerms,
		},
	} = post;

	const terms = keyBy( flatten( embeddedTerms ), 'id' );

	return (
		<div className="wordcamp-latest-post">
			<h4 className="wordcamp-latest-post-title">
				<a href={ link }>
					{ stripTagsAndEncodeText( title ) }
					{/* todo ^ shouldn't be needed to properly display diacritics. same below */}
				</a>
			</h4>

			<span className="wordcamp-latest-post-date">
				{ new Date( date ).toLocaleDateString() }
				{/* todo show time of the post in addition to the date, since that's relevant in this context */}

				{/* todo is there a gutenberg function that does something similar, but with the site locale? would that be preferable? */}
			</span>

			<div className="wordcamp-latest-post-excerpt">
				{ stripTagsAndEncodeText( excerpt ) }
				{/* todo "continue reading..." link is stripped out */}
			</div>

			{/* todo removed categories b/c doesn't seem important in this context, but consider adding them back if anyone disagrees */}
		</div>
	);
};
