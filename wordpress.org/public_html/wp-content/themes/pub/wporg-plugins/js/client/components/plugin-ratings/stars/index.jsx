import React from 'react';

export default React.createClass( {
	displayName: 'Stars',

	fillStars( rating ) {
		const template = '<span class="%1$s"></span>';
		let counter = rating * 2,
			output = '',
			i = 0;

		for ( i; i < 5; i++ ) {
			switch ( counter ) {
				case 0:
					output += template.replace( '%1$s', 'dashicons dashicons-star-empty' );
					break;

				case 1:
					output += template.replace( '%1$s', 'dashicons dashicons-star-half' );
					counter--;
					break;

				default:
					output += template.replace( '%1$s', 'dashicons dashicons-star-filled' );
					counter -= 2;
			}
		}

		return output;
	},

	render() {
		const titleTemplate = '%s out of 5 stars',
			title = titleTemplate.replace( '%s', this.props.rating / 20 );

		return (
			<div
				className="wporg-ratings"
				aria-label={ title }
				data-title-template={ titleTemplate }
				data-rating={ this.props.rating / 20 }
				dangerouslySetInnerHTML={ { __html: this.fillStars( Math.round( this.props.rating / 10 ) / 2 ) } }
			></div>
		)
	}
} );
