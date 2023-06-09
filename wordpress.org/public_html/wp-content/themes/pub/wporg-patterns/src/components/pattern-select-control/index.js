/**
 * Wordpress dependencies
 */
import { addQueryArgs, getQueryArg } from '@wordpress/url';
import { SelectControl } from '@wordpress/components';
import { useViewportMatch } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { useRoute } from '../../hooks';

const PatternSelectControl = ( { defaultValue, label, param, options } ) => {
	const { path, replace } = useRoute();
	const hideLabel = useViewportMatch( 'medium', '>=' );

	if ( ! options ) {
		return null;
	}
	const value = getQueryArg( window.location.href, param ) || defaultValue;

	return (
		<div className="pattern-select-control">
			<SelectControl
				label={ label }
				labelPosition="side"
				hideLabelFromVision={ hideLabel }
				value={ value }
				options={ options }
				onChange={ ( newValue ) => {
					replace( addQueryArgs( path, { [ param ]: newValue } ).replace( /\/page\/[\d]+/, '' ) );
				} }
			/>
		</div>
	);
};

export default PatternSelectControl;
