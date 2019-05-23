/**
 * External dependencies
 */
import classnames   from 'classnames';
import { debounce } from 'lodash';

/**
 * WordPress dependencies
 */
const { BaseControl, Button, ButtonGroup, PanelBody, PanelRow, RangeControl, ToggleControl, Toolbar } = wp.components;
const { Component, Fragment }                                                                         = wp.element;
const { __ }                                                                                          = wp.i18n;

/**
 * Internal dependencies
 */
import './inspector-controls.scss';

/**
 * Component for a UI control for image size.
 *
 * This control assumes the image only has one adjustable size dimension. For avatars, this is because the images are
 * always square. For featured images, this is because the width is adjustable, while the height is automatically
 * calculated to maintain the correct aspect ratio.
 *
 * @param {Object} props {
 *     @type {string}   className
 *     @type {string}   label
 *     @type {string}   help
 *     @type {number}   value
 *     @type {Array}    sizePresets
 *     @type {Function} onChange
 *     @type {number}   initialPosition
 *     @type {Object}   rangeProps
 * }
 */
export class ImageSizeControl extends Component {
	/**
	 * Run additional operations during component initialization.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.state = {
			value    : props.value,
			onChange : debounce( props.onChange, 10 ), // Higher values lead to a noticeable degradation in visual feedback.
		};

		this.onChange = this.onChange.bind( this );
	}

	/**
	 * Wrapper for debouncing the onChange callback set in props.
	 *
	 * @param {number} value
	 */
	onChange( value ) {
		this.setState( { value } );
		this.state.onChange( value );
	}

	/**
	 * Render the size control.
	 *
	 * @return {Element}
	 */
	render() {
		const {
			className,
			label,
			help,
			sizePresets = [],
			initialPosition,
			rangeProps,
		} = this.props;
		const { value } = this.state;

		return (
			<BaseControl
				className={ classnames( 'wordcamp-image__size', className ) }
				label={ label }
				help={ help }
			>
				<div className="wordcamp-image__size-preset-buttons">
					{ sizePresets.length > 0 &&
						<ButtonGroup aria-label={ label }>
							{ sizePresets.map( ( preset ) => {
								const { name, shortName, size, slug } = preset;
								const isCurrent = value === size;

								return (
									<Button
										key={ slug }
										isLarge
										isPrimary={ isCurrent }
										aria-label={ name }
										aria-pressed={ isCurrent }
										onClick={ () => this.onChange( Number( size ) ) }
									>
										{ shortName || name }
									</Button>
								);
							})}
						</ButtonGroup>
					}

					<Button
						className="wordcamp-image__size-button-reset"
						isLarge
						isDefault
						onClick={ () => this.onChange( Number( initialPosition ) ) }
					>
						{ __( 'Reset', 'wordcamporg' ) }
					</Button>
				</div>

				<RangeControl
					className="wordcamp-image__size-range"
					value={ value }
					initialPosition={ initialPosition }
					onChange={ this.onChange }
					beforeIcon="format-image"
					afterIcon="format-image"
					aria-label={ label }
					{ ...rangeProps }
				/>
			</BaseControl>
		);
	}
}

/**
 * Component for a UI control for image alignment.
 *
 * @param {Object} props {
 *     @type {string}   className
 *     @type {string}   label
 *     @type {string}   help
 *     @type {string}   value
 *     @type {Function} onChange
 *     @type {Array}    alignOptions
 * }
 *
 * @return {Element}
 */
export function ImageAlignmentControl( {
    className,
    label,
    help,
    value,
    onChange,
    alignOptions,
} ) {
	return (
		<BaseControl
			className={ classnames( 'wordcamp-image__alignment', className ) }
			label={ label }
			help={ help }
		>
			<Toolbar
				controls={ alignOptions.map( ( alignment ) => {
					const isActive = value === alignment.value;
					const iconSlug = `align-${ alignment.value }`;

					return {
						title    : alignment.label,
						icon     : iconSlug,
						isActive : isActive,
						onClick  : () => {
							onChange( alignment.value );
						},
					};
				} ) }
			/>
		</BaseControl>
	);
}

/**
 * Component to add an Inspector panel with image-related controls.
 *
 * @param {Object} props {
 *     @type {string}   title
 *     @type {boolean}  initialOpen
 *     @type {string}   className
 *     @type {boolean}  show
 *     @type {Function} onChangeShow
 *     @type {number}   size
 *     @type {Function} onChangeSize
 *     @type {Object}   sizeSchema
 *     @type {Array}    sizePresets
 *     @type {string}   align
 *     @type {Function} onChangeAlign
 *     @type {Array}    alignOptions
 * }
 */
export class ImageInspectorPanel extends Component {
	/**
	 * Render the control.
	 *
	 * @return {Element}
	 */
	render() {
		const {
			title = __( 'Image Settings', 'wordcamporg' ),
			initialOpen = true,
			className,
			show,
			onChangeShow,
			size,
			onChangeSize,
			sizeSchema,
			sizePresets = [],
			align,
			onChangeAlign,
			alignOptions = [],
		} = this.props;

		return (
			<PanelBody
				title={ title }
				initialOpen={ initialOpen }
				className={ classnames( 'wordcamp-image__inspector-panel', className ) }
			>
				<ToggleControl
					label={ __( 'Show images', 'wordcamporg' ) }
					checked={ show }
					onChange={ onChangeShow }
				/>
				{ show &&
					<Fragment>
						<ImageSizeControl
							label={ __( 'Size', 'wordcamporg' ) }
							value={ Number( size ) }
							initialPosition={ Number( sizeSchema.default ) }
							sizePresets={ sizePresets }
							onChange={ onChangeSize }
							rangeProps={ {
								min : Number( sizeSchema.minimum ),
								max : Number( sizeSchema.maximum ),
							} }
						/>
						{ /* The PanelRow wrapper prevents the toolbar from expanding to full width. */ }
						<PanelRow>
							<ImageAlignmentControl
								label={ __( 'Alignment', 'wordcamporg' ) }
								value={ align }
								onChange={ onChangeAlign }
								alignOptions={ alignOptions }
							/>
						</PanelRow>
					</Fragment>
				}
			</PanelBody>
		);
	}
}


