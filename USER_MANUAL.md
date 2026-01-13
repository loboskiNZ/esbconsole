# X32-Live-DMX System Guide

Welcome to the **X32-Live-DMX Controller**, a unified performance system designed to bridge the gap between Audio, Lighting, and Setlist management. This guide serves as your comprehensive reference for architecture, usage, and pro tips.

---

## 1. Architectural Overview

The core philosophy of this system is **"One Interface to Rule Them All."** Instead of juggling a mixing console, a lighting desk, a setlist app, and a DAW controller, this system centralizes everything into a single, responsive Web UI.

### Map of Protocols & Systems

This system acts as a "Universal Translator" in the center of your stage network.

```mermaid
graph TD
    User[👩‍💻 User / Tablet] -->|WebSocket / HTTP| Server[🖥️ Node.js Server]
    
    subgraph "Audio World"
        Server -->|OSC (UDP 10023)| X32[🎚️ Behringer X32]
        X32 -->|OSC Updates| Server
    end
    
    subgraph "Music World"
        Server -->|MIDI over USB| Ableton[🎹 Ableton Live / Push]
        Ableton -->|MIDI Clock/Notes| Server
    end
    
    subgraph "Lighting World"
        Server -->|ArtNet / DMX| Lights[💡 DMX Lighting Grid]
    end
    
    subgraph "Cloud & Files"
        Server -->|Microsoft Graph API| SP[☁️ SharePoint]
        SP -->|Get PDFs| Server
    end
```

### Key Technologies
- **Node.js (Backend)**: The brain of the operation. It maintains the "State" of the show.
- **React + Vite (Frontend)**: The beautiful Dark Mode interface you interact with.
- **OSC (Open Sound Control)**: The language of the X32. We send thousands of messages per second to keep faders in sync.
- **WebSocket (Socket.IO)**: Real-time bi-directional link between your tablet and the server. Latency is near-zero.
- **Microsoft Graph**: Connects to your band's cloud storage to fetch charts automatically.

---

## 2. Feature Guide: Frontend

### A. The Main Mixer (The "Desk")
The main view replicates the faders you know, but smarter.
- **Faders**: Control main mixes. Changes reflect instantly on the physical desk.
- **Mute Groups**: Custom groups for Drums, Vox, etc.
- **Smart Solo**:
    - **Exclusive Solo**: Pressing 'S' on a group isolates it (mutes everything else).
    - **Dim Mode**: In Solo mode, background tracks aren't killed completely; they are dimmed by -20dB so you keep context.

### B. Gain & Preamp Overlay
Click on any channel strip header to open the detailed **Channel Inspector**.
- **Gain & Phantom**: Control the analog headamp gain and 48V phantom power.
- **Phase (Ø)**: Fix polarity issues instantly.
- **Gate & Comp**: Visual sliders for dynamics.
- **Instrument Presets**:
    - **Pro Tip**: Use the "Quick Presets" menu to instantly dial in a Kick Drum, Snare, or Vocal. These are professionally tuned starting points.

### C. Monitors Overlay (The "IEM" Controller)
Designed for musicians to control their own ears.
- **Sends on Fader**: Select a Bus (e.g., "Vox Ed") and see all channel sends to that bus.
- **Smart Naming**: The system automatically names Monitor Buses based on your Setlist routing. If you assign "Vox Ed" to Bus 1, the fader is labelled "Vox Ed".

### D. Setlist Management (The "Conductor")
Forget paper setlists.
- **Active Song**: Clicking a song makes it "Active". This syncs across all iPads on stage.
- **Chart Viewer**: Automatically displays the correct PDF chart (fetched from SharePoint) for the assigned instrument.
- **Automation**: Loading a song can trigger MIDI changes in Ableton (for backing tracks) and Lighting Scene changes.

### E. Scene Management (The "Recall")
Safely save and load the comprehensive state of the X32.
- **Sequential Sync**: Loading a scene performs a "Safe Sync" over 5-10 seconds to prevent audio pops.
- **Auto-Routing**: It restores your FX Rack (Reverbs/Delays) and names the FX Returns automatically ("FX 1" - "FX 4").

---

## 3. FX Rack & "Dub" Features

This system specializes in **Live Dub Mixing**.
- **Bus 13 (Dub Throw)**: A dedicated "Send" bus for dub delays.
- **Master Combinator (Slot 8)**: A powerful multiband processor on the Master Bus.
- **Auto-FX**: The system defaults to a classic Dub setup:
    1.  **Vintage Room** (Drums)
    2.  **Plate Reverb** (Vocals)
    3.  **Stereo Delay** (The "Dub" Echo)
    4.  **Combinator** (Master Glue)

---

## 4. Tips & Troubleshooting

### Known Issues
- **"Black Strips"**: If a channel appears black after loading, it means the Scene File lacked color data.
    - **Fix**: The system now attempts to "Auto-Color" buses to Yellow/Magenta to prevent invisible controls. Reloading the scene usually fixes this.
- **Connection Drop**: If the "DEBUG" banner turns Red, check your Ethernet cable to the X32.

### Recommendations
1.  **Save Often**: Use the backend's "Save Scene" feature. It saves JSON files that are human-readable (and editable!).
2.  **Use Groups**: Assign input channels to Groups (Drums, Vox) in `config.js` to enable the powerful "Group Solo" features.
3.  **Tablet Mode**: Add the web page to your iPad Home Screen for a fullscreen "App-like" experience.

---

*This system was built with ❤️ for Ed and The Shadow Boys.*

---

## 5. Pro Tips: Tuning Guide (Restored)

These tuning tips were originally embedded in the system's core configuration. They provide the philosophy behind each "Quick Preset."

### Drums
- **Kick (Out)**: *Let the low end breathe.* Boost ~60Hz, Cut ~400Hz (Mud), Boost ~4kHz (Click). Gate hard.
- **Snare**: *Body Boost & Crack.* Boost ~200Hz, Cut Boxiness, Add Air ~5kHz.
- **Rack Tom**: *Reduce Ring.* Cut ~500Hz, Add Attack ~3kHz.
- **Floor Tom**: *Control the Boom.* Cut ~550Hz to remove boxiness.
- **Overheads**: *Cymbals Only.* HPF at 400Hz to remove drum bleed. Boost Air.
- **Conga**: *Ring/Slap Control.* Cut 650Hz (Ring), Boost 850Hz (Slap).
- **Cowbell**: *Pure Attack.* Boost 800Hz.

### Instruments
- **Bass Gtr**: *Definition is key.* Cut Mud at ~300Hz, Boost Definition at ~800Hz. Compress 4:1.
- **E. Guitar**: *Cut the Fizz.* HPF at 100Hz, Cut Mud. Boost Bite at 2.5kHz. High Cut at 6kHz (Fizz).
- **Ac. Guitar**: *Sparkle & Control.* Cut Body Boom, Cut Feedback range (~650Hz). Add Sparkle.
- **Ukelele**: *Remove "Plastic" sound.* Cut ~700Hz.

### Vocals
- **Male Vox**: *Presence & Clarity.* Cut Mud ~300Hz. Boost Presence ~3kHz.
- **Female Vox**: *Warmth not Mud.* Balance 600Hz. Boost Air ~4kHz.

### Horns
- **Trumpet**: *Remove Honk.* Cut ~650Hz. Add Brightness.
- **Sax (Alto/Tenor)**: *Remove Squawk.* Cut ~600Hz. Boost Reed/Body.
- **Trombone**: *Bite.* Cut Mud, Boost 750Hz.
- **Sousaphone**: *Heavy Compression.* Low Boost. Ratio 4:1.
