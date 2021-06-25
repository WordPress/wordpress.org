/**
 * Update the height of the nested `iframe` to match the height of the nested
 * element. This function sets the value of the `--openverse-embed-height` CSS
 * custom property (which is `100vh`, by default).
 *
 * @param {{ height: number }} value - the new dimensions of the iframe
 */
function updateHeight(value) {
	const height = value.height;
	let heightProp
	if (height) {
		heightProp = `${height}px`;
	} else {
		heightProp = '100vh';
	}
	document
		.documentElement
		.style
		.setProperty('--openverse-embed-height', heightProp);
}

/**
 * Update the URL in the address bar when a page navigation occurs inside the
 * `iframe` This function uses the History API to change the address without a
 * page reload.
 *
 * @param {{
 *   path: string,
 *   title?: string,
 *   state?: any,
 * }} value - the attributes of the new history state
 */
function updatePath(value) {
	const path = value.path;
	const url = `${openverseSubpath}${path}`; // openverseSubpath defined in `index.php`

	console.log(`Replacing state URL: ${url}`);
	history.replaceState(
		value.state,
		value.title ?? 'Openverse',
		url,
	);

	if (value.title) {
		console.log(`Setting document title: ${value.title}`);
		document.title = value.title;
	}
}

/**
 * This is the default handler for all messages received in this frame that do
 * not have a handler configured for them.
 */
function logUnhandled() {
	console.error('No handler configured for event received');
}

/**
 * Responds to messages sent from the nested `iframe`.
 *
 * @param {MessageEvent<{
 *   debug?: boolean,
 *   type: string,
 *   value: any,
 * }>} message - the message object sent to this document
 */
function handleIframeMessages ({ origin, data }) {
	if (data.debug) {
		console.log(`Received message from origin ${origin}:`);
		console.log(data);
	}

	let handler
	switch(data.type) {
		case 'resize':
			handler = updateHeight;
			break;
		case 'urlChange':
			handler = updatePath;
			break;
		default:
			handler = logUnhandled;
	}
	handler(data.value);
}

window.addEventListener('message', handleIframeMessages);
