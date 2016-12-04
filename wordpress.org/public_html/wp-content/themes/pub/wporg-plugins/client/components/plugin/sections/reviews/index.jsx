import React from 'react';

export default React.createClass( {
	displayName: 'Reviews',

	render() {
		return (
			<div>
				<div id="reviews" className="read-more">
					<div className="plugin-reviews">
						<h2>Reviews</h2>
						<div dangerouslySetInnerHTML={ { __html: this.props.content } } />
					</div>
				</div>
				<a className="reviews-link" href={ `https://wordpress.org/support/plugin/${ this.props.slug }/reviews/` } aria-expanded="false">Read all { this.props.numRatings } reviews</a>
			</div>
		)
	}
} );
