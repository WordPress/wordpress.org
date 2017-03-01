/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

export class Stars extends Component {
	static propTypes = {
		rating: PropTypes.number,
		translate: PropTypes.func,
	};

	static defaultProps = {
		rating: 0,
		translate: identity,
	};

	/**
	 * Returns filled stars representative of rating.
	 *
	 * @param {Number} rating Plugin rating.
	 * @return {String} Rating stars.
	 */
	fillStars = ( rating ) => {
		let counter = rating * 2,
			output = '',
			i = 0;

		for ( i; i < 5; i++ ) {
			switch ( counter ) {
				case 0:
					output += '<span class="dashicons dashicons-star-empty"></span>';
					break;

				case 1:
					output += '<span class="dashicons dashicons-star-half"></span>';
					counter--;
					break;

				default:
					output += '<span class="dashicons dashicons-star-filled"></span>';
					counter -= 2;
			}
		}

		return output;
	};

	render() {
		const { rating, translate } = this.props;
		const stars =  Math.round( rating / 0.5 ) * 0.5;

		return (
			<div
				className="wporg-ratings"
				aria-label={ translate( '%(stars)s out of 5 stars', { args: { stars } } ) }
				dangerouslySetInnerHTML={ { __html: this.fillStars( stars ) } }
			/>
		);
	}
}

export default localize( Stars );
