/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, search } from '@wordpress/icons';

const CategorySearch = ( { defaultValue, isLoading, isVisible, onUpdate, onSubmit } ) => {
	if ( isLoading ) {
		return <span className="category-search--is-loading" />;
	}

	if ( ! isVisible ) {
		return null;
	}

	return (
		<form method="get" action="/" className="category-search" onSubmit={ onSubmit }>
			<label htmlFor="pattern-search" className="screen-reader-text">
				{ __( 'Search for:', 'wporg-patterns' ) }
			</label>
			<input
				onChange={ onUpdate }
				defaultValue={ defaultValue }
				id="pattern-search"
				type="search"
				placeholder={ __( 'Search patterns', 'wporg-patterns' ) }
			/>
			<button type="submit" className="category-search__button">
				<span className="screen-reader-text"> { __( 'Search patterns', 'wporg-patterns' ) }</span>
				<Icon icon={ search } />
			</button>
		</form>
	);
};

export default CategorySearch;
