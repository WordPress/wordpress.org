/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Animate, Guide } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';

const CopyPasteImage = () => (
	// Wrap the image to avoid the UI shift after the GIF loads
	<div style={ { height: '220px' } }>
		<img
			src={ `${ wporgPatternsUrl.assets }/images/copy-paste-demo.gif` }
			alt={ __( 'GIF of copy and pasting.', 'wporg-patterns' ) }
		/>
	</div>
);

const CopyGuide = ( { onFinish } ) => {
	return (
		<Animate type="appear" options={ { origin: 'bottom' } }>
			{ ( { className } ) => (
				<Guide
					className={ `pattern-actions__guide ${ className }` }
					onFinish={ onFinish }
					finishButtonText={ __( 'Close', 'wporg-patterns' ) }
					pages={ [
						{
							image: <CopyPasteImage />,
							content: (
								<div className="pattern-actions__guide-content">
									<h3 className="pattern-actions__guide-title">
										{ __( 'How to use patterns on your WordPress site.', 'wporg-patterns' ) }
									</h3>
									<p>
										{ __(
											'Patterns are really just text. And, just like you can copy and paste text, you can copy and paste patterns. It’s really easy!',
											'wporg-patterns'
										) }
									</p>
									<ol>
										<li>
											<p>
												{ __(
													'Open any post or page in the WordPress block editor.',
													'wporg-patterns'
												) }
											</p>
										</li>
										<li>
											<p>
												{ __(
													'Place your cursor where you want to add the pattern.',
													'wporg-patterns'
												) }
											</p>
										</li>
										<li>
											<p>
												{ createInterpolateElement(
													__(
														'Paste the contents of your clipboard by holding down <kbd>ctrl</kbd> control (Windows) or <kbd>⌘</kbd> command (Mac) and pressing the <kbd>v</kbd> key, or right-clicking and choose “Paste” from the menu.',
														'wporg-patterns'
													),
													{
														kbd: <kbd className="pattern-actions__guide-shortcut" />,
													}
												) }
											</p>
										</li>
									</ol>
								</div>
							),
						},
					] }
				/>
			) }
		</Animate>
	);
};

export default CopyGuide;
