/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import { favoritePlugin } from 'state/favorites/actions';
import { isFavorite } from 'state/selectors';

export class FavoriteButton extends Component {
	static propTypes = {
		favorite: PropTypes.bool,
		favoritePlugin: PropTypes.func,
		plugin: PropTypes.object,
		translate: PropTypes.func,
	};

	static defaultProps = {
		favorite: false,
		favoritePlugin: () => {},
		plugin: {},
		translate: identity,
	};

	toggleFavorite = ( event ) => {
		const $button = jQuery( event.target );

		this.props.favoritePlugin( this.props.plugin );

		$button.addClass( 'is-animating' ).one( 'animationend', () => {
			$button.toggleClass( 'is-animating favorited' );
		} );
	};

	render() {
		if ( 0 === pluginDirectory.userId ) {
			return null;
		}

		const { favorite, plugin, translate } = this.props;
		const classNames = [ 'plugin-favorite-heart' ];

		if ( favorite ) {
			classNames.push( 'favorited' );
		}

		return (
			<div className="plugin-favorite">
				<button type="button" className={ classNames.join( ' ' ) } onClick={ this.toggleFavorite } >
					<span className="screen-reader-text">
						{ favorite
							? translate( 'Unfavorite %(name)s', { components: { name: plugin.name } } )
							: translate( 'Favorite  %(name)s', { components: { name: plugin.name } } )
						}
					</span>
				</button>
			</div>
		);
	}
}

export default connect(
	( state ) => ( {
		favorite: isFavorite( state ),
	} ),
	{
		favoritePlugin,
	},
)( localize( FavoriteButton ) );
