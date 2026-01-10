module.exports = {
  // Define Global Channel Map
  // X32 Color IDs: 1:Red, 2:Green, 3:Yellow, 4:Blue, 5:Magenta, 6:Cyan, 7:White
  inputs: [
    { id: 1, name: 'Kick', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 2, name: 'Snare', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 3, name: 'Hat', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 4, name: 'Rack Tom 1', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 5, name: 'Rack Tom 2', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 6, name: 'Floor Tom', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 7, name: 'OH1', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 8, name: 'OH2', type: 'drum', colorId: 6, colorHex: '#00FFFF' },
    { id: 9, name: 'Bass', type: 'instrument', colorId: 5, colorHex: '#FF00FF' },
    { id: 10, name: 'Gat', type: 'instrument', colorId: 1, colorHex: '#FF0000' },
    { id: 11, name: 'Keys 1', type: 'instrument', colorId: 3, colorHex: '#FFFF00' },
    { id: 12, name: 'Keys 2', type: 'instrument', colorId: 3, colorHex: '#FFFF00' },
    { id: 13, name: 'Sous', type: 'brass', colorId: 2, colorHex: '#00FF00' },
    { id: 14, name: 'Tromb', type: 'brass', colorId: 2, colorHex: '#00FF00' },
    { id: 15, name: 'Sax', type: 'brass', colorId: 2, colorHex: '#00FF00' },
    { id: 16, name: 'Trump', type: 'brass', colorId: 2, colorHex: '#00FF00' },
    { id: 17, name: 'Vox Ed', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 18, name: 'Vox Nathan', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 19, name: 'Vox Claire', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 20, name: 'Vox Alan', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 21, name: 'Vox Hyram', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 22, name: 'Vox Davina', type: 'vocal', colorId: 4, colorHex: '#0000FF' },
    { id: 23, name: '-', type: 'empty', colorId: 0, colorHex: '#333333' },
    { id: 24, name: '-', type: 'empty', colorId: 0, colorHex: '#333333' },
    { id: 25, name: 'ABL Drums', type: 'track', colorId: 7, colorHex: '#FFFFFF' },
    { id: 26, name: 'ABL Bass', type: 'track', colorId: 7, colorHex: '#FFFFFF' },
    { id: 27, name: 'ABL Keys', type: 'track', colorId: 7, colorHex: '#FFFFFF' },
    { id: 28, name: 'ABL Spare 1', type: 'track', colorId: 7, colorHex: '#FFFFFF' },
    { id: 29, name: 'ABL Spare 2', type: 'track', colorId: 7, colorHex: '#FFFFFF' },
    { id: 30, name: 'Spare 3', type: 'empty', colorId: 0, colorHex: '#333333' },
    { id: 31, name: 'Spare 4', type: 'empty', colorId: 0, colorHex: '#333333' },
    { id: 32, name: 'Spare 5', type: 'empty', colorId: 0, colorHex: '#333333' },
  ],

  groups: [
      { key: 'drums', ids: ['1','2','3','4','5','6','7','8'], label: 'Drums', bg: '#00FFFF', txt: 'black' }, 
      { key: 'bass', ids: ['9'], label: 'Bass', bg: '#FF00FF', txt: 'black' },
      { key: 'gats', ids: ['10'], label: 'Gats', bg: '#FF0000', txt: 'white' },
      { key: 'keys', ids: ['11','12'], label: 'Keys', bg: '#FFFF00', txt: 'black' },
      { key: 'horns', ids: ['13','14','15','16'], label: 'Horns', bg: '#00FF00', txt: 'black' },
      { key: 'vox', ids: ['17','18','19','20','21','22'], label: 'Vox', bg: '#0000FF', txt: 'white' },
      { key: 'samples', ids: ['25','26','27','28','29'], label: 'Tracks', bg: '#FFFFFF', txt: 'black' },
      { key: 'click', ids: [], label: 'Click', bg: '#CCCCCC', txt: 'black' },
  ],
  
  songs: [
    {
      id: 1,
      title: 'Demo Song',
      parts: [
        { 
            name: 'Intro', 
            cues: { 
                x32: { 
                    '1': { mute: false, level: 0.75 }, // Kick
                    '25': { mute: false, level: 0.8 }, // Drums Track
                }, 
                midi: { note: 10 }, // Scene 1
                dmx: { values: { 1: 255, 2: 0, 3: 0 } } // Red Light
            } 
        },
        { 
            name: 'Verse 1', 
            cues: { 
                x32: { 
                    '25': { level: 0.5 }, // Drop tracks
                    '17': { mute: false, level: 0.8 } // Vox Ed
                }, 
                midi: { note: 11 }, 
                dmx: { values: { 1: 0, 2: 0, 3: 255 } } // Blue Light
            } 
        },
        { 
            name: 'Chorus', 
            cues: { 
                x32: { 
                    '1': { level: 0.85 },
                    '2': { level: 0.85 },
                    '25': { level: 0.8 }
                }, 
                midi: { note: 12 }, 
                dmx: { values: { 1: 255, 2: 255, 3: 255 } } // White strobe?
            } 
        },
        { name: 'Bridge', cues: { x32: {}, midi: { note: 13 }, dmx: {} } },
        { name: 'Outro', cues: { x32: {}, midi: { note: 14 }, dmx: {} } }
      ]
    },
    {
      id: 2,
      title: 'Another Song',
      parts: [
        { name: 'Intro', cues: { midi: { note: 20 } } },
        { name: 'Main', cues: { midi: { note: 21 } } },
        { name: 'End', cues: { midi: { note: 22 } } }
      ]
    }
  ]
};
