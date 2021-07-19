/**
 * Wordpress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs, getQueryArg } from '@wordpress/url';
import { SelectControl } from '@wordpress/components';
import { useViewportMatch } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';

const PatternOrderSelect = ( { options } ) => {
	const { path, replace } = useRoute();
	const hideLabel = useViewportMatch( 'medium', '>=' );

	if ( ! options ) {
		return null;
	}

	return (
		<div className="pattern-order-select">
			<SelectControl
				label={ __( 'Order by', 'wporg-patterns' ) }
				labelPosition="side"
				hideLabelFromVision={ hideLabel }
				value={ getQueryArg( window.location.href, 'orderby' ) }
				options={ options }
				onChange={ ( value ) => {
					replace( addQueryArgs( path, { orderby: value } ) );
				} }
			/>
		</div>
	);
};

export default PatternOrderSelect;
