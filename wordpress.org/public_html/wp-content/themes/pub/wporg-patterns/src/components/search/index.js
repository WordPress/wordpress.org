/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, search } from '@wordpress/icons';
import { useInstanceId } from '@wordpress/compose';

const Search = ( { defaultValue, isLoading, isVisible, onUpdate, onSubmit } ) => {
	const instanceId = useInstanceId( Search );
	if ( isLoading ) {
		return <span className="search is-loading" />;
	}

	if ( ! isVisible ) {
		return null;
	}

	return (
		<form method="get" action="/" className="pattern-search" onSubmit={ onSubmit }>
			<label htmlFor={ `search-${ instanceId }` } className="screen-reader-text">
				{ __( 'Search for:', 'wporg-patterns' ) }
			</label>
			<input
				onChange={ onUpdate }
				defaultValue={ defaultValue }
				id={ `search-${ instanceId }` }
				type="search"
				placeholder={ __( 'Search patterns', 'wporg-patterns' ) }
			/>
			<button type="submit" className="pattern-search__button">
				<span className="screen-reader-text"> { __( 'Search patterns', 'wporg-patterns' ) }</span>
				<Icon icon={ search } />
			</button>
		</form>
	);
};

export default Search;
