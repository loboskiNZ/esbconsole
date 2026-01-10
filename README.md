# X32-Live-DMX Controller

This application orchestrates a Behringer X32, Ableton Live, and DMX Lights using a unified "Song/Part" based interface.

## Features
- **X32 Control**: Sends OSC commands via Ethernet.
- **Ableton Control**: Sends MIDI notes/CC via USB.
- **DMX Control**: Support for USB-DMX interfaces (e.g., Enttec).
- **Web Interface**: Modern dark-mode UI for live performance.

## Setup

1. **Install Dependencies**:
   ```bash
   npm install
   cd client && npm install
   ```

2. **Network Configuration**:
   - Connect X32 via Ethernet.
   - Set X32 IP in `index.js` (Default: `192.168.1.50`).
   - Ensure your computer is in the same subnet.

3. **Ableton Setup**:
   - Connect Ableton computer via USB (MIDI).
   - In Ableton Preferences > MIDI, enable "Remote" for the input coming from this controller.
   - Map Scenes to MIDI Notes as defined in `config.js`.

## Running the App

1. **Build the Interface**:
   ```bash
   cd client
   npm run build
   cd ..
   ```

2. **Start the Controller**:
   ```bash
   node index.js
   ```

3. **Open the UI**:
   - Go to `http://localhost:3000` in your browser.

## Configuration
Edit `config.js` to define your songs and cues.

```javascript
songs: [
  {
    id: 1,
    title: 'My Song',
    parts: [
      { 
        name: 'Chorus', 
        cues: { 
          x32: { '1': { mute: false, level: 0.8 } }, // Unmute Ch 1, Fader 0.8
          midi: { note: 10 }, // Trigger MIDI Note 10 (Scene 10)
          dmx: { scene: 5 } 
        } 
      }
    ]
  }
]
```

## Channel Map
The app is pre-configured with your specific channel layout (1-27).
