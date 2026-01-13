const easymidi = require('easymidi');

console.log('--- Inputs ---');
easymidi.getInputs().forEach(input => console.log(input));

console.log('\n--- Outputs ---');
easymidi.getOutputs().forEach(output => console.log(output));
