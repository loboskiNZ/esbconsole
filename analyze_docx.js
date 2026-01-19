const fs = require('fs');
const PizZip = require('pizzip');
const Docxtemplater = require('docxtemplater');

const content = fs.readFileSync('resources/ESB_Tech_Rider.docx', 'binary');
const zip = new PizZip(content);
const doc = new Docxtemplater(zip, {
    paragraphLoop: true,
    linebreaks: true,
});

const text = doc.getFullText();
const tags = text.match(/\{[^}]+\}/g);
console.log("Found tags:", [...new Set(tags)]);
