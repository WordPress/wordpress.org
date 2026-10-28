/**
 * Since we are using a theme template, but hiding everything but 'wporg-pattern-preview',
 * we should erase layouts that could stop our element from displaying full width.
 * If we don't, we get previews that only take up half of the viewport.
 * Reset all the container padding so that there is no extra whitespace around the pattern.
 */
const setParentDisplay = ( element ) => {
    let currElement = element;

    while( currElement.parentElement ){
        currElement.parentElement.style.display = 'block';
		currElement.parentElement.style.padding = '0';
		currElement.parentElement.style.margin = '0';

        currElement = currElement.parentElement;
    }
}

/**
 * All elements outside of the preview container are hidden (with CSS, display: none),
 * to display just the pattern, so only the parent elements of the container
 * need to be unhidden.
 */
const init = () => {
    var container = document.getElementById( 'wporg-pattern-preview' );

    if ( ! container ) {
        return;
    }

    setParentDisplay( container );
}   

window.addEventListener( 'load', init );
