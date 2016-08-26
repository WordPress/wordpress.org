import React from 'react';

export default React.createClass( {
	displayName: 'MetaWidget',

	renderTags() {
		if ( this.props.plugin.tags.length ) {
			return ( <li>Categories: <div className="tags">{ this.props.plugin.tags.map( tag =>
				<a key={ tag } href={ `https://wordpress.org/plugins-wp/category/${ tag }/` } rel="tag">{ tag }</a>
			) }</div></li> );
		}
	},

	render() {

		return (
			<div className="widget plugin-meta">
				<h3 className="screen-reader-text">Meta</h3>
				<link itemProp="applicationCategory" href="http://schema.org/OtherApplication" />
				<span itemProp="offers" itemScope itemType="http://schema.org/Offer">
					<meta itemProp="price" content="0.00" />
					<meta itemProp="priceCurrency" content="USD" />
					<span itemProp="seller" itemScope itemType="http://schema.org/Organization">
						<span itemProp="name" content="WordPress.org" />
					</span>
				</span>

				<ul>
					<li>Version: <strong>{ this.props.plugin.version }</strong></li>
					<li>Last updated: <strong><span itemProp="dateModified" content={ this.props.plugin.last_updated }>{ this.props.plugin.last_updated }</span> ago</strong></li>
					<li>Active installs: <strong>{ this.props.plugin.active_installs }</strong></li>
					<li>Tested up to: <strong>{ this.props.plugin.tested }</strong></li>
					{ this.renderTags() }
				</ul>
			</div>
		)
	}
} );
