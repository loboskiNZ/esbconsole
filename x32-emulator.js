const OSC = require('osc-js');

// Configuration
// We send TO the controller which is listening on 10023
const REMOTE_HOST = '127.0.0.1';
const REMOTE_PORT = 10023;

const osc = new OSC({
  plugin: new OSC.DatagramPlugin({
    send: { host: REMOTE_HOST, port: REMOTE_PORT },
    // We don't really need to open a port to listen, but good practice
    open: { host: '0.0.0.0', port: 10024 } 
  })
});

osc.open();

console.log(`👻 Ghost Console X32 running...`);
console.log(`📡 Sending OSC events to ${REMOTE_HOST}:${REMOTE_PORT}`);
console.log(`Press Ctrl+C to stop the haunting.`);

// Helper to send a fader move
function sendFader(channel, value) {
    const ch = String(channel).padStart(2, '0');
    const address = `/ch/${ch}/mix/fader`;
    osc.send(new OSC.Message(address, value));
    // console.log(`🎚 Ch ${channel} -> ${(value*100).toFixed(0)}%`);
}

// Helper to send a mute toggle
function sendMute(channel, isMuted) {
    const ch = String(channel).padStart(2, '0');
    const address = `/ch/${ch}/mix/on`;
    // X32 Logic: 0 = Muted, 1 = Unmuted. 
    // If isMuted is true, we want Mute, so send 0.
    const val = isMuted ? 0 : 1;
    osc.send(new OSC.Message(address, val));
    console.log(`🚫 Ch ${channel} Mute: ${isMuted}`);
}

// --- The Haunting Logic ---

// 1. Random Fader Waves
setInterval(() => {
    // Pick a random channel between 1 and 27
    const ch = Math.floor(Math.random() * 27) + 1;
    // Random float 0.0 to 1.0
    const level = Math.random();
    sendFader(ch, level);
}, 200); // Very fast updates (5 per second)

// 2. Random Mute Toggles
setInterval(() => {
    const ch = Math.floor(Math.random() * 27) + 1;
    const muteState = Math.random() > 0.5;
    sendMute(ch, muteState);
}, 2000); // Every 2 seconds

// 3. Sine Wave on Channel 1 (Kick)
let angle = 0;
setInterval(() => {
    angle += 0.1;
    const level = (Math.sin(angle) + 1) / 2; // Normalize -1..1 to 0..1
    sendFader(1, level);
}, 100);
