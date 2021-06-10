/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import IconHeartOutline from '../icons/heart-outline';
import IconHeartFilled from '../icons/heart-filled';
import { store as patternStore } from '../../store';

const FavoriteButton = ( { showLabel = true, patternId } ) => {
	const { hasPermission, isFavorite } = useSelect( ( select ) => {
		// Fetch favorites so that the state is synced.
		select( patternStore ).getFavorites();
		return {
			// canUser defaults to adding `/wp/v2/` prefix, so we need to backtrack up the path.
			hasPermission: !! select( coreStore ).canUser( 'create', '../../wporg/v1/pattern-favorites' ),
			isFavorite: select( patternStore ).isFavorite( patternId ),
		};
	} );
	const { addFavorite, removeFavorite } = useDispatch( patternStore );
	const onClick = useCallback( () => {
		if ( isFavorite ) {
			removeFavorite( patternId );
		} else {
			addFavorite( patternId );
		}
	}, [ isFavorite ] );

	if ( ! hasPermission ) {
		return null;
	}

	const buttonClasses = classnames( 'button button-link pattern-favorite-button', {
		'is-favorited': isFavorite,
		'has-label': showLabel,
	} );
	const labelClasses = classnames( {
		'screen-reader-text': ! showLabel,
	} );

	return (
		<button className={ buttonClasses } onClick={ onClick }>
			<IconHeartFilled className="pattern__favorite-filled" />
			<IconHeartOutline className="pattern__favorite-outline" />
			<span className={ labelClasses }>
				{ isFavorite
					? __( 'Remove from favorites', 'wporg-patterns' )
					: __( 'Add to favorites', 'wporg-patterns' ) }
			</span>
		</button>
	);
};

export default FavoriteButton;
