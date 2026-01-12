import React, { useState, useEffect } from 'react';
import axios from 'axios';

const MonitorsOverlay = ({ onClose, x32State, config }) => {
    // Local state for 'Sends on Fader' view
    // If NOT null, we are viewing that Bus ID (e.g., 'bus1')
    const [selectedBusId, setSelectedBusId] = useState(null);
    const [musicians, setMusicians] = useState([]);

    useEffect(() => {
        // Fetch musicians to map to buses
        axios.get('/api/musicians').then(res => setMusicians(res.data)).catch(console.error);
    }, []);

    // Get assigned musician for a Bus ID
    const getMusicianForBus = (busIdNum) => {
        return musicians.find(m => m.mixBusId === busIdNum);
    };

    // Helper to get Fader Level for a Bus Master
    // Bus state stored as x32State['bus1'] etc
    const getBus = (busId) => x32State[busId] || { level: 0, mute: false, name: '' };

    // Helper for dB conversion (X32 Curve Approximation)
    const floatToDB = (f) => {
        if (f === 0) return '-∞ dB';
        let db;
        if (f >= 0.75) db = (f - 0.75) * 40;
        else if (f >= 0.5) db = (f - 0.5) * 40 - 10;
        else if (f >= 0.25) db = (f - 0.25) * 80 - 30;
        else db = (f) * 120 - 60;
        return (db > 0 ? '+' : '') + db.toFixed(1) + ' dB';
    };

    // Helper to resolve color (Shared)
    const resolveColor = (val) => {
        if (!val) return null;
        if (String(val).startsWith('#')) return val;
        const num = parseInt(val);
        if (!isNaN(num)) {
            const map = {
                1: '#FF0000', 2: '#00FF00', 3: '#FFFF00', 4: '#0000FF',
                5: '#FF00FF', 6: '#00FFFF', 7: '#FFFFFF', 0: '#333333'
            };
            return map[num];
        }
        return val;
    };

    // --- RENDER ---
    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.9)', zIndex: 2000,
            display: 'flex', flexDirection: 'column', padding: '20px'
        }}>
            {/* HEADER */}
            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'20px', borderBottom:'1px solid #444', paddingBottom:'20px'}}>
                <h1 style={{margin:0, color:'#0088ff', textShadow:'0 0 10px #004488'}}>
                    MONITORS (v2) {selectedBusId ? `> ${getBus(selectedBusId).name || `Bus ${selectedBusId.replace('bus','')}`}` : ''}
                </h1>
                <div style={{display:'flex', gap:'20px'}}>
                    {selectedBusId && (
                        <button 
                            onClick={() => setSelectedBusId(null)}
                            style={{background:'#444', color:'#ccc', border:'1px solid #666', padding:'10px 20px', fontSize:'1.2em', borderRadius:'8px', cursor:'pointer'}}
                        >
                            BACK TO OVERVIEW
                        </button>
                    )}
                    <button 
                        onClick={onClose}
                        style={{background:'#f00', color:'#fff', border:'none', padding:'10px 20px', fontSize:'1.2em', borderRadius:'8px', cursor:'pointer'}}
                    >
                        CLOSE
                    </button>
                </div>
            </div>

            {/* CONTENT START */}
            <div style={{flex:1, overflow:'hidden', display:'flex'}}>
                
                {/* VIEW 1: BUS OVERVIEW (Select a Monitor) */}
                {!selectedBusId && (
                    <div style={{
                        display:'grid', gridTemplateColumns:'repeat(12, 1fr)', gap:'10px', 
                        width:'100%', height:'100%', overflowX:'auto'
                    }}>
                        {[...Array(12)].map((_, i) => {
                            const busNum = i + 1;
                            const busId = `bus${busNum}`;
                            const busState = getBus(busId);
                            const musician = getMusicianForBus(busNum);
                            const hasMusician = !!musician;

                            // Resolve Bus Color
                            const rawColor = resolveColor(busState.color);
                            const busColor = (rawColor && rawColor !== '#333333') ? rawColor : '#0088ff'; // Default Blue
                            const isBright = ['#FF0000', '#00FF00', '#FFFF00', '#00FFFF', '#FFFFFF', '#FF00FF'].includes(busColor);
                            const txtColor = isBright ? 'black' : 'white';

                            return (
                                <div key={busId} style={{
                                    background: '#222', borderRadius: '8px', 
                                    border: '1px solid #333',
                                    display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '10px',
                                    position: 'relative'
                                }}>
                                    {/* TITLE / SCRIBBLE STRIP */}
                                    <div style={{
                                        width: '100%', 
                                        background: busColor, 
                                        color: txtColor,
                                        marginTop:0, marginBottom:'10px',
                                        padding: '5px 0',
                                        borderRadius: '4px',
                                        textAlign: 'center',
                                        fontWeight: 'bold', fontSize:'1.1em',
                                        boxShadow: '0 2px 4px rgba(0,0,0,0.3)'
                                    }}>
                                        Bus {busNum}
                                    </div>
                                    
                                    {/* MUSICIAN DISPLAY */}
                                    <div style={{
                                        background: hasMusician ? '#333' : '#300', 
                                        width: '100%', padding: '10px', borderRadius: '4px', textAlign: 'center',
                                        marginBottom: '20px', border: hasMusician ? '1px solid #444' : '1px solid #f00'
                                    }}>
                                        {hasMusician ? (
                                            <>
                                                <div style={{color:'white', fontWeight:'bold', fontSize:'1.1em'}}>{musician.name.split(' ')[0]}</div>
                                                <div style={{color:'#aaa', fontSize:'0.8em'}}>{musician.role}</div>
                                            </>
                                        ) : (
                                            <div style={{color:'#f00', fontWeight:'bold'}}>NOT ASSIGNED</div>
                                        )}
                                    </div>

                                    {/* BUS MASTER FADER */}
                                    <div style={{flex:1, position:'relative', width:'60px', background:'#111', borderRadius:'4px', marginBottom:'5px', display:'flex', justifyContent:'center'}}>
                                         {/* 0dB Marker */}
                                         <div style={{position:'absolute', bottom:'75%', width:'100%', height:'2px', background:'rgba(255,255,255,0.3)', pointerEvents:'none', zIndex:1}}></div>
                                         <div style={{position:'absolute', bottom:'75%', right:'-25px', color:'#666', fontSize:'0.7em', pointerEvents:'none'}}>0dB</div>
                                         
                                         <input 
                                            type="range" min="0" max="1" step="0.005"
                                            value={busState.level || 0}
                                            onChange={(e) => {
                                                const val = parseFloat(e.target.value);
                                                axios.post('/api/set-param', { channelId: busId, type: 'level', value: val });
                                            }}
                                            style={{
                                                writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                                width: '100%', height: '100%', accentColor: busColor,
                                                zIndex: 5
                                            }}
                                         />
                                    </div>
                                    
                                    {/* dB Value Label */}
                                    <div style={{
                                         marginBottom:'15px', textAlign:'center', 
                                         color:'white', fontSize:'0.9em', fontWeight:'bold', fontFamily:'monospace'
                                    }}>
                                         {floatToDB(busState.level || 0)}
                                    </div>

                                    {/* MUTE BUTTON */}
                                    <button 
                                        onClick={() => {
                                            const newVal = !busState.mute;
                                            axios.post('/api/set-param', { channelId: busId, type: 'mute', value: newVal });
                                        }}
                                        style={{
                                            width: '100%', padding: '10px', borderRadius: '4px', fontWeight:'bold', marginBottom:'10px',
                                            background: busState.mute ? '#f00' : '#333', color: 'white',
                                            border: busState.mute ? '2px solid maroon' : '1px solid #555'
                                        }}
                                    >
                                        {busState.mute ? 'MUTED' : 'ON'}
                                    </button>

                                    {/* OPEN SENDS BUTTON */}
                                    <button
                                        onClick={() => setSelectedBusId(busId)}
                                        style={{
                                            width:'100%', background: busColor === '#0088ff' ? '#444' : busColor, 
                                            color: txtColor, 
                                            border:'none',  
                                            padding:'15px', borderRadius:'4px', fontWeight:'bold', cursor:'pointer',
                                            opacity: 0.9
                                        }}
                                    >
                                        MIX SENDS
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* VIEW 2: SENDS ON FADER (Mixing Inputs to Selected Bus) */}
                {selectedBusId && (
                    <div style={{
                        width:'100%', height:'100%', overflowX:'auto',
                        background:'#1a1a1a', borderRadius:'12px', padding:'20px', border:'2px solid #0088ff',
                        display:'flex', flexDirection:'column'
                    }}>
                         <div style={{
                             display:'flex', alignItems:'center', gap:'10px',
                             borderBottom:'1px solid #444', paddingBottom:'10px', marginBottom:'10px'
                         }}>
                             <h2 style={{margin:0, color:'#ccc'}}>Sends on Fader &gt;</h2>
                             
                             {/* Bus Scribble Strip */}
                             <div style={{
                                 background: getBus(selectedBusId).color ? getBus(selectedBusId).color : '#333',
                                 color: getBus(selectedBusId).color ? (getBus(selectedBusId).labelColor || 'black') : 'white',
                                 padding:'5px 15px', borderRadius:'6px', fontWeight:'bold', fontSize:'1.2em',
                                 border: '1px solid #555', textAlign:'center', minWidth:'120px',
                                 boxShadow: '0 2px 5px rgba(0,0,0,0.5)'
                             }}>
                                 {getBus(selectedBusId).name || `Bus ${selectedBusId.replace('bus','')}`}
                             </div>
                         </div>
                        <div style={{flex:1, display:'flex', gap:'5px', overflowY:'hidden', overflowX:'auto'}}>
                             {config.inputs.map(ch => {
                                 // Current Send Level
                                 const busNum = selectedBusId.replace('bus', '');
                                 // Check x32State[ch.id].mixSends[busNum]
                                 const chState = x32State[ch.id] || {};
                                 const sendState = chState.mixSends && chState.mixSends[busNum] ? chState.mixSends[busNum] : { level: 0, on: false };
                                 const isLinked = musicians.find(m => m.mixBusId === parseInt(busNum))?.linkedChannels.includes(ch.id);
                                 // Helper to resolve color
                                 const resolveColor = (val) => {
                                     // If null/undefined
                                     if (!val) return null;
                                     // If explicit hex
                                     if (String(val).startsWith('#')) return val;
                                     // If numeric ID (X32)
                                     const num = parseInt(val);
                                     if (!isNaN(num)) {
                                         const map = {
                                             1: '#FF0000', // Red
                                             2: '#00FF00', // Green
                                             3: '#FFFF00', // Yellow
                                             4: '#0000FF', // Blue
                                             5: '#FF00FF', // Magenta
                                             6: '#00FFFF', // Cyan
                                             7: '#FFFFFF', // White
                                             0: '#333333'
                                         };
                                         return map[num];
                                     }
                                     return val; // Fallback (e.g. named color)
                                 };

                                 const stateColor = resolveColor(chState.color);
                                 const configColor = resolveColor(ch.colorHex) || ch.colorHex;

                                 // FIX: If state says "Black" (#333333), ignore it and use Config Color.
                                 // This ensures that uninitialized or default-black channels on Console still show their System Config color.
                                 const effectiveStateColor = (stateColor && stateColor !== '#333333') ? stateColor : null;
                                 
                                 const chColor = effectiveStateColor || configColor || (isLinked ? '#0088ff' : '#666'); 

                                 // Determine Text Color based on Background (Simple Logic)
                                 // If color is Red/Green/Yellow/Cyan/White/Magenta (Bright) -> Black Text
                                 // If color is Blue/Black (Dark) -> White Text
                                 const isBright = ['#FF0000', '#00FF00', '#FFFF00', '#00FFFF', '#FFFFFF', '#FF00FF'].includes(chColor);
                                 const txtColor = isBright ? 'black' : 'white';

                                 return (
                                     <div key={ch.id} style={{
                                         minWidth:'80px', flex:'0 0 auto', background: isLinked ? '#002233' : '#222', 
                                         // border: isLinked ? '2px solid #0088ff' : '1px solid #333', 
                                         border: '1px solid #444',
                                         borderRadius:'6px',
                                         display:'flex', flexDirection:'column', alignItems:'center', padding:'5px',
                                         overflow: 'hidden'
                                     }}>
                                         {/* Full Colored Scribble Strip Header */}
                                         <div style={{
                                             width: '100%', 
                                             background: chColor, 
                                             color: txtColor,
                                             marginBottom:'5px', 
                                             fontWeight:'bold', 
                                             textAlign:'center',
                                             padding: '5px 0',
                                             borderRadius: '4px',
                                             fontSize:'0.9em',
                                             textShadow: 'none',
                                             boxShadow: '0 2px 4px rgba(0,0,0,0.3)'
                                         }}>
                                             {ch.name || `CH ${ch.id}`}
                                         </div>

                                         {/* DEBUG: Raw Color Value */}
                                         <div style={{fontSize:'0.6em', color:'yellow', marginBottom:'2px', whiteSpace:'pre'}}>
                                             St: {String(chState.color)} | Cfg: {String(ch.colorHex)}
                                         </div>
                                         
                                         {/* Fader */}
                                         <div style={{flex:1, width:'40px', background:'#111', borderRadius:'4px', marginBottom:'5px', position:'relative', display:'flex', justifyContent:'center'}}>
                                              {/* 0dB Marker */}
                                              <div style={{position:'absolute', bottom:'75%', width:'100%', height:'2px', background:'rgba(255,255,255,0.3)', pointerEvents:'none', zIndex:1}}></div>
                                              
                                              <input 
                                                  type="range" min="0" max="1" step="0.005"
                                                  value={sendState.level || 0}
                                                  onChange={(e) => {
                                                      const val = parseFloat(e.target.value);
                                                      // Optimistic
                                                      axios.post('/api/set-param', { 
                                                          channelId: ch.id, 
                                                          type: 'mixSend', 
                                                          value: val,
                                                          bus: busNum 
                                                      });
                                                  }}
                                                  style={{
                                                      writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                                      width: '100%', height: '100%', 
                                                      accentColor: chColor, // Dynamic Color
                                                      zIndex: 5
                                                  }}
                                              />
                                         </div>
                                         
                                          {/* dB Value Label */}
                                          <div style={{
                                              marginBottom:'5px', textAlign:'center', 
                                              color:'#ccc', fontSize:'0.7em', fontWeight:'bold', fontFamily:'monospace'
                                          }}>
                                              {floatToDB(sendState.level || 0)}
                                          </div>

                                          <div style={{fontSize:'0.7em', color:'#555'}}>{ch.id}</div>
                                     </div>
                                 )
                             })}
                        </div>
                    </div>
                )}

            </div>
        </div>
    );
};

export default MonitorsOverlay;
