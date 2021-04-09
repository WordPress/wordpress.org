/**
 * External dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';

function CategoryContextBar( { message, title, links } ) {
	const [ height, setHeight ] = useState( 0 );
	const innerRef = useRef( null );

	useEffect( () => {
		if ( message ) {
			setHeight( innerRef.current.offsetHeight );
		} else {
			setHeight( 0 );
		}
	}, [ message ] );

	return (
		<header className="category-context__bar" style={ { height: `${ height }px` } }>
			<div ref={ innerRef }>
				<h2 className="category-context__bar__copy">{ message }</h2>
				{ links && links.length > 0 && (
					<div className="category-context__bar__links">
						<h3 className="category-context__bar__title">{ title }</h3>

						<ul>
							{ links.map( ( i ) => (
								<li key={ i.href }>
									<a href={ i.href }>{ i.label }</a>
								</li>
							) ) }
						</ul>
					</div>
				) }
			</div>
		</header>
	);
}

export default CategoryContextBar;
