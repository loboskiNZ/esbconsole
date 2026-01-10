const DMX = require('dmx');
const LightingEngine = require('./lighting-engine');

const dmx = new DMX();
const engine = new LightingEngine(dmx, 'test_universe', 'null');

const scenes = [
    'setup', 'hell', 'sunshine', 'madness', 'aqua', 'rasta', 
    'focus', 'focusLeft', 'focusRight', 'police'
];

let index = 0;

console.log('🚀 Starting Self-Test Cycle...');

const nextScene = () => {
    if (index >= scenes.length) {
        console.log('✅ All Scenes Passed. Shutting Down.');
        engine.blackout();
        process.exit(0);
        return;
    }

    const scene = scenes[index];
    console.log(`\n--- Testing: ${scene.toUpperCase()} ---`);
    engine.play(scene);

    // Simulate "Pulse" for beat-synced scenes
    if (['hell', 'sunshine'].includes(scene)) {
        console.log('   (Sending Beat Pulse...)');
        engine.pulse();
    }

    index++;
    // Run each scene for 2 seconds
    setTimeout(nextScene, 2000);
};

// Start
nextScene();
