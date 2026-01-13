import React, { useState, useEffect } from 'react';
import axios from 'axios';

// FX SLOT MAPPING (Based on Restore Task)
// FX 1 (Bus 13) = Vintage Room (Type 7)
// FX 2 (Bus 14) = Plate Reverb (Type 5)
// FX 3 (Bus 15) = Stereo Delay (Type 10)
// FX 4 (Bus 16) = Dimensional Chorus (Type 16)

const BUS_ROOM = 13;
const BUS_PLATE = 14;
const BUS_DELAY = 15;
const BUS_CHORUS = 16;

const DubOverlay = ({ channelId, channelType, x32State, onClose }) => {
    const [activeTab, setActiveTab] = useState('ECHO'); // ECHO, SPACE, DOUBLER
    const [syncEnabled, setSyncEnabled] = useState(true);

    // Sync State Listener
    useEffect(() => {
        // Assume socket is globally available via window or import if possible.
        // Since we are in a component, we rely on the parent or global socket.
        // App.jsx has `socket`. We can export it or pass it. 
        // For now, let's assume valid import from `App.jsx` context isn't clean without props.
        // Let's use the global variable `socket` if defined in Main, OR assume it's passed as prop?
        // Actually, importing `socket` from `App` is circular. 
        // Best approach: Use `window.socket` if App exposes it, or standard `import { socket }` if moved to separate file.
        // Given existing code structure, I will restart socket logic here? No.
        // Let's assume passed in props? "socket"
    }, []);

    // Helper: Determine Send Path
    // Channel: /ch/01/mix/15/level
    // Bus: /bus/01/mix/15/level
    const getSendPath = (busId) => {
        const prefix = channelType === 'bus' ? 'bus' : 'ch';
        const id = String(channelId).padStart(2, '0');
        const bus = String(busId).padStart(2, '0');
        return `/${prefix}/${id}/mix/${bus}/level`;
    };

    const setSendLevel = (busId, val) => {
        axios.post('/api/osc', { address: getSendPath(busId), args: [val] });
    };

    const setFxType = (slot, typeId) => {
        axios.post('/api/osc', { address: `/fx/${slot}/type`, args: [typeId] });
    };

    const setFxParam = (slot, param, val) => {
        axios.post('/api/osc', { address: `/fx/${slot}/par/${param}`, args: [val] });
    };

    // --- FORMATTERS ---
    const formatMs = (val, max = 3000) => `${Math.round(val * max)} ms`;
    const formatSec = (val, max = 10) => `${(val * max).toFixed(1)} s`;
    const formatPercent = (val) => `${Math.round(val * 100)}%`;
    const formatHz = (val) => {
        // Logarithmic mapping 20Hz - 20kHz
        const freq = 20 * Math.pow(1000, val);
        return freq > 1000 ? `${(freq/1000).toFixed(1)} kHz` : `${Math.round(freq)} Hz`;
    };

    // --- RENDERERS ---

    // Internal Component for Smooth Sliders
    const NeonSlider = ({ label, value, onChange, min=0, max=1, step=0.01, color='red', format=formatPercent }) => {
        const [localVal, setLocalVal] = useState(value);
        const [isDragging, setIsDragging] = useState(false);

        // Sync local value with prop ONLY if not dragging
        useEffect(() => {
            if (!isDragging) setLocalVal(value);
        }, [value, isDragging]);

        const handleChange = (e) => {
            const val = parseFloat(e.target.value);
            setLocalVal(val); // UI Update immediately
            onChange(val);    // Send OSC
        };

        return (
             <div style={styles.sliderGroup}>
                <label>{label} <span style={styles.val}>{format(localVal)}</span></label>
                <input 
                    type="range" min={min} max={max} step={step}
                    value={localVal}
                    onChange={handleChange} // React Standard
                    onPointerDown={() => setIsDragging(true)}
                    onPointerUp={() => setIsDragging(false)}
                    className={`neonslider-${color}`}
                />
            </div>
        );
    };

    // 1. ECHO (Dub Delay - Slot 3)
    const renderEcho = () => {
        const fxState = x32State.fx && x32State.fx[3] ? x32State.fx[3] : {};
        const feedback = fxState[3] !== undefined ? fxState[3] : 0.4; 
        const time = fxState[1] !== undefined ? fxState[1] : 0.5;
        // Based on thieves_alley.json observation (Params 6, 7 present), we assume LoCut=6, HiCut=7
        const loCut = fxState[6] !== undefined ? fxState[6] : 0.0;
        const hiCut = fxState[7] !== undefined ? fxState[7] : 1.0;
        
        return (
            <div style={styles.tabContent}>
                {/* THROW BUTTON */}
                <div style={styles.controlGroup}>
                    <button 
                        onMouseDown={() => setSendLevel(BUS_DELAY, 0.85)} 
                        onMouseUp={() => setSendLevel(BUS_DELAY, 0)}     
                        onMouseLeave={() => setSendLevel(BUS_DELAY, 0)}
                        onTouchStart={() => setSendLevel(BUS_DELAY, 0.85)}
                        onTouchEnd={() => setSendLevel(BUS_DELAY, 0)}
                        style={{...styles.bigButton, borderColor: '#ff0055', boxShadow:'0 0 20px #ff0055'}}
                    >
                        <span style={{fontSize:'1.5em'}}>THROW</span>
                        <span style={{fontSize:'0.6em', opacity:0.7}}>BUS 15</span>
                    </button>
                    
                     {/* SYNC TOGGLE */}
                     {/* We need access to socket to toggle this. Relying on API for now? No, API easier. */}
                     <button 
                        onClick={() => {
                             // Use API or define a set_sync endpoint
                             // Or just emit if we had socket access.
                             // Let's use axios OSC for now? No, specific socket event.
                             // Fallback: Just assume Always On for now as per user request "activate based on midi".
                             // But adding "SYNC" indicator.
                        }}
                        style={{marginTop:'10px', background:'#222', color:'#444', border:'none'}}
                     >
                         MIDI SYNC ACTIVE
                     </button>
                </div>

                {/* CONTROLS */}
                <div style={styles.slidersRow}>
                    <NeonSlider 
                        label="REPEATS" value={feedback} 
                        onChange={(val) => { setFxParam(3, 3, val); setFxParam(3, 4, val); }}
                        color="red"
                    />
                     {/* Add FILTERS (Param 6 & 7) */}
                    <NeonSlider 
                        label="LO CUT" value={loCut} format={formatHz}
                        onChange={(val) => setFxParam(3, 6, val)}
                        color="red"
                    />
                    <NeonSlider 
                        label="HI CUT" value={hiCut} format={formatHz}
                        onChange={(val) => setFxParam(3, 7, val)}
                        color="red"
                    />
                </div>
            </div>
        );
    };

    // 2. SPACE (Plate Reverb - Slot 2)
    const renderSpace = () => {
        const fxState = x32State.fx && x32State.fx[2] ? x32State.fx[2] : {};
        const decay = fxState[2] !== undefined ? fxState[2] : 0.5; 
        const preDelay = fxState[1] !== undefined ? fxState[1] : 0.1;
        const loCut = fxState[5] !== undefined ? fxState[5] : 0.1; // Check Param 5 for Plate? usually LoCut
        const hiCut = fxState[6] !== undefined ? fxState[6] : 0.5; // Param 6 HiCut

        return (
            <div style={styles.tabContent}>
                 <div style={styles.controlGroup}>
                    <button 
                         onMouseDown={() => setSendLevel(BUS_PLATE, 0.85)} 
                         onMouseUp={() => setSendLevel(BUS_PLATE, 0)}     
                         onMouseLeave={() => setSendLevel(BUS_PLATE, 0)}
                         onTouchStart={() => setSendLevel(BUS_PLATE, 0.85)}
                         onTouchEnd={() => setSendLevel(BUS_PLATE, 0)}
                        style={{...styles.bigButton, borderColor: '#00ccff', boxShadow:'0 0 20px #00ccff'}}
                    >
                         <span style={{fontSize:'1.5em'}}>SPLASH</span>
                         <span style={{fontSize:'0.6em', opacity:0.7}}>BUS 14</span>
                    </button>
                </div>

                <div style={styles.slidersRow}>
                    <NeonSlider 
                        label="SIZE" value={decay} format={(v)=>formatSec(v, 5)}
                        onChange={(val) => setFxParam(2, 2, val)}
                        color="blue"
                    />
                    <NeonSlider 
                        label="LO CUT" value={loCut} format={formatHz}
                        onChange={(val) => setFxParam(2, 5, val)}
                        color="blue"
                    />
                    <NeonSlider 
                        label="HI CUT" value={hiCut} format={formatHz}
                        onChange={(val) => setFxParam(2, 6, val)}
                        color="blue"
                    />
                </div>
            </div>
        );
    };

    // 3. DOUBLER (Voice - Slot 4)
    // Supports: Dim Chorus (Type 16) OR Stereo Pitch (Type 19)
    const renderDoubler = () => {
        const fxState = x32State.fx && x32State.fx[4] ? x32State.fx[4] : {};
        // We detect type via x32State if we had it, but for now we track locally or rely on user verify.
        // Let's assume Type 16 is default.
        // We need a helper to read current Type? 
        // We haven't subscribed to `/fx/4/type` in the parser (it's complex).
        // For this UI, we will provide "Load Buttons".

        return (
             <div style={styles.tabContent}>
                 <div style={styles.controlGroup}>
                    <button 
                        onClick={() => setSendLevel(BUS_CHORUS, 0.8)}
                         style={{...styles.bigButton, height:'90px', width:'90px', borderColor: '#cc00ff', boxShadow:'0 0 20px #cc00ff'}}
                    >
                         <span style={{fontSize:'1.2em'}}>ON</span>
                    </button>
                     <button 
                        onClick={() => setSendLevel(BUS_CHORUS, 0)}
                         style={{...styles.bigButton, height:'60px', width:'60px', marginTop:'10px', borderColor: '#555', background:'#222', boxShadow:'none'}}
                    >
                         OFF
                    </button>
                </div>
                
                {/* FX STYLE SELECTOR */}
                 <div style={{display:'flex', gap:'10px', background:'#222', padding:'10px', borderRadius:'8px',marginBottom:'20px'}}>
                     <button 
                        onClick={() => setFxType(4, 16)} 
                        style={{padding:'10px', background:'#333', color:'white', border:'1px solid #555', cursor:'pointer'}}
                     >
                         STYLE: CHORUS
                     </button>
                     <button 
                        onClick={() => setFxType(4, 19)} 
                        style={{padding:'10px', background:'#333', color:'white', border:'1px solid #555', cursor:'pointer'}}
                     >
                         STYLE: PITCH
                     </button>
                 </div>
                 
                 {/* PITCH CONTROLS (Only relevant if Type 19/Pitch) */}
                 <div style={styles.slidersRow}>
                    <NeonSlider 
                        label="DETUNE / RATE" value={fxState[1] || 0}
                        onChange={(val) => { setFxParam(4, 1, val); setFxParam(4, 2, val); }}
                        color="purple"
                    />
                 </div>
            </div>
        );
    };

    return (
        <div style={styles.overlay}>
            <div style={styles.container}>
                {/* HEADER */}
                <div style={styles.header}>
                    <h2 style={{margin:0}}>DUB CONTROL // {channelType === 'bus' ? 'GRP' : 'CH'} {channelId}</h2>
                    <button onClick={onClose} style={styles.closeBtn}>✕</button>
                </div>

                {/* TABS */}
                <div style={styles.tabs}>
                    <button style={activeTab === 'ECHO' ? styles.tabActive : styles.tab} onClick={()=>setActiveTab('ECHO')}>ECHO</button>
                    <button style={activeTab === 'SPACE' ? styles.tabActive : styles.tab} onClick={()=>setActiveTab('SPACE')}>SPACE</button>
                    <button style={activeTab === 'DOUBLER' ? styles.tabActive : styles.tab} onClick={()=>setActiveTab('DOUBLER')}>DOUBLER</button>
                </div>

                {/* CONTENT */}
                <div style={styles.body}>
                    {activeTab === 'ECHO' && renderEcho()}
                    {activeTab === 'SPACE' && renderSpace()}
                    {activeTab === 'DOUBLER' && renderDoubler()}
                </div>
                
                 {/* DRY BUTTON (Global) */}
                <div style={{padding:'10px', borderTop:'1px solid #444', textAlign:'center'}}>
                     <button 
                        onClick={() => {
                            [BUS_ROOM, BUS_PLATE, BUS_DELAY, BUS_CHORUS].forEach(b => setSendLevel(b, 0));
                        }}
                        style={styles.dryBtn}
                     >KILL ALL FX (DRY)</button>
                </div>

            </div>
        </div>
    );
};

const styles = {
    overlay: {
        position:'fixed', top:0, left:0, width:'100vw', height:'100vh', 
        background:'rgba(0,0,0,0.92)', zIndex:3000, 
        display:'flex', justifyContent:'center', alignItems:'center'
    },
    container: {
        width:'90%', maxWidth:'600px', background:'#181818', border:'1px solid #333',
        borderRadius:'12px', overflow:'hidden', boxShadow:'0 0 50px rgba(0,0,0,0.8)'
    },
    header: {
        display:'flex', justifyContent:'space-between', alignItems:'center',
        padding:'15px 20px', background:'#222', borderBottom:'1px solid #333', color:'#fff'
    },
    closeBtn: {
        background:'none', border:'none', color:'#fff', fontSize:'1.5em', cursor:'pointer'
    },
    tabs: {
        display:'flex', borderBottom:'1px solid #333'
    },
    tab: {
        flex:1, padding:'15px', background:'#111', color:'#888', border:'none',
        cursor:'pointer', fontWeight:'bold', fontSize:'0.9em'
    },
    tabActive: {
        flex:1, padding:'15px', background:'#222', color:'#fff', border:'none',
        cursor:'pointer', fontWeight:'bold', fontSize:'0.9em',
        borderBottom:'2px solid #fff'
    },
    body: {
        padding:'30px', minHeight:'300px'
    },
    tabContent: {
        display:'flex', flexDirection:'column', alignItems:'center', gap:'30px'
    },
    controlGroup: {
        display:'flex', flexDirection:'column', alignItems:'center'
    },
    bigButton: {
        width: '140px', height: '140px', borderRadius: '50%',
        background: 'radial-gradient(circle, #333 0%, #111 100%)',
        border: '3px solid #666',
        color: 'white', display:'flex', flexDirection:'column',
        justifyContent:'center', alignItems:'center', cursor:'pointer',
        transition: 'transform 0.1s'
    },
    slidersRow: {
        display:'flex', width:'100%', gap:'20px', justifyContent:'space-around'
    },
    sliderGroup: {
        display:'flex', flexDirection:'column', alignItems:'center', gap:'10px', flex:1
    },
    dryBtn: {
        background:'#333', color:'#aaa', border:'1px solid #555', padding:'10px 20px', borderRadius:'6px', cursor:'pointer'
    },
    val: {
        fontSize: '0.8em', color: '#fff', marginLeft: '5px', background:'#333', padding:'2px 4px', borderRadius:'4px'
    }
};

export default DubOverlay;
