import React from 'react';

export default React.createClass( {
	displayName: 'FavoriteButton',

	getInitialState: function() {
		return {
			favorite: this.props.initialFavorite
		};
	},

	toggleFavorite( event ) {
		const component = this,
			$button = jQuery( event.target );

		this.props.toggleFavorite( event );

		$button.addClass( 'is-animating' ).one( 'animationend', function() {
			$button.toggleClass( 'is-animating favorited' );
			component.setState( { favorite: ! component.state.favorite } );
		} );
	},

	render() {
		let classNames = [ 'plugin-favorite-heart' ];

		if ( this.state.favorite ) {
			classNames.push( 'favorited' );
		}

		return (
			<div className="plugin-favorite">
				<button type="button" className={ classNames.join( ' ' ) } onClick={ this.toggleFavorite } >
					<span className="screen-reader-text">{ `Favorite ${ this.props.plugin.name }` }</span>
				</button>
			</div>
		)
	}
} );
