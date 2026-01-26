
const fs = require('fs');

// Read file content
const content = fs.readFileSync('/Volumes/WORKER/machines/x32-controller/lighting-engine.js', 'utf8');

console.log("Verifying cleanups...");

// 1. Check Patch Comment
if (content.includes('// 4x Bars (48ch each) -> 1, 49, 97, 145')) {
    console.log("Patch Comment: CORRECT");
} else {
    console.error("Patch Comment: FAILED");
    process.exit(1);
}

// 2. Check applyFocus reset
if (content.includes('this.fixtures.bars.forEach(addr => { for(let i=0; i<8; i++) this.setBarPixel(update, addr+(i*6), 0,0,0); });')) {
    console.log("applyFocus Reset: CORRECT (8 segments x 6ch cleared)");
} else {
    console.error("applyFocus Reset: FAILED (Looked for setBarPixel loop)");
    process.exit(1);
}

// 3. Check for duplicates
const countOccurrences = (str, sub) => str.split(sub).length - 1;

const countHell = countOccurrences(content, 'toggleHellColor() {');
const countSun = countOccurrences(content, 'pulseSunshine() {');

console.log(`toggleHellColor definitions: ${countHell}`);
console.log(`pulseSunshine definitions: ${countSun}`);

if (countHell === 1 && countSun === 1) {
    console.log("Duplicates Removed: YES");
} else {
    console.error("Duplicates Removed: NO");
    process.exit(1);
}

console.log("All checks passed.");
