/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';
import { Link } from 'react-router';
import { localize } from 'i18n-calypso';
import { identity } from 'lodash';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';

export class MetaWidget extends Component {
	static propTypes = {
		moment: PropTypes.func,
		numberFormat: PropTypes.func,
		plugin: PropTypes.object,
		translate: PropTypes.func,
	};

	static defaultProps = {
		moment: identity,
		numberFormat: identity,
		plugin: {},
		translate: identity,
	};

	/**
	 * Returns a string representing the number of active installs for a plugin.
	 *
	 * @return {String} Active installs.
	 */
	activeInstalls() {
		const { numberFormat, translate, plugin } = this.props;
		let text;

		if ( plugin.meta.active_installs <= 10 ) {
			text = translate( 'Less than 10' );
		} else if ( plugin.meta.active_installs >= 1000000 ) {
			text = translate( '1+ million' );
		} else {
			text = numberFormat( plugin.meta.active_installs ) + '+';
		}

		return text;
	}

	/**
	 * Returns markup to display tags, if there are any.
	 *
	 * @return {XML} Tags markup.
	 */
	renderTags() {
		const { plugin_tags: tags } = this.props.plugin;

		if ( tags && tags.length ) {
			const tagsList = (
				<div className="tags">
					{ tags.map( ( tag ) => <Link key={ tag } to={ `tags/${ tag }/` } rel="tag">{ tag }</Link> ) }
				</div>
			);

			return (
				<li>
					{ this.props.translate( 'Tag: {{tagsList/}}', 'Tags: {{tagsList/}}', {
						count: tags.length,
						components: { tagsList },
					} ) }
				</li>
			);
		}
	}

	render() {
		const { moment, plugin, translate } = this.props;

		return (
			<div className="widget plugin-meta">
				<h3 className="screen-reader-text">{ translate( 'Meta' ) }</h3>
				<link itemProp="applicationCategory" href="http://schema.org/OtherApplication" />
				<span itemProp="offers" itemScope itemType="http://schema.org/Offer">
					<meta itemProp="price" content="0.00" />
					<meta itemProp="priceCurrency" content="USD" />
					<span itemProp="seller" itemScope itemType="http://schema.org/Organization">
						<span itemProp="name" content="WordPress.org" />
					</span>
				</span>

				<ul>
					<li>
						{ translate( 'Version: {{strong}}%(version)s{{/strong}}', {
							args: { version: plugin.meta.version },
							components: { strong: <strong /> },
						} ) }
					</li>
					<li>
						{ translate( 'Last updated: {{strong}}%(updated)s{{/strong}}', {
							args: { updated: moment( plugin.modified_gmt ).fromNow() },
							components: { strong: <strong itemProp="dateModified" content={ plugin.modified_gmt } /> },
						} ) }
					</li>
					<li>
						{ translate( 'Active installs: {{strong}}%(installs)s{{/strong}}', {
							args: { installs: this.activeInstalls() },
							components: { strong: <strong /> },
						} ) }
					</li>
					<li>
						{ translate( 'Tested up to: {{strong}}%(tested)s{{/strong}}', {
							args: { tested: plugin.meta.tested },
							components: { strong: <strong /> },
						} ) }
					</li>
					{ this.renderTags() }
				</ul>
			</div>
		);
	}
}

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( MetaWidget ) );
