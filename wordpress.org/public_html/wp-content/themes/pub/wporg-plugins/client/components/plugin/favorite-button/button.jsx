import React from 'react';
import { connect } from 'react-redux';
import includes from 'lodash/includes';

const FavoriteButton = React.createClass( {
	displayName: 'FavoriteButton',

	toggleFavorite( event ) {
		const $button = jQuery( event.target );

		this.props.toggleFavorite( event );

		$button.addClass( 'is-animating' ).one( 'animationend', function() {
			$button.toggleClass( 'is-animating favorited' );
		} );
	},

	render() {
		let classNames = [ 'plugin-favorite-heart' ];

		if ( this.props.favorite ) {
			classNames.push( 'favorited' );
		}

		return (
			<div className="plugin-favorite">
				<button type="button" className={ classNames.join( ' ' ) } onClick={ this.toggleFavorite } >
					<span className="screen-reader-text">
						{ this.props.favorite
							? `Unfavorite ${ this.props.plugin.name }`
							: `Favorite ${ this.props.plugin.name }`
						}
					</span>
				</button>
			</div>
		)
	}
} );

export default connect(
	( state, { plugin } ) => ( {
		favorite: includes( state.favorites, plugin.slug )
	} ),
)( FavoriteButton );