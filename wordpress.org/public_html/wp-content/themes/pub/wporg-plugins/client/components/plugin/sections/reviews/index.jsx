/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

export const Reviews = ( { content, numberFormat, numRatings, slug, translate } ) => {
	if ( ! numRatings ) {
		return null;
	}

	return (
		<div>
			<div id="reviews" className="section">
				<div className="plugin-reviews">
					<h2>{ translate( 'Reviews' ) }</h2>
					<div dangerouslySetInnerHTML={ { __html: content } }/>
				</div>
			</div>
			<a
				className="reviews-link"
				href={ `https://wordpress.org/support/plugin/${ slug }/reviews/` }
				aria-expanded="false"
			>
				{ translate( 'Read all %(numRatings)s reviews', {
					args: { numRatings: numberFormat( numRatings ) },
				} ) }
			</a>
		</div>
	);
};

Reviews.propTypes = {
	content: PropTypes.string,
	numberFormat: PropTypes.func,
	numRatings: PropTypes.number.isRequired,
	slug: PropTypes.string.isRequired,
	translate: PropTypes.func,
};

Reviews.defaultProps = {
	content: null,
	numberFormat: identity,
	translate: identity,
};

export default localize( Reviews );
