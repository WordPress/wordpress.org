/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { localize } from 'i18n-calypso';
import { identity } from 'lodash';

/**
 * Internal dependencies.
 */
import Stars from './stars';

export const PluginRatings = ( { numberFormat, rating, ratingCount, translate } ) => (
	<div className="plugin-rating" itemProp="aggregateRating" itemScope="" itemType="http://schema.org/AggregateRating">
		<meta itemProp="ratingCount" content={ ratingCount } />
		<meta itemProp="ratingValue" content={ rating } />

		<Stars rating={ rating } />
		<span className="rating-count">
			{ translate( '(%(count)s{{span}} total ratings{{/span}})', {
				args: { count: numberFormat( ratingCount ) },
				components: { span: <span className="screen-reader-text" /> },
			} ) }
		</span>
	</div>
);

PluginRatings.propTypes = {
	numberFormat: PropTypes.func,
	rating: PropTypes.number,
	ratingCount: PropTypes.number,
	translate: PropTypes.func,
};

PluginRatings.defaultProps = {
	numberFormat: identity,
	rating: 0,
	ratingCount: 0,
	translate: identity,
};

export default localize( PluginRatings );
