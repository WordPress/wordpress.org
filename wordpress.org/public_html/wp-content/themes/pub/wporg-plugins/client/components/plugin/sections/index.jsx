import React from 'react';

export default React.createClass( {
	displayName: 'Section',

	render() {
		return (
			<div>
				<div id={ this.props.slug } className="section read-more">
					<h2>{ this.props.title }</h2>
					<div dangerouslySetInnerHTML={ { __html: this.props.content } } />
				</div>
				<button type="button" className="button-link section-toggle" aria-controls={ this.props.slug } aria-expanded="false">Read more</button>
			</div>
		)
	}
} );
