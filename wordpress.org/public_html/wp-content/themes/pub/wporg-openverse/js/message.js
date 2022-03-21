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
  let url = `${openverseSubpath}${path}`; // openverseSubpath defined in `index.php`
  if (localeSlug) {
    url = url.replace(`openverse/${localeSlug}`, 'openverse');
  }

  history.replaceState(
    value.state,
    value.title ?? 'Openverse',
    url,
  );

  if (value.title) {
    document.title = value.title;
  }
}

/**
 * Set the meta attributes on the top-level document based on the meta tags
 * passed by the `iframe`.
 *
 * @param {{
 *   meta: [{
 *     name: string,
 *     content: string,
 *  }]
 * }} value - the meta data data supplied by the `iframe`
 */
function updateMeta(value) {
  value.meta.forEach((metaItem) => {
    let metaTag = document.head.querySelector(`meta[name="${metaItem.name}"]`);
    if (metaTag) {
      // Update the tag, if it already exists
      metaTag.content = metaItem.content;
    } else {
      // Create a new tag, otherwise
      metaTag = document.createElement('meta');
      metaTag.name = metaItem.name;
      metaTag.content = metaItem.content;
      document.head.appendChild(metaTag);
    }
  })
}

/**
 * Emit a message to the `iframe` containing information about the current
 * locale. This function combines the locale from WordPress with attributes
 * from the top-level HTML document.
 */
function emitLocale() {
  const currentLang = document.documentElement.lang;
  const currentDir = document.documentElement.dir;

  const iframe = document.getElementById('openverse_embed');
  iframe.contentWindow.postMessage(
    {
      type: 'localeSet',
      value: {
        dir: currentDir,
        lang: currentLang,
        locale: currentLocale, // set in `header.php`
      },
    },
    '*', // Bad practice, but we are not sending sensitive info
  );
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
function handleIframeMessages({ origin, data }) {
  if (data.debug) {
    console.log(`Received message from origin ${origin}:`);
    console.log(data);
  }

  let handler;
  switch (data.type) {
    case 'urlChange':
      handler = updatePath;
      break;
    case 'setMeta':
      handler = updateMeta;
      break;
    case 'localeGet':
      handler = emitLocale;
      break;
    default:
      handler = logUnhandled;
  }
  handler(data.value);
}

window.addEventListener('message', handleIframeMessages);
