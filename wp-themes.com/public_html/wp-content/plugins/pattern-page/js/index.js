/**
 * Loops through children of container and set visiblity to 'visible'.
 */
const makeVisible = ( children ) => {
    for ( var i = 0; i < children.length; i++ ){
        var child = children[i];
        child.style.visibility = 'visible';

        if( child.children.length > 0 ) {
            makeVisible( child.children );
        }
    }
}

/**
 * Move the pattern to the top of the page.
 */
const adjustOffset = ( element ) => {
    const { top, height } = element.getBoundingClientRect();
    element.style.transform = `translate(0, -${ top }px)`;

    // If the element is smaller than the window width, we'll center it
    if( height < window.innerHeight ) {
        element.style.height = '100vh';
    }
}

/**
 * Since we are using a theme template, but hiding everything but 'wporg-pattern-preview',
 * we should erase layouts that could stop our element from displaying full width.
 * If we don't, we get previews that only take up half of the viewport.
 * 
 */
const setParentDisplay = ( element ) => {
    let currElement = element;

    while( currElement.parentElement ){
        currElement.parentElement.style.display = 'block';

        currElement = currElement.parentElement;
    }
}

/**
 * We set all elements to visibility: hidden in CSS and then turn visibility on for our pattern container.
 * This allows us to preview the page without any distractions, like header, footer, etc...
 */
const init = () => {
    var container = document.getElementById( 'wporg-pattern-preview' );

    if( ! container ) {
        return;
    }

    makeVisible( container.children );
    adjustOffset( container );
    setParentDisplay( container );
}   

window.addEventListener( 'load', init );
