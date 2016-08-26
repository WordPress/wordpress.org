import React from 'react';

export default React.createClass( {
	displayName: 'SupportWidget',
	resolutions: false,

	componentWillMount() {
		this.resolutions = ( this.props.plugin.support_threads || 'buddypress' === this.props.plugin.slug || 'bbpress' === this.props.plugin.slug );
	},

	componentDidUpdate() {
		this.resolutions = this.resolutions || this.props.plugin.support_threads;
	},

	supportBar() {
		const { support_threads: threads, support_threads_resolved: resolved } = this.props.plugin;

		return (
			<div>
				<p className="aside">Issues resolved in last two months:</p>
				<p className="counter-container">
					<span className="counter-back">
						<span className="counter-bar" style={ { width: `${ 100 * resolved / threads }%` } } />
					</span>
					<span className="counter-count">
						{ resolved } out of { threads }
					</span>
				</p>
			</div>
		)
	},

	getSupportUrl() {
		let supportURL = `https://wordpress.org/support/plugin/${ this.props.plugin.slug }/`;

		/*
		 * bbPress and BuddyPress get special treatment here.
		 * In the future we could open this up to all plugins that define a custom support URL.
		 */
		if ( 'buddypress' === this.props.plugin.slug ) {
			supportURL = 'https://buddypress.org/support/';
		} else if ( 'bbpress' === this.props.plugin.slug ) {
			supportURL = 'https://bbpress.org/forums/';
		}

		return supportURL;
	},

	render() {
		return (
			<div className="widget plugin-support">
				<h4 className="widget-title">Support</h4>
				{ this.resolutions ?
					this.supportBar() :
					<p>Got something to say? Need help?</p>
				}
				<p>
					<a className="button" href={ this.getSupportUrl() }>View support forum</a>
				</p>
			</div>
		)
	}
} );
