import React from 'react';
import rangeRight from 'lodash/rangeRight';

import PluginRatings from 'components/plugin-ratings';

export default React.createClass( {
	displayName: 'RatingsWidget',

	ratingsBreakdown() {
		return (
			<div>
				<a className="reviews-link" href={ `https://wordpress.org/support/plugin/${ this.props.plugin.slug }/reviews/` }>See all</a>

				<PluginRatings rating={ this.props.plugin.rating } ratingCount={ this.props.plugin.num_ratings } />

				<ul className="ratings-list">
					{ rangeRight( 1, 6 ).map( stars => {
						const barWidth = this.props.plugin.num_ratings ? 100 * this.props.plugin.ratings[ stars ] / this.props.plugin.num_ratings : 0;

						return (
							<li className="counter-container">
								<a href={ `https://wordpress.org/support/plugin/${ this.props.plugin.slug }/reviews/?filter=${ stars }` }>
									<span className="counter-label">{ stars > 1 ? `${ stars } stars` : `${ stars } star` }</span>
									<span className="counter-back">
										<span className="counter-bar" style={ { width: `${ barWidth }%` } } />
									</span>
									<span className="counter-count">{ this.props.plugin.ratings[ stars ] }</span>
								</a>
							</li>
						);
					} ) }
				</ul>
			</div>
		);
	},

	render() {
		return (
			<div className="widget plugin-ratings">
				<h4 className="widget-title">Ratings</h4>
				<meta itemProp="ratingCount" content={ this.props.plugin.num_ratings } />
				{ this.props.plugin.num_ratings ?
					this.ratingsBreakdown() :
					<div className="rating"><p>This plugin has not been rated yet.</p></div>
				}
				<div className="user-rating">
					<a className="button button-secondary" href={ `https://wordpress.org/support/plugin/${ this.props.plugin.slug }/reviews/#new-post` }>Add my review</a>
				</div>
			</div>
		)
	}
} );
