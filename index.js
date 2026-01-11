const express = require('express');
const http = require('http');
const https = require('https');
const socketIo = require('socket.io');
const OSC = require('osc-js');
const easymidi = require('easymidi');
const os = require('os');
const DMX = require('dmx');
const config = require('./config');
const authManager = require('./authManager');
const setlistManager = require('./setlistManager');
const path = require('path');
const fs = require('fs');

// GLOBAL ERROR HANDLER
process.on('uncaughtException', (err) => {
    console.error('🔥 UNCAUGHT EXCEPTION:', err);
    fs.appendFileSync('crash.log', `[${new Date().toISOString()}] CRASH: ${err.stack}\n`);
    // Keep running if possible, or exit? For dev, let's try to keep running or at least log effectively.
    // process.exit(1); 
});
process.on('unhandledRejection', (reason, promise) => {
    console.error('🔥 UNHANDLED REJECTION:', reason);
    fs.appendFileSync('crash.log', `[${new Date().toISOString()}] REJECTION: ${reason}\n`);
});

// --- GLOBALS ---
let meterInterval = null;
let musiciansData = { musicians: [], sharepoint: {} };
try {
    if (fs.existsSync('./musicians.json')) {
        musiciansData = JSON.parse(fs.readFileSync('./musicians.json', 'utf8'));
    }
} catch (e) {
    console.error("Failed to load musicians.json", e);
}

// --- SOLO GROUPS DEFINITION ---
const SOLO_GROUPS = {
    drums: ['1','2','3','4','5','6','7','8'],
    bass: ['9'],
    gats: ['10'],
    keys: ['11','12'],
    horns: ['13','14','15','16'],
    vox: ['17','18','19','20','21','22'],
    samples: ['25','26','27','28','29']
};

const app = express();

// HTTPS CONFIG
let server;
try {
    const certPath = path.join(__dirname, 'certs');
    const options = {
        key: fs.readFileSync(path.join(certPath, 'key.pem')),
        cert: fs.readFileSync(path.join(certPath, 'cert.pem'))
    };
    server = https.createServer(options, app);
    console.log("🔒 HTTPS Enabled");
} catch (e) {
    console.warn("⚠️ HTTPS Failed (Missing certs?), falling back to HTTP", e.message);
    server = http.createServer(app);
}

const io = socketIo(server, { cors: { origin: '*' } });

// Middleware - MUST BE BEFORE ROUTES
app.use(express.json());
app.use(express.static(path.join(__dirname, 'client/dist')));

const PORT = 3000;
const X32_PORT = 10023;
const X32_IP = '10.0.0.202';

const STATE_FILE = path.join(__dirname, 'x32_state.json');

// --- Interfaces ---

const x32State = {}; 

// Persistence Helpers
function saveState() {
    // Debounce or just raw write? For simplicity and robustness, specific save call or throttled.
    // Let's use a simple throttled flag.
    if (global.saveTimer) clearTimeout(global.saveTimer);
    global.saveTimer = setTimeout(() => {
        fs.writeFile(STATE_FILE, JSON.stringify(x32State, null, 2), (err) => {
            if (err) console.error("❌ Failed to save state:", err);
            else console.log("💾 State Saved");
        });
    }, 1000); // 1-second debounce
}

function loadState() {
    try {
        if (fs.existsSync(STATE_FILE)) {
            const data = fs.readFileSync(STATE_FILE);
            const saved = JSON.parse(data);
            Object.assign(x32State, saved);
            console.log("📂 State Loaded from Disk");
        }
    } catch (err) {
        console.error("⚠️ Failed to load state:", err);
    }
}

// --- SCENES API ---

app.get('/api/scenes', (req, res) => {
    const scenesDir = path.join(__dirname, 'scenes');
    if (!fs.existsSync(scenesDir)) fs.mkdirSync(scenesDir);
    
    fs.readdir(scenesDir, (err, files) => {
        if (err) return res.status(500).json({ error: err.message });
        const scenes = files.filter(f => f.endsWith('.json')).map(f => f.replace('.json', ''));
        res.json(scenes);
    });
});

app.get('/api/config', (req, res) => {
    res.json(config);
});

app.post('/api/scenes/save', (req, res) => {
    const { name } = req.body;
    if (!name) return res.status(400).json({ error: "Name required" });
    
    const filePath = path.join(__dirname, 'scenes', `${name}.json`);
    fs.writeFile(filePath, JSON.stringify(x32State, null, 2), (err) => {
        if (err) return res.status(500).json({ error: err.message });
        res.json({ success: true, name });
    });
});

app.post('/api/scenes/load', (req, res) => {
    const { name } = req.body;
    const filePath = path.join(__dirname, 'scenes', `${name}.json`);
    
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: "Scene not found" });
    
    fs.readFile(filePath, (err, data) => {
        if (err) return res.status(500).json({ error: err.message });
        
        try {
            const loadedState = JSON.parse(data);
            
            // 1. Update Internal State
            Object.assign(x32State, loadedState);
            saveState(); // Persist as "current" state
            
            // 2. Notify Frontend
            io.emit('x32_bulk_update', x32State);
            
            // 3. SYNC HARDWARE (Blast OSC)
            console.log(`Title: Loading Scene "${name}" - Syncing Hardware...`);
            Object.entries(x32State).forEach(([chId, ch]) => {
                // Fader
                let addr = getX32Address(chId, 'level');
                if (addr && ch.level !== undefined) osc.send(new OSC.Message(addr, Number(ch.level)));
                
                // Mute
                addr = getX32Address(chId, 'mute');
                if (addr && ch.mute !== undefined) osc.send(new OSC.Message(addr, ch.mute ? 0 : 1));
                
                // EQ On/Off
                addr = getX32Address(chId, 'eq');
                if (addr && ch.eq !== undefined) osc.send(new OSC.Message(addr, ch.eq ? 1 : 0));
                
                // ... (Add other params as needed: Gate, Dyn, Bands)
                // Minimally viable sync for now to avoid prolonged freeze
            });
            
            res.json({ success: true });
        } catch (e) {
            res.status(500).json({ error: "Corrupt scene file" });
        }
    });
});

// Static Files lasth default empty state
for (let i = 1; i <= 32; i++) {
    x32State[String(i)] = { 
        level: 0, 
        mute: true,
        // ... (standard props)
        gate: false, dyn: false, eq: false, hpf: false,
        hpfFreq: 0.0, hcut: false, hcutFreq: 1.0, 
        hpfFreq: 0.0, hcut: false, hcutFreq: 1.0, 
        preampGain: 0.5, phantom: false, invert: false,
        pan: 0.5, link: false,
        gateThr: 0.5, gateAttack: 0.0, gateHold: 0.0, gateRelease: 0.0,
        dynThr: 0.5, dynRatio: 0.0, dynGain: 0.5, dynAttack: 0.0, dynHold: 0.0, dynRelease: 0.0,
        // Custom Color Props
        color: null,
        labelColor: null,
        
        // EQ Bands: 1=Low, 2=LMid, 3=HMid, 4=High
        // Type: 0=PEQ, 1=LowShelf, 2=HiShelf
        eqBands: {
            1: { f: 0.2, g: 0.5, q: 0.5, type: 0 }, 
            2: { f: 0.4, g: 0.5, q: 0.5, type: 0 },
            3: { f: 0.6, g: 0.5, q: 0.5, type: 0 },
            4: { f: 0.8, g: 0.5, q: 0.5, type: 0 }
        }
    };
}
// Initialize Bus State (1-16)
for (let i = 1; i <= 16; i++) {
    x32State['bus' + i] = {
        level: 0,
        mute: false,
        name: `Bus ${i}`,
        color: null
    };
}

// Initialize Master State
x32State.master = {
    level: 0,
    mute: false,
    eqBands: {
        1: { f: 0.2, g: 0.5, q: 0.5, type: 0 },
        2: { f: 0.4, g: 0.5, q: 0.5, type: 0 },
        3: { f: 0.6, g: 0.5, q: 0.5, type: 0 },
        4: { f: 0.8, g: 0.5, q: 0.5, type: 0 }
    }
};

// Load persisted state immediately
loadState();

// 1. X32 OSC Interface
console.log(`🔌 Connecting to X32 at ${X32_IP}:${X32_PORT}...`);
const osc = new OSC({
    plugin: new OSC.DatagramPlugin({
        send: { host: X32_IP, port: X32_PORT },
        open: { host: '0.0.0.0', port: 10023 }
    })
});

osc.on('open', () => {
    console.log('✅ OSC Port Open');
    syncConfigToConsole();
    
    // Start X32 Subscription Loop (Heartbeat)
    setInterval(() => {
        try {
            osc.send(new OSC.Message('/xremote'));
        } catch(e) { /* ignore */ }
    }, 9000);
});
osc.open();

// --- FORCE SYNC ---
function syncConfigToConsole() {
    console.log("🔄 FORCE SYNC: Overwriting Console Strip Config...");
    config.inputs.forEach(ch => {
        if (!ch.name || ch.id > 32) return;

        // 1. Set Name
        // Address: /ch/{01..32}/config/name s "Name"
        const paddedId = String(ch.id).padStart(2, '0');
        osc.send(new OSC.Message(`/ch/${paddedId}/config/name`, ch.name));

        // 2. Set Color
        // Address: /ch/{01..32}/config/color i {id}
        // Colors: 1:Red, 2:Green, 3:Yellow, 4:Blue, 5:Magenta, 6:Cyan, 7:White
        if (ch.colorId) {
             osc.send(new OSC.Message(`/ch/${paddedId}/config/color`, Number(ch.colorId)));
        }
        
        // Also update internal state so UI gets it immediately if connected
        if (!x32State[ch.id]) x32State[ch.id] = {};
        x32State[ch.id].name = ch.name;
        x32State[ch.id].color = ch.colorHex;

        // Spread transmission to avoid packet loss
        // (Actually osc-js handles queueing reasonably well, but we rely on speed here)
    });
    console.log("✅ Sync Commands Sent");
}

// Start Polling (Attaches listeners)
// Ensure startMeterPolling is defined/hoisted or move this call to after definition.
// Functions are hoisted in JS if defined with function keyword.
startMeterPolling();

// Helper to parse OSC path back to Channel ID
function parseX32Path(address) {
    const parts = address.split('/');
    
    if (parts[1] === 'headamp') {
        const ha = parseInt(parts[2], 10);
        const ch = ha + 1;
        if (parts[3] === 'gain') return { id: String(ch), type: 'preampGain' };
        if (parts[3] === 'phantom') return { id: String(ch), type: 'phantom' };
    }
    
    if (parts[1] === 'main' && parts[2] === 'st') {
        if (parts[3] === 'mix') {
             if (parts[4] === 'on') return { id: 'master', type: 'mute' };
             if (parts[4] === 'fader') return { id: 'master', type: 'level' };
        }
        if (parts[3] === 'eq') {
             const band = parseInt(parts[4]);
             if (!isNaN(band)) {
                  const param = parts[5];
                  if (['f', 'g', 'q', 'type'].includes(param)) {
                      return { id: 'master', type: 'eqParam', band, param };
                  }
             }
        }
    }

    if (parts[1] === 'ch') {
        const id = parseInt(parts[2], 10);
        if (parts[3] === 'mix') {
             if (parts[4] === 'on') return { id: String(id), type: 'mute' };
             if (parts[4] === 'fader') return { id: String(id), type: 'level' };
             
             // Mix Sends: /ch/XX/mix/BUS/level or on
             const bus = parts[4];
             if (!isNaN(parseInt(bus))) {
                 const param = parts[5];
                 if (['level', 'on'].includes(param)) {
                     // Normalize bus ID (remove leading zero) to match x32State keys
                     const normalizedBus = String(parseInt(bus, 10));
                     return { id: String(id), type: 'mixSend', bus: normalizedBus, param };
                 }
             }
        }
        if (parts[3] === 'gate') {
            if (parts[4] === 'on') return { id: String(id), type: 'gate' };
            if (parts[4] === 'thr') return { id: String(id), type: 'gateThr' };
            if (parts[4] === 'attack') return { id: String(id), type: 'gateAttack' };
            if (parts[4] === 'hold') return { id: String(id), type: 'gateHold' };
            if (parts[4] === 'release') return { id: String(id), type: 'gateRelease' };
        }
        if (parts[3] === 'preamp') {
             if (parts[4] === 'invert') return { id: String(id), type: 'invert' }; // Maps to x32State[id].invert
             if (parts[4] === 'hpon') return { id: String(id), type: 'hpf' };
             if (parts[4] === 'hpf') return { id: String(id), type: 'hpfFreq' };
        }
        if (parts[3] === 'dyn') {
            if (parts[4] === 'on') return { id: String(id), type: 'dyn' };
            if (parts[4] === 'thr') return { id: String(id), type: 'dynThr' };
            if (parts[4] === 'ratio') return { id: String(id), type: 'dynRatio' };
            // ...
        }
        if (parts[3] === 'eq') {
             if (parts[4] === 'on') return { id: String(id), type: 'eq' };
             const band = parseInt(parts[4]);
             if (!isNaN(band)) {
                 const param = parts[5]; 
                 if (['f', 'g', 'q', 'type'].includes(param)) {
                     return { id: String(id), type: 'eqParam', band, param };
                 }
             }
        }
        if (parts[3] === 'preamp') {
             if (parts[4] === 'hpon') return { id: String(id), type: 'hpf' };
             if (parts[4] === 'hpf') return { id: String(id), type: 'hpfFreq' };
        }
    }

    if (parts[1] === 'bus') {
        const id = parseInt(parts[2], 10);
        const busId = 'bus' + id;
        if (parts[3] === 'mix') {
             if (parts[4] === 'on') return { id: busId, type: 'mute' };
             if (parts[4] === 'fader') return { id: busId, type: 'level' };
        }
        if (parts[3] === 'config') {
             if (parts[4] === 'name') return { id: busId, type: 'name' };
             if (parts[4] === 'color') return { id: busId, type: 'color' };
        }
    }
    // ...
    return null;
}

// Generic Listener
osc.on('/*', message => {
    const info = parseX32Path(message.address);
    if (!info) return;
    
    let value = message.args[0];
    
    if (info.type === 'mute') {
        value = (message.args[0] === 0);
    } else if (['gate', 'dyn', 'eq', 'hpf', 'phantom'].includes(info.type)) {
        value = (message.args[0] === 1);
    }
    
    // Update State
    if (x32State[info.id]) {
        if (info.type === 'eqParam') {
            // Updating a specific band param
            // Ensure eqBands exists (if legacy state)
            if (!x32State[info.id].eqBands) x32State[info.id].eqBands = {};
            if (!x32State[info.id].eqBands[info.band]) x32State[info.id].eqBands[info.band] = {};
            
            x32State[info.id].eqBands[info.band][info.param] = value;
            io.emit('x32_update', { id: info.id, type: 'eqBand', band: info.band, param: info.param, value });
        } else if (info.type === 'mixSend') {
            // Mix Send Update
            if (!x32State[info.id].mixSends) x32State[info.id].mixSends = {};
            if (!x32State[info.id].mixSends[info.bus]) x32State[info.id].mixSends[info.bus] = {};
            
            // Value conversion
            let finalVal = value;
            if (info.param === 'on') finalVal = (message.args[0] === 1);
            
            x32State[info.id].mixSends[info.bus][info.param] = finalVal;
            io.emit('x32_update', { id: info.id, type: 'mixSend', bus: info.bus, param: info.param, value: finalVal });
            
        } else {
            // Standard update
            x32State[info.id][info.type] = value;
            io.emit('x32_update', { id: info.id, type: info.type, value });
        }
        saveState(); // PERSIST
    }
});




try {
  osc.open(); 
  console.log(`📡 OSC Interface ready (Target: ${X32_IP}:${X32_PORT}, Listening on 10023)`);
  
  // POLL FOR BUS NAMES & CONFIG (Startup)
  setTimeout(() => {
     console.log("📥 Fetching Bus Configs...");
     for(let i=1; i<=16; i++) {
        const id = String(i).padStart(2, '0');
        try {
            osc.send(new OSC.Message(`/bus/${id}/config/name`));
            osc.send(new OSC.Message(`/bus/${id}/config/color`));
            osc.send(new OSC.Message(`/bus/${id}/mix/fader`));
            osc.send(new OSC.Message(`/bus/${id}/mix/on`));
        } catch(e) { console.error("Bus Fetch Error", e); }
     }
  }, 2000); 
} catch (err) {
  console.error('❌ OSC Error:', err.message);
}

function getX32Address(channelId, type, extra) {
    if (channelId === 'master') {
        switch (type) {
            case 'mute': return '/main/st/mix/on';
            case 'level': return '/main/st/mix/fader';
            case 'eq': return '/main/st/eq/on';
            case 'dyn': return '/main/st/dyn/on';
            case 'eqParam': return `/main/st/eq/${extra.band}/${extra.param}`;
            default: return null;
        }
    }

    // BUS ADDRESSING
    if (String(channelId).startsWith('bus')) {
        const busNum = parseInt(channelId.replace('bus',''));
        const ch = String(busNum).padStart(2, '0');
        switch(type) {
            case 'mute': return `/bus/${ch}/mix/on`;
            case 'level': return `/bus/${ch}/mix/fader`;
            case 'name': return `/bus/${ch}/config/name`;
            case 'color': return `/bus/${ch}/config/color`;
        }
    }

    const ch = String(channelId).padStart(2, '0');
    const ha = String(parseInt(channelId) - 1).padStart(3, '0');
    
    switch (type) {
        case 'mute': return `/ch/${ch}/mix/on`;
        case 'level': return `/ch/${ch}/mix/fader`;
        case 'preamp': return `/headamp/${ha}/gain`; // Headamp Gain (0.0 to 1.0)
        case 'phantom': return `/headamp/${ha}/phantom`;
        case 'phase': return `/ch/${ch}/preamp/invert`;
        case 'hpf': return `/ch/${ch}/preamp/hpon`;
        case 'hpfFreq': return `/ch/${ch}/preamp/hpf`;
        case 'gate': return `/ch/${ch}/gate/on`;
        case 'gateThr': return `/ch/${ch}/gate/thr`;
        case 'gateAttack': return `/ch/${ch}/gate/attack`;
        case 'gateHold': return `/ch/${ch}/gate/hold`;
        case 'gateRelease': return `/ch/${ch}/gate/release`;
        case 'dyn': return `/ch/${ch}/dyn/on`;
        case 'dynThr': return `/ch/${ch}/dyn/thr`;
        case 'dynRatio': return `/ch/${ch}/dyn/ratio`;
        case 'dynGain': return `/ch/${ch}/dyn/mgain`;
        case 'dynAttack': return `/ch/${ch}/dyn/attack`;
        case 'dynHold': return `/ch/${ch}/dyn/hold`;
        case 'dynRelease': return `/ch/${ch}/dyn/release`;
        case 'eq': return `/ch/${ch}/eq/on`;
        case 'eqParam': return `/ch/${ch}/eq/${extra.band}/${extra.param}`;
        case 'hpf': return `/ch/${ch}/preamp/hpon`;
        case 'hpfFreq': return `/ch/${ch}/preamp/hpf`;
        case 'preampGain': return `/headamp/${ha}/gain`;
        case 'phantom': return `/headamp/${ha}/phantom`;
        case 'pan': return `/ch/${ch}/mix/pan`;
        // Link is special, usually handled via /config/chlink
        case 'link': {
             // Link works on pairs 1-2, 3-4 etc.
             // We need to find the pair ID.
             const idNum = parseInt(channelId);
             const isOdd = idNum % 2 !== 0;
             const pairStart = isOdd ? idNum : idNum - 1;
             const pairEnd = pairStart + 1;
             return `/config/chlink/${pairStart}-${pairEnd}`;
        }
        case 'mixSend': {
             const bus = String(parseInt(extra)).padStart(2, '0');
             const addr = `/ch/${ch}/mix/${bus}/level`;
             console.log(`🔧 Generated MixSend Address: ${addr} (ch=${ch}, bus=${bus}, extra=${extra})`);
             return addr;
        }
        default: return null;
    }
}



// ... MIDI & DMX ...

// ... Logic ...

// ... API ...

// ...

// NEW GENERIC ENDPOINT
app.post('/api/set-param', (req, res) => {
    const { channelId, type, value, band, param, labelColor } = req.body;
    // value: boolean for toggles, float for sliders
    
    if (type === 'delayDivision') {
        delayDivision = value;
        updateDelayTime();
        res.json({ success: true, division: delayDivision });
        return;
    }

    // internal params check
    const isInternal = ['color', 'labelColor', 'name'].includes(type);
    let addr = null;

    if (!isInternal) {
        if (type === 'eqParam') {
            addr = getX32Address(channelId, type, { band, param });
        } else if (type === 'mixSend') {
            const targetBus = req.body.bus; 
            addr = getX32Address(channelId, 'mixSend', targetBus);
        } else {
            addr = getX32Address(channelId, type);
        }
    }

    if (addr) {
        let oscVal = value;
        
        // Handle Mute Logic Inversion
        if (type === 'mute') {
            oscVal = value ? 0 : 1; 
        } 
        // Handle other booleans
        else if (typeof value === 'boolean') {
            oscVal = value ? 1 : 0;
        }

        console.log(`📡 Sending OSC: ${addr} = ${oscVal} (Type: ${typeof oscVal})`);
        
        try {
            // Force Float for levels to ensure X32 accepts it
            if (type === 'level' || type === 'mixSend' || type === 'dynThr' || type === 'gateThr') {
                 osc.send(new OSC.Message(addr,  Number(oscVal)));
            } else {
                 osc.send(new OSC.Message(addr, oscVal));
            }
        } catch (e) {
            console.error("❌ OSC Send Error:", e.message);
        }
    } else {
        console.warn(`⚠️ Could not resolve OSC address for Channel ${channelId} Type ${type}`);
    }
    
    // Update internal state
    if (!x32State[channelId]) x32State[channelId] = {};
    
    if (type === 'eqParam') {
            if (!x32State[channelId].eqBands) x32State[channelId].eqBands = {};
            if (!x32State[channelId].eqBands[band]) x32State[channelId].eqBands[band] = {};
            x32State[channelId].eqBands[band][param] = value;
            io.emit('x32_update', { id: channelId, type: 'eqBand', band, param, value });
    } else if (type === 'color') {
            x32State[channelId].color = value;
            if (labelColor) x32State[channelId].labelColor = labelColor;
            io.emit('x32_update', { 
                id: channelId, 
                type: 'color', 
                color: value, 
                labelColor: labelColor || x32State[channelId].labelColor 
            });
            io.emit('x32_update', { 
                id: channelId, 
                type: 'color', 
                color: value, 
                labelColor: labelColor || x32State[channelId].labelColor 
            });
    } else if (type === 'mixSend') {
        const busNum = req.body.bus;
        if (!busNum) {
            console.error("❌ MixSend request missing 'bus' parameter");
            return res.status(400).json({ error: "Missing bus parameter" });
        }

        if (!x32State[channelId].mixSends) x32State[channelId].mixSends = {};
        if (!x32State[channelId].mixSends[busNum]) x32State[channelId].mixSends[busNum] = {};
        
        x32State[channelId].mixSends[busNum].level = value;
        io.emit('x32_update', { id: channelId, type: 'mixSend', bus: busNum, param: 'level', value });
    } else {
        x32State[channelId][type] = value;
        io.emit('x32_update', { id: channelId, type, value });
    }
    saveState(); // PERSIST
    
    res.json({ success: true });
});

// --- MIDI CLOCK ---
let clockTicks = 0;
let lastBeatTime = Date.now();
let currentBpm = 0;
let delayDivision = '1/4'; // Default

const DIVISIONS = {
    '1/2': 2,
    '1/3': 4/3, // Whole note / 3
    '1/4': 1,
    '1/6': 2/3, // Whole note / 6
    '1/8': 0.5,
    '1/16': 0.25
};

function updateDelayTime() {
    if (currentBpm < 30 || currentBpm > 300) return;
    
    // 1 beat (1/4 note) in ms
    const beatMs = 60000 / currentBpm;
    const mult = DIVISIONS[delayDivision] || 1;
    const targetMs = beatMs * mult;
    
    // X32 Stereo Delay Time is usually 0.0 to 1.0 mapping to 1ms-3000ms
    // Check if simplified scale works
    const x32Val = Math.max(0, Math.min(1, targetMs / 3000));
    
    // Send to FX 1 Parameter 1 (Time)
    // NOTE: Might range 0-3000ms directly in some firmware/libs? 
    // osc-js usually sends floats.
    // Let's try sending float.
    osc.send(new OSC.Message('/fx/1/par/01', x32Val));
    
    // Notify frontend
    io.emit('delay_sync', { division: delayDivision, bpm: Math.round(currentBpm), ms: Math.round(targetMs) });
}

// Listen to all inputs for Clock
const midiInputs = easymidi.getInputs();
midiInputs.forEach(inputName => {
    const input = new easymidi.Input(inputName);
    input.on('clock', () => {
        clockTicks++;
        
        // 1/16th Note (Every 6 ticks)
        if (clockTicks % 6 === 0) {
             if (lighting.pulseSixteenth) lighting.pulseSixteenth();
        }

        if (clockTicks >= 24) {
            const now = Date.now();
            const duration = now - lastBeatTime;
            lastBeatTime = now;
            clockTicks = 0;
            
            if (duration > 0) {
                const bpm = 60000 / duration;
                lighting.pulse(); // Trigger beat flash
                // Simple smoothing (only update if significant change to avoid jitter)
                if (Math.abs(bpm - currentBpm) > 1.5) {
                    currentBpm = bpm;
                    io.emit('bpm_update', { bpm: Math.round(currentBpm) });
                    updateDelayTime();
                }
            }
        }
    });

    // LISTENER: MIDI NOTES
    input.on('noteon', (msg) => {
        console.log(`🎹 Note On: ${msg.note} Vel: ${msg.velocity} Ch: ${msg.channel}`);
        
        // Broadcast to Frontend
        io.emit('midi_msg', { 
            note: msg.note, 
            velocity: msg.velocity, 
            channel: msg.channel 
        });

        // Trigger Lighting Scenes
        const map = {
            36: { fn: 'blackout', evt: 'blackout' },
            37: { fn: 'sceneSetup', evt: 'setup' },
            44: { fn: 'sceneFocusLeft', evt: 'focusLeft' },
            45: { fn: 'sceneFocus', evt: 'focus' },
            46: { fn: 'sceneFocusRight', evt: 'focusRight' },
            52: { fn: 'sceneHell', evt: 'hell' },
            53: { fn: 'sceneSunshine', evt: 'sunshine' },
            54: { fn: 'sceneAqua', evt: 'aqua' },
            55: { fn: 'sceneRasta', evt: 'rasta' },
            56: { fn: 'scenePolice', evt: 'police' },
            60: { fn: 'sceneMadness', evt: 'madness' },
            63: { fn: 'scenePolice', evt: 'police' },
            127: { fn: 'blackout', evt: 'blackout' }
        };

        if (map[msg.note]) {
            const action = map[msg.note];
            if (lighting[action.fn]) lighting[action.fn]();
            io.emit('scene_change', action.evt);
            console.log(`🎹 Triggered Scene: ${action.evt} (Note ${msg.note})`);
        }
    });
    console.log(`🎹 Listening for Clock on: ${inputName}`);
});


// 2. MIDI Interface (Ableton)
let midiOutput = null;
let foundName = null;
try {
  const outputs = easymidi.getOutputs();
  console.log('🎹 Available MIDI Outputs:', outputs);
  
  // Attempt to auto-select a likely candidate
  const targetParams = ['IAC', 'Ableton', 'Bus 1'];
  foundName = outputs.find(name => targetParams.some(t => name.includes(t))) || outputs[0];

  if (foundName) {
      midiOutput = new easymidi.Output(foundName);
      console.log(`✅ MIDI Output connected to: "${foundName}"`);
  } else {
      console.log('⚠️ No MIDI devices found. MIDI features will be disabled.');
  }

} catch (err) {
  console.warn('⚠️ MIDI setup failed:', err.message);
}

// 3. DMX Interface & Lighting Engine
const LightingEngine = require('./lighting-engine');

const dmx = new DMX();
// Use 'null' driver for virtual dev. Change to real driver path for hardware.
const lighting = new LightingEngine(dmx, 'main', 'null');
console.log('--- SERVER RESTART DEBUG ---');
console.log('💡 DMX Lighting Engine Initialized');

// Broadcast DMX updates to Frontend Visualizer
let lastDmxUpdate = Date.now();
let dmxUpdateTimeout = null;
dmx.on('update', (universe, state) => {
    const timeSinceLast = Date.now() - lastDmxUpdate;

    const dispatch = () => {
        io.emit('dmx_update', { universe, state });
        lastDmxUpdate = Date.now();
        dmxUpdateTimeout = null;
    };

    // Throttle to ~20fps (50ms)
    if (timeSinceLast > 50) {
        if (dmxUpdateTimeout) clearTimeout(dmxUpdateTimeout);
        dispatch();
    } else {
        if (dmxUpdateTimeout) clearTimeout(dmxUpdateTimeout);
        dmxUpdateTimeout = setTimeout(dispatch, 50 - timeSinceLast);
    }
});

// 4. MIDI Input (Control Listener)
// Reuse the found MIDI device name from above if possible
if (midiOutput && foundName) {
    try {
        const midiInput = new easymidi.Input(foundName);
        console.log(`🎹 MIDI Input connected to: "${foundName}"`);
        
        midiInput.on('noteon', (msg) => {
            // Ignore Note Off (velocity 0) if you only want trigger on press
            // Some devices send NoteOn vel 0 for release.
            if (msg.velocity > 0) {
                console.log(`🎹 Trigger: ${msg.note}`);
                switch(msg.note) {
                    case 10: lighting.play('hell'); break;
                    case 11: lighting.play('sunshine'); break;
                    case 12: lighting.play('madness'); break;
                    case 13: lighting.play('aqua'); break;
                    case 14: lighting.play('rasta'); break;
                    case 15: lighting.play('focus'); break;
                    case 16: lighting.play('focusLeft'); break;
                    case 17: lighting.play('focusRight'); break;
                    case 18: lighting.play('police'); break;
                    case 36: lighting.blackout(); break;
                    case 0: lighting.blackout(); break;
                    
                    // SOLOS
                    case 70: console.log('🎹 MIDI 70: Drums'); toggleSolo(SOLO_GROUPS.drums, 'drums'); break;
                    case 71: console.log('🎹 MIDI 71: Keys'); toggleSolo(SOLO_GROUPS.keys, 'keys'); break;
                    case 72: console.log('🎹 MIDI 72: Gats'); toggleSolo(SOLO_GROUPS.gats, 'gats'); break;
                    case 73: console.log('🎹 MIDI 73: Bass'); toggleSolo(SOLO_GROUPS.bass, 'bass'); break;
                    case 74: 
                        console.log('🎹 MIDI 74: Samples. IDs:', SOLO_GROUPS.samples); 
                        toggleSolo(SOLO_GROUPS.samples, 'samples'); 
                        break;
                    case 75: console.log('🎹 MIDI 75: Vox'); toggleSolo(SOLO_GROUPS.vox, 'vox'); break;
                    case 76: console.log('🎹 MIDI 76: Horns'); toggleSolo(SOLO_GROUPS.horns, 'horns'); break;
                }
            }
        });
    } catch(e) {
        console.warn('⚠️ MIDI Input setup failed (Device busy?):', e.message);
    }
}


// 5. System Monitor (Broadcast every 2s)
setInterval(() => {
    const mem = process.memoryUsage();
    // Mac loadavg is [1min, 5min, 15min]. We take 1min.
    // Normalized by CPU count roughly gives "percentage" (1.0 = 100% of single core)
    const cpus = os.cpus().length;
    const load = os.loadavg()[0]; 
    const cpuPercent = Math.min(100, Math.round((load / cpus) * 100));
    
    // DEBUG LOG
    // if (Math.random() < 0.1) console.log('📊 Broadcasting System Stats:', { cpu: cpuPercent, mem: Math.round(mem.rss / 1024 / 1024) });

    io.emit('system_stats', {
        cpu: cpuPercent,
        mem: Math.round(mem.rss / 1024 / 1024), // MB
        uptime: Math.floor(process.uptime())
    });
}, 2000);

// --- Logic ---

function triggerPart(songId, partName) {
  console.log(`🚀 Triggering: Song ${songId}, Part ${partName}`);
  const song = config.songs.find(s => s.id == songId);
  if (!song) return;
  const part = song.parts.find(p => p.name == partName);
  if (!part) return;

  // 1. Send OSC Commands
  if (part.cues.x32) {
    Object.entries(part.cues.x32).forEach(([channelId, settings]) => {
        if (settings.mute !== undefined) {
            const addr = getX32Address(channelId, 'mute');
            // X32: 0 = Muted (Off), 1 = Unmuted (On)
            // Our config: mute: true (silence) -> send 0. mute: false (sound) -> send 1.
            // Wait, standard X32 logic: /mix/on 0 is Mute (LED ON often triggers me, but internally 0 is off).
            // Let's assume standard: 1 = ON (Audio passing), 0 = OFF (Audio muted).
            // User config: 'mute: false' -> Audio ON -> 1
            const val = settings.mute ? 0 : 1; 
            if(addr) osc.send(new OSC.Message(addr, val));
            
            // Update Internal State
            if(x32State[channelId]) x32State[channelId].mute = settings.mute;
        }
        if (settings.level !== undefined) {
             const addr = getX32Address(channelId, 'level');
             // X32 Fader is float 0.0 - 1.0
             if(addr) osc.send(new OSC.Message(addr, Number(settings.level)));
             
             // Update Internal State
             if(x32State[channelId]) x32State[channelId].level = settings.level;
        }
    });
    // Broadcast bulk update to UI for these changes
    io.emit('x32_bulk_update', x32State); 
  }

  // 2. Send MIDI Config
  // Logic: Send a specific Note to trigger a Scene in Ableton
  if (part.cues.midi && midiOutput) {
      if (part.cues.midi.note) {
          // Sending momentary note on/off to trigger
          midiOutput.send('noteon', { note: part.cues.midi.note, velocity: 127, channel: 0 });
          setTimeout(() => {
            midiOutput.send('noteoff', { note: part.cues.midi.note, velocity: 0, channel: 0 });
          }, 100);
          console.log(`🎹 Sent MIDI Note: ${part.cues.midi.note}`);
      }
  }

  // 3. Send DMX Config
  // Logic: Update all channels for a scene
  if (part.cues.dmx && universe) {
      if (part.cues.dmx.values) {
          universe.update(part.cues.dmx.values);
          console.log('💡 Updated DMX Universe');
      }
      // Or just a general "Scene" index mapped to DMX channels?
      // For now, assuming raw channel values map.
  }
  
  // Notify UI of success
  io.emit('active_part', { songId, partName });
}


// --- SCENE MANAGEMENT ---

const SCENES_DIR = path.join(__dirname, 'scenes');
// Ensure scenes dir exists
  // Actually, let's just check if fs/promises or fs is imported. 
  // Assuming fs is imported at top. If not, I'll assume standard node.
  if (!fs.existsSync(SCENES_DIR)){
      fs.mkdirSync(SCENES_DIR);
  }

// --- METER POLLING ---
// --- METER POLLING ---
// --- METER POLLING ---
function startMeterPolling() {
    if (meterInterval) clearInterval(meterInterval);
    if (!osc) return;

    // Remove old listeners to prevent duplicates if function called multiple times on same object?
    // Actually, creating a new OSC object clears listeners automatically. 
    // We only call this after connectOSC creates a NEW object.
    
    // Setup Listeners
    osc.on('/meters/6', message => { // Main L/R
        if (message.args.length > 0 && message.args[0] instanceof Uint8Array) {
             const blob = message.args[0];
             // FIX: Respect byteOffset to avoid reading garbage memory
             const floatView = new DataView(blob.buffer, blob.byteOffset, blob.byteLength);
             try {
                const l = floatView.getFloat32(0, true);
                const r = floatView.getFloat32(4, true);
                io.emit('meters_master', { l, r });
             } catch(e){}
        }
    });

    osc.on('/meters/1', message => { // Inputs 1-32
        if (message.args.length > 0 && message.args[0] instanceof Uint8Array) {
             const blob = message.args[0];
             // FIX: Respect byteOffset
             const floatView = new DataView(blob.buffer, blob.byteOffset, blob.byteLength);
             const levels = [];
             for(let i=0; i<32; i++) {
                 try {
                    levels.push(floatView.getFloat32(i*4, true));
                 } catch(e) { levels.push(0); }
             }
             io.emit('meters_inputs', levels);
        }
    });

    meterInterval = setInterval(() => {
        // Request Meter Data for Main L/R and Inputs
        try {
            osc.send(new OSC.Message('/meters', '/meters/6')); 
            osc.send(new OSC.Message('/meters', '/meters/1'));
        } catch (e) { /* ignore */ }
        
        // --- SIMULATION (ACTIVE) ---
        // Generate Main L/R from noise if 0
        // Generate RTA from noise
        // const rta = Array(31).fill(0).map((_,i) => Math.random() * 0.8 * (1 - i/31));
        const rta = Array(31).fill(0); // Silence for now
        io.emit('rta_data', rta);

        // Input Simulation DISABLED
        // io.emit('meters_inputs', Array(32).fill(0));

    }, 50);
}





// Start polling is called inside connectOSC now.

// --- CONFIG API ---
app.get('/api/config', (req, res) => {
    // Return static config
    res.json({ ...config, x32_ip: X32_IP });
});

app.get('/api/scenes', (req, res) => {
    fs.readdir(SCENES_DIR, (err, files) => {
        if (err) return res.status(500).json({ error: 'Failed to list scenes' });
        const scenes = files
            .filter(f => f.endsWith('.json'))
            .map(f => f.replace('.json', ''));
        res.json(scenes);
    });
});

app.post('/api/scenes', (req, res) => {
    const { name } = req.body;
    if (!name) return res.status(400).json({ error: 'Name required' });
    const safeName = name.replace(/[^a-z0-9-_]/gi, '_'); // Basic sanitization
    const filePath = path.join(SCENES_DIR, `${safeName}.json`);
    
    fs.writeFile(filePath, JSON.stringify(x32State, null, 2), (err) => {
        if (err) return res.status(500).json({ error: 'Failed to save scene' });
        console.log(`💾 Scene saved: ${safeName}`);
        res.json({ success: true, name: safeName });
    });
});

app.post('/api/scenes/:name/load', (req, res) => {
    const { name } = req.params;
    const safeName = name.replace(/[^a-z0-9-_]/gi, '_');
    const filePath = path.join(SCENES_DIR, `${safeName}.json`);
    
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) return res.status(404).json({ error: 'Scene not found' });
        
        try {
            console.log("Parsing scene data...");
            const loadedState = JSON.parse(data);
            
            // 1. Update Internal State
            // Clear existing keys first to remove stale state
            Object.keys(x32State).forEach(key => delete x32State[key]);
            Object.assign(x32State, loadedState);
            
            // 2. CRITICAL: SYNC PHYSICAL CONSOLE (Bit-Bang)
            console.log(`🔄 Syncing X32 to Scene: ${safeName}`);
            
            Object.keys(x32State).forEach(chId => {
                const ch = x32State[chId];
                
                // Mute
                if (ch.mute !== undefined) {
                    const addr = getX32Address(chId, 'mute');
                    if (addr) osc.send(new OSC.Message(addr, ch.mute ? 0 : 1));
                }
                
                // Fader Level
                if (ch.level !== undefined) {
                    const addr = getX32Address(chId, 'level');
                    if (addr) osc.send(new OSC.Message(addr, Number(ch.level)));
                }

                // Mix Bus Sends (Level & On)
                if (ch.mixSends) {
                    Object.entries(ch.mixSends).forEach(([busId, sendSettings]) => {
                        if (sendSettings.level !== undefined) {
                            const addr = getX32Address(chId, `mix/${busId}/level`);
                            if (addr) osc.send(new OSC.Message(addr, Number(sendSettings.level)));
                        }
                        if (sendSettings.on !== undefined) {
                            const addr = getX32Address(chId, `mix/${busId}/on`);
                            if (addr) osc.send(new OSC.Message(addr, sendSettings.on ? 1 : 0));
                        }
                    });
                }

                // Add other sync params as needed here
            });
            
            // 3. Broadcast to UI
            io.emit('x32_bulk_update', x32State);
            console.log(`✅ Scene Loaded: ${safeName}`);
            res.json({ success: true });
            
        } catch (e) {
            console.error("❌ LOAD FAILURE:", e);
            res.status(500).json({ error: 'Failed to parse scene file', details: e.message });
        }
    });
});

// --- API ---

app.get('/api/config', (req, res) => {
  res.json(config);
});

app.post('/api/trigger', (req, res) => {
  const { songId, partName } = req.body;
  triggerPart(songId, partName);
  res.json({ success: true });
});

app.post('/api/toggle-mute', (req, res) => {
    const { channelId, mute } = req.body; // Expect explicit state or just toggle? User plan said toggle logic in Frontend.
    // Let's make it robust: Frontend says "I want this specific state".
    
    // 1. Send OSC
    const addr = getX32Address(channelId, 'mute');
    if (addr) {
        // OSC: 0 = Muted, 1 = Unmuted.
        // If 'mute' is true (we want silence), send 0.
        const val = mute ? 0 : 1; 
        osc.send(new OSC.Message(addr, val));
    }
    
    // 2. Update Internal State immediately (Optimistic)
    if (x32State[channelId]) {
        x32State[channelId].mute = mute;
        // Broadcast update
        io.emit('x32_update', { id: channelId, type: 'mute', value: mute });
    }
    
    res.json({ success: true });
});

app.post('/api/rename-channel', (req, res) => {
    const { channelId, name } = req.body;
    if (!channelId || name === undefined) return res.status(400).json({ error: "Missing parameters" });

    // Initialize if missing
    if (!x32State[channelId]) x32State[channelId] = {};

    // Update state
    x32State[channelId].name = name;
    saveState();

    // Broadcast update
    io.emit('x32_update', { 
        id: channelId, 
        type: 'name', 
        name: name 
    });

    console.log(`📝 Renamed CH${channelId} to "${name}"`);
    res.json({ success: true, name });
});

app.post('/api/set-fader', (req, res) => {
    const { channelId, level } = req.body; 
    // level should be 0.0 - 1.0
    
    const addr = getX32Address(channelId, 'level');
    if (addr) {
        osc.send(new OSC.Message(addr, Number(level)));
    }
    
    // Update Internal State
    if (x32State[channelId]) {
        x32State[channelId].level = Number(level);
        io.emit('x32_update', { id: channelId, type: 'level', value: Number(level) });
    }
    
    res.json({ success: true });
});

app.get('/api/capture', (req, res) => {
    // Generate a cue object based on current state
    const cue = {};
    config.inputs.forEach(input => {
       if (x32State[input.id]) {
           cue[input.id] = { ...x32State[input.id] };
       }
    });
    console.log('📸 State Captured');
    res.json(cue);
});

// --- SOLO MODE LOGIC ---

// X32 Fader Math (Approximation of 7-bit / 1024 step log curve)
// 0.0 = -oo, 0.75 = 0dB, 1.0 = +10dB
function floatToDB(f) {
    if (f < 0.1) return -90; // effectively mute
    // Simple segmented linear approx for speed/robustness
    if (f >= 0.75) return (f - 0.75) * 40; // 0.75->0dB, 1.0->+10dB (slope 40)
    if (f >= 0.5) return (f - 0.5) * 40 - 10; // 0.5->-10dB, 0.75->0dB
    if (f >= 0.25) return (f - 0.25) * 80 - 30; // 0.25->-30dB, 0.5->-10dB
    return (f) * 120 - 60; // 0.0->-60 (clamped lower)
}

function dbToFloat(db) {
    if (db <= -60) return 0.0;
    if (db >= 10) return 1.0;
    
    if (db >= 0) return 0.75 + (db / 40);
    if (db >= -10) return 0.5 + ((db + 10) / 40);
    if (db >= -30) return 0.25 + ((db + 30) / 80);
    return (db + 60) / 120;
}



// Rate limit ramping to avoid flooding OSC
const RAMP_INTERVAL = 30; 

function rampFader(channelId, targetFloat, duration = 300) {
    const startFloat = x32State[channelId]?.level !== undefined ? x32State[channelId].level : 0;
    const addr = getX32Address(channelId, 'level');
    if (!addr) return;
    
    // If target is same as current, skip
    if (Math.abs(targetFloat - startFloat) < 0.001) return;

    const steps = duration / RAMP_INTERVAL;
    const stepSize = (targetFloat - startFloat) / steps;
    let current = startFloat;
    let step = 0;
    
    // Clear any existing interval for this channel if we tracked them, 
    // but for now relying on aggressive overwrite is okay-ish/hacky.
    
    const i = setInterval(() => {
        step++;
        current += stepSize;
        
        // Clamp
        if (targetFloat > startFloat && current > targetFloat) current = targetFloat;
        if (targetFloat < startFloat && current < targetFloat) current = targetFloat;
        
        osc.send(new OSC.Message(addr, current));
        
        // Update Internal State (Silent/Optimistic)
        if(x32State[channelId]) x32State[channelId].level = current;
        
        if (step >= steps) {
            clearInterval(i);
            // Final set to ensure precision
            osc.send(new OSC.Message(addr, Number(targetFloat)));
            if(x32State[channelId]) {
                x32State[channelId].level = Number(targetFloat);
                io.emit('x32_update', { id: channelId, type: 'level', value: targetFloat });
            }
        }
    }, RAMP_INTERVAL);
}

// SOLO_GROUPS defined at top of file


// --- SOLO LOGIC (CLEAN IMPLEMENTATION) ---

const soloContext = {
    activeGroup: null,   // The name of the currently soloed group (e.g., 'drums')
    activeIds: new Set(),// The IDs of the soloed channels
    snapshots: {}        // Backup of original fader levels: { "1": 0.75, "2": 0.5 ... }
};

function restoreSolo() {
    console.log('↩️ Restoring Levels from Snapshot...');
    if (!soloContext.snapshots) return;

    Object.entries(soloContext.snapshots).forEach(([id, level]) => {
        // Direct Apply (No Ramp for instant snap-back, or ramp for smoothness? User didn't specify, assume safe direct)
        // Actually, ramp feels nicer.
        rampFader(id, level, 200);
    });

    soloContext.activeGroup = null;
    soloContext.activeIds = new Set();
    soloContext.snapshots = {};
    
    io.emit('solo_update', { activeIds: [] });
}

function applySolo(targetIds, groupName) {
    console.log(`🎸 Applying Solo: Group="${groupName}" (+5dB Target, -2dB Others)`);
    
    const targetSet = new Set(targetIds.map(String));
    const allInputs = config.inputs; // [{id:1, ...}, ...]

    // 1. Snapshot EVERYTHING that is about to change
    // We are affecting ALL inputs (some up, some down), so we snapshot all.
    const newSnapshots = {};
    
    allInputs.forEach(input => {
        const id = String(input.id);
        if (x32State[id]) {
            newSnapshots[id] = x32State[id].level !== undefined ? x32State[id].level : 0;
        }
    });
    
    soloContext.snapshots = newSnapshots; // Save BEFORE modification
    soloContext.activeGroup = groupName;
    soloContext.activeIds = targetSet;

    // 2. Apply Changes
    allInputs.forEach(input => {
        const id = String(input.id);
        const currentLevel = x32State[id]?.level || 0;
        const currentDb = floatToDB(currentLevel); // Helper from earlier
        
        let targetLevel = currentLevel;

        if (targetSet.has(id)) {
            // TARGET: Boost +5dB
            const boostedDb = Math.min(10, currentDb + 5); // Cap at +10dB
            targetLevel = dbToFloat(boostedDb);
        } else {
            // OTHERS: Dim -2dB
            const dimmedDb = Math.max(-90, currentDb - 2); // Cap at bottom
            targetLevel = dbToFloat(dimmedDb);
        }

        // Apply
        if (Math.abs(targetLevel - currentLevel) > 0.001) {
            rampFader(id, targetLevel, 200); 
        }
    });

    io.emit('solo_update', { activeIds: Array.from(targetSet) });
}

function toggleSolo(targetIds, groupName) {
    // 1. If Same Group -> RESTORE (Toggle Off)
    if (soloContext.activeGroup === groupName) {
        restoreSolo();
        return { success: true, state: 'restored' };
    }

    // 2. If Different Active Group -> RESTORE FIRST (Switching)
    if (soloContext.activeGroup !== null) {
        restoreSolo();
        // Small delay to allow restore to start? 
        // Logic-wise, we just overwrote the X32 state with restore, but `rampFader` is async. 
        // This is tricky. 
        // "start again" implies we should treat the 'restored' levels as the baseline.
        // We can just immediately apply the new solo using the snapshots we Just restored (conceptually).
        // However, `rampFader` takes time.
        
        // SIMPLIFICATION:
        // We just reset the internal state to the snapshot values immediately for the calculation of the NEW target.
        // We don't need to wait for the physical faders to move back.
    }
    
    // 3. Apply New Solo
    applySolo(targetIds, groupName);
    return { success: true, state: 'activated' };
}

// Ensure rampFader (defined earlier) uses logging
// ...

app.post('/api/solo', (req, res) => {
    let { channelIds, groupName } = req.body;
    // Normalize
    if (!channelIds && req.body.targets) channelIds = req.body.targets;
    if (!channelIds) return res.status(400).json({error: 'Missing channelIds'});
    
    if (!Array.isArray(channelIds)) channelIds = [channelIds];
    
    const result = toggleSolo(channelIds, groupName);
    res.json(result);
});

// Remove old activateSolo if it exists or just let this block replace the region


app.get('/api/solo-status', (req, res) => {
    // Send back array
    const list = soloContext.activeIds ? Array.from(soloContext.activeIds) : [];
    res.json({ activeIds: list });
});

// ... imports

// ... existing code ...

// --- SHAREPOINT PROXY ---
// --- SHAREPOINT PROXY ---
// Load SharePoint config
// Note: musiciansData is defined below, but accessible in callbacks due to module scope.

// Routes:
app.get('/api/sharepoint/config', (req, res) => {
    res.json(musiciansData.sharepoint || {});
});

app.post('/api/sharepoint/config', (req, res) => {
    const conf = req.body;
    musiciansData.sharepoint = conf;
    // Save to musicians.json
    fs.writeFile('./musicians.json', JSON.stringify(musiciansData, null, 2), (err) => {
        if (err) return res.status(500).json({ error: "Save failed" });
        res.json({ success: true });
    });
});

app.get('/api/sharepoint/files', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if(!token) return res.status(401).json({error: "No token"});
        
        const folderId = req.query.folderId || musiciansData.sharepoint?.folderId;
        const driveId = musiciansData.sharepoint?.driveId;
        const siteId = musiciansData.sharepoint?.siteId;

        console.log(`📂 SharePoint List: folder=${folderId} drive=${driveId} site=${siteId}`);

        const client = authManager.getGraphClient(token);
        
        let query;
        if (folderId) {
             if (driveId) {
                 query = client.api(`/drives/${driveId}/items/${folderId}/children`);
                 console.log(`   -> Query: /drives/${driveId}/items/${folderId}/children`);
             } else {
                 query = client.api(`/me/drive/items/${folderId}/children`);
                 console.log(`   -> Query: /me/drive/items/${folderId}/children`);
             }
        } else if (siteId && driveId) {
             query = client.api(`/sites/${siteId}/drives/${driveId}/root/children`);
        } else if (driveId) {
             query = client.api(`/drives/${driveId}/root/children`);
        } else {
             // Default to Me
             query = client.api('/me/drive/root/children');
        }

        const response = await query.select('id,name,folder,size,webUrl').get();
        if (response.value && response.value.length > 0) {
            console.log("Graph Item 0:", JSON.stringify(response.value[0], null, 2));
        }
        res.json(response.value.map(val => ({
            id: val.id,
            name: val.name,
            folder: !!val.folder,
            size: val.size,
            webUrl: val.webUrl
        })));
    } catch (e) {
        console.error("SharePoint List Error", e);
        fs.appendFileSync('crash.log', `[${new Date().toISOString()}] SHAREPOINT ERROR: ${e.message}\n`);
        res.status(500).json({ error: e.message });
    }
});

app.get('/api/sharepoint/download/:id', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if(!token) return res.status(401).json({error: "No token"});
        
        const fileId = req.params.id;
        const driveId = musiciansData.sharepoint?.driveId;
        
        const client = authManager.getGraphClient(token);
        // We need to get the download URL or stream
        // Graph API /content endpoint redirects to a download URL
        
        // Use a raw fetch to handle stream better or use client.api(...).responseType('stream')
        const endpoint = driveId 
            ? `/drives/${driveId}/items/${fileId}/content`
            : `/me/drive/items/${fileId}/content`;

        // We can redirect the client to the @microsoft.graph.downloadUrl 
        // OR proxy the stream. 
        // client-side expects 'blob' from this endpoint.
        // Let's try proxying.
        
        const stream = await client.api(endpoint).responseType('stream').get();
        stream.pipe(res);
        
    } catch (e) {
        console.error("SharePoint Download Error", e);
        res.status(500).json({ error: e.message });
    }
});

app.post('/api/sharepoint/folder', async (req, res) => {
    try {
        const token = req.headers.authorization?.split(' ')[1];
        if(!token) return res.status(401).json({error: "No token"});
        
        const { parentId, name } = req.body;
        const driveId = musiciansData.sharepoint?.driveId;
        
        if (!name) return res.status(400).json({error: "Missing name"});

        const client = authManager.getGraphClient(token);
        
        const endpoint = driveId 
            ? `/drives/${driveId}/items/${parentId || 'root'}/children`
            : `/me/drive/items/${parentId || 'root'}/children`;

        const newFolder = {
            name: name,
            folder: {},
            "@microsoft.graph.conflictBehavior": "rename"
        };

        const response = await client.api(endpoint).post(newFolder);
        res.status(201).json(response);

    } catch (e) {
        console.error("Create Folder Error", e);
        fs.appendFileSync('crash.log', `[${new Date().toISOString()}] CREATE FOLDER ERROR: ${e.message}\n`);
        res.status(500).json({ error: e.message });
    }
});

// --- MUSICIANS API ---
// musiciansData is defined in Globals

// --- SETLIST API ---
app.get('/api/setlist/data', (req, res) => {
    res.json(setlistManager.getAll());
});

app.post('/api/setlist/update', (req, res) => {
    const { id, updates } = req.body;
    const result = setlistManager.updateSetlist(id, updates);
    res.json(result);
});

// ... (Other Setlist routes can be added later as needed)

app.get('/api/musicians', (req, res) => {
    res.json(musiciansData.musicians);
});

app.post('/api/musicians', (req, res) => {
    const list = req.body;
    if (!Array.isArray(list)) return res.status(400).json({ error: "Expected array" });
    
    musiciansData.musicians = list;
    
    fs.writeFile('./musicians.json', JSON.stringify(musiciansData, null, 2), (err) => {
        if (err) return res.status(500).json({ error: "Save failed" });
        res.json({ success: true });
    });
});

io.on('connection', (socket) => {
  console.log('⚡ Client Connected:', socket.id);
  socket.emit('x32_bulk_update', x32State);

  socket.on('dmx_trigger', (sceneName) => {
        console.log('💡 Manual Trigger:', sceneName);
        try {
            if(lighting) lighting.play(sceneName);
        } catch (e) {
            console.error('❌ DMX Trigger Error:', e);
        }
  });
});

server.listen(PORT, () => {
  const protocol = server instanceof https.Server ? 'https' : 'http';
  console.log(`🌟 Controller Server running at ${protocol}://localhost:${PORT}`);
});
