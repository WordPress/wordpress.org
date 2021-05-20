window.addEventListener('message', ({ origin, data }) => {
    if (data.debug) {
        console.log(`Received message from origin ${origin}`);
        console.log(`Data: ${JSON.stringify(data)}`)
    }
    let height = data?.height;
    if (height) {
        height = `${height}px`;
    } else {
        height = '100vh';
    }
    document
        .documentElement
        .style
        .setProperty('--openverse-embed-height', height);
})