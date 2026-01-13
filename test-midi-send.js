const easymidi = require('easymidi');

const outputs = easymidi.getOutputs();
const targetParamsInput = ['IAC', 'Ableton', 'Bus 1'];
const foundOutputName = outputs.find(name => targetParamsInput.some(t => name.includes(t))) || outputs[0];

if (!foundOutputName) {
    console.error("No MIDI outputs found!");
    process.exit(1);
}

console.log(`Sending Program Change 1 to: ${foundOutputName}`);
const output = new easymidi.Output(foundOutputName);

// Send Program Change 1 (which usually maps to index 1 or 2 depending on 0-indexing)
output.send('program', {
    channel: 0,
    number: 1
});

console.log("Message sent.");
setTimeout(() => process.exit(0), 100);
