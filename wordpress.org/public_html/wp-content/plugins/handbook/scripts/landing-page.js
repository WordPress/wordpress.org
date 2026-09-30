const { PluginPostStatusInfo } = wp.editPost;
const { select } = wp.data;
const { registerPlugin } = wp.plugins;
const { Fragment } = wp.element;

function LandingPageMessage() {
	// Using vanilla JS here instead of JSX to avoid needing a build process just for this.

	// return (
	// 	<Fragment>
	// 		<PluginPostStatusInfo className="handbook-landing-page-message">
	// 			{ handbookLandingPage.message }
	// 		</PluginPostStatusInfo>
	// 	</Fragment>
	// );
	return wp.element.createElement(
		wp.element.Fragment,
		{},
		wp.element.createElement(
			wp.editPost.PluginPostStatusInfo,
			{ className: 'handbook-landing-page-message' },
			handbookLandingPage.message
		)
	);
	
}

registerPlugin( 'landing-page-message-plugin', { render: LandingPageMessage } );
