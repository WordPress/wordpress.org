/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import IconHeartFilled from '../icons/heart-filled';
import { store as patternStore } from '../../store';

const FavoriteButtonSmall = ( { className, label, patternId } ) => {
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

	const buttonClasses = classnames( className, 'pattern-favorite-button-small', {
		button: hasPermission,
		'button-link': hasPermission,
		'is-favorited': isFavorite,
	} );

	return ! hasPermission ? (
		<span className={ buttonClasses }>
			<IconHeartFilled className="pattern-favorite-button__filled" />
			<span>{ label }</span>
		</span>
	) : (
		<button className={ buttonClasses } onClick={ onClick }>
			<IconHeartFilled className="pattern-favorite-button__filled" />
			<span>{ label }</span>
		</button>
	);
};

export default FavoriteButtonSmall;
