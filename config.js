module.exports = {
  // Define Global Channel Map
  inputs: [
    { id: 1, name: 'Kick', type: 'drum' },
    { id: 2, name: 'Snare', type: 'drum' },
    { id: 3, name: 'Hat', type: 'drum' },
    { id: 4, name: 'Rack Tom 1', type: 'drum' },
    { id: 5, name: 'Rack Tom 2', type: 'drum' },
    { id: 6, name: 'Floor Tom', type: 'drum' },
    { id: 7, name: 'OH1', type: 'drum' },
    { id: 8, name: 'OH2', type: 'drum' },
    { id: 9, name: 'Bass', type: 'instrument' },
    { id: 10, name: 'Gat', type: 'instrument' },
    { id: 11, name: 'Keys 1', type: 'instrument' },
    { id: 12, name: 'Keys 2', type: 'instrument' },
    { id: 13, name: 'Sous', type: 'brass' },
    { id: 14, name: 'Tromb', type: 'brass' },
    { id: 15, name: 'Sax', type: 'brass' },
    { id: 16, name: 'Trump', type: 'brass' },
    { id: 17, name: 'Vox Ed', type: 'vocal' },
    { id: 18, name: 'Vox Nathan', type: 'vocal' },
    { id: 19, name: 'Vox Claire', type: 'vocal' },
    { id: 20, name: 'Vox Alan', type: 'vocal' },
    { id: 21, name: 'Vox Hyram', type: 'vocal' },
    { id: 22, name: 'Vox Davina', type: 'vocal' },
    { id: 23, name: '-', type: 'empty' },
    { id: 24, name: '-', type: 'empty' },
    { id: 25, name: 'ABL Drums', type: 'track' },
    { id: 26, name: 'ABL Bass', type: 'track' },
    { id: 27, name: 'ABL Keys', type: 'track' },
    { id: 28, name: 'ABL Spare 1', type: 'track' },
    { id: 29, name: 'ABL Spare 2', type: 'track' },
    { id: 30, name: 'Spare 3', type: 'empty' },
    { id: 31, name: 'Spare 4', type: 'empty' },
    { id: 32, name: 'Spare 5', type: 'empty' },
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
