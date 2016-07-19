import React from 'react';

import { getPage } from '../actions';

export default React.createClass( {
	displayName: 'Page',

	componentDidMount: function() {
		this.props.dispatch( getPage( this.props.route.path ) );
	},

	render() {
		if ( ! this.props.page ) {
			return (
				<article className="page type-page">
					<header className="entry-header">
						<h1 className="entry-title"> </h1>
					</header>
					<div className="entry-content">
						<section>
							<div className="container"> LOADING </div>
						</section>
					</div>
				</article>
			)
		}

		return (
			<article className="page type-page">
				<header className="entry-header">
					<h1 className="entry-title">{ this.props.page.title.rendered }</h1>
				</header>
				<div className="entry-content">
					<section>
						<div className="container" dangerouslySetInnerHTML={ { __html: this.props.page.content.rendered } } />
					</section>
				</div>
			</article>
		)
	}
} );
