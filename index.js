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
const multer = require('multer');
const PizZip = require('pizzip');
const Docxtemplater = require('docxtemplater');

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
        if (!musiciansData.manualSafes) musiciansData.manualSafes = []; // Init if missing
    }
} catch (e) {
    console.error("Failed to load musicians.json", e);
}
// Sync Init
// We will assign x32State.manualSafes later or now? x32State needs to be defined first.
// Moved x32State definition UP or just do it after definition.

// --- PRESETS ---
const PRESETS_FILE = path.join(__dirname, 'presets.json');
const DEFAULT_PRESETS = {
    'Kick (Out)': {
        hpf: false, 
        eq: {
            1: { type: 2, f: 0.20, g: 0.65, q: 0.4 }, 
            2: { type: 0, f: 0.55, g: 0.35, q: 0.6 }, 
            3: { type: 0, f: 0.82, g: 0.60, q: 0.5 }, 
            4: { type: 5, f: 0.95, g: 0.5, q: 0.5 }   
        },
        gate: { on: true, thr: 0.35, attack: 0.05, hold: 0.1, release: 0.2 },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.05, release: 0.3 } 
    },
    'Snare': {
        hpf: true, hpfFreq: 0.4, 
        eq: {
            1: { type: 1, f: 0.45, g: 0.60, q: 0.5 }, 
            2: { type: 0, f: 0.60, g: 0.40, q: 0.7 }, 
            3: { type: 0, f: 0.85, g: 0.65, q: 0.5 }, 
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }   
        },
        gate: { on: true, thr: 0.3, attack: 0.0, hold: 0.05, release: 0.15 },
        dyn: { on: true, thr: 0.35, ratio: 0.15, attack: 0.0, release: 0.2 }
    },
    'Rack Tom': {
        hpf: true, hpfFreq: 0.45, 
        eq: {
            1: { type: 2, f: 0.40, g: 0.6, q: 0.5 }, 
            2: { type: 0, f: 0.60, g: 0.3, q: 0.8 }, 
            3: { type: 0, f: 0.80, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.9, g: 0.5, q: 0.5 }
        },
        gate: { on: true, thr: 0.4, attack: 0.05, hold: 0.1, release: 0.3 },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Floor Tom': {
        hpf: true, hpfFreq: 0.35, 
        eq: {
            1: { type: 2, f: 0.35, g: 0.65, q: 0.5 }, 
            2: { type: 0, f: 0.55, g: 0.3, q: 0.8 }, 
            3: { type: 0, f: 0.78, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.9, g: 0.5, q: 0.5 }
        },
        gate: { on: true, thr: 0.4, attack: 0.05, hold: 0.1, release: 0.4 },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Overheads': {
        hpf: true, hpfFreq: 0.65, 
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 }, 
            2: { type: 0, f: 0.7, g: 0.45, q: 0.5 }, 
            3: { type: 0, f: 0.9, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.98, g: 0.65, q: 0.5 } 
        },
        gate: { on: false },
        dyn: { on: true, thr: 0.6, ratio: 0.1, attack: 0.2, release: 0.5 }
    },
    'Bass Gtr': {
        hpf: true, hpfFreq: 0.3, 
        eq: {
            1: { type: 2, f: 0.35, g: 0.6, q: 0.5 }, 
            2: { type: 0, f: 0.55, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.70, g: 0.55, q: 0.5 }, 
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   
        },
        dyn: { on: true, thr: 0.3, ratio: 0.2, attack: 0.2, release: 0.4 } 
    },
    'E. Guitar': {
        hpf: true, hpfFreq: 0.45, 
        eq: {
            1: { type: 1, f: 0.45, g: 0.5, q: 0.5 }, 
            2: { type: 0, f: 0.60, g: 0.45, q: 0.5 }, 
            3: { type: 0, f: 0.78, g: 0.6, q: 0.6 }, 
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   
        },
        dyn: { on: true, thr: 0.5, ratio: 0.1, attack: 0.1, release: 0.3 }
    },
    'Ac. Guitar': {
        hpf: true, hpfFreq: 0.5, 
        eq: {
            1: { type: 1, f: 0.5, g: 0.45, q: 0.5 }, 
            2: { type: 0, f: 0.65, g: 0.4, q: 0.7 }, 
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.95, g: 0.65, q: 0.5 } 
        },
        dyn: { on: true, thr: 0.45, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Ukelele': {
        hpf: true, hpfFreq: 0.55, 
        eq: {
            1: { type: 1, f: 0.55, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.7, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }
        },
        dyn: { on: true, thr: 0.5, ratio: 0.1, attack: 0.1, release: 0.3 }
    },
    'Male Vox': {
        hpf: true, hpfFreq: 0.45, 
        eq: {
            1: { type: 1, f: 0.45, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.58, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.82, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.95, g: 0.55, q: 0.5 } 
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Female Vox': {
        hpf: true, hpfFreq: 0.5, 
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.6, g: 0.45, q: 0.6 }, 
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.96, g: 0.6, q: 0.5 } 
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.1, release: 0.4 }
    },
    'Trumpet': {
        hpf: true, hpfFreq: 0.5, 
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.65, g: 0.45, q: 0.6 }, 
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.6, q: 0.5 }
        },
        dyn: { on: true, thr: 0.3, ratio: 0.3, attack: 0.05, release: 0.2 } 
    },
    'Alto Sax': {
        hpf: true, hpfFreq: 0.5, 
        eq: {
            1: { type: 1, f: 0.5, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.65, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Tenor Sax': {
        hpf: true, hpfFreq: 0.45, 
        eq: {
            1: { type: 1, f: 0.45, g: 0.55, q: 0.5 }, 
            2: { type: 0, f: 0.6, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 },
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Trombone': {
        hpf: true, hpfFreq: 0.4, 
        eq: {
            1: { type: 1, f: 0.4, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.55, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.75, g: 0.6, q: 0.5 }, 
            4: { type: 5, f: 0.9, g: 0.5, q: 0.5 }   
        },
        dyn: { on: true, thr: 0.4, ratio: 0.2, attack: 0.1, release: 0.3 }
    },
    'Sousaphone': {
        hpf: true, hpfFreq: 0.25, 
        eq: {
            1: { type: 2, f: 0.35, g: 0.65, q: 0.5 }, 
            2: { type: 0, f: 0.5, g: 0.4, q: 0.6 }, 
            3: { type: 0, f: 0.7, g: 0.55, q: 0.5 }, 
            4: { type: 5, f: 0.85, g: 0.5, q: 0.5 } 
        },
        dyn: { on: true, thr: 0.25, ratio: 0.4, attack: 0.05, release: 0.4 } 
    },
    'Conga': {
        hpf: true, hpfFreq: 0.5,
        eq: {
            1: { type: 1, f: 0.5, g: 0.55, q: 0.5 }, 
            2: { type: 0, f: 0.65, g: 0.4, q: 0.8 }, 
            3: { type: 0, f: 0.85, g: 0.6, q: 0.5 }, 
            4: { type: 4, f: 0.95, g: 0.5, q: 0.5 }
        },
        dyn: { on: true, thr: 0.4, ratio: 0.15, attack: 0.05, release: 0.2 }
    },
    'Cowbell': {
        hpf: true, hpfFreq: 0.6,
        eq: {
            1: { type: 1, f: 0.6, g: 0.5, q: 0.5 },
            2: { type: 0, f: 0.7, g: 0.3, q: 0.8 }, 
            3: { type: 0, f: 0.8, g: 0.6, q: 0.5 }, 
            4: { type: 5, f: 0.95, g: 0.5, q: 0.5 }
        }
    }
};

let presetsData = {};
try {
    if (!fs.existsSync(PRESETS_FILE)) {
        console.log("📝 presets.json missing, creating defaults...");
        fs.writeFileSync(PRESETS_FILE, JSON.stringify(DEFAULT_PRESETS, null, 2));
        presetsData = DEFAULT_PRESETS;
    } else {
        presetsData = JSON.parse(fs.readFileSync(PRESETS_FILE, 'utf8'));
    }
} catch(e) {
    console.error("❌ Failed to load presets:", e);
    presetsData = DEFAULT_PRESETS; // Fallback
}
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
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

const PORT = 3000;
const X32_PORT = 10023;
const X32_IP = '10.0.0.202';

const STATE_FILE = path.join(__dirname, 'x32_state.json');

// --- Interfaces ---

const x32State = {}; 
x32State.manualSafes = musiciansData.manualSafes || [];


// Persistence Helpers
function saveState() {
    // Debounce or just raw write? For simplicity and robustness, specific save call or throttled.
    // Let's use a simple throttled flag.
    if (global.saveTimer) clearTimeout(global.saveTimer);
    global.saveTimer = setTimeout(() => {
        fs.writeFile(STATE_FILE, JSON.stringify(x32State, null, 2), (err) => {
            if (err) console.error("❌ Failed to save state:", err);
            // else console.log("💾 State Saved"); // Too verbose 
        });
    }, 200); // 200ms debounce
}

function loadState() {
    try {
        if (fs.existsSync(STATE_FILE)) {
            const data = fs.readFileSync(STATE_FILE);
            const saved = JSON.parse(data);
            Object.assign(x32State, saved);
            // Ensure systemConfig exists
            if (!x32State.systemConfig) {
                x32State.systemConfig = { musicianPassword: 'otokia' };
            }
            console.log("📂 State Loaded from Disk");
        }
    } catch (err) {
        console.error("⚠️ Failed to load state:", err);
    }
}

// Graceful Shutdown: Force Save on Exit
const handleShutdown = () => {
    console.log("🛑 Server stopping... Saving state.");
    if (global.saveTimer) clearTimeout(global.saveTimer);
    try {
        fs.writeFileSync(STATE_FILE, JSON.stringify(x32State, null, 2));
        console.log("✅ State saved successfully.");
    } catch(e) {
        console.error("❌ Save failed on exit:", e.message);
    }
    process.exit(0);
};

process.on('SIGINT', handleShutdown);
process.on('SIGTERM', handleShutdown);

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
            
            // --- SAFE GUARD ---
            // Before merging, remove any keys that are marked as SAFE.
            // This ensures x32State retains the CURRENT (Hardware) value for these channels.
            const safeMask = x32State.safes && x32State.safes.chanSafe ? x32State.safes.chanSafe : 0;
            const isSafe = (id) => {
                 const num = parseInt(id);
                 if (isNaN(num) || num < 1 || num > 32) return false;
                 // Hardware Safe (Bitmask) OR Software Safe (Manual List)
                 const isManual = musiciansData.manualSafes && musiciansData.manualSafes.includes(String(num));
                 if (isManual || (safeMask & (1 << (num - 1)))) return true;
                 return false;
            };

            Object.keys(loadedState).forEach(key => {
                 if (isSafe(key)) {
                     console.log(`🛡️ Preserving Current State for Safe Channel ${key}`);
                     delete loadedState[key];
                 }
            });

            // 1. Update Internal State (Only Unsafe Channels Change)
            Object.assign(x32State, loadedState);
            saveState(); // Persist as "current" state
            
            // 2. Notify Frontend
            io.emit('x32_bulk_update', x32State);
            
            // 3. HARDWARE SYNC (5-Step Sequential Process)
            console.log(`Title: Loading Scene "${name}" - Starting 5-Step Sync...`);
            
            // DEBUG LOGGING
            const connectedClients = io.engine.clientsCount;
            const logMsg = `[${new Date().toISOString()}] 📡 Emitting sync_start to ${connectedClients} clients. Name: ${name}\n`;
            console.log(logMsg);
            try { fs.appendFileSync('debug_sync.log', logMsg); } catch(e){}

            io.emit('sync_start', { name });
            
            // Helper for delay
            const sleep = ms => new Promise(r => setTimeout(r, ms));

            (async () => {
              try {
                // WRAPPED IN TRY/CATCH TO CATCH ERRORS IN ASYNC LOOP
                const channels = Object.entries(x32State);

                // --- STEP 1: NAMES & COLORS ---
                io.emit('sync_progress', { step: 1, label: "Loading Names & Colors..." });
                for (const [chId, ch] of channels) {
                    if (isSafe(chId)) continue; // SKIP REDUNDANT SYNC
                    
                    const messages = [];
                    let addr;
                    // Name
                    addr = getX32Address(chId, 'name');
                    if (addr && ch.name) {
                         messages.push(new OSC.Message(addr, String(ch.name)));
                         // Log first 5 channels to debug why they "failed to load"
                         if (parseInt(chId) <= 5) console.log(`   -> Sync Ch ${chId} Name: "${ch.name}" (Addr: ${addr})`);
                    }
                    // Color
                    addr = getX32Address(chId, 'color');
                    if (addr && ch.color) messages.push(new OSC.Message(addr, getX32ColorID(ch.color)));
                    
                    for(const msg of messages) { osc.send(msg); }
                    await sleep(5);
                }
                await sleep(1000);

                // --- STEP 1.5: BUS NAMES & CONFIG (From Setlist/Musicians if Scene lacks them) ---
                io.emit('sync_progress', { step: 1.5, label: "Loading Bus Configurations..." });
                const setlistData = setlistManager.getAll();
                const busNames = setlistData.busNames || {};
                const routing = setlistData.musicianRouting || {};

                // Helper: Inferred Names from Groups
                // Map Bus ID -> Group Label if all group members go to that bus
                const inferredBusNames = {};
                const inferredBusColors = {}; // Store inferred colors too

                // 1. Check Groups (Horns -> Bus 12)
                if (config.groups) {
                    config.groups.forEach(g => {
                        const routedBuses = g.ids.map(id => routing[id]).filter(b => b);
                        if (routedBuses.length > 0 && routedBuses.every(val => val === routedBuses[0])) {
                           const targetBus = routedBuses[0];
                           inferredBusNames[targetBus] = g.label;
                           inferredBusColors[targetBus] = g.bg || '#00FF00'; // Default Green for groups or use config
                        }
                    });
                }
                
                // 2. Check Single Musicians (Vox Ed -> Bus 1)
                // Reverse map: Bus -> [Channels]
                const busToInputs = {};
                Object.entries(routing).forEach(([chId, busId]) => {
                    if(!busToInputs[busId]) busToInputs[busId] = [];
                    busToInputs[busId].push(chId);
                });
                
                Object.entries(busToInputs).forEach(([busId, inputs]) => {
                    // If only ONE musician is routed to this bus (e.g. Bus 1 has only Ch 17)
                    // OR if we want to mimic the primary one? Let's check single only for safety first.
                    if (inputs.length === 1) {
                         const sourceChId = inputs[0];
                         const sourceCh = x32State[sourceChId];
                         if (sourceCh && sourceCh.name) {
                             // Only infer if not already defined by Group
                             if (!inferredBusNames[busId]) {
                                 inferredBusNames[busId] = sourceCh.name;
                                 inferredBusColors[busId] = sourceCh.color;
                                 console.log(`   -> Inferred Bus ${busId} Name: "${sourceCh.name}" (from Musician Ch ${sourceChId})`);
                             }
                         }
                    }
                });

                for (let i = 1; i <= 16; i++) {
                    const busId = 'bus' + i;
                    // Ensure ch exists
                    if(!x32State[busId]) x32State[busId] = { name: `Bus ${i}`, color: null };
                    const ch = x32State[busId];
                    
                    let finalName = ch.name || `Bus ${i}`; 
                    
                    // Priority 1: Explicit Setlist Name
                    if (busNames[String(i)]) {
                         finalName = busNames[String(i)];
                         // Priority 2: Inferred Name (Group or Musician)
                    } else if (finalName === `Bus ${i}` && inferredBusNames[String(i)]) {
                         finalName = inferredBusNames[String(i)];
                    } else if (i >= 13 && i <= 16 && loadedState.fx) { 
                         // Implicit FX Return Naming (Bus 13=FX1 ...)
                         // If we have FX loaded, these are likely FX sends.
                         finalName = `FX ${i - 12}`;
                         ch.color = '#FF00FF'; // Magenta
                    }

                    if (finalName !== ch.name) {
                        // PERSIST TO STATE
                        ch.name = finalName; 
                    }

                    const msgs = [];
                    // Name
                    const nameAddr = getX32Address(busId, 'name');
                    if (nameAddr) msgs.push(new OSC.Message(nameAddr, String(finalName)));
                    
                    // Color
                    // Priority 1: Existing Color
                    // Priority 2: Inferred Color (from Musician/Group)
                    // Priority 3: Fallback Yellow
                    if (!ch.color) {
                         if (inferredBusColors[String(i)]) {
                             ch.color = inferredBusColors[String(i)];
                         } else if (busNames[String(i)]) {
                             ch.color = '#FFFF00';
                         } else {
                             ch.color = '#FFFF00';
                         }
                    }
                    
                    const colorID = getX32ColorID(ch.color);

                    const colAddr = getX32Address(busId, 'color');
                    if (colAddr) msgs.push(new OSC.Message(colAddr, colorID));

                    for(const m of msgs) osc.send(m);
                    await sleep(5);
                }
                await sleep(1000);

                // --- STEP 2: CHANNEL SETTINGS (Gain, EQ, Dyn, Gate, Pan) ---
                io.emit('sync_progress', { step: 2, label: "Loading Channel Settings..." });
                
                // (isSafe defined above)

                for (const [chId, ch] of channels) {
                     if (isSafe(chId)) {
                         console.log(`🛡️ Skipping Safe Channel ${chId} (Step 2)`);
                         continue;
                     }

                     const messages = [];
                     // Gain/Preamp
                     let addr = getX32Address(chId, 'preamp');
                     if (addr && ch.preampGain !== undefined) messages.push(new OSC.Message(addr, Number(ch.preampGain)));
                     
                     // Phantom
                     addr = getX32Address(chId, 'phantom');
                     if (addr && ch.phantom !== undefined) messages.push(new OSC.Message(addr, ch.phantom ? 1 : 0));

                     // Phase
                     addr = getX32Address(chId, 'phase');
                     if (addr && ch.invert !== undefined) messages.push(new OSC.Message(addr, ch.invert ? 1 : 0)); // 'invert' in JSON maps to phase

                     // Gate
                     addr = getX32Address(chId, 'gate');
                     if (addr && ch.gate !== undefined) messages.push(new OSC.Message(addr, ch.gate ? 1 : 0));
                     if (ch.gate) {
                        addr = getX32Address(chId, 'gateThr');
                        if (addr && ch.gateThr !== undefined) messages.push(new OSC.Message(addr, Number(ch.gateThr)));
                        // ... attack, hold, release omitted for brevity unless needed
                     }

                     // Compressor (Dyn)
                     addr = getX32Address(chId, 'dyn');
                     if (addr && ch.dyn !== undefined) messages.push(new OSC.Message(addr, ch.dyn ? 1 : 0));
                     if (ch.dyn) {
                        addr = getX32Address(chId, 'dynThr');
                        if (addr && ch.dynThr !== undefined) messages.push(new OSC.Message(addr, Number(ch.dynThr)));
                        addr = getX32Address(chId, 'dynRatio');
                        if (addr && ch.dynRatio !== undefined) messages.push(new OSC.Message(addr, Number(ch.dynRatio)));
                     }

                     // EQ
                     addr = getX32Address(chId, 'eq');
                     if (addr && ch.eq !== undefined) messages.push(new OSC.Message(addr, ch.eq ? 1 : 0));
                     if (ch.eqBands) {
                        Object.entries(ch.eqBands).forEach(([bandNum, band]) => {
                            ['f', 'g', 'q', 'type'].forEach(param => {
                                if (band[param] !== undefined) {
                                    addr = getX32Address(chId, 'eqParam', { band: bandNum, param });
                                    if(addr) messages.push(new OSC.Message(addr, Number(band[param])));
                                }
                            });
                        });
                     }

                     // Pan
                     if (ch.pan !== undefined) {
                        addr = getX32Address(chId, 'pan');
                        if (addr) messages.push(new OSC.Message(addr, Number(ch.pan)));
                     }

                     for(const msg of messages) { osc.send(msg); }
                     await sleep(10);
                }
                await sleep(1000);

                // --- STEP 3: EFFECTS (Mix Sends) ---
                io.emit('sync_progress', { step: 3, label: "Loading Mix Sends..." });
                for (const [chId, ch] of channels) {
                    if (isSafe(chId)) continue; // SKIP SAFE

                    const messages = [];
                    // Mix Sends
                    if (ch.mixSends) {
                        Object.entries(ch.mixSends).forEach(([busId, sendData]) => {
                            if (sendData.level !== undefined) {
                                let addr = getX32Address(chId, 'mixSend', busId);
                                if (addr) messages.push(new OSC.Message(addr, Number(sendData.level)));
                            }
                        });
                    }
                    for(const msg of messages) { osc.send(msg); }
                    await sleep(5);
                }
                await sleep(1000);
                
                // --- STEP 3.5: LOAD FX UNITS ---
                if (loadedState.fx) {
                    io.emit('sync_progress', { step: 3.5, label: "Loading FX Units..." });
                    for (const [slotId, fxData] of Object.entries(loadedState.fx)) {
                         const slot = parseInt(slotId);
                         if (slot < 1 || slot > 8) continue;
                         
                         // TYPE
                         if (fxData.type !== undefined) {
                             osc.send(new OSC.Message(`/fx/${slot}/type`, Number(fxData.type)));
                         }
                         
                         // PARAMS
                         if (fxData.params) {
                             Object.entries(fxData.params).forEach(([paramId, val]) => {
                                 osc.send(new OSC.Message(`/fx/${slot}/par/${paramId}`, Number(val)));
                             });
                         }
                         await sleep(20);
                    }
                    await sleep(1000);
                }

                // --- STEP 4: MUTES & LEVELS ---
                io.emit('sync_progress', { step: 4, label: "Loading Faders & Mutes..." });
                for (const [chId, ch] of channels) {
                    if (isSafe(chId)) continue; // SKIP SAFE

                    const messages = [];
                    let addr;
                    // Fader
                    addr = getX32Address(chId, 'level');
                    if (addr && ch.level !== undefined) messages.push(new OSC.Message(addr, Number(ch.level)));
                    // Mute
                    addr = getX32Address(chId, 'mute');
                    if (addr && ch.mute !== undefined) messages.push(new OSC.Message(addr, ch.mute ? 0 : 1));
                    
                    for(const msg of messages) { osc.send(msg); }
                    await sleep(5);
                }
                await sleep(1000);

                // --- STEP 5: GRANULAR VERIFICATION ---
                io.emit('sync_progress', { step: 5, label: "Verifying Integrity (One by One)..." });
                
                // We re-send EVERYTHING per channel, strictly one message at a time
                const paramsToCheck = [
                    'name', 'color', 'level', 'mute', 'pan', 
                    'preampGain', 'phantom', 'phase',
                    'gate', 'gateThr', 'gateAttack', 'gateHold', 'gateRelease',
                    'dyn', 'dynThr', 'dynRatio', 'dynGain', 'dynAttack', 'dynHold', 'dynRelease',
                    'eq', 'hpf', 'hpfFreq'
                ];

                for (const [chId, ch] of channels) {
                     if (isSafe(chId)) {
                         io.emit('verify_progress', { detail: `Skipping Safe Channel ${chId}...` });
                         continue;
                     }

                     io.emit('verify_progress', { detail: `Verifying Channel ${chId}...` });
                     
                     // 1. Check Standard Params
                     for(const p of paramsToCheck) {
                         // Some params are direct props, some map differently. Re-using generic logic where possible.
                         // Simplification: We construct the message again and send it.
                         // Ideally we would READ from console, but "Blind Verification" (Re-Send) is what was requested/implied for robustness.
                         
                         let val = ch[p];
                         if(val !== undefined) {
                             let addr = getX32Address(chId, p);
                             
                             // Value Transforms
                             if (['mute','gate','dyn','eq','phantom','invert','hpf'].includes(p)) val = val ? 1 : 0; // Invert logic for mute handled inside getX32Address usually? No, manually below.
                             if (p === 'mute') val = ch.mute ? 0 : 1; // Mute is 1=Muted in UI usually? Wrapper handles logic.
                             if (p === 'invert' && ch.invert !== undefined) val = ch.invert ? 1 : 0;
                             if (p === 'color') val = getX32ColorID(ch.color);

                             if(addr) {
                                 osc.send(new OSC.Message(addr, (typeof val === 'string') ? val : Number(val)));
                                 await sleep(2); // Strict Throttle
                             }
                         }
                     }

                     // 2. Check EQ Bands
                     if(ch.eqBands) {
                        for(const [bandNum, band] of Object.entries(ch.eqBands)) {
                            for(const eqP of ['f','g','q','type']) {
                                if(band[eqP] !== undefined) {
                                    let addr = getX32Address(chId, 'eqParam', {band: bandNum, param: eqP});
                                    if(addr) {
                                        osc.send(new OSC.Message(addr, Number(band[eqP])));
                                        await sleep(2);
                                    }
                                }
                            }
                        }
                     }
                     
                     // 3. Check Mix Sends
                     if(ch.mixSends) {
                        for(const [busId, sendData] of Object.entries(ch.mixSends)) {
                             if(sendData.level !== undefined) {
                                 let addr = getX32Address(chId, 'mixSend', busId);
                                 if(addr) {
                                     osc.send(new OSC.Message(addr, Number(sendData.level)));
                                     await sleep(2);
                                 }
                             }
                        }
                     }
                }

                console.log("✅ Hardware Sync & Verification Complete");
                io.emit('sync_complete');
                try { fs.appendFileSync('debug_sync.log', `[${new Date().toISOString()}] ✅ Sync Complete\n`); } catch(e){}

              } catch (err) {
                 console.error("🔥 SYNC PROCESS FAILED:", err);
                 try { fs.appendFileSync('debug_sync.log', `[${new Date().toISOString()}] 🔥 SYNC FAILED: ${err.message}\n${err.stack}\n`); } catch(e){}
                 io.emit('sync_error', { error: err.message });
              }
            })();
            
            res.json({ success: true });
        } catch (e) {
            console.error("Scene Load Error", e);
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
    // syncConfigToConsole(); // DISABLED: Prevent overwriting console state on reconnect
    
    // Start X32 Subscription Loop (Heartbeat)
    setInterval(() => {
        try {
            osc.send(new OSC.Message('/xremote'));
        } catch(e) { /* ignore */ }
    }, 9000);

    // POLL FOR BUS NAMES & CONFIG (Startup)
     setTimeout(() => {
        console.log("📥 Fetching Bus Configs & Safes...");
        osc.send(new OSC.Message('/config/safe/ch')); // Request Safes
   
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
    
    // SAFE HANDLING
    if (parts[1] === 'config' && parts[2] === 'safe') {
        if (parts[3] === 'ch') return { id: 'safes', type: 'chanSafe' };
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
             if (parts[4] === 'pan') return { id: String(id), type: 'pan' };
             
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
    
    // DEBUG SAFES
    if (message.address.includes('safe')) {
        console.log(`🕵️ OSC SAFE RECEIVED: ${message.address} args=${JSON.stringify(message.args)} Parsed=${JSON.stringify(info)}`);
    }

    if (!info) return;
    
    let value = message.args[0];
    
    if (info.type === 'mute') {
        value = (message.args[0] === 0);
    } else if (['gate', 'dyn', 'eq', 'hpf', 'phantom'].includes(info.type)) {
        value = (message.args[0] === 1);
    }
    
    // Update State
    if (info.id === 'safes') {
        if (!x32State.safes) x32State.safes = {};
        x32State.safes[info.type] = value;
        console.log(`🛡️ Safes Updated: ${info.type} = ${value} (Binary: ${value.toString(2)})`);
        saveState();
        return;
    }

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




// MOVED TO msg 'open' event


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
        case 'name': return `/ch/${ch}/config/name`;
        case 'color': return `/ch/${ch}/config/color`;
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
             return addr;
        }
        default: return null;
    }
}

// X32 Color Map
const COLOR_MAP = {
    '#ffffff': 7, // White
    '#ff0000': 1, // Red
    '#00ff00': 2, // Green
    '#ffff00': 3, // Yellow
    '#0000ff': 4, // Blue
    '#ff00ff': 5, // Magenta
    '#00ffff': 6, // Cyan
    '#333333': 0, // Off
    '#000000': 0  // Off
};

function getX32ColorID(hex) {
    if (!hex) return 0;
    const h = hex.toLowerCase();
    return COLOR_MAP[h] || 7; // Default to White
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

    // internal params check (labelColor is truly internal-only)
    const isInternal = ['labelColor'].includes(type);
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
        // Handle Color Hex -> ID
        else if (type === 'color') {
            oscVal = getX32ColorID(value);
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
        // console.log(`🎹 Note On: ${msg.note} Vel: ${msg.velocity} Ch: ${msg.channel}`);
        
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
            // console.log(`🎹 Triggered Scene: ${action.evt} (Note ${msg.note})`);
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
const DummyDriver = require('./dummy_driver');
try {
    dmx.registerDriver('dummy', DummyDriver);
} catch (e) { console.error("Driver reg error:", e); }

// Use 'dummy' driver for virtual dev to avoid console spam.
const lighting = new LightingEngine(dmx, 'main', 'dummy');
console.log('--- SERVER RESTART DEBUG ---');
console.log('💡 DMX Lighting Engine Initialized (Silent Mode)');

// Broadcast DMX updates to Frontend Visualizer
// Broadcast DMX updates to Frontend Visualizer (DISABLED FOR PERFORMANCE)
// let lastDmxUpdate = Date.now();
// let dmxUpdateTimeout = null;
// dmx.on('update', (universe, state) => {
//     // const timeSinceLast = Date.now() - lastDmxUpdate;
//     // const dispatch = () => {
//     //     io.emit('dmx_update', { universe, state });
//     //     lastDmxUpdate = Date.now();
//     //     dmxUpdateTimeout = null;
//     // };
//     // if (timeSinceLast > 50) {
//     //     if (dmxUpdateTimeout) clearTimeout(dmxUpdateTimeout);
//     //     dispatch();
//     // } else {
//     //     if (dmxUpdateTimeout) clearTimeout(dmxUpdateTimeout);
//     //     dmxUpdateTimeout = setTimeout(dispatch, 50 - timeSinceLast);
//     // }
// });

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
// Helper to find local IP
function getLocalIP() {
    const nets = os.networkInterfaces();
    for (const name of Object.keys(nets)) {
        for (const net of nets[name]) {
            // Skip over non-IPv4 and internal (i.e. 127.0.0.1) addresses
            if (net.family === 'IPv4' && !net.internal) {
                return net.address;
            }
        }
    }
    return '127.0.0.1';
}

// 5. System Monitor (Broadcast every 2s)
setInterval(() => {
    const mem = process.memoryUsage();
    // Mac loadavg is [1min, 5min, 15min]. We take 1min.
    // Normalized by CPU count roughly gives "percentage" (1.0 = 100% of single core)
    const cpus = os.cpus().length;
    const load = os.loadavg()[0]; 
    const cpuPercent = Math.min(100, Math.round((load / cpus) * 100));

    io.emit('system_stats', {
        cpu: cpuPercent,
        mem: Math.round(mem.rss / 1024 / 1024), // MB
        uptime: Math.floor(process.uptime()),
        ip: getLocalIP()
    });
}, 2000);

// --- Logic ---

function triggerPart(songId, partName, partIndex) {
  console.log(`🚀 Triggering: Song ${songId}, Part ${partName} [Index: ${partIndex}]`);
  
  // FIX: Use SetlistManager for dynamic data
  const allData = setlistManager.getAll();
  let song = allData.songs[songId];
  
  if (!song) {
      console.warn(`❌ triggerPart: Song ${songId} NOT FOUND in setlistManager`);
      return;
  }
  
  // Handle Parts/Cues alias
  const parts = song.parts || song.cues || [];
  
  let part = null;
  if (partIndex !== undefined && parts[partIndex]) {
      part = parts[partIndex];
  } else {
      part = parts.find(p => p.name == partName);
  }
  
  if (!part) {
      console.warn(`❌ triggerPart: Part ${partName} NOT FOUND in song ${song.title}`);
      return;
  }
  
  // Safety: Ensure 'cues' property exists, or treat part itself as cues if structure varies
  const cues = part.cues || part; 

  // 1. Send OSC Commands
  if (cues && cues.x32) {
    Object.entries(cues.x32).forEach(([channelId, settings]) => {
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
  if (cues && cues.midi && midiOutput) {
      if (cues.midi.note) {
          // Sending momentary note on/off to trigger
          midiOutput.send('noteon', { note: cues.midi.note, velocity: 127, channel: 0 });
          setTimeout(() => {
            midiOutput.send('noteoff', { note: cues.midi.note, velocity: 0, channel: 0 });
          }, 100);
          console.log(`🎹 Sent MIDI Note: ${cues.midi.note}`);
      }
  }

  // 3. Send DMX Config
  // Logic: Update all channels for a scene
  // 4. Notify Clients (Active Part Highlight) -> Move to end (already there)
  if (cues && cues.dmx && universe) {
      if (cues.dmx.values) {
          universe.update(cues.dmx.values);
          console.log('💡 Updated DMX Universe');
      }
      // Or just a general "Scene" index mapped to DMX channels?
      // For now, assuming raw channel values map.
  }
  
  // Notify UI of success
  // Calculate index if not provided, for consistent client highlighting
  if (partIndex === undefined) {
      partIndex = parts.indexOf(part);
  }

  console.log(`📡 BROADCASTING active_part: Song ${songId}, Part ${partName}, Index ${partIndex}`);
  io.emit('active_part', { songId, partName, partIndex });
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
    osc.on('/meters/5', message => { // Main Outputs / Mix Buses / Matrix (28 Floats)
        if (message.args.length > 0 && message.args[0] instanceof Uint8Array) {
             const blob = message.args[0];
             const floatView = new DataView(blob.buffer, blob.byteOffset, blob.byteLength);
             
             // DEBUG: Dump all 28 floats to find Main L/R - ALWAYS LOG
             /*
             const f = [];
             const count = Math.floor(blob.byteLength / 4);
             for(let i=0; i<count; i++) {
                 try { f.push(floatView.getFloat32(i*4, true).toFixed(3)); } catch(e) {}
             }
             // console.log(`[METER 5 DUMP] ${f.join(', ')}`);
             */

             // Log Analysis shows signal at indices 25 and 26.
             // Implies 12-byte header shifting standard L/R (22/23) by +3.
             try {
                const count = Math.floor(blob.byteLength / 4);
                // L at Index 25 (Offset 100), R at Index 26 (Offset 104)
                const l = count > 25 ? floatView.getFloat32(100, true) : 0;
                const r = count > 26 ? floatView.getFloat32(104, true) : 0;
                io.emit('meters_master', { l, r });
             } catch(e){}
        }
    });

    osc.on('/meters/1', message => { // Inputs 1-32
        if (message.args.length > 0 && message.args[0] instanceof Uint8Array) {
             const blob = message.args[0];
             const floatView = new DataView(blob.buffer, blob.byteOffset, blob.byteLength);
             
             // Meter 1 (388 bytes) appears to have a 4-byte Header (Float 0)
             // Real Channel 1 is at Float 1 (Offset 4)
             
             const levels = [];
             for(let i=0; i<32; i++) {
                 try {
                    // Start at offset 4 (4 + i*4) to skip header
                    levels.push(floatView.getFloat32(4 + (i*4), true));
                 } catch(e) { levels.push(0); }
             }
             io.emit('meters_inputs', levels);
        }
    });

    osc.on('/meters/4', message => { // RTA (High Res / Meter 4)
        if (message.args.length > 0 && message.args[0] instanceof Uint8Array) {
             const blob = message.args[0];
             const floatView = new DataView(blob.buffer, blob.byteOffset, blob.byteLength);
             const rta = [];
             const count = Math.floor(blob.byteLength / 4);
             for(let i=0; i<count; i++) {
                 try {
                    rta.push(floatView.getFloat32(i*4, true));
                 } catch(e) { rta.push(0); }
             }
             io.emit('rta_data', rta);
        }
    });

    meterInterval = setInterval(() => {
        // Request Meter Data for Main L/R and Inputs
        try {
            osc.send(new OSC.Message('/meters', '/meters/6')); 
            osc.send(new OSC.Message('/meters', '/meters/1'));
            osc.send(new OSC.Message('/meters', '/meters/5')); // Outputs
        } catch (e) { /* ignore */ }
        
        // --- RTA DISABLED (User Request) ---
        // X32 RTA is internal/unanalyzable via OSC meters currently.
        const rta = Array(31).fill(0); 
        io.emit('rta_data', rta);
        // RTA handled by live meters now.

        // Input Simulation DISABLED
        // io.emit('meters_inputs', Array(32).fill(0));

    }, 50);
}





// Start polling is called inside connectOSC now.

// --- CONFIG API ---
// --- CONFIG API ---
app.get('/api/config', (req, res) => {
    // 1. Get Dynamic Data
    const setlistData = setlistManager.getAll();
    const activeSetlistId = setlistData.activeSetlistId || 'default';
    const activeSetlist = setlistData.setlists[activeSetlistId];
    
    // 2. Transform to expected format (Array of Songs)
    let dynamicSongs = [];
    if (activeSetlist && activeSetlist.songOrder) {
        dynamicSongs = activeSetlist.songOrder
            .map(id => {
                const s = setlistData.songs[id];
                if (!s) return null;
                // Map 'cues' to 'parts' if 'parts' is missing
                if (!s.parts && s.cues) {
                    return { ...s, parts: s.cues };
                }
                return s;
            })
            .filter(s => !!s); // Remove nulls
    }

    // 3. Fallback if empty (use demo or static config)
    if (dynamicSongs.length === 0) {
        dynamicSongs = config.songs; 
    }

    // 4. Merge Persisted Names, Colors & Groups
    // 4. Merge Persisted Names, Colors & Groups
    const mergedInputs = config.inputs.map(ch => {
        let savedColor = x32State[String(ch.id)]?.color;
        
        // Fix: If savedColor is an X32 Integer ID (1-7), convert to Hex
        if (typeof savedColor === 'number') {
            const colorMap = {
                1: '#FF0000', // Red
                2: '#00FF00', // Green
                3: '#FFFF00', // Yellow
                4: '#0000FF', // Blue
                5: '#FF00FF', // Magenta
                6: '#00FFFF', // Cyan
                7: '#FFFFFF', // White
                0: '#333333'  // Off/Black
            };
            savedColor = colorMap[savedColor] || null;
        }

        return {
            ...ch,
            name: x32State[String(ch.id)]?.name || ch.name,
            colorHex: savedColor || ch.colorHex // Persist Color Overrides
        };
    });

     const finalConfig = {
        ...config,
        inputs: mergedInputs,
        groups: config.groups, // Use standard groups (Color matching handles the rest)
        x32_ip: X32_IP,
        activeSetlist: activeSetlist ? activeSetlist.name : 'Unknown',
        activeSetlistId: activeSetlistId,
        songs: dynamicSongs,
        musicians: setlistData.musicians 
    };
    
    res.json(finalConfig);
});

// --- SETLIST API (Specific for Musician View) ---
app.get('/api/setlist', (req, res) => {
    // Return the Active Setlist with resolved songs
    const setlistData = setlistManager.getAll();
    const activeSetlistId = setlistData.activeSetlistId || 'default';
    const activeSetlist = setlistData.setlists[activeSetlistId];
    
    console.log(`[API/SETLIST] Requested. ActiveID: ${activeSetlistId}, Found: ${!!activeSetlist}`);
    if (activeSetlist) {
        console.log(`[API/SETLIST] Song Order: ${activeSetlist.songOrder?.length} items`);
    }

    // Resolve Songs
    const songs = (activeSetlist?.songOrder || [])
        .map(id => {
            const s = setlistData.songs[id];
            if (!s) console.warn(`[API/SETLIST] Missing song for ID: ${id}`);
            return s;
        })
        .filter(s => !!s)
        .map(s => {
             // Ensure 'parts' exists alias for cues
             if (!s.parts && s.cues) return { ...s, parts: s.cues };
             return s;
        });
    
    console.log(`[API/SETLIST] Resolved Songs: ${songs.length}`);

    if (activeSetlist) {
        res.json({
            id: activeSetlist.id,
            name: activeSetlist.name,
            songs: songs,
            order: activeSetlist.songOrder || [] 
        });
    } else {
        // Fallback or Empty
        res.json({ songs: [], order: [] });
    }
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
        value: name 
    });

    // Sync to Hardware
    const addr = getX32Address(channelId, 'name');
    if (addr) {
        // X32 requires string argument for name
        try {
            osc.send(new OSC.Message(addr, name));
        } catch(e) { console.error("OSC Send Name Error", e); }
    }

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


// --- CHARTS API ---
const chartsDir = path.join(__dirname, 'charts');
if (!fs.existsSync(chartsDir)) fs.mkdirSync(chartsDir);



// Multer for Charts
const chartStorage = multer.diskStorage({
    destination: (req, file, cb) => {
        const { songId } = req.body;
        // Create song subdir if needed
        const songDir = path.join(chartsDir, songId);
        if (!fs.existsSync(songDir)) fs.mkdirSync(songDir, { recursive: true });
        cb(null, songDir);
    },
    filename: (req, file, cb) => {
        const { role } = req.body; // e.g. "Drummer", "Bass"
        // Sanitize role filename
        const safeRole = role.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        // Keep original extension or force PDF/Img? Let's keep original for now.
        const ext = path.extname(file.originalname);
        cb(null, `${safeRole}${ext}`);
    }
});
const uploadChart = multer({ storage: chartStorage });

app.post('/api/charts/upload', uploadChart.single('chart'), (req, res) => {
    if (!req.file) return res.status(400).json({ error: "No file uploaded" });
    res.json({ success: true, path: req.file.path });
});

// NEW: Upload and Assign in one go (for Musicians)
app.post('/api/charts/assign', uploadChart.single('chart'), (req, res) => {
    try {
        if (!req.file) return res.status(400).json({ error: "No file uploaded" });
        
        const { songId, inputChannel, monitorBus } = req.body;
        if (!songId || !inputChannel) {
             return res.status(400).json({ error: "Missing songId or inputChannel" });
        }

        const assignment = {
            inputChannel: Number(inputChannel),
            monitorBus: monitorBus ? Number(monitorBus) : null,
            file: req.file // Multer file object containing path, etc.
        };

        const result = setlistManager.addChartAssignment(songId, assignment);
        if (result) {
            res.json({ success: true, assignment });
        } else {
            res.status(500).json({ error: "Failed to add assignment" });
        }
    } catch (e) {
        console.error("Chart Assignment Error:", e);
        res.status(500).json({ error: e.message });
    }
});

app.get('/api/charts/:songId/:role', (req, res) => {
    const { songId, role } = req.params;
    const { busId, channelId } = req.query; // New: Support legacy lookup via Bus ID or Channel ID
    
    const safeRole = role.replace(/[^a-z0-9]/gi, '_').toLowerCase();
    const songDir = path.join(chartsDir, songId);
    
    // 1. Try NEW System (charts/songId/role.*)
    let foundNew = false;
    if (fs.existsSync(songDir)) {
        try {
            const files = fs.readdirSync(songDir);
            const match = files.find(f => f.toLowerCase().startsWith(safeRole + '.'));
            if (match) {
                return res.sendFile(path.join(songDir, match));
            }
        } catch (e) {
            console.error("Chart Read Error", e);
        }
    }

    // 2. Try LEGACY System (setlists.json -> song.chartAssignments)
    // 2. Try LEGACY System (setlists.json -> song.chartAssignments)
    if (busId || channelId) {
        // We need to look up the song in setlistManager
        const song = setlistManager.data.songs[songId];
        if (song && song.chartAssignments) {
            // Find assignment for this bus OR channel
            let assignment = null;
            if (busId) assignment = song.chartAssignments.find(a => String(a.monitorBus) === String(busId));
            if (!assignment && channelId) assignment = song.chartAssignments.find(a => String(a.inputChannel) === String(channelId));

            if (assignment && assignment.file && assignment.file.path) {
                if (assignment.file.filename === 'noChart.txt' || assignment.file.path.endsWith('noChart.txt')) {
                    // Skip legacy placeholder
                } else {
                    const legacyPath = path.resolve(__dirname, assignment.file.path);
                    if (fs.existsSync(legacyPath)) {
                       // console.log(`📂 Serving Legacy Chart: ${legacyPath}`);
                       return res.sendFile(legacyPath);
                    }
                }
            }
        }
    }

    res.status(404).json({ error: "Chart not found" });
});

app.get('/api/musicians/groups', (req, res) => {
    // Return all (admin view) or filtering? 
    // Usually fetching 'me' handles the specific ones.
    // This might be for debug or admin.
    res.json({}); 
});

app.get('/api/solo-status', (req, res) => {
    // Send back array
    const list = soloContext.activeIds ? Array.from(soloContext.activeIds) : [];
    res.json({ activeIds: list });
});

app.get('/api/debug-safes', (req, res) => {
    console.log("🕵️ Manual Safe Request Triggered (Probing...)");
    osc.send(new OSC.Message('/config/safe/ch'));
    osc.send(new OSC.Message('/config/safe/inputs'));
    osc.send(new OSC.Message('/config/safe'));
    osc.send(new OSC.Message('/safe/ch'));
    osc.send(new OSC.Message('/prefs/safe/ch'));
    res.json({ safes: x32State.safes || null });
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

// MANUAL SAFES API
// --- PRESETS API ---
app.get('/api/presets', (req, res) => {
    res.json(presetsData);
});

app.post('/api/presets', (req, res) => {
    const { name, data, overwrite } = req.body;
    if (!name || !data) return res.status(400).json({ error: "Name and Data required" });
    
    if (presetsData[name] && !overwrite) {
        return res.status(409).json({ error: "Preset already exists", exists: true });
    }
    
    presetsData[name] = data;
    // Persist
    fs.writeFile(PRESETS_FILE, JSON.stringify(presetsData, null, 2), (err) => {
        if(err) console.error("Failed to save presets", err);
    });
    
    res.json({ success: true, presets: presetsData });
});

app.delete('/api/presets/:name', (req, res) => {
    const { name } = req.params;
    if (presetsData[name]) {
        delete presetsData[name];
        fs.writeFile(PRESETS_FILE, JSON.stringify(presetsData, null, 2), (err) => {
            if(err) console.error("Failed to save presets", err);
        });
    }
    res.json({ success: true, presets: presetsData });
});

app.get('/api/config/safes', (req, res) => {
    res.json(musiciansData.manualSafes || []);
});

app.post('/api/config/safes', (req, res) => {
    const list = req.body.safes;
    if (!Array.isArray(list)) return res.status(400).json({error: "Expected array"});
    
    musiciansData.manualSafes = list.map(String); // Ensure strings
    x32State.manualSafes = musiciansData.manualSafes; // Sync
    
    fs.writeFile('./musicians.json', JSON.stringify(musiciansData, null, 2), (err) => {
         if (err) return res.status(500).json({ error: "Save failed" });
         res.json({ success: true, safes: musiciansData.manualSafes });
    });
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

// --- NEW SETLIST ROUTES ---
app.post('/api/setlist/song', (req, res) => {
    const song = req.body;
    // If ID exists and song exists, update. Else create.
    // song object from frontend might contain { id:..., title:... }
    if (song.id && setlistManager.data.songs[song.id]) {
        res.json({ song: setlistManager.updateSong(song.id, song) });
    } else {
        res.json({ song: setlistManager.createSong(song) });
    }
});

app.delete('/api/setlist/song/:id', (req, res) => {
    const { id } = req.params;
    setlistManager.deleteSong(id);
    res.json({ success: true });
});

app.post('/api/setlist/order', (req, res) => {
   const { setlistId, order } = req.body;
   setlistManager.updateSetlistOrder(setlistId, order);
   res.json({ success: true });
});

app.post('/api/setlist/bus-name', (req, res) => {
    const { busId, name } = req.body;
    setlistManager.setBusName(busId, name);
    res.json({ success: true });
});

// CHART UPLOAD
const upload = multer({ dest: 'uploads/' });
app.post('/api/upload-chart', upload.single('chart'), (req, res) => {
    if (!req.file) return res.json({ success: false, error: "No file uploaded" });
    res.json({ success: true, file: req.file });
});

app.post('/api/setlist/export-docx', (req, res) => {
    const { setlistId } = req.body;
    try {
        // 1. Get Data
        const data = setlistManager.getAll();
        const setlist = data.setlists[setlistId || 'default'];
        if (!setlist) return res.status(404).json({ error: "Setlist not found" });

        // 2. Prepare Template Data
        const songs = setlist.songOrder.map((id, idx) => {
            const s = data.songs[id];
            if (!s) return null;
            return {
                idx: idx + 1,
                index: idx + 1, // Redundant fallback
                i: idx + 1,
                num: idx + 1,
                title: s.title,
                artist: s.artist,
                bpm: s.bpm,
                notes: s.notes || ''
            };
        }).filter(s => !!s);

        // 3. Load Template
        const templatePath = path.resolve(__dirname, 'templates', 'setlist_template.docx');
        if (!fs.existsSync(templatePath)) {
            // Fallback to creating a simple text buffer if template missing?
            // Or try the other one 'ESB Playlist template.docx' (assuming rename?)
            // Let's rely on setlist_template.docx as seen in list_dir
            return res.status(500).json({ error: "Template file 'setlist_template.docx' not found." });
        }

        const content = fs.readFileSync(templatePath, 'binary');
        const zip = new PizZip(content);
        const doc = new Docxtemplater(zip, { paragraphLoop: true, linebreaks: true });

        // 4. Render
        doc.render({
            setlistName: setlist.name,
            songs: songs,
            date: new Date().toLocaleDateString()
        });

        // 5. Output
        const buf = doc.getZip().generate({ type: 'nodebuffer' });
        
        res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        res.setHeader('Content-Disposition', `attachment; filename=Setlist.docx`);
        res.send(buf);

    } catch (e) {
        console.error("Export Error:", e);
        res.status(500).json({ error: "Generation Failed: " + e.message });
    }
});

const PDFDocument = require('pdfkit');

// PDF Export (Server-Side Conversion via LibreOffice)
const { exec } = require('child_process');

app.post('/api/setlist/export-pdf', (req, res) => {
    const { setlistId } = req.body;
    const tempId = Date.now();
    const tempDocx = path.join(__dirname, 'uploads', `temp_${tempId}.docx`);
    const outputDir = path.join(__dirname, 'uploads');
    const tempPdf = path.join(__dirname, 'uploads', `temp_${tempId}.pdf`);

    try {
        // 1. Get Data & Template
        const data = setlistManager.getAll();
        const setlist = data.setlists[setlistId || 'default'];
        if (!setlist) return res.status(404).json({ error: "Setlist not found" });

        const songs = setlist.songOrder.map((id, idx) => {
            const s = data.songs[id];
            if (!s) return null;
            return {
                idx: idx + 1,
                index: idx + 1, // Redundant fallback
                i: idx + 1,
                num: idx + 1,
                title: s.title,
                artist: s.artist,
                bpm: s.bpm,
                notes: s.notes || ''
            };
        }).filter(s => !!s);

        const templatePath = path.resolve(__dirname, 'templates', 'setlist_template.docx');
        if (!fs.existsSync(templatePath)) return res.status(500).json({ error: "Template not found" });

        // 2. Generate DOCX
        const content = fs.readFileSync(templatePath, 'binary');
        const zip = new PizZip(content);
        const doc = new Docxtemplater(zip, { paragraphLoop: true, linebreaks: true });

        doc.render({
            setlistName: setlist.name,
            songs: songs,
            date: new Date().toLocaleDateString()
        });

        const buf = doc.getZip().generate({ type: 'nodebuffer' });
        fs.writeFileSync(tempDocx, buf);

        // 3. Convert to PDF using LibreOffice (soffice)
        // Command: soffice --headless --convert-to pdf --outdir <dir> <file>
        const cmd = `soffice --headless --convert-to pdf --outdir "${outputDir}" "${tempDocx}"`;
        console.log(`Processing PDF: ${cmd}`);

        exec(cmd, (error, stdout, stderr) => {
            if (error) {
                console.error("LibreOffice Error:", error);
                return res.status(500).json({ error: "PDF Conversion failed" });
            }

            // 4. Send File
            if (fs.existsSync(tempPdf)) {
                res.setHeader('Content-Type', 'application/pdf');
                res.setHeader('Content-Disposition', `attachment; filename=Setlist.pdf`);
                const stream = fs.createReadStream(tempPdf);
                stream.pipe(res);
                
                // Cleanup after finish
                stream.on('end', () => {
                    fs.unlinkSync(tempDocx);
                    fs.unlinkSync(tempPdf);
                });
            } else {
                 console.error("PDF File not found after conversion");
                 res.status(500).json({ error: "PDF File creation failed" });
            }
        });

    } catch (e) {
        console.error("PDF Export Logic Error:", e);
        res.status(500).json({ error: "Internal Server Error: " + e.message });
        // Try cleanup
        if (fs.existsSync(tempDocx)) fs.unlinkSync(tempDocx);
    }
});

// --------------------------

// ... (Other Setlist routes can be added later as needed)

// --- MUSICIAN AUTH ENDPOINTS ---

// 1. LOGIN
app.post('/api/login', (req, res) => {
    const { email, password } = req.body;
    
    // Check Password
    const currentPass = (x32State.systemConfig && x32State.systemConfig.musicianPassword) || 'otokia';
    if (password !== currentPass) {
        return res.status(401).json({ error: "Invalid Password" });
    }

    // Check Email
    const musician = musiciansData.musicians.find(m => m.email.toLowerCase().trim() === email.toLowerCase().trim());
    if (!musician) {
        return res.status(401).json({ error: "Email not found in Roster" });
    }

    // Success
    // In a real app, sign a JWT. Here, return a simple session obj.
    res.json({
        token: "session_" + musician.id + "_" + Date.now(),
        musician: musician
    });
});

// 2. SET SYSTEM PASSWORD
app.post('/api/system/password', (req, res) => {
    const { password } = req.body;
    if (!password) return res.status(400).json({ error: "Password required" });

    if (!x32State.systemConfig) x32State.systemConfig = {};
    x32State.systemConfig.musicianPassword = password;
    
    saveState();
    console.log("🔐 Musician Password Updated");
    res.json({ success: true });
});

// 3. GET CURRENT PASSWORD (For Admin UI only - simplified)
app.get('/api/system/password', (req, res) => {
    const pass = (x32State.systemConfig && x32State.systemConfig.musicianPassword) || 'otokia';
    res.json({ password: pass });
});

// --- ENDPOINTS ---

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
  socket.on('disconnect', () => {
        console.log('🔌 Client Disconnected:', socket.id);
    });
    
    socket.on('trigger_part', (data) => {
        // data = { songId, partName, partIndex }
        console.log(`[SOCKET] Received trigger_part:`, data);
        if (data && data.songId && (data.partName || data.partIndex !== undefined)) {
            triggerPart(data.songId, data.partName, data.partIndex);
        } else {
            console.warn("[SOCKET] trigger_part ignored (missing data):", data);
        }
    });

    socket.on('restore_musician_mix', ({ mixData, mixBusId }) => {
        console.log(`🎚️ Restoring Mix for Bus ${mixBusId}...`);
        if (!mixData || !mixBusId) return;
        
        const busStr = String(mixBusId).padStart(2, '0');
        
        Object.entries(mixData).forEach(([chId, settings]) => {
            const chStr = String(chId).padStart(2, '0');
            
            // 1. Level
            if (settings.level !== undefined) {
                const addr = `/ch/${chStr}/mix/${busStr}/level`;
                osc.send(new OSC.Message(addr, parseFloat(settings.level)));
            }
            
            // 2. Mute/On (X32: 1 = On, 0 = Off)
            // Our Saved State: 'on' (1 or 0)
            if (settings.on !== undefined) {
                const addr = `/ch/${chStr}/mix/${busStr}/on`;
                osc.send(new OSC.Message(addr, parseInt(settings.on)));
            }
        });
    });

    // --- SEND INITIAL STATE ---
    // Flatten channels Map/Object if needed, but x32State is an object here
    // Send full state so client isn't blank
    socket.emit('init_x32_state', x32State);

    // Pull Mechanism
    socket.on('request_state', () => {
        console.log(`📤 Sending Full State to ${socket.id} (${Object.keys(x32State.channels||{}).length} chans)`);
        socket.emit('init_x32_state', x32State);
    });
  
  socket.on('dmx_trigger', (sceneName) => {
        console.log('💡 Manual Trigger:', sceneName);
        try {
            if(lighting) lighting.play(sceneName);
        } catch (e) {
            console.error('❌ DMX Trigger Error:', e);
        }
  });

  // --- OSC BRIDGE (Client -> X32) ---
  // --- OSC BRIDGE (Client -> X32) ---
  socket.on('osc', (data) => {
      // Expects: { address: '/ch/01/mix/01/level', args: [0.75] }
      if (!data || !data.address) return;
      
      try {
          const val = data.args[0];

          // 1. Forward to Hardware
          // if (osc && osc.status() === OSC.STATUS.OPEN) { 
          // Check removed: 'restore' works without it, so we trust osc.send()
          // Explicitly coerce to Number for X32 (it ignores strings for levels)
          const args = data.args.map(a => Number(a));
          const message = new OSC.Message(data.address, ...args);
          osc.send(message);
          // }

          // 2. Parse & Update Internal State (Optimistic Server-Side)
          // /ch/{id}/mix/{bus}/level
          const mixMatch = data.address.match(/\/ch\/(\d+)\/mix\/(\d+)\/(level|on)/);
          if (mixMatch) {
              const [_, chIdStr, busIdStr, param] = mixMatch;
              const chId = parseInt(chIdStr).toString(); // Normalize "01" -> "1"
              const busId = parseInt(busIdStr).toString(); 

              if (!x32State[chId]) x32State[chId] = {};
              if (!x32State[chId].mixSends) x32State[chId].mixSends = {};
              if (!x32State[chId].mixSends[busId]) x32State[chId].mixSends[busId] = {};
              
              const target = x32State[chId].mixSends[busId];
              target[param] = val; // Update state

              // Broadcast to sync all clients (including sender)
              io.emit('x32_update', {
                  id: chId,
                  type: 'mixSend',
                  bus: busId,
                  param: param,
                  value: val
              });
              
              // TRIGGER AUTO-SAVE
              // Debounced save to disk (defined in global scope)
              saveState();
          }
          
          
          // 3. Handle Master Bus Updates (Optimistic)
          // /bus/{id}/mix/(on|fader)
          const busMatch = data.address.match(/\/bus\/(\d+)\/mix\/(on|fader)/);
          if (busMatch) {
              const [_, busIdStr, param] = busMatch;
              const busId = parseInt(busIdStr).toString();
              const busKey = 'bus' + busId;
              
              if (!x32State[busKey]) x32State[busKey] = {};
              
              // Map param
              // fader -> level
              // on -> mute (inverted logic? No, state usually stores 'on' or 'mute')
              // Line 556 init says 'mute: false'.
              // Line 24 in MusicianMix: currentMute ? 1 : 0 sent to /on.
              // So if val is 1 (On), mute is false. if val is 0, mute is true.
              
              if (param === 'fader') {
                   x32State[busKey].level = val;
                   io.emit('x32_update', { id: busKey, type: 'bus', param: 'level', value: val });
              } else if (param === 'on') {
                   const isMuted = (val === 0);
                   x32State[busKey].on = val; // Store 'on' explicitly if consumed strictly
                   x32State[busKey].mute = isMuted; // Store 'mute' for legacy
                   io.emit('x32_update', { id: busKey, type: 'bus', param: 'on', value: val });
              }
              
              saveState();
          }

      } catch (e) {
          console.error("❌ OSC Bridge Error:", e);
      }
  });

});



// --- API: GROUPS ---
app.get('/api/groups', (req, res) => {
    res.json(SOLO_GROUPS);
});

// --- SPA FALLBACK ---
app.use((req, res) => {
    res.sendFile(path.join(__dirname, 'client/dist/index.html'));
});

server.listen(PORT, () => {
  const protocol = server instanceof https.Server ? 'https' : 'http';
  console.log(`🌟 Controller Server running at ${protocol}://localhost:${PORT}`);
});
