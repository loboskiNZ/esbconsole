const OSC = require('osc-js');
const osc = new OSC({ plugin: new OSC.DatagramPlugin({ send: { host: '127.0.0.1', port: 10023 } }) });

osc.on('open', () => {
  console.log('Sending Unique Test Pulse...');
  osc.send(new OSC.Message('/live/scene_duration', 32)); // 8 bars
  setTimeout(() => {
    osc.send(new OSC.Message('/live/beat', 12.0)); // Bar 4.1
    setTimeout(() => process.exit(0), 500);
  }, 200);
});

osc.open();
