import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import io from 'socket.io-client';
import './index.css';

const socket = io('/', { path: '/socket.io' });

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }
  static getDerivedStateFromError(error) { return { hasError: true, error }; }
  componentDidCatch(error, errorInfo) { console.error("Uncaught error:", error, errorInfo); }
  render() {
    if (this.state.hasError) {
      return <div style={{padding: 50, color: 'red'}}><h1>Something went wrong.</h1><pre>{this.state.error.toString()}</pre><button onClick={() => window.location.reload()}>Refresh</button></div>;
    }
    return this.props.children;
  }
}

// --- OVERLAYS ---
const SendsOverlay = ({ channelId, state, onClose }) => {
    // We assume Bus 13 is the DUB DELAY
    const DUB_BUS = 13;
    const sendState = state.mixSends && state.mixSends[DUB_BUS] ? state.mixSends[DUB_BUS] : { level: 0, on: 0 };
    
    // Helper to send level
    const setSendLevel = (val) => {
         axios.post('/api/osc', { address: `/ch/${channelId}/mix/${DUB_BUS < 10 ? '0'+DUB_BUS : DUB_BUS}/level`, args: [val] });
    };

    return (
        <div style={{
            position:'fixed', top:0, left:0, width:'100vw', height:'100vh', 
            background:'rgba(0,0,0,0.85)', zIndex:2000, display:'flex', justifyContent:'center', alignItems:'center'
        }}>
            <div style={{
                width:'80%', maxWidth:'500px', background:'#222', border:'1px solid #555', 
                borderRadius:'8px', padding:'20px', display:'flex', flexDirection:'column', gap:'20px'
            }}>
                <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', borderBottom:'1px solid #444', paddingBottom:'10px'}}>
                    <h2 style={{margin:0, color:'white'}}>SENDS - {channelId}</h2>
                    <button onClick={onClose} style={{background:'transparent', border:'none', color:'white', fontSize:'1.5em', cursor:'pointer'}}>✕</button>
                </div>
                
                {/* DUB THROW SECTION */}
                <div style={{background:'#333', padding:'20px', borderRadius:'8px', display:'flex', flexDirection:'column', alignItems:'center', gap:'10px'}}>
                    <h3 style={{margin:0, color:'#aaa'}}>DUB THROW (Bus 13)</h3>
                    
                    <button 
                        onMouseDown={() => setSendLevel(0.75)} // ~0dB
                        onMouseUp={() => setSendLevel(0)}     // -inf
                        onMouseLeave={() => setSendLevel(0)}  // Safety
                        onTouchStart={() => setSendLevel(0.75)}
                        onTouchEnd={() => setSendLevel(0)}
                        style={{
                            width:'120px', height:'120px', borderRadius:'50%', 
                            background: 'radial-gradient(circle, #ff0055 0%, #990033 100%)',
                            border: '4px solid #ff55aa',
                            color: 'white', fontWeight:'bold', fontSize:'1.5em',
                            boxShadow: '0 0 20px #ff0055',
                            cursor: 'pointer', outline:'none'
                        }}
                    >
                        THROW
                    </button>
                    <div style={{color:'#666', fontSize:'0.8em'}}>Press & Hold to Send</div>
                </div>

                {/* OTHER SENDS GRID */}
                <div style={{
                    display:'grid', gridTemplateColumns:'repeat(8, 1fr)', gap:'10px', 
                    width:'100%', overflowY:'auto', padding:'10px', background:'#222', borderRadius:'8px'
                }}>
                    {[...Array(16)].map((_, i) => {
                        const bus = i+1;
                        if(bus === DUB_BUS) return null; // Skip Dub bus (handled above)
                        
                        const sVal = state.mixSends && state.mixSends[bus] ? state.mixSends[bus].level : 0;
                        
                        return (
                            <div key={bus} style={{
                                background:'#111', padding:'5px', textAlign:'center', borderRadius:'4px', 
                                display:'flex', flexDirection:'column', alignItems:'center', height:'140px'
                            }}>
                                <div style={{fontSize:'0.7em', color:'#888', marginBottom:'5px'}}>Bus {bus}</div>
                                <input 
                                    type="range" min="0" max="1" step="0.01"
                                    value={sVal || 0}
                                    onChange={(e) => {
                                        const val = parseFloat(e.target.value);
                                        // Update Local Optimistically
                                        /* We rely on parent state update via socket, but for smooth drag we might need local state? 
                                           For now, direct send. Broadcast will update UI. */
                                        axios.post('/api/osc', { address: `/ch/${channelId}/mix/${bus < 10 ? '0'+bus : bus}/level`, args: [val] });
                                    }}
                                    style={{
                                        writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                        width: '20px', flex:1, accentColor: bus > 12 ? '#00bbff' : '#00ff00' // FX Buses blue
                                    }}
                                />
                                <div style={{fontSize:'0.6em', color:'#666', marginTop:'2px'}}>{Math.round((sVal||0)*100)}%</div>
                            </div>
                        )
                    })}
                </div>

            </div>
        </div>
    );
};

// Helper for dB conversion
const floatToDB = (f) => {
    if (f < 0.1) return '-oo';
    let db;
    if (f >= 0.75) db = (f - 0.75) * 40; 
    else if (f >= 0.5) db = (f - 0.5) * 40 - 10;
    else if (f >= 0.25) db = (f - 0.25) * 80 - 30;
    else db = (f) * 120 - 60;
    return db.toFixed(1) + ' dB';
};

const MasterStrip = ({ state, onOpen }) => {
    const [bpm, setBpm] = useState(0);
    const [division, setDivision] = useState('1/4');
    const [localLevel, setLocalLevel] = useState(null);
    const [meters, setMeters] = useState({ l: 0, r: 0 });
    
    useEffect(() => {
        socket.on('bpm_update', d => setBpm(d.bpm));
        socket.on('delay_sync', d => {
            setBpm(d.bpm);
            setDivision(d.division);
        });
        socket.on('meters_master', d => setMeters(d));

        return () => {
            socket.off('bpm_update');
            socket.off('delay_sync');
            socket.off('meters_master');
        };
    }, []);

    const setMasterLevel = (val) => {
        setLocalLevel(val);
        // Use set-param for immediate state update + OSC
        axios.post('/api/set-param', { channelId: 'master', type: 'level', value: val });
    };

    const toggleMasterMute = () => {
        const newVal = !state.mute; 
        axios.post('/api/set-param', { channelId: 'master', type: 'mute', value: newVal });
    };
    
    const setDiv = (d) => {
        setDivision(d);
        axios.post('/api/set-param', { type: 'delayDivision', value: d });
    };
    
    const renderMeter = (val) => (
        <div style={{
            width: '8px', height: '100%', 
            background: 'linear-gradient(to top, #0f0 0%, #0f0 62%, #fc0 63%, #fc0 75%, #f00 76%, #f00 100%)',
            borderRadius: '2px', position: 'relative', overflow: 'hidden',
            border: '1px solid #333'
        }}>
            {/* The "Curtain" - Black overlay that shrinks as signal grows */}
            <div style={{
                position: 'absolute', top: 0, left: 0, right: 0,
                height: `${Math.max(0, 100 - (val * 100))}%`,
                background: '#1a1a1a', // Dark gray "off" state
                transition: 'height 50ms linear'
            }}></div>
            
            {/* Optional Segment Lines */}
            <div style={{
                 position:'absolute', top:0, left:0, right:0, bottom:0,
                 backgroundImage: 'linear-gradient(rgba(0,0,0,0.5) 1px, transparent 1px)',
                 backgroundSize: '100% 4px',
                 pointerEvents:'none'
            }}></div>
        </div>
    );

    return (
        <div style={{
            display:'flex', flexDirection:'row', alignItems:'center', padding:'5px 10px', gap:'12px',
            background:'#111', borderRadius:'8px', border:'2px solid #555', 
            height: '100px'
        }}>
            {/* Title & Sync */}
            <div style={{display:'flex', flexDirection:'column', alignItems:'center', width:'60px'}}>
                <h3 style={{color:'white', margin:'0 0 5px 0', fontSize:'0.8em'}}>MAIN</h3>
                <div style={{fontSize:'0.9em', color:'#0ff', fontWeight:'bold'}}>{bpm > 0 ? bpm : '--'}</div>
                <div style={{fontSize:'0.5em', color:'#666', marginBottom:'2px'}}>BPM</div>
                <select 
                    value={division} 
                    onChange={(e) => setDiv(e.target.value)}
                    style={{width:'100%', background:'#333', color:'#aaa', border:'none', fontSize:'0.6em', padding:0}}
                >
                     {['1/2','1/3','1/4','1/6','1/8','1/16'].map(d => <option key={d} value={d}>{d}</option>)}
                </select>
            </div>

            {/* EQ / COMP */}
            <div style={{display:'flex', flexDirection:'column', gap:'4px'}}>
                <button 
                  onClick={() => onOpen('eq', 'MAIN EQUALIZER')}
                  style={{
                    background: state.eq ? '#00bb00' : '#444', 
                    color: state.eq ? 'black' : '#ccc', 
                    border:'1px solid #555', borderRadius:'3px', cursor:'pointer', fontWeight:'bold',
                    fontSize: '0.6em', padding: '4px 6px'
                  }}
                >EQ</button>
                <button 
                  onClick={() => onOpen('dyn', 'MAIN COMPRESSOR')}
                  style={{
                    background: state.dyn ? '#00bb00' : '#444', 
                    color: state.dyn ? 'black' : '#ccc', 
                    border:'1px solid #555', borderRadius:'3px', cursor:'pointer', fontWeight:'bold',
                    fontSize: '0.6em', padding: '4px 6px'
                }}
                >DYN</button>
            </div>

            {/* Meters */}
            <div style={{display:'flex', gap:'3px', height:'80px', padding:'0 4px', background:'#050505', borderRadius:'3px', alignItems:'center'}}>
                {renderMeter(meters.l)}
                {renderMeter(meters.r)}
            </div>

            {/* Fader Area */}
            <div style={{display:'flex', gap:'5px', height:'100%', alignItems:'center'}}>
                {/* dB Label */}
                <div style={{
                    fontSize:'0.7em', color:'#aaa', width:'35px', textAlign:'right', 
                    display:'flex', alignItems:'center', justifyContent:'flex-end', height:'100%'
                }}>
                    {floatToDB(localLevel !== null ? localLevel : (state.level || 0))}
                </div>

                {/* Fader Track */}
                <div style={{position:'relative', height:'80px', width:'24px', background:'#000', borderRadius:'3px'}}>
                    {/* 0dB Marker (at 75%) */}
                    <div style={{
                        position:'absolute', bottom:'75%', left:0, right:0, 
                        height:'1px', background:'#888', zIndex:0,
                        boxShadow: '0 0 2px black'
                    }}></div>
                    
                    <input 
                        type="range" min="0" max="1" step="0.005"
                        value={localLevel !== null ? localLevel : (state.level || 0)}
                        onChange={(e) => setMasterLevel(parseFloat(e.target.value))}
                        onMouseUp={() => setLocalLevel(null)}
                        onTouchEnd={() => setLocalLevel(null)}
                        title={`Master Level: ${floatToDB(localLevel !== null ? localLevel : (state.level || 0))}`}
                        style={{
                            position:'absolute', top:0, left:0, margin:0,
                            WebkitAppearance: 'slider-vertical',
                            width: '100%', height: '100%', accentColor: '#ff0055',
                            opacity: 0.8, zIndex: 1, cursor: 'pointer'
                        }}
                    />
                </div>
            </div>

            {/* Mute */}
            <button 
                onClick={toggleMasterMute}
                style={{
                    width:'50px', height:'50px', borderRadius:'50%', 
                    background: state.mute ? 'red' : '#333',
                    color: 'white', fontWeight:'bold', border:'3px solid #555',
                    boxShadow: state.mute ? '0 0 15px red' : 'none', cursor:'pointer',
                    fontSize: '1em', marginLeft: '5px',
                    display: 'flex', alignItems: 'center', justifyContent: 'center'
                }}
            >
                M
            </button>
        </div>
    );
};

// --- RTA VISUALIZER ---
const RTAVisualizer = () => {
    const canvasRef = useRef(null);
    const [data, setData] = useState(Array(31).fill(0));

    useEffect(() => {
        if(socket) {
            socket.on('rta_data', d => setData(d));
        }
        return () => {
            if(socket) socket.off('rta_data');
        };
    }, []);

    useEffect(() => {
        // Debug Socket
        socket.on('connect', () => console.log('✅ Socket.IO Connected!', socket.id));
        socket.on('connect_error', (err) => console.error('❌ Socket.IO Error:', err));
    }, []);

    useEffect(() => {
        const cvs = canvasRef.current;
        if (!cvs) return;
        const ctx = cvs.getContext('2d');
        const w = cvs.width;
        const h = cvs.height;
        ctx.clearRect(0, 0, w, h);
        
        const numBands = 31;
        const barW = w / numBands;
        const gap = 2; 
        
        data.forEach((val, i) => {
             const barH = Math.max(0, val * h);
             const x = i * barW;
             const y = h - barH;
             
             // Gradient Fill
             const grad = ctx.createLinearGradient(x, h, x, 0);
             grad.addColorStop(0, '#0f0');
             grad.addColorStop(0.6, '#0f0');
             grad.addColorStop(0.61, '#fc0');
             grad.addColorStop(0.75, '#fc0');
             grad.addColorStop(0.76, '#f00');
             grad.addColorStop(1, '#f00');
             
             ctx.fillStyle = grad;
             ctx.fillRect(x + gap/2, y, barW - gap, barH);
        });
        
    }, [data]);

    return (
        <div style={{flex:1, height:'100%', background:'#000', borderRadius:'4px', border:'1px solid #333', margin:'0 15px', position:'relative', minWidth:'300px'}}>
             <canvas ref={canvasRef} width={800} height={100} style={{width:'100%', height:'100%', display:'block'}} />
             {/* Grid overlay */}
             <div style={{position:'absolute', top:0, left:0, right:0, bottom:0, pointerEvents:'none', background:'linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px)', backgroundSize:'100% 25%'}}></div>
        </div>
    );
};

// --- SYSTEM MONITOR ---
const SystemMonitor = ({ socket }) => {
    const [stats, setStats] = useState({ cpu: 0, mem: 0, uptime: 0 });

    useEffect(() => {
        if (!socket) return;
        const handler = (data) => setStats(data);
        socket.on('system_stats', handler);
        return () => socket.off('system_stats', handler);
    }, [socket]);

    const formatTime = (s) => {
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        return `${h}h ${m}m`;
    };

    return (
        <div style={{
            position: 'fixed', bottom: '10px', right: '10px',
            background: 'rgba(0,0,0,0.9)', color: '#0f0', border: '1px solid #0f0',
            padding: '8px', borderRadius: '4px', fontSize: '12px',
            fontFamily: 'monospace', zIndex: 10000, pointerEvents: 'none',
            display: 'flex', gap: '15px',
            boxShadow: '0 0 10px #000'
        }}>
            <span style={{fontWeight:'bold'}}>MONITOR:</span>
            <span>CPU: {stats.cpu}%</span>
            <span>MEM: {stats.mem} MB</span>
            <span>UP: {formatTime(stats.uptime)}</span>
        </div>
    );
};

// --- DMX VISUALIZER ---
const DMXVisualizer = ({ socket, onClose }) => {
    const [dmx, setDmx] = useState({});

    useEffect(() => {
        console.log('📊 SystemMonitor: Mounted');
        if (!socket) {
            console.log('❌ SystemMonitor: No Socket!');
            return;
        }
        const handler = (data) => {
            // console.log('📊 SystemMonitor: Received', data);
            // setStats(data); // This line would cause an error as setStats is not defined in DMXVisualizer
            // Assuming the intent was to log and not to set state for DMXVisualizer with 'system_stats'
            console.log('📊 SystemMonitor (DMXVisualizer context): Received system_stats data:', data);
        };
        socket.on('system_stats', handler);
        return () => socket.off('system_stats', handler);
    }, [socket]);

    useEffect(() => {
        if (!socket) return;
        const handler = ({ state }) => {
             setDmx(prev => ({ ...prev, ...state }));
        };
        socket.on('dmx_update', handler);
        return () => socket.off('dmx_update', handler);
    }, [socket]);

    const getRGB = (addr) => {
        const r = dmx[addr] || 0;
        const g = dmx[addr+1] || 0;
        const b = dmx[addr+2] || 0;
        return `rgb(${r},${g},${b})`;
    };

    const getMoverColor = (addr) => {
        const dim = (dmx[addr+5] || 0) / 255;
        const r = dmx[addr+7] || 0;
        const g = dmx[addr+8] || 0;
        const b = dmx[addr+9] || 0;
        return `rgb(${r*dim},${g*dim},${b*dim})`;
    };

    const getMoverTransform = (addr) => {
        const panVal = dmx[addr] === undefined ? 127 : dmx[addr]; 
        const tiltVal = dmx[addr+1] === undefined ? 127 : dmx[addr+1];
        
        // Calibration: 0 = Top (0deg), 128 = Bottom (180deg)
        // CSS 0deg points Down. So we add 180 to make 0 DMX point Up.
        const panDeg = ((panVal / 255) * 360) + 180;
        
        // Tilt: Length 20px to 50px
        const len = 20 + ((tiltVal/255)*30);
        
        return { 
            transform: `rotate(${panDeg}deg)`,
            height: `${len}px`
        };
    };

    return (
        <div style={{
            position: 'fixed', top: '10%', left: '10%', width: '80%', height: '80%',
            background: '#111', border: '2px solid #555', borderRadius: '10px',
            zIndex: 2000, padding: '20px', display: 'flex', flexDirection: 'column',
            boxShadow: '0 0 50px rgba(0,0,0,0.8)'
        }}>
            <div style={{display:'flex', justifyContent:'space-between', marginBottom:'20px'}}>
                <h2 style={{margin:0, color:'#fff'}}>💡 DMX VISUALIZER</h2>
                <div style={{display:'flex', gap:'10px'}}>
                     {/* TEST BUTTONS */}
                     <button onClick={() => socket.emit('dmx_trigger', 'setup')} style={{background:'#eef', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>SETUP</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'hell')} style={{background:'#cc0000', color:'#fff', border:'none', padding:'5px', cursor:'pointer'}}>HELL 🔥</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'sunshine')} style={{background:'#ffcc00', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>SUN ☀️</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'madness')} style={{background:'#fff', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>MAD 🌪️</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'aqua')} style={{background:'#00ffff', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>AQUA 🌊</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'rasta')} style={{background:'#00aa00', color:'#fff', border:'none', padding:'5px', cursor:'pointer'}}>RASTA 🦁</button>
                     <div style={{display:'inline-block', margin:'0 10px'}}>
                        <button onClick={() => socket.emit('dmx_trigger', 'focusLeft')} style={{background:'#ccc', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>◀️</button>
                        <button onClick={() => socket.emit('dmx_trigger', 'focus')} style={{background:'#ccc', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>FOCUS 🎯</button>
                        <button onClick={() => socket.emit('dmx_trigger', 'focusRight')} style={{background:'#ccc', color:'#000', border:'none', padding:'5px', cursor:'pointer'}}>▶️</button>
                     </div>
                     <button onClick={() => socket.emit('dmx_trigger', 'police')} style={{background: 'linear-gradient(90deg, darkblue, darkred)', color:'#fff', border:'none', padding:'5px', cursor:'pointer'}}>POLICE 🚨</button>
                     <button onClick={() => socket.emit('dmx_trigger', 'blackout')} style={{background:'#333', color:'#fff', border:'none', padding:'5px', cursor:'pointer'}}>OFF</button>

                     <button onClick={onClose} style={{background:'#f00', color:'#fff', border:'none', padding:'5px 15px', marginLeft:'10px'}}>CLOSE</button>
                </div>
            </div>

            <div style={{flex:1, position:'relative', background:'#000', borderRadius:'5px', overflow:'hidden'}}> 
                 {/* STAGE BACK: Movers */}
                 <div style={{position:'absolute', top:'10%', left:0, width:'100%', display:'flex', justifyContent:'space-around'}}>
                      {[100, 113, 126].map((addr, i) => (
                          <div key={addr} style={{
                              width:'60px', height:'60px', borderRadius:'50%',
                              border: '2px solid #333', position:'relative',
                              display:'flex', alignItems:'center', justifyContent:'center'
                          }}>
                               {/* BASE */}
                               <div style={{position:'absolute', width:'100%', height:'100%', borderRadius:'50%', border:'1px dashed #444'}}></div>

                               {/* CROSSHAIR MARKERS */}
                               <div style={{position:'absolute', top:0, bottom:0, left:'50%', width:'1px', background:'#333'}}></div>
                               <div style={{position:'absolute', left:0, right:0, top:'50%', height:'1px', background:'#333'}}></div>

                               {/* LABELS */}
                               <div style={{position:'absolute', top:'-15px', width:'100%', textAlign:'center', color:'#666', fontSize:'0.7em'}}>0</div>
                               <div style={{position:'absolute', bottom:'-15px', width:'100%', textAlign:'center', color:'#666', fontSize:'0.7em'}}>128</div>

                               {/* HEAD / BEAM POINTER */}
                              <div style={{
                                  position: 'absolute', top: '50%', left: '50%', marginLeft: '-5px',
                                  width:'10px', background: getMoverColor(addr),
                                  borderRadius:'5px', 
                                  boxShadow: `0 0 15px ${getMoverColor(addr)}`,
                                  transformOrigin: 'top center',
                                  transition: 'all 0.1s linear',
                                  ...getMoverTransform(addr)
                              }}>
                                  {/* Tip */}
                                  <div style={{position:'absolute', bottom:0, width:'100%', height:'2px', background:'#fff', opacity:0.8}}></div> 
                              </div>
                              
                              <div style={{position:'absolute', color:'#888', fontSize:'0.6em', bottom:'-20px'}}>M{i+1}</div>
                          </div>
                      ))}
                 </div>
                 {/* STAGE TOP: Washes */}
                 <div style={{position:'absolute', top:'40%', left:0, width:'100%', display:'flex', justifyContent:'space-around'}}>
                      {[150, 154, 158, 162].map((addr, i) => (
                          <div key={addr} style={{
                              width:'50px', height:'50px', borderRadius:'50%',
                              background: getRGB(addr),
                              boxShadow: `0 0 20px ${getRGB(addr)}`,
                              border: '2px solid #444',
                              color:'#fff', display:'flex', alignItems:'center', justifyContent:'center'
                          }}>W{i+1}</div>
                      ))}
                 </div>
                 {/* STAGE FRONT: Bars */}
                 <div style={{position:'absolute', bottom:'10%', left:'5%', right:'5%', display:'flex', justifyContent:'space-between'}}>
                      {[1, 25, 49, 73].map((barAddr, i) => (
                          <div key={barAddr} style={{
                              display:'flex', width:'22%', height:'30px', background:'#222',
                              border: '1px solid #444', overflow:'hidden'
                          }}>
                               {Array(8).fill(0).map((_, seg) => {
                                   const c = getRGB(barAddr + (seg * 3));
                                   return <div key={seg} style={{flex:1, background:c, boxShadow:`0 0 10px ${c}`}} />
                               })}
                          </div>
                      ))}
                 </div>
            </div>
        </div>
    );
};




function App() {
  return (
    <ErrorBoundary>
        <AppContent />
    </ErrorBoundary>
  );
}

function AppContent() {
  const [config, setConfig] = useState(null);
  const [selectedSongId, setSelectedSongId] = useState(null);
  const [activePart, setActivePart] = useState(null);
  const [status, setStatus] = useState({ x32: 'connected', midi: 'pending', dmx: 'ready' });

  const [showDMX, setShowDMX] = useState(false);
  const [showVisualizer, setShowVisualizer] = useState(false);
  const [midiMsg, setMidiMsg] = useState(null);
  const [x32State, setX32State] = useState({});
  const [overlay, setOverlay] = useState(null); // { channelId, type, title }
  const [soloIds, setSoloIds] = useState([]); // Array of strings
  const [scenes, setScenes] = useState([]); // List of scene names
  const [inputMeters, setInputMeters] = useState(Array(32).fill(0));

  // Load initial config & scenes
  useEffect(() => {
     if(inputMeters[0] > 0.01) console.log('Meter Data Alive:', inputMeters[0]);
  }, [inputMeters]);

  useEffect(() => {
    axios.get('/api/config')
      .then(res => {
        setConfig(res.data);
        if (res.data.songs.length > 0) {
          setSelectedSongId(res.data.songs[0].id);
        }
      })
      .catch(err => console.error("Failed to load config", err));
      
    // Check initial solo status
    axios.get('/api/solo-status').then(res => setSoloIds(res.data.activeIds || []));

    axios.get('/api/scenes')
        .then(res => {
            if (Array.isArray(res.data)) setScenes(res.data);
            else console.log("Scenes data invalid:", res.data);
        })
        .catch(e => console.error(e));

    socket.on('connect', () => {
      console.log("Socket connected");
    });
    
    socket.on('active_part', (data) => {
        if (data.songId === selectedSongId) {
            setActivePart(data.partName);
        }
    });

    socket.on('midi_msg', (data) => {
        setMidiMsg(data);
        if(window.midiTimeout) clearTimeout(window.midiTimeout);
        window.midiTimeout = setTimeout(() => setMidiMsg(null), 1500);
    });

    socket.on('meters_inputs', (data) => {
        setInputMeters(data);
    });

    socket.on('meters_inputs', (levels) => {
        setInputMeters(levels);
    });

    socket.on('x32_update', (data) => {
        setX32State(prev => {
            const chanState = prev[data.id] || {};
            
            if (data.type === 'mixSend') {
                const sends = chanState.mixSends ? {...chanState.mixSends} : {};
                const send = sends[data.bus] ? {...sends[data.bus]} : {};
                send[data.param] = data.value;
                sends[data.bus] = send;
                
                return {
                    ...prev,
                    [data.id]: {
                        ...chanState,
                        mixSends: sends
                    }
                };
            } else if (data.type === 'name') {
                 return {
                    ...prev,
                    [data.id]: { ...chanState, name: data.name }
                };
            } else if (data.type === 'color') {
                 return {
                    ...prev,
                    [data.id]: { ...chanState, color: data.color, labelColor: data.labelColor }
                };
            } else if (data.type === 'master') {
                const master = prev.master ? {...prev.master} : {level:0, mute:false};
                master[data.param] = data.value;
                return { ...prev, master: master };
            }

            if (data.type === 'masterEqBand') {
                const master = prev.master ? {...prev.master} : {level:0, mute:false};
                const bands = master.eqBands ? {...master.eqBands} : {};
                if (!bands[data.band]) bands[data.band] = {};
                bands[data.band][data.param] = data.value;
                master.eqBands = bands;
                return { ...prev, master: master };
            }
            
            if (data.type === 'eqBand') {
                const bands = chanState.eqBands ? {...chanState.eqBands} : {};
                const band = bands[data.band] ? {...bands[data.band]} : {};
                band[data.param] = data.value;
                bands[data.band] = band;
                
                return {
                    ...prev,
                    [data.id]: {
                        ...chanState,
                        eqBands: bands
                    }
                };
            }
            
            return {
                ...prev,
                [data.id]: {
                    ...chanState,
                    [data.type]: data.value
                }
            };
        });
    });
    
    socket.on('solo_update', (data) => {
        console.log("🎸 Solo Update:", data);
        setSoloIds(data.activeIds || []);
    });
    
    socket.on('x32_bulk_update', (data) => {
        setX32State(data);
    });

    return () => {
        socket.off('connect');
        socket.off('active_part');
        socket.off('x32_update');
        socket.off('solo_update');
        socket.off('x32_bulk_update');
    };
  }, [selectedSongId]);

  const handleTrigger = async (partName) => {
    if (!selectedSongId) return;
    setActivePart(partName);
    try {
      await axios.post('/api/trigger', { songId: selectedSongId, partName });
    } catch (err) {
      console.error("Trigger failed", err);
    }
  };
  
  const handleCapture = async () => {
      try {
          const res = await axios.get('/api/capture');
          console.log("CAPTURED STATE:", JSON.stringify(res.data, null, 2));
          alert("State captured to console! (Check browser dev tools)");
      } catch (err) {
          console.error("Capture failed", err);
      }
  }

  const handleToggleMute = async (channelId, currentMuteState) => {
      // Toggle logic
      const promptMute = !currentMuteState; 
      try {
          await axios.post('/api/toggle-mute', { channelId, mute: promptMute });
      } catch (err) {
          console.error("Mute toggle failed", err);
      }
  };

  const handleToggleParam = async (channelId, type, currentState) => {
      const newState = !currentState;
      // Optimistic
      setX32State(prev => ({
          ...prev, 
          [channelId]: { ...prev[channelId], [type]: newState }
      }));
      await axios.post('/api/set-param', { channelId, type, value: newState });
  };

  const handleSaveScene = async () => {
    const name = prompt("Enter scene name:");
    if (!name) return;
    try {
      await axios.post('/api/scenes', { name });
      setScenes(prev => [...prev, name]);
      alert("Scene saved!");
    } catch (err) {
      console.error("Failed to save scene", err);
      alert("Error saving scene");
    }
  };

  const handleLoadScene = async (name) => {
    if (!confirm(`Load scene "${name}"? Current settings will be overwritten.`)) return;
    try {
      await axios.post(`/api/scenes/${name}/load`);
      alert("Scene loaded!");
    } catch (err) {
      console.error("Failed to load scene", err);
      alert("Error loading scene");
    }
  };

  const handleDeleteScene = async (name) => {
      if (!confirm(`Permanently delete scene "${name}"?`)) return;
      try {
          await axios.delete(`/api/scenes/${name}`);
          setScenes(prev => prev.filter(s => s !== name));
      } catch (err) {
          console.error("Failed to delete scene", err);
          alert("Error deleting scene");
      }
  };

  const handleOverwriteScene = async (name) => {
      if (!confirm(`Overwrite scene "${name}" with current settings?`)) return;
      try {
          await axios.post('/api/scenes', { name });
          alert("Scene updated!");
      } catch (err) {
          console.error("Failed to save scene", err);
          alert("Error saving scene");
      }
  };
  
  const openOverlay = (channelId, type, title) => {
      setOverlay({ channelId, type, title });
  };

  if (!config) return <div className="glass-panel"><h1>loading...</h1></div>;

  const currentSong = config ? config.songs.find(s => s.id === selectedSongId) : null;



  return (
    <div className="app-container">
      <SystemMonitor socket={socket} />

      {/* HEADER: FIXED TOP */}
      <header style={{
          position:'fixed', top:0, left:0, right:0, height:'120px', zIndex:1000,
          background:'#000', borderBottom:'1px solid #444',
          display:'flex', alignItems:'center', justifyContent:'space-between', padding:'0 20px',
          boxShadow:'0 4px 10px rgba(0,0,0,0.5)'
      }}>
            {/* LEFT: Logo & Status */}
            <div style={{display:'flex', gap:'20px', alignItems:'center'}}>
                <div style={{display:'flex', flexDirection:'column', gap:'5px', minWidth:'140px'}}>
                    <div style={{display:'flex', alignItems:'center', gap:'15px'}}>
                        <h1 style={{margin:0, color:'#ff0055', fontSize:'1.5em', textShadow:'0 0 10px rgba(255,0,85,0.5)'}}>ESB Console <span style={{fontSize:'0.4em', color:'#666', verticalAlign:'middle', border:'1px solid #444', borderRadius:'4px', padding:'2px 4px'}}>v2.02</span></h1>
                        <button onClick={() => setShowDMX(!showDMX)} style={{
                            background: showDMX ? '#0f0' : '#222', color: showDMX ? '#000' : '#888',
                            border: '1px solid #444', padding: '4px 10px', borderRadius: '4px', cursor: 'pointer',
                            fontWeight: 'bold', fontSize:'0.8em'
                        }}>STAGE</button>
                    </div>
                    <div style={{display:'flex', gap:'8px', fontSize:'0.7em'}}>
                        <span style={{color: status.x32 ? '#0f0' : '#555'}}>● X32 ({config ? config.x32_ip : ''})</span>
                        <span style={{color: status.midi ? '#0f0' : '#555'}}>● MIDI</span>
                        <span style={{color: status.dmx ? '#0f0' : '#555'}}>● DMX</span>
                    </div>
                </div>
            </div>

            {/* CENTER: RTA VISUALIZER */}
            <div style={{flex:1, height:'100%', display:'flex', alignItems:'center', justifyContent:'center', overflow:'hidden'}}>
                 <RTAVisualizer socket={socket} />
            </div>

            {/* RIGHT: Master Controls & Scenes */}
             <div style={{display:'flex', gap:'15px', alignItems:'center', height:'100%'}}>
                 
                 {/* MIDI DISPLAY */}
                 <div style={{
                    display:'flex', flexDirection:'column', justifyContent:'center', alignItems:'center',
                    background:'#111', padding:'2px 5px', borderRadius:'4px', border:'1px solid #333',
                    height:'80px', width:'60px'
                 }}>
                    <div style={{fontSize:'0.5em', color:'#888', fontWeight:'bold', marginBottom:'2px'}}>MIDI</div>
                    {midiMsg ? (
                        <>
                            <div style={{fontSize:'1.2em', color:'#0f0', fontWeight:'bold'}}>{midiMsg.note}</div>
                            <div style={{fontSize:'0.6em', color:'#0a0'}}>{midiMsg.velocity}</div>
                        </>
                    ) : (
                        <div style={{fontSize:'1em', color:'#333'}}>--</div>
                    )}
                </div>

                 {/* SCENES */}
                 <div style={{
                     display:'flex', flexDirection:'column', gap:'2px', 
                     background:'#111', padding:'5px', borderRadius:'4px', border:'1px solid #333', height:'80px', width:'160px'
                 }}>
                     <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'2px'}}>
                         <span style={{fontSize:'0.6em', color:'#888', fontWeight:'bold'}}>SCENES</span>
                         <div style={{display:'flex', gap:'2px'}}>
                             <button onClick={handleCapture} style={{padding:'2px 4px', fontSize:'0.6em', background:'#ffaa00', color:'black', border:'none', borderRadius:'2px'}}>CAM</button>
                             <button onClick={handleSaveScene} style={{padding:'2px 4px', fontSize:'0.6em', background:'#444', color:'white', border:'none', borderRadius:'2px'}}>NEW</button>
                         </div>
                     </div>
                     <div style={{display:'flex', flexDirection:'column', gap:'1px', overflowY:'auto', flex:1}}>
                          {Array.isArray(scenes) && scenes.map(s => (
                              <div key={s} style={{display:'flex', justifyContent:'space-between', background:'#111', padding:'1px 2px', fontSize:'0.7em', alignItems:'center'}}>
                                  <span style={{cursor:'pointer', flex:1, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'}} onClick={() => handleLoadScene(s)}>{s}</span>
                                  <button onClick={() => handleOverwriteScene(s)} style={{fontSize:'0.6em', background:'#353', color:'#afa', border:'none', marginRight:'1px'}}>💾</button>
                                  <button onClick={() => handleDeleteScene(s)} style={{fontSize:'0.6em', background:'#533', color:'#faa', border:'none'}}>✕</button>
                              </div>
                          ))}
                     </div>
                 </div>

                 {/* MASTER CONTROL STRIP (Compact) */}
                 <div style={{transform: 'scale(0.9)', transformOrigin: 'right center'}}>
                    <MasterStrip 
                        state={x32State.master || {level:0, mute:false}} 
                        onOpen={(type, title) => setOverlay({channelId:'master', type, title})}
                    />
                 </div>
             </div>
      </header>
      


      {/* OVERLAY: CHANNEL CONTROL */}
      {overlay && (
          <div style={{
              position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
              background: 'rgba(0,0,0,0.8)', zIndex: 1000,
              display: 'flex', justifyContent: 'center', alignItems: 'center'
          }} onClick={() => setOverlay(null)}>
              <div style={{
                  width: '400px', height: 'auto', maxHeight: '90vh', background: '#222', 
                  borderRadius: '16px', padding: '24px', border: '1px solid #444',
                  boxShadow: '0 10px 40px rgba(0,0,0,0.8)', overflowY: 'auto'
              }} onClick={e => e.stopPropagation()}>
                  <h2>CH {overlay.channelId} | {x32State[overlay.channelId]?.name || 'Unknown'} - {overlay.title}</h2>
                  
                  <div style={{display:'flex', justifyContent:'center', marginTop:'30px', gap:'40px'}}>
                      
                      {/* GAIN / PREAMP CONTROLS */}
                      {(overlay.type === 'hpf' || overlay.type === 'gain') && (
                          <>
                            <div style={{display:'flex', flexDirection:'column', alignItems:'center', justifyContent: 'center'}}>
                                <button 
                                    onClick={() => handleToggleParam(overlay.channelId, 'phantom', x32State[overlay.channelId]?.phantom || false)}
                                    style={{
                                        width:'60px', height:'60px', borderRadius:'50%', 
                                        background: x32State[overlay.channelId]?.phantom ? '#f00' : '#444',
                                        color: 'white', fontWeight:'bold', border:'2px solid #222',
                                        boxShadow: x32State[overlay.channelId]?.phantom ? '0 0 15px #f00' : 'none'
                                    }}
                                >
                                    48V
                                </button>
                            </div>
                            
                            <div style={{display:'flex', flexDirection:'column', alignItems:'center'}}>
                                <label style={{marginBottom:'10px'}}>GAIN</label>
                                <div style={{display:'flex', gap:'10px', height:'150px', alignItems:'center'}}>
                                    <input 
                                        type="range" min="0" max="1" step="0.005"
                                        value={x32State[overlay.channelId]?.preampGain || 0}
                                        onChange={(e) => {
                                            if(!overlay.channelId) return;
                                            const val = parseFloat(e.target.value);
                                            setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], preampGain: val}}));
                                            axios.post('/api/set-param', { channelId: overlay.channelId, type: 'preampGain', value: val });
                                        }}
                                        style={{
                                            writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                            width: '20px', height: '100%', accentColor: '#ffaa00'
                                        }}
                                    />
                                    <div style={{display:'flex', flexDirection:'column', justifyContent:'center'}}>
                                        <input 
                                            type="number"
                                            value={(() => {
                                                const val = x32State[overlay.channelId]?.preampGain || 0;
                                                return ((val * 72) - 12).toFixed(1);
                                            })()}
                                            onChange={(e) => {
                                                if(!overlay.channelId) return;
                                                let db = parseFloat(e.target.value);
                                                if (isNaN(db)) return;
                                                if (db < -12) db = -12;
                                                if (db > 60) db = 60;
                                                const val = (db + 12) / 72;
                                                setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], preampGain: val}}));
                                                axios.post('/api/set-param', { channelId: overlay.channelId, type: 'preampGain', value: val });
                                            }}
                                            style={{
                                                width: '70px', background: '#333', border: '1px solid #555',
                                                color: 'white', padding: '5px', borderRadius: '4px',
                                                textAlign: 'center', marginBottom: '5px'
                                            }}
                                        />
                                        <span style={{fontSize:'0.8em', color:'#888', textAlign:'center'}}>dB</span>
                                    </div>
                                </div>
                            </div>
                          </>
                      )}

                      {/* GATE CONTROLS */}
                      {overlay.type === 'gate' && (
                          <div style={{display:'flex', gap: '30px', alignItems:'center', height: '200px'}}>
                             {/* THRESHOLD */}
                            <div style={{display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center'}}>
                                <label style={{marginBottom:'10px', fontSize:'0.9em'}}>THRESHOLD</label>
                                <input 
                                    type="range" min="0" max="1" step="0.005"
                                    value={x32State[overlay.channelId]?.gateThr || 0}
                                    onChange={(e) => {
                                        const val = parseFloat(e.target.value);
                                        setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], gateThr: val}}));
                                        axios.post('/api/set-param', { channelId: overlay.channelId, type: 'gateThr', value: val });
                                    }}
                                    style={{
                                        writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                        width: '20px', height: '150px', accentColor: '#ff0055'
                                    }}
                                />
                                <div style={{marginTop:'5px', fontSize:'0.8em', color:'#aaa'}}>
                                   {/* Approx dB: -80 to 0 */}
                                   {((x32State[overlay.channelId]?.gateThr || 0) * 80 - 80).toFixed(0)} dB
                                </div>
                            </div>
                            
                            {/* ENVELOPE CONTROLS */}
                             <div style={{display:'flex', flexDirection:'column', gap:'20px', justifyContent:'center'}}>
                                {['Attack', 'Hold', 'Release'].map(param => {
                                    const key = `gate${param}`;
                                    const val = x32State[overlay.channelId]?.[key] || 0;
                                    return (
                                        <div key={param} style={{display:'flex', alignItems:'center', justifyContent:'space-between', width:'180px'}}>
                                            <label style={{fontSize:'0.8em', width:'60px'}}>{param.toUpperCase()}</label>
                                            <input 
                                                type="range" min="0" max="1" step="0.01"
                                                value={val}
                                                onChange={(e) => {
                                                    const newVal = parseFloat(e.target.value);
                                                    setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], [key]: newVal}}));
                                                    axios.post('/api/set-param', { channelId: overlay.channelId, type: key, value: newVal });
                                                }}
                                                style={{flex:1, accentColor: '#aaa'}}
                                            />
                                        </div>
                                    )
                                })}
                             </div>
                          </div>
                      )}

                      {/* COMPRESSOR CONTROLS */}
                      {overlay.type === 'dyn' && (
                          <div style={{display:'flex', gap: '40px', alignItems:'center', height: '200px'}}>
                             {/* INPUT: THRESHOLD */}
                            <div style={{display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center'}}>
                                <label style={{marginBottom:'10px', fontSize:'0.9em', color:'#4fecff'}}>THRESHOLD</label>
                                <input 
                                    type="range" min="0" max="1" step="0.005"
                                    value={x32State[overlay.channelId]?.dynThr || 0}
                                    onChange={(e) => {
                                        const val = parseFloat(e.target.value);
                                        setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], dynThr: val}}));
                                        axios.post('/api/set-param', { channelId: overlay.channelId, type: 'dynThr', value: val });
                                    }}
                                    style={{
                                        writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                        width: '20px', height: '150px', accentColor: '#4fecff'
                                    }}
                                />
                                <div style={{marginTop:'5px', fontSize:'0.8em', color:'#aaa'}}>
                                   {/* Approx dB: 0 to -60 ish */}
                                   {((x32State[overlay.channelId]?.dynThr || 0) * 60 - 60).toFixed(0)} dB
                                </div>
                            </div>
                            
                            {/* CENTER: RATIO & ENVELOPE */}
                             <div style={{display:'flex', flexDirection:'column', gap:'20px', justifyContent:'center'}}>
                                {['Ratio', 'Attack', 'Hold', 'Release'].map(param => {
                                    const key = `dyn${param}`;
                                    const val = x32State[overlay.channelId]?.[key] || 0;
                                    let displayVal = val.toFixed(2);
                                    if(param === 'Ratio') displayVal = `1:${(val * 100).toFixed(0)}`; // Rough calc
                                    
                                    return (
                                        <div key={param} style={{display:'flex', alignItems:'center', justifyContent:'space-between', width:'180px'}}>
                                            <label style={{fontSize:'0.8em', width:'60px'}}>{param.toUpperCase()}</label>
                                            <input 
                                                type="range" min="0" max="1" step="0.01"
                                                value={val}
                                                onChange={(e) => {
                                                    const newVal = parseFloat(e.target.value);
                                                    setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], [key]: newVal}}));
                                                    axios.post('/api/set-param', { channelId: overlay.channelId, type: key, value: newVal });
                                                }}
                                                style={{flex:1, accentColor: '#4fecff'}}
                                            />
                                        </div>
                                    )
                                })}
                             </div>

                             {/* OUTPUT: MAKEUP GAIN */}
                            <div style={{display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center'}}>
                                <label style={{marginBottom:'10px', fontSize:'0.9em', color:'#4fecff'}}>MAKEUP</label>
                                <input 
                                    type="range" min="0" max="1" step="0.005"
                                    value={x32State[overlay.channelId]?.dynGain || 0}
                                    onChange={(e) => {
                                        const val = parseFloat(e.target.value);
                                        setX32State(prev => ({...prev, [overlay.channelId]: {...prev[overlay.channelId], dynGain: val}}));
                                        axios.post('/api/set-param', { channelId: overlay.channelId, type: 'dynGain', value: val });
                                    }}
                                    style={{
                                        writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                        width: '20px', height: '150px', accentColor: '#4fecff'
                                    }}
                                />
                                <div style={{marginTop:'5px', fontSize:'0.8em', color:'#aaa'}}>
                                   {((x32State[overlay.channelId]?.dynGain || 0) * 24).toFixed(1)} dB
                                </div>
                            </div>
                          </div>
                      )}

                      {/* EQ CONTROLS */}
                      {overlay.type === 'eq' && (() => {
                          const w = 350;
                          const h = 150; 
                          const isMaster = overlay.channelId === 'master';
                          const channelState = isMaster ? (x32State.master||{}) : (x32State[overlay.channelId]||{});
                          const bands = channelState.eqBands || {};
                          const hasHPF = channelState.hpf || false;
                          const hpfFreq = channelState.hpfFreq || 0.0;
                          const hasHCut = channelState.hcut || false;
                          const hcutFreq = channelState.hcutFreq || 1.0;
                          
                          // Log Scale Helpers
                          const minLog = Math.log10(20);
                          const maxLog = Math.log10(20000);
                          const rangeLog = maxLog - minLog;
                          
                          
                          const fToHz = (f) => Math.pow(10, minLog + (f * rangeLog));
                          const hzToF = (hz) => (Math.log10(hz) - minLog) / rangeLog;

                          // HPF Specific (20Hz - 400Hz)
                          const maxHpfLog = Math.log10(400); 
                          const rangeHpfLog = maxHpfLog - minLog;
                          const hpfToHz = (f) => Math.pow(10, minLog + (f * rangeHpfLog));

                          
                          // Mapping
                          const fToX = (f) => f * w; 
                          const xToF = (x) => Math.max(0, Math.min(1, x / w));
                          const gToY = (g) => h - (g * h); // 0.5 => h/2
                          const yToG = (y) => Math.max(0, Math.min(1, (h - y) / h));

                          // Drag Handler
                          const handleMouseDown = (e, bandIdx) => {
                             e.preventDefault(); e.stopPropagation();
                             const startX = e.clientX;
                             const startY = e.clientY;
                             const startF = bands[bandIdx]?.f || 0.5;
                             const startG = bands[bandIdx]?.g || 0.5;
                             
                             const onMove = (mv) => {
                                 const dx = mv.clientX - startX;
                                 const dy = mv.clientY - startY;
                                 const newF = Math.max(0, Math.min(1, startF + (dx / w)));
                                 const newG = Math.max(0, Math.min(1, startG - (dy / h)));
                                 
                                 setX32State(prev => {
                                      const id = overlay.channelId;
                                      // Handle Master state structure vs Channel structure
                                      const targetState = id === 'master' ? (prev.master || {}) : (prev[id] || {});
                                      const bandState = targetState.eqBands ? {...targetState.eqBands} : {};
                                      
                                      if(!bandState[bandIdx]) bandState[bandIdx] = {};
                                      bandState[bandIdx].f = newF;
                                      bandState[bandIdx].g = newG;
                                      
                                      if (id === 'master') {
                                          return {...prev, master: {...targetState, eqBands: bandState}};
                                      } else {
                                          return {...prev, [id]: {...targetState, eqBands: bandState}};
                                      }
                                 });
                             };
                             const onUp = () => {
                                 window.removeEventListener('mousemove', onMove);
                                 window.removeEventListener('mouseup', onUp);
                                 
                                 // Send Commit
                                 const id = overlay.channelId;
                                 const targetState = id === 'master' ? (x32State.master || {}) : (x32State[id] || {});
                                 const b = targetState.eqBands ? targetState.eqBands[bandIdx] : null;
                                 
                                 if (b) {
                                    if (id === 'master') {
                                        // Main EQ OSC
                                        axios.post('/api/osc', { address: `/main/st/eq/${bandIdx}/f`, args: [b.f] });
                                        axios.post('/api/osc', { address: `/main/st/eq/${bandIdx}/g`, args: [b.g] });
                                    } else {
                                        // Channel EQ OSC
                                        axios.post('/api/set-param', { channelId: id, type: 'eqParam', band: bandIdx, param: 'f', value: b.f });
                                        axios.post('/api/set-param', { channelId: id, type: 'eqParam', band: bandIdx, param: 'g', value: b.g });
                                    }
                                 }
                             };
                             window.addEventListener('mousemove', onMove);
                             window.addEventListener('mouseup', onUp);
                          };
                          
                          return (
                          <div style={{display:'flex', flexDirection:'column', alignItems:'center', width:'100%'}}>
                             {/* EQ GRAPH */}
                             <div style={{
                                 width: w+'px', height: h+'px', background:'#111', 
                                 border:'1px solid #444', borderRadius:'8px', marginBottom:'20px',
                                 position:'relative', overflow:'hidden', cursor:'crosshair'
                             }}>
                                 <svg width={w} height={h} style={{display:'block'}}>
                                     {/* Log Grid Lines */}
                                     {[100, 1000, 10000].map(hz => {
                                         const x = hzToF(hz) * w;
                                         return (
                                             <g key={hz}>
                                                 <line x1={x} y1={0} x2={x} y2={h} stroke="#333" />
                                                 <text x={x+3} y={h-5} fill="#555" fontSize="10">{hz>=1000? (hz/1000)+'k':hz}</text>
                                             </g>
                                         );
                                     })}
                                     <line x1={0} y1={h/2} x2={w} y2={h/2} stroke="#333" strokeDasharray="4 4" />
                                     
                                     {/* Combined Response Curve */}
                                     {(() => {
                                         // Sample every 2px
                                         let d = `M 0 ${h/2}`;
                                         for(let x=0; x<=w; x+=2) { 
                                             const fNorm = x / w; // 0-1
                                             const currentHz = fToHz(fNorm);
                                             
                                             let totalResponseDB = 0;
                                             
                                             // 1. HPF (Butterworth HP)
                                             if (hasHPF) {
                                                 const cutoffHz = hpfToHz(hpfFreq);
                                                 // MagSq = 1 / (1 + (fc/f)^2n ) for HP. Order 2 (12dB/oct) -> n=2
                                                 // Attenuation dB = 10 log10(MagSq)
                                                 // Avoid div by 0
                                                 const f = Math.max(0.1, currentHz);
                                                 const ratio = cutoffHz / f;
                                                 const attenuation = 10 * Math.log10(1 / (1 + Math.pow(ratio, 4)));
                                                 totalResponseDB += attenuation;
                                             }
                                             
                                             // 2. High Cut (Butterworth LP)
                                             if (hasHCut) {
                                                 const cutoffHz = fToHz(hcutFreq);
                                                 const f = Math.max(0.1, currentHz);
                                                 const ratio = f / cutoffHz;
                                                 const attenuation = 10 * Math.log10(1 / (1 + Math.pow(ratio, 4)));
                                                 totalResponseDB += attenuation;
                                             }
                                             
                                             // 3. Bands
                                             [1, 2, 3, 4].forEach(i => {
                                                 const b = bands[i] || { f: i*0.2, g: 0.5, q: 0.5, type: 0 };
                                                 const bf = b.f || 0;
                                                 const bg = b.g || 0.5; 
                                                 const gainDB = (bg - 0.5) * 30; // +/- 15
                                                 const bq = b.q || 0.5;
                                                 const centerHz = fToHz(bf);
                                                 
                                                 const type = b.type || 0;
                                                 const f = currentHz;
                                                 
                                                 if (Math.abs(gainDB) < 0.1) return;

                                                 if (type === 1) { // Low Shelf
                                                      // Approximate Shelf
                                                      // Low Boost/Cut below freq. 
                                                      // Sigmoid transition near CenterHz
                                                      const slope = 1 + (bq*2); // Q affects slope
                                                      const ratio = f / centerHz;
                                                      // Transition from 1 to 0 as f goes 0->Inf
                                                      const weight = 1 / (1 + Math.pow(ratio, slope*4));
                                                      totalResponseDB += (gainDB * weight);

                                                 } else if (type === 2) { // Hi Shelf
                                                      const slope = 1 + (bq*2);
                                                      const ratio = centerHz / f;
                                                      const weight = 1 / (1 + Math.pow(ratio, slope*4));
                                                      totalResponseDB += (gainDB * weight);
                                                 } else {
                                                     // PEQ (Bell)
                                                     // RBJ Audio EQ Cookbook approx magnitude
                                                     // Simpler gaussian visualization for speed
                                                     // Width in octaves related to Q
                                                     // Q = fc / BW. BW = fc/Q.
                                                     // In log domain, dist = log(f) - log(fc).
                                                     // Sigma ~ 1/Q approx for visual
                                                     const logF = Math.log2(f);
                                                     const logC = Math.log2(centerHz);
                                                     const dist = logF - logC;
                                                     const width = 0.5 / (bq + 0.1); 
                                                     
                                                     const resp = gainDB * Math.exp(-(dist*dist)/(2*width*width));
                                                     totalResponseDB += resp;
                                                 }
                                             });
                                             
                                             // Limit Y
                                             const y = Math.max(0, Math.min(h, (h/2) - (totalResponseDB / 30 * h)));
                                             d += ` L ${x} ${y}`;
                                         }
                                         return <path d={d} stroke="#0f0" fill="none" strokeWidth="2" strokeLinejoin="round" />;
                                     })()}
                                     
                                     {/* Drag Handles */}
                                     {[1,2,3,4].map(i => {
                                        const b = bands[i] || { f: i*0.2, g: 0.5 };
                                        const x = fToX(b.f || 0);
                                        const y = gToY(b.g || 0.5);
                                        const colors = ['#f00', '#ffaa00', '#ffff00', '#0f0'];
                                        return (
                                            <g key={i} onMouseDown={(e) => handleMouseDown(e, i)} style={{cursor:'grab'}}>
                                                <circle cx={x} cy={y} r="8" fill={colors[i-1]} stroke="white" strokeWidth="2" />
                                                <text x={x} y={y-15} fill={colors[i-1]} fontSize="10" textAnchor="middle" style={{fontWeight:'bold', pointerEvents:'none'}}>{i}</text>
                                            </g>
                                        );
                                     })}
                                 </svg>
                             </div>
                             
                             {/* CONTROLS STRIP */}
                             <div style={{display:'flex', gap:'5px', flexWrap:'nowrap', overflowX:'auto'}}>
                                 
                                 {/* LOW CUT (Input Channels Only) */}
                                 {!isMaster && (
                                     <div style={{display:'flex', flexDirection:'column', alignItems:'center', border:'1px solid #555', padding:'4px', borderRadius:'4px', background:'#222', minWidth:'60px'}}>
                                         <div style={{fontSize:'0.7em', color:'#aaa', marginBottom:'5px'}}>LO CUT</div>
                                         <button 
                                            onClick={() => {
                                                const newVal = !hasHPF;
                                                axios.post('/api/set-param', { channelId: overlay.channelId, type: 'hpf', value: newVal });
                                                setX32State(p=>({...p, [overlay.channelId]: {...p[overlay.channelId], hpf: newVal}}));
                                            }}
                                            style={{background: hasHPF?'red':'#444', border:'none', color:'white', fontSize:'0.7em', padding:'2px 5px', marginBottom:'5px', cursor:'pointer'}}
                                         >
                                            {hasHPF?'ON':'OFF'}
                                         </button>
                                         <input 
                                             type="range" min="0" max="1" step="0.005"
                                             value={hpfFreq}
                                             onChange={(e)=> {
                                                const v = parseFloat(e.target.value);
                                                axios.post('/api/set-param', { channelId: overlay.channelId, type: 'hpfFreq', value: v });
                                                setX32State(p=>({...p, [overlay.channelId]: {...p[overlay.channelId], hpfFreq: v}}));
                                             }}
                                             style={{width:'50px', accentColor:'#ffff00'}}
                                         />
                                         <div style={{fontSize:'0.6em', color:'#888'}}>{Math.round(hpfToHz(hpfFreq))} Hz</div>
                                     </div>
                                 )}
                                 
                                 {/* HIGH CUT */}
                                 <div style={{display:'flex', flexDirection:'column', alignItems:'center', border:'1px solid #555', padding:'4px', borderRadius:'4px', background:'#222', minWidth:'60px'}}>
                                     <div style={{fontSize:'0.7em', color:'#aaa', marginBottom:'5px'}}>HI CUT</div>
                                     <button 
                                        onClick={() => {
                                            const newVal = !hasHCut;
                                            axios.post('/api/set-param', { channelId: overlay.channelId, type: 'hcut', value: newVal });
                                            setX32State(p=>({...p, [overlay.channelId]: {...p[overlay.channelId], hcut: newVal}}));
                                        }}
                                        style={{background: hasHCut?'red':'#444', border:'none', color:'white', fontSize:'0.7em', padding:'2px 5px', marginBottom:'5px', cursor:'pointer'}}
                                     >
                                        {hasHCut?'ON':'OFF'}
                                     </button>
                                     <input 
                                       type="range" min="0" max="1" step="0.005"
                                       value={hcutFreq}
                                       title={`HCut Freq: ${Math.round(fToHz(hcutFreq))} Hz`}
                                       onChange={(e) => {
                                           const val = parseFloat(e.target.value);
                                           axios.post('/api/set-param', { channelId: overlay.channelId, type: 'hcutFreq', value: val });
                                           setX32State(p=>({...p, [overlay.channelId]: {...p[overlay.channelId], hcutFreq: val}}));
                                       }}
                                       style={{width:'50px', accentColor:'#ffff00'}} 
                                     />
                                     <div style={{fontSize:'0.6em', color:'#888', marginTop:'2px'}}>{Math.round(fToHz(hcutFreq))} Hz</div>
                                 </div>
                             </div>
                          </div>
                      )})()}

                      {/* SINGLE SEND OVERLAY (DLY/VRB) - Moved Inline */}
                      {/* Using IIFE-like structure or just conditional since we are inside render */}
                      { (overlay.type === 'dly' || overlay.type === 'vrb') && (() => {
                          const isDly = overlay.type === 'dly';
                          const bus = isDly ? '13' : '14';
                          const label = isDly ? 'DELAY SEND' : 'REVERB SEND';
                          const color = isDly ? '#0ff' : '#f0f';
                          const val = x32State[overlay.channelId]?.mixSends?.[bus]?.level || 0;
                          return (
                              <div style={{display:'flex', flexDirection:'column', alignItems:'center', height:'100%', width:'100%'}}>
                                  <h2 style={{color:color, marginTop:0}}>{label}</h2>
                                  <div style={{flex:1, display:'flex', justifyContent:'center', width:'100%', margin:'20px 0'}}>
                                      <input 
                                          type="range" min="0" max="1" step="0.005"
                                          value={val}
                                          onChange={(e) => {
                                              const v = parseFloat(e.target.value);
                                              axios.post('/api/osc', { address: `/ch/${overlay.channelId}/mix/${bus}/level`, args: [v] });
                                              setX32State(prev => {
                                                  const chSt = prev[overlay.channelId] ? {...prev[overlay.channelId]} : {};
                                                  if(!chSt.mixSends) chSt.mixSends = {};
                                                  if(!chSt.mixSends[bus]) chSt.mixSends[bus] = {};
                                                  // This input specifically controls 'level'
                                                  chSt.mixSends[bus].level = v;
                                                  console.log(`Updated MixSend CH${overlay.channelId} Bus${bus} level = ${v}`);
                                                  return {...prev, [overlay.channelId]: chSt};
                                              });
                                          }}
                                          style={{
                                              writingMode: 'bt-lr', WebkitAppearance: 'slider-vertical',
                                              width: '50px', height: '200px', accentColor: color
                                          }}
                                      />
                                  </div>
                                  <div style={{fontSize:'2em', color:'white', fontWeight:'bold'}}>
                                      {Math.round(val * 100)}%
                                  </div>
                              </div>
                          );
                      })()}


                      {/* Fallback for others */}
                      {overlay.type !== 'hpf' && overlay.type !== 'gate' && overlay.type !== 'dyn' && overlay.type !== 'eq' && overlay.type !== 'SENDS' && overlay.type !== 'gain' && overlay.type !== 'dly' && overlay.type !== 'vrb' && (
                          <div style={{textAlign:'center'}}>
                              <div style={{fontSize:'3em'}}>🎛️</div>
                              <div style={{marginTop:'10px'}}>Controls Coming Soon</div>
                          </div>
                      )}
                  </div>
                  
                  <button style={{marginTop:'40px', width:'100%'}} onClick={() => setOverlay(null)}>CLOSE</button>
              </div>
          </div>
      )}



            {/* 2. SUB-HEADER: PERFORMANCE CONTROLS */}
            <div style={{
                position:'fixed', top:'120px', left:0, right:0, height:'60px', zIndex:990,
                background:'#151515', borderBottom:'1px solid #333',
                display:'flex', alignItems:'center', padding:'0 20px', gap:'40px',
                boxShadow:'0 2px 5px rgba(0,0,0,0.3)'
            }}>
                <div style={{display:'flex', alignItems:'center', gap:'15px'}}>
                     <span style={{color:'#666', fontSize:'0.8em', textTransform:'uppercase', letterSpacing:'1px', fontWeight:'bold'}}>SETLIST:</span>
                     <select 
                        value={selectedSongId || ''} 
                        onChange={(e) => setSelectedSongId(parseInt(e.target.value))}
                        style={{
                            background:'#333', color:'white', border:'1px solid #555', 
                            padding:'5px 10px', borderRadius:'4px', fontSize:'1em', minWidth:'250px'
                        }}
                    >
                        {config && config.songs.map(song => (
                            <option key={song.id} value={song.id}>{song.title}</option>
                        ))}
                    </select>
                </div>

                <div style={{display:'flex', gap:'10px', alignItems:'center'}}>
                    <span style={{color:'#666', fontSize:'0.8em', textTransform:'uppercase', letterSpacing:'1px', fontWeight:'bold', borderLeft:'1px solid #444', paddingLeft:'20px'}}>CUES:</span>
                    {currentSong && currentSong.parts.map(part => (
                        <button 
                            key={part.name} 
                            onClick={()=>handleTrigger(part.name)} 
                            style={{
                                background: activePart===part.name ? '#00bbff' : '#222',
                                color: activePart===part.name ? 'black' : '#ccc',
                                border: '1px solid #444', borderRadius:'4px',
                                padding: '8px 20px', fontWeight:'bold', cursor:'pointer',
                                textTransform: 'uppercase', fontSize:'0.8em',
                                transition: 'all 0.1s',
                                boxShadow: activePart===part.name ? '0 0 10px rgba(0,255,255,0.5)' : 'none'
                            }}>
                            {part.name}
                        </button>

                    ))}
                </div>
            </div>

            {/* GROUPS / MUTES (Moved Above) */}
            <section className="glass-panel" style={{ opacity: 0.9, marginTop: '190px', marginBottom: '20px', padding: '0 20px' }}>
                <div style={{}}>
                    <h3 style={{marginTop:0, color:'#888', borderBottom:'1px solid #444', paddingBottom:'5px'}}>GROUPS / MUTES</h3>
                    <div style={{display:'flex', flexWrap:'wrap', gap:'8px', justifyContent:'center'}}>
                        {config && config.groups && config.groups.map(c => {
                            // Use explicit IDs for robustness
                            const groupChannels = c.ids && c.ids.length > 0
                                ? config.inputs.filter(ch => c.ids.includes(String(ch.id)))
                                : config.inputs.filter(ch => x32State[ch.id]?.color === c.bg);

                            const isGroupActive = groupChannels.length > 0;
                            const allMuted = isGroupActive && groupChannels.every(ch => x32State[ch.id]?.mute === true);
                            
                            // Check if ANY channel in this group is currently soloed (visual feedback)
                            const isGroupSolo = isGroupActive && groupChannels.some(ch => soloIds.includes(String(ch.id)));
                            
                            // DEBUG: Trace why groups might highlight unexpectedly
                            if (isGroupSolo && c.key !== 'samples' && soloIds.includes('25')) {
                                 console.warn(`⚠️ ANOMALY: Group ${c.key} is SOLOED but Samples (25) is active!`, { groupIds: c.ids, soloIds });
                            }

                            return (
                                <div 
                                   key={c.key}
                                   draggable
                                   onDragStart={(e) => {
                                       e.dataTransfer.setData('colorData', JSON.stringify(c));
                                   }}
                                   style={{
                                       background: c.bg, color: c.txt,
                                       padding: '0', textAlign: 'center', borderRadius: '4px',
                                       fontWeight:'bold', fontSize:'0.9em', cursor: 'pointer',
                                       border: allMuted ? '3px solid red' : '1px solid #555',
                                       boxShadow: allMuted ? '0 0 10px red' : 'none',
                                       opacity: isGroupActive ? 1 : 0.5,
                                       animation: allMuted ? 'pulseRed 1s infinite' : 'none',
                                       transition: 'all 0.2s',
                                       position: 'relative',
                                       width: '80px', height: '80px', display:'flex', alignItems:'center', justifyContent:'center',
                                       overflow: 'hidden'
                                   }}
                                   title={isGroupActive ? 'Click Label to Mute, Click S to Solo' : 'Drag to assign color'}
                                >
                                    {/* MUTE AREA (Main) */}
                                    <div 
                                        onClick={(e) => {
                                            e.stopPropagation(); // prevent drag
                                            if (!isGroupActive) return;
                                            const anyLive = groupChannels.some(ch => !x32State[ch.id]?.mute);
                                            const targetMute = anyLive ? true : false;
                                            
                                            groupChannels.forEach(ch => {
                                                axios.post('/api/toggle-mute', { channelId: ch.id, mute: targetMute });
                                                setX32State(p => ({...p, [ch.id]: {...p[ch.id], mute: targetMute}}));
                                            });
                                        }}
                                        style={{width:'100%', height:'100%', display:'flex', alignItems:'center', justifyContent:'center'}}
                                    >
                                        {c.label}
                                        {allMuted && <div style={{marginTop:'5px', width:'10px', height:'10px', background:'red', borderRadius:'50%', boxShadow:'0 0 5px red'}}></div>}
                                    </div>

                                    {/* SOLO BUTTON (Inner Area) */}
                                    {isGroupActive && (
                                        <button 
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Call API with specific Channel IDs + Group Name
                                                const ids = groupChannels.map(ch => ch.id);
                                                axios.post('/api/solo', { channelIds: ids, groupName: c.key });
                                            }}
                                            style={{
                                                position: 'absolute', top: 0, right: 0,
                                                width: '24px', height: '24px',
                                                background: isGroupSolo ? '#ffff00' : 'rgba(0,0,0,0.3)',
                                                color: isGroupSolo ? 'black' : 'white',
                                                border: 'none',
                                                borderRadius: '0 0 0 6px',
                                                fontSize: '0.7em', fontWeight: 'bold',
                                                cursor: 'pointer',
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                zIndex: 100
                                            }}
                                            title="Solo Group (+5dB others -2dB)"
                                        >
                                            S
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* 3. MAIN CHANNEL STRIPS */}
            <div style={{marginTop:'0px', padding:'0 20px 150px 20px', display:'flex', flexDirection:'row', alignItems:'flex-start', gap:'20px'}}>
                
                {/* INPUTS ROW */}
                <div style={{marginBottom:'10px', flex:1, minWidth:0}}>
                    <h3 style={{color:'#666', borderBottom:'1px solid #333', paddingBottom:'5px', fontStyle:'italic'}}>INPUT CHANNELS</h3>
                    <div style={{ display: 'flex', flexWrap: 'nowrap', gap: '8px', overflowX: 'auto', paddingBottom: '15px', alignItems: 'flex-start' }}>
            {config && config.inputs.map(ch => {
                const state = x32State[ch.id] || { level: 0, mute: true, gate: false, dyn: false, eq: false, hpf: false };
                
                // Determine Header Style via PERSISTED STATE
                const headerStyle = state.color ? {
                    background: state.color,
                    color: state.labelColor,
                    borderBottom: '1px solid currentColor'
                } : {
                    background: 'transparent',
                    color: '#ccc'
                };

                return (
                <div 
                    key={ch.id} 
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        console.log("Dropped color on CH" + ch.id);
                        try {
                            const json = e.dataTransfer.getData('colorData');
                            if (!json) return;
                            const data = JSON.parse(json);
                            
                            // Optimistic Update
                            setX32State(prev => {
                                const chan = prev[ch.id] || {};
                                return {
                                    ...prev,
                                    [ch.id]: { 
                                        ...chan, 
                                        color: data.bg, 
                                        labelColor: data.txt 
                                    }
                                };
                            });

                            // Send to Backend
                            axios.post('/api/set-param', { channelId: ch.id, type: 'color', value: data.bg, labelColor: data.txt });
                        } catch(err) { console.error("Drop error", err); }
                    }}
                    style={{ 
                        display: 'flex', flexDirection: 'column', alignItems: 'center',
                        padding: '5px', 
                        minWidth: '70px', 
                        flex: '0 0 auto',
                        background: '#111',
                        borderRadius: '8px',
                        border: state.mute ? '2px solid #ff0000' : '2px solid #00ff00', 
                        boxShadow: state.mute ? '0 0 5px #500' : '0 0 5px #050' 
                    }}
                >
                    <div 
                        role="button"
                        onMouseDown={(e) => e.stopPropagation()} 
                        onDoubleClick={(e) => {
                            e.stopPropagation(); 
                            e.preventDefault();
                            const newName = prompt("Rename Channel:", state.name || ch.name);
                            if (newName !== null) {
                                setX32State(prev => ({...prev, [ch.id]: {...prev[ch.id], name: newName}}));
                                axios.post('/api/rename-channel', { channelId: ch.id, name: newName });
                            }
                        }}
                        style={{
                            width: '100%', textAlign: 'center', borderRadius:'4px', padding:'4px 2px',
                            fontWeight: 'bold', fontSize: '0.9em', marginBottom:'5px',
                            cursor: 'pointer', userSelect: 'none',
                            transition: 'all 0.2s',
                            boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.1)',
                            ...headerStyle
                        }}
                        title="Double-click to rename"
                        onMouseEnter={(e) => e.currentTarget.style.filter = 'brightness(1.2)'}
                        onMouseLeave={(e) => e.currentTarget.style.filter = 'none'}
                    >
                        {state.name || ch.name}
                        <div style={{fontWeight:'normal', fontSize:'0.7em', opacity:0.8}}>CH {ch.id}</div>
                    </div>
                    <div style={{height: '150px', width: '100%', display: 'flex', justifyContent: 'center', marginBottom: '10px', position:'relative'}}>
                         {/* Background VU Meter (Curtain Style) */}
                         <div style={{
                             position:'absolute', bottom:0, width:'40px', height: '100%',
                             background: 'linear-gradient(to top, #0f0 0%, #0f0 62%, #fc0 63%, #fc0 75%, #f00 76%, #f00 100%)',
                             opacity: 0.8, 
                             zIndex: 0,
                             pointerEvents: 'none',
                             borderRadius:'4px',
                             overflow: 'hidden'
                         }}>
                             {/* Curtain Mask */}
                             <div style={{
                                 position: 'absolute', top: 0, left: 0, right: 0,
                                 height: `${Math.max(0, 100 - ((inputMeters[parseInt(ch.id)-1] || 0) * 100))}%`,
                                 background: '#242424', 
                                 transition: 'height 50ms linear'
                             }}></div>

                             {/* Grid Lines */}
                             <div style={{
                                 position:'absolute', top:0, left:0, right:0, bottom:0,
                                 backgroundImage: 'linear-gradient(rgba(0,0,0,0.5) 1px, transparent 1px)',
                                 backgroundSize: '100% 4px',
                                 pointerEvents:'none'
                             }}></div>
                         </div>

                        <input 
                            type="range" min="0" max="1" step="0.01"
                            value={state.level || 0}
                            onChange={(e) => {
                                const val = parseFloat(e.target.value);
                                setX32State(prev => ({...prev, [ch.id]: {...prev[ch.id], level: val}}));
                                axios.post('/api/set-fader', { channelId: ch.id, level: val });
                            }}
                            style={{
                                appearance: 'slider-vertical',
                                WebkitAppearance: 'slider-vertical',
                                width: '40px', 
                                height: '100%', 
                                accentColor: '#00ff00',
                                outline: 'none',
                                cursor: 'ns-resize',
                                position: 'relative', 
                                zIndex: 1,
                                background: 'transparent'
                            }}
                        />
                    </div>

                    {/* Solo Button */}
                    <div style={{display:'flex', width:'100%', gap:'2px', marginBottom:'5px'}}>
                         <button 
                            disabled={state.mute}
                            onClick={() => {
                                axios.post('/api/solo', { channelId: ch.id });
                            }}
                            style={{
                                width: '100%', height: '24px', 
                                padding: '2px', fontSize: '0.8em', fontWeight:'bold',
                                background: soloIds.includes(String(ch.id)) ? '#ffff00' : (state.mute ? '#222' : '#444'),
                                color: soloIds.includes(String(ch.id)) ? 'black' : (state.mute ? '#444' : '#ccc'),
                                border: soloIds.includes(String(ch.id)) ? '2px solid #ffff00' : '1px solid #444',
                                borderRadius: '4px', cursor: state.mute ? 'not-allowed' : 'pointer',
                                transition: 'all 0.1s',
                                opacity: state.mute ? 0.5 : 1
                            }}
                         >
                            S
                         </button>
                    </div>

                    {/* Param Buttons Vertical Stack */}
                    <div style={{
                        display: 'flex', flexDirection: 'column', gap: '5px', 
                        width: '100%', marginBottom: '10px'
                    }}>
                        <button 
                            className={`param-btn ${state.hpf ? 'active' : ''}`}
                            onClick={() => handleToggleParam(ch.id, 'hpf', state.hpf)}
                            onDoubleClick={() => setOverlay({channelId:ch.id, type:'gain'})}
                            title="Click: Low Cut | Double: Preamp"
                        >GAIN</button>

                        <button 
                            className={`param-btn ${state.gate ? 'active' : ''}`}
                            onClick={() => handleToggleParam(ch.id, 'gate', state.gate)}
                            onDoubleClick={() => setOverlay({channelId:ch.id, type:'gate'})}
                        >GATE</button>
                        
                        <button 
                            className={`param-btn ${state.dyn ? 'active' : ''}`}
                            onClick={() => handleToggleParam(ch.id, 'dyn', state.dyn)}
                            onDoubleClick={() => setOverlay({channelId:ch.id, type:'dyn'})}
                        >DYN</button>
                        
                        <button 
                            className={`param-btn ${state.eq ? 'active' : ''}`} 
                            onClick={() => handleToggleParam(ch.id, 'eq', state.eq)}
                            onDoubleClick={() => openOverlay(ch.id, 'eq', 'EQUALIZER')}
                        >EQ</button>

                        <button 
                            className={`param-btn ${state.mixSends?.['13']?.on ? 'active' : ''}`}
                            onClick={() => {
                                // Optimistic Update
                                setX32State(prev => {
                                    const next = {...prev};
                                    if(!next[ch.id]) next[ch.id] = {};
                                    if(!next[ch.id].mixSends) next[ch.id].mixSends = {};
                                    if(!next[ch.id].mixSends['13']) next[ch.id].mixSends['13'] = {};
                                    // Toggle logic needs to be safe. If currently undefined, assume false -> become true.
                                    next[ch.id].mixSends['13'].on = !state.mixSends?.['13']?.on;
                                    return next;
                                });
                                // Network Request
                                const current = state.mixSends?.['13']?.on;
                                axios.post('/api/osc', { address: `/ch/${ch.id}/mix/13/on`, args: [current ? 0 : 1] });
                            }}
                            onDoubleClick={() => setOverlay({channelId:ch.id, type:'dly'})}
                        >DLY</button>
                        
                        <button 
                             className={`param-btn ${state.mixSends?.['14']?.on ? 'active' : ''}`}
                            onClick={() => {
                                // Optimistic Update
                                setX32State(prev => {
                                    const next = {...prev};
                                    if(!next[ch.id]) next[ch.id] = {};
                                    if(!next[ch.id].mixSends) next[ch.id].mixSends = {};
                                    if(!next[ch.id].mixSends['14']) next[ch.id].mixSends['14'] = {};
                                    next[ch.id].mixSends['14'].on = !state.mixSends?.['14']?.on;
                                    return next;
                                });
                                // Network Request
                                const current = state.mixSends?.['14']?.on;
                                axios.post('/api/osc', { address: `/ch/${ch.id}/mix/14/on`, args: [current ? 0 : 1] });
                            }}
                            onDoubleClick={() => setOverlay({channelId:ch.id, type:'vrb'})}
                        >VRB</button>

                        {/* SENDS OVERLAY */}
                        <button 
                             className="param-btn"
                             style={{ background:'#4466aa', color:'white', marginTop:'2px' }} 
                             onClick={() => setOverlay({channelId:ch.id, type:'SENDS'})}
                        >SENDS</button>
                    </div>

                    {/* Mute Button */}
                    <button 
                        onClick={() => handleToggleParam(ch.id, 'mute', state.mute)}
                        style={{
                            width: '40px', height: '40px', padding: 0,
                            borderRadius: '50%',
                            background: state.mute ? '#ff0000' : '#222',
                            border: state.mute ? 'none' : '2px solid #444',
                            color: 'white', fontWeight: 'bold',
                            boxShadow: state.mute ? '0 0 10px #f00' : 'none'
                        }}
                    >
                        {state.mute ? 'M' : ''}
                    </button>
                </div>
            )})}
        </div>
                </div>

            </div>








      
      {/* GLOBAL OVERLAYS */}
      {overlay && overlay.type === 'EQ' && (
         <EQOverlay channelId={overlay.id} state={x32State[overlay.id] || {}} onClose={() => setOverlay(null)} />
      )}
      {overlay && overlay.type === 'SENDS' && (
         <SendsOverlay channelId={overlay.id} state={x32State[overlay.id] || {}} onClose={() => setOverlay(null)} />
      )}

      

      <SystemMonitor socket={socket} />
      
      <div className="status-bar">
         <div style={{flex:1}}>
              <button 
                  onClick={() => setShowVisualizer(true)}
                  style={{
                      background: '#444', color: '#fff', border: '1px solid #666',
                      padding: '2px 10px', borderRadius: '4px', cursor: 'pointer',
                      fontSize: '0.8em', marginRight: '10px'
                  }}
              >
                  OPEN VISUALIZER 💡
              </button>
         </div>
         <div>X32: <span style={{color: '#4fecff'}}>CONNECTED</span></div>
         <div>ABLETON: <span style={{color: '#ffaa00'}}>WAITING</span></div>
         <div>DMX: <span style={{color: '#0f0'}}>READY</span></div>
      </div>
      
      {showVisualizer && <DMXVisualizer socket={socket} onClose={() => setShowVisualizer(false)} />}
    </div>
  );
}

export default App;
