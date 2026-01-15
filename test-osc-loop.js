const OSC = require('osc-js');
const osc = new OSC({ plugin: new OSC.DatagramPlugin({ send: { host: '127.0.0.1', port: 10023 } }) });

osc.on('open', () => {
    console.log('Starting Looping UI Test Pulse...');
    osc.send(new OSC.Message('/live/scene_duration', 32)); // 8 bars
    
    let beat = 0;
    const interval = setInterval(() => {
        osc.send(new OSC.Message('/live/beat', beat));
        beat += 1.0;
        if (beat > 32) beat = 0;
        console.log(`UI Pulse: Beat ${beat}`);
    }, 1000);

    setTimeout(() => {
        clearInterval(interval);
        process.exit(0);
    }, 15000); // Run for 15 seconds
});

osc.open();
