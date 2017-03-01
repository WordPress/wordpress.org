/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';
import { rangeRight } from 'lodash';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';
import PluginRatings from 'components/plugin-ratings';

export class RatingsWidget extends Component {
	static propTypes = {
		plugin: PropTypes.object,
		translate: PropTypes.func,
	};

	static defaultProps = {
		plugin: {},
		translate: identity,
	};

	ratingsBreakdown() {
		const { plugin, translate } = this.props;

		if ( ! plugin.ratings.length ) {
			return (
				<div className="rating"><p>{ translate( 'This plugin has not been rated yet.' ) }</p></div>
			);
		}

		return (
			<div>
				<a className="reviews-link" href={ `https://wordpress.org/support/plugin/${ plugin.slug }/reviews/` }>
					{ translate( 'See all' ) }
				</a>

				<PluginRatings rating={ plugin.meta.rating } ratingCount={ plugin.ratings.length } />

				<ul className="ratings-list">
					{ rangeRight( 1, 6 ).map( ( stars ) => {
						const barWidth = plugin.ratings.length ? 100 * plugin.ratings[ stars ] / plugin.ratings.length : 0;
						const link = `https://wordpress.org/support/plugin/${ plugin.slug }/reviews/?filter=${ stars }`;

						return (
							<li key={ stars } className="counter-container">
								<a href={ link }>
									<span className="counter-label">
										{ translate( '1 star', '%(stars)s stars', { count: stars, args: { stars } } ) }
									</span>
									<span className="counter-back">
										<span className="counter-bar" style={ { width: `${ barWidth }%` } } />
									</span>
									<span className="counter-count">{ plugin.ratings[ stars ] }</span>
								</a>
							</li>
						);
					} ) }
				</ul>
			</div>
		);
	}

	render() {
		const { plugin, translate } = this.props;

		return (
			<div className="widget plugin-ratings">
				<h4 className="widget-title">{ translate( 'Ratings' ) }</h4>
				<meta itemProp="ratingCount" content={ plugin.ratings.length } />

				{ this.ratingsBreakdown() }

				<div className="user-rating">
					<a
						className="button button-secondary"
						href={ `https://wordpress.org/support/plugin/${ plugin.slug }/reviews/#new-post` }
					>
						{ translate( 'Add my review' ) }
					</a>
				</div>
			</div>
		);
	}
}

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( RatingsWidget ) );
