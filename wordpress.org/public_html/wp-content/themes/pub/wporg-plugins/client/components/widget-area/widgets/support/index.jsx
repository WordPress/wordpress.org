/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';

export class SupportWidget extends Component {
	resolutions: false;

	static propTypes = {
		plugin: PropTypes.object,
		translate: PropTypes.func,
	};

	static defaultProps = {
		plugin: {},
		translate: identity,
	};

	componentWillMount() {
		const { meta, slug } = this.props.plugin;
		this.resolutions = ( meta.support_threads || 'buddypress' === slug || 'bbpress' === slug );
	}

	componentDidUpdate() {
		this.resolutions = this.resolutions || this.props.plugin.meta.support_threads;
	}

	supportBar() {
		const { support_threads: threads, support_threads_resolved: resolved } = this.props.plugin.meta;

		if ( ! this.resolutions ) {
			return (
				<p>{ this.props.translate( 'Got something to say? Need help?' ) }</p>
			);
		}

		return (
			<div>
				<p className="aside">{ this.props.translate( 'Issues resolved in last two months:' ) }</p>
				<p className="counter-container">
					<span className="counter-back">
						<span className="counter-bar" style={ { width: `${ 100 * resolved / threads }%` } } />
					</span>
					<span className="counter-count">
						{ this.props.translate( '%(resolved)s out of %(threads)s', { args: { resolved, threads } } ) }
					</span>
				</p>
			</div>
		);
	}

	getSupportUrl() {
		const { slug } = this.props.plugin;
		let supportURL = `https://wordpress.org/support/plugin/${ slug }/`;

		/*
		 * bbPress and BuddyPress get special treatment here.
		 * In the future we could open this up to all plugins that define a custom support URL.
		 */
		if ( 'buddypress' === slug ) {
			supportURL = 'https://buddypress.org/support/';
		} else if ( 'bbpress' === slug ) {
			supportURL = 'https://bbpress.org/forums/';
		}

		return supportURL;
	}

	render() {
		return (
			<div className="widget plugin-support">
				<h4 className="widget-title">{ this.props.translate( 'Support' ) }</h4>

				{ this.supportBar() }

				<p>
					<a className="button" href={ this.getSupportUrl() }>
						{ this.props.translate( 'View support forum' ) }
					</a>
				</p>
			</div>
		);
	}
}

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( SupportWidget ) );
