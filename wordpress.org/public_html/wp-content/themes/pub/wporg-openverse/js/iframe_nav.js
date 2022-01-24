document.addEventListener('DOMContentLoaded', () => {
  const { pathname: path, search: query } = window.location; // starts with /

  let iframePath = path
    .toLocaleLowerCase()
    .replace(openverseSubpath, '')
    .replace(/^\/$/, ''); // Remove Openverse site subpath
  iframePath = `${openverseUrl}${iframePath}${query}`; // Add domain and query

  console.log(`Navigating iframe to ${iframePath}`);
  const iframe = document.getElementById('openverse_embed');
  iframe.src = iframePath;
});
