const easymidi = require('easymidi');

const inputs = easymidi.getInputs();
const targetParamsInput = ['IAC', 'Ableton', 'Bus 1'];
const foundInputName = inputs.find(name => targetParamsInput.some(t => name.includes(t))) || inputs[0];

if (!foundInputName) {
    console.error("No MIDI inputs found!");
    process.exit(1);
}

console.log(`Listening on: ${foundInputName}`);
console.log("Waiting for messages... (Press Ctrl+C to stop)");

const input = new easymidi.Input(foundInputName);

input.on('noteon', msg => console.log('NOTE ON:', msg));
input.on('program', msg => console.log('PROGRAM CHANGE:', msg));
input.on('cc', msg => console.log('CC:', msg));
