import React from 'react';

export default React.createClass( {
	displayName: 'DonateWidget',

	render() {
		if ( ! this.props.plugin.donate_link ) {
			return <div />;
		}

		return (
			<div className="widget plugin-donate">
				<h4 className="widget-title">Donate</h4>
				<p className="aside">Would you like to support the advancement of this plugin?</p>
				<p>
					<a className="button button-secondary" href={ this.props.plugin.donate_link } rel="nofollow">
						Donate to this plugin
					</a>
				</p>
			</div>
		)
	}
} );
