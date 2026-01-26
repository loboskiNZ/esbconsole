import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { ChevronDown, ChevronRight, ChevronLeft } from 'lucide-react';

export default function SetlistManager({ onClose, onUpdate, config, x32State, socket }) {
    const [data, setData] = useState(null);
    const [selectedSongId, setSelectedSongId] = useState(null);
    const [newSongTitle, setNewSongTitle] = useState('');
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [flashIndex, setFlashIndex] = useState(null); // For "Next Cue" warning
    const [abletonTime, setAbletonTime] = useState(null);
    const [learnMode, setLearnMode] = useState(false);
    const [assignmentsCollapsed, setAssignmentsCollapsed] = useState(false); // Default Open
    const [warning, setWarning] = useState(null);
    const [learnStatus, setLearnStatus] = useState({ hasData: false, songCount: 0, totalCues: 0 });
    
    // START FIX: Race Condition Prevention
    // We use a Ref to store the latest songs state immediately.
    // We only sync from `data` initially, or if we haven't edited locally yet.
    // Ideally, we treat `songsRef` as the "Editor State".
    const songsRef = React.useRef(null);
    
    // Sync Ref when data loads (One-time Init)
    useEffect(() => {
        if (data && data.songs && !songsRef.current) {
            songsRef.current = data.songs;
        }
    }, [data]);
    // END FIX

    useEffect(() => {
        fetchData();

        if (socket) {
            const handleActive = (d) => {
                // d.songId
                if (d.songId) {
                    setSelectedSongId(d.songId);
                    // Scroll into view
                    const el = document.getElementById(`song-row-${d.songId}`);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            };

            const handleFlash = (d) => {
                // d.index (Index in the list)
                setFlashIndex(d.index);
                // Auto-clear after 500ms
                setTimeout(() => setFlashIndex(null), 500);
            };  

            socket.on('setlist_active', handleActive);
            socket.on('setlist_flash', handleFlash);
            socket.on('learn_mode_changed', (d) => setLearnMode(d.enabled));
            
            socket.on('setlist_warning', (d) => {
                setWarning(d.message);
                setTimeout(() => setWarning(null), 3000);
            });

            socket.on('learn_status', (d) => {
                setLearnStatus(d);
            });

            const onAbletonTime = (data) => {
                const { relativeBar, relativeBeat, totalBars } = data;
                if (relativeBar === undefined) return;
                setAbletonTime({
                    formatted: totalBars > 0 ? `Bar ${relativeBar} of ${totalBars}` : `Bar ${relativeBar}.${relativeBeat}`,
                    isDownbeat: relativeBeat === 1
                });
            };
            socket.on('ableton_time', onAbletonTime);

            socket.on('song_updated', (d) => {
                setData(prev => {
                    if (!prev) return prev;
                    return {
                        ...prev,
                        songs: {
                            ...prev.songs,
                            [d.id]: d.song
                        }
                    };
                });
            });

            return () => {
                socket.off('setlist_active', handleActive);
                socket.off('setlist_flash', handleFlash);
                socket.off('ableton_time', onAbletonTime);
                socket.off('song_updated');
            };
        }
    }, [socket]);

    const fetchData = () => {
        axios.get('/api/setlist/data').then(res => {
            setData(res.data);
            if(onUpdate) onUpdate();
        });
    };

    const handleAddSong = () => {
        if (!newSongTitle.trim()) return;
        axios.post('/api/setlist/song', { 
            title: newSongTitle, 
            artist: '', 
            bpm: 120 
        }).then(res => {
            fetchData();
            setNewSongTitle('');
            
            // Auto-add to setlist order
            const currentOrder = data.setlists.default.songOrder;
            const newOrder = [...currentOrder, res.data.song.id];
            axios.post('/api/setlist/order', { setlistId: 'default', order: newOrder })
                 .then(() => fetchData());
        });
    };

    const handleDeleteSong = (id, e) => {
        e.stopPropagation();
        if(!confirm("Delete this song?")) return;
        axios.delete(`/api/setlist/song/${id}`).then(() => {
            fetchData();
            if (selectedSongId === id) setSelectedSongId(null);
        });
    };

    // START NEW: Saving Indicator
    const [isSaving, setIsSaving] = useState(false);
    // END NEW

    const handleUpdateSong = (id, updates) => {
        // Init Ref if null (shouldn't happen if loaded, but safe guard)
        if (!songsRef.current && data) songsRef.current = data.songs;
        
        // Use Ref as source of truth 
        const currentSong = (songsRef.current && songsRef.current[id]) || (data && data.songs[id]); 
        if (!currentSong) return;

        const newSong = { ...currentSong, ...updates };
        
        // 1. Immediate Update of Ref
        if(songsRef.current) songsRef.current[id] = newSong;

        // 2. Optimistic UI Update 
        setData(prev => {
             if (!prev) return prev;
             return {
                 ...prev,
                 songs: { ...prev.songs, [id]: newSong }
             };
        });

        // 3. Send Merged Data to Server
        setIsSaving(true);
        axios.post('/api/setlist/song', newSong)
             .then(() => {
                 setTimeout(() => setIsSaving(false), 500); // Visual delay for reassurance
             })
             .catch(err => {
                 console.error("Save Failed:", err);
                 setIsSaving(false);
                 const updateMsg = err.response ? `Server Error: ${err.response.status}` : err.message;
                 alert(`Failed to save: ${updateMsg}`);
             });
    };

    const moveSong = (index, direction) => {
        const order = [...data.setlists.default.songOrder];
        if (direction === -1 && index > 0) {
            [order[index], order[index-1]] = [order[index-1], order[index]];
        } else if (direction === 1 && index < order.length - 1) {
            [order[index], order[index+1]] = [order[index+1], order[index]];
        }
        axios.post('/api/setlist/order', { setlistId: 'default', order }).then(() => fetchData());
    };

    // Helper to get best name (Reuse this for Dropdown and Prompt)
    const getBestBusName = (bId) => {
        const sId = String(bId);
        if(!data) return `Bus ${bId}`;
        
        // 1. Persistent Name
        if(data.busNames && data.busNames[sId]) return data.busNames[sId];
        
        // 2. Infer from routing
        const inputsOnBus = Object.entries(data.musicianRouting || {})
            .filter(([k,v]) => String(v) === sId)
            .map(([k]) => k);
        
        if(inputsOnBus.length === 1) {
            const ch = config && config.inputs ? config.inputs.find(c => String(c.id) === String(inputsOnBus[0])) : null;
            return ch ? ch.name : `Bus ${bId}`; 
        }
        return `Bus ${bId}`;
    };

    // --- RENDER ---
    if (!data) return <div className="p-8 text-white">Loading...</div>;

    const activeSetlist = data.setlists.default;
    const selectedSong = selectedSongId ? data.songs[selectedSongId] : null;

    return (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[2000] flex items-center justify-center p-8">
            <div className="bg-gray-900 w-full max-w-6xl h-full max-h-[90vh] rounded-xl shadow-2xl flex flex-col overflow-hidden border border-gray-700 relative">
                
                {/* HEADER */}
                {/* HEADER */}
                {/* HEADER */}
                <div style={{
                    width: '100%', 
                    padding: '8px 16px', 
                    borderBottom: '1px solid #374151', 
                    display: 'flex', 
                    flexDirection: 'row', 
                    justifyContent: 'space-between', 
                    alignItems: 'center', 
                    backgroundColor: '#1f2937',
                    flexShrink: 0  
                }}>
                    {/* LEFT: Title & Name Input */}
                    <div style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '16px' }}>
                        <h2 className="text-lg font-bold text-white m-0 whitespace-nowrap">Setlist:</h2>
                        <div style={{ width: '250px', position: 'relative' }}>
                            <input 
                                value={activeSetlist.name}
                                onChange={(e) => {
                                    const newName = e.target.value;
                                    const newData = { ...data };
                                    newData.setlists.default.name = newName;
                                    setData(newData);
                                }}
                                onBlur={() => {
                                    axios.post('/api/setlist/update', { id: 'default', updates: { name: activeSetlist.name } })
                                         .catch(err => console.error("Failed to save name:", err));
                                }}
                                className="bg-transparent border-none border-b border-gray-600 text-white text-xl font-bold focus:outline-none focus:border-blue-500 w-full py-0"
                            />
                        </div>
                    </div>
                
                {/* RIGHT: STATUS & ACTIONS WRAPPER */}
                <div style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '20px' }}>
                    
                    {/* STATUS GROUP (Learn + Ableton) */}
                    <div style={{display:'flex', alignItems:'center', gap:'12px'}}>
                        {/* LEARN MODE TOGGLE */}
                        <div 
                            onClick={() => {
                                const newState = !learnMode;
                                setLearnMode(newState);
                                socket.emit('toggle_learn_mode', { enabled: newState });
                            }}
                            style={{
                                display:'flex', alignItems:'center', gap:'6px', cursor:'pointer',
                                padding:'2px 8px', borderRadius:'20px', 
                                background: learnMode ? '#aa0000' : '#333',
                                border: '1px solid #444',
                                transition: 'all 0.2s',
                                fontSize: '0.9em'
                            }}
                        >
                            <div style={{
                                width:'8px', height:'8px', borderRadius:'50%', 
                                background: learnMode ? '#ff0000' : '#444',
                                boxShadow: learnMode ? '0 0 10px #ff0000' : 'none'
                            }} />
                            <span style={{fontSize:'0.9em', fontWeight:'bold', color: learnMode ? 'white' : '#888'}}>
                                {learnMode ? 'LEARN ON' : 'LEARN OFF'}
                            </span>
                        </div>

                        <div style={{
                            display:'flex', alignItems:'center', gap:'8px', background:'#333', padding:'2px 8px', 
                            borderRadius:'20px', border: (abletonTime && abletonTime.isDownbeat) ? '1px solid #00ff00' : '1px solid #444',
                            opacity: abletonTime ? 1 : 0.4
                        }}>
                            <div style={{fontSize:'0.7em', color:'#888'}}>ABLETON:</div>
                            <div style={{
                                fontSize:'1em', fontFamily:'monospace', fontWeight:'bold', 
                                color: (abletonTime && abletonTime.isDownbeat) ? '#00ff00' : '#ccc'
                            }}>
                                {abletonTime ? abletonTime.formatted : 'WAITING...'}
                            </div>
                            {abletonTime && (
                                 <div style={{display:'flex', gap:'5px', marginLeft:'5px'}}>
                                     <button 
                                        onClick={() => socket.emit('osc', { address: '/live/scene', args: [0] })}
                                        title="Reset to Bar 1"
                                        style={{
                                            background:'#444', border:'none', color:'#ccc', fontSize:'0.7em', 
                                            padding:'1px 6px', borderRadius:'4px', cursor:'pointer'
                                        }}
                                     >SYNC</button>
                                     <button 
                                        onClick={() => socket.emit('reset_to_global')}
                                        title="Reset to Global Project Time"
                                        style={{
                                            background:'#aa6600', border:'none', color:'white', fontSize:'0.7em', 
                                            padding:'1px 6px', borderRadius:'4px', cursor:'pointer', fontWeight:'bold'
                                        }}
                                     >GLOBAL</button>
                                 </div>
                            )}
                        </div>

                        {/* COMMIT LEARNED DATA */}
                        {learnStatus.hasData && (
                            <div style={{
                                display:'flex', alignItems:'center', gap:'10px', 
                                background:'#004400', padding:'2px 8px', borderRadius:'20px',
                                border:'1px solid #00ff00'
                            }}>
                                <span style={{fontSize:'0.75em', fontWeight:'bold', color:'#00ff00'}}>
                                    LEARNED: {learnStatus.totalCues} CUES ({learnStatus.songCount} SONGS)
                                </span>
                                <button 
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        if(confirm(`Commit ${learnStatus.totalCues} cue durations to setlist?`)) {
                                            socket.emit('commit_learn');
                                        }
                                    }}
                                    style={{
                                        background:'#00cc00', color:'black', border:'none', 
                                        padding:'1px 8px', borderRadius:'4px', cursor:'pointer',
                                        fontSize:'0.75em', fontWeight:'bold'
                                    }}
                                >COMMIT ALL</button>
                            </div>
                        )}
                    </div>

                    {/* BUTTON GROUP (Export + Close) */}
                    <div style={{display:'flex', gap:'8px'}}>
                        <button onClick={() => {
                            const safeName = (activeSetlist.name || 'Setlist').replace(/[^a-z0-9\s-_]/gi, '').trim();
                            const fileName = `Setlist - ${safeName}.docx`;
                            
                            axios.post('/api/setlist/export-docx', { setlistId: 'default' }, { responseType: 'blob' })
                                 .then(res => {
                                     const url = window.URL.createObjectURL(new Blob([res.data]));
                                     const link = document.createElement('a');
                                     link.href = url;
                                     link.setAttribute('download', fileName);
                                     document.body.appendChild(link);
                                     link.click();
                                     link.remove();
                                 })
                                 .catch(e => {
                                     const msg = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Failed to export. Check console.";
                                     alert("Export Failed: " + msg);
                                 });
                        }} style={{
                            padding:'2px 8px', background:'#004488', color:'white', border:'none', cursor:'pointer', fontSize:'0.9em'
                        }}>EXPORT DOCX</button>

                        <button onClick={() => {
                            const safeName = (activeSetlist.name || 'Setlist').replace(/[^a-z0-9\s-_]/gi, '').trim();
                            const fileName = `Setlist - ${safeName}.pdf`;
                            
                            // Optimistic feedback
                            const originalText = document.activeElement.innerText;
                            document.activeElement.innerText = "Generating...";
                            
                            axios.post('/api/setlist/export-pdf', { setlistId: 'default' }, { responseType: 'blob' })
                                 .then(res => {
                                     const url = window.URL.createObjectURL(new Blob([res.data]));
                                     const link = document.createElement('a');
                                     link.href = url;
                                     link.setAttribute('download', fileName);
                                     document.body.appendChild(link);
                                     link.click();
                                     link.remove();
                                     document.activeElement.innerText = originalText;
                                 })
                                 .catch(e => {
                                     console.error(e);
                                     const msg = e.response && e.response.data && e.response.data.error ? e.response.data.error : "Failed to generate PDF. Check server logs.";
                                     alert("PDF Export Failed: " + msg);
                                     document.activeElement.innerText = originalText;
                                 });
                        }} style={{
                            padding:'2px 8px', background:'#222', color:'#ccc', border:'1px solid #444', cursor:'pointer', fontSize:'0.9em'
                        }}>EXPORT PDF</button>

                        <button onClick={onClose} style={{
                            padding:'2px 8px', background:'#444', color:'white', border:'none', cursor:'pointer', fontSize:'0.9em'
                        }}>CLOSE</button>
                    </div>
                </div>
            </div>

            {/* WARNING BANNER */}
            {warning && (
                <div style={{
                    background: '#aa6600', color: 'white', padding: '10px 20px', 
                    textAlign: 'center', fontSize: '0.9em', fontWeight: 'bold',
                    borderBottom: '1px solid #ffaa00'
                }}>
                    ⚠️ {warning}
                </div>
            )}

            {/* BODY */}
            <div style={{flex: 1, display: 'flex', overflow: 'hidden'}}>
                
                {/* LEFT: SONG LIST */}
                <div style={{width: '350px', borderRight: '1px solid #333', display:'flex', flexDirection:'column'}}>
                    <div style={{padding:'10px', borderBottom:'1px solid #333', display:'flex', gap:'5px'}}>
                        <input 
                            value={newSongTitle} 
                            onChange={e => setNewSongTitle(e.target.value)}
                            placeholder="New Song Title..."
                            style={{flex:1, padding:'5px', background:'#222', border:'1px solid #444', color:'white'}}
                            onKeyDown={e => e.key === 'Enter' && handleAddSong()}
                        />
                        <button onClick={handleAddSong} style={{background:'#00aa00', color:'white', border:'none', cursor:'pointer'}}>+</button>
                    </div>
                    
                    <div style={{flex:1, overflowY:'auto'}}>
                        {activeSetlist.songOrder.map((songId, idx) => {
                            const song = data.songs[songId];
                            if(!song) return null;
                            const isSelected = selectedSongId === songId;
                            const isFlashing = flashIndex === idx;
                            
                            return (
                                <div key={songId} 
                                    id={`song-row-${songId}`}
                                    onClick={() => setSelectedSongId(songId)}
                                    style={{
                                        padding: '10px', borderBottom: '1px solid #222',
                                        background: isFlashing ? '#ffff00' : (isSelected ? '#333' : 'transparent'), // Flash Yellow
                                        color: isFlashing ? 'black' : (isSelected ? 'white' : '#ccc'),
                                        cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '10px',
                                        transition: 'background 0.1s'
                                    }}
                                >
                                    <div style={{color:'#666', fontSize:'0.8em', width:'20px'}}>{idx + 1}</div>
                                    <div style={{flex:1, fontWeight: isSelected?'bold':'normal'}}>{song.title}</div>
                                    
                                    {/* ORDER CONTROLS */}
                                    <div style={{display:'flex', flexDirection:'column'}}>
                                        <button onClick={(e) => { e.stopPropagation(); moveSong(idx, -1); }} 
                                            style={{fontSize:'0.6em', background:'none', border:'none', color:'#888', cursor:'pointer'}}>▲</button>
                                        <button onClick={(e) => { e.stopPropagation(); moveSong(idx, 1); }} 
                                            style={{fontSize:'0.6em', background:'none', border:'none', color:'#888', cursor:'pointer'}}>▼</button>
                                    </div>

                                    <button onClick={(e) => handleDeleteSong(songId, e)} 
                                            style={{background:'none', border:'none', color:'#aa0000', cursor:'pointer'}}>
                                        ×
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* CENTER: SONG DETAIL */}
                <div style={{flex: 1, padding: '20px', overflowY:'auto', background:'#161616'}}>
                    {selectedSong ? (
                        <div style={{display:'flex', flexDirection:'column', gap:'20px'}}>
                            <div>
                                <label style={{display:'block', color:'#666', fontSize:'0.8em'}}>TITLE</label>
                                <input 
                                    value={selectedSong.title} 
                                    onChange={e => handleUpdateSong(selectedSong.id, { title: e.target.value })}
                                    style={{fontSize:'1.5em', background:'transparent', border:'none', borderBottom:'1px solid #444', color:'white', width:'100%'}} 
                                />
                            </div>
                            
                            <div style={{display:'flex', gap:'20px'}}>
                                <div style={{flex:1}}>
                                    <label style={{display:'block', color:'#666', fontSize:'0.8em'}}>ARTIST</label>
                                    <input 
                                        value={selectedSong.artist || ''} 
                                        onChange={e => handleUpdateSong(selectedSong.id, { artist: e.target.value })}
                                        style={{background:'#222', border:'1px solid #444', color:'white', padding:'5px', width:'100%'}} 
                                    />
                                </div>
                                <div style={{width:'100px'}}>
                                    <label style={{display:'block', color:'#666', fontSize:'0.8em'}}>BPM</label>
                                    <input 
                                        type="number"
                                        value={selectedSong.bpm || 120} 
                                        onChange={e => handleUpdateSong(selectedSong.id, { bpm: parseInt(e.target.value) })}
                                        style={{background:'#222', border:'1px solid #444', color:'white', padding:'5px', width:'100%'}} 
                                    />
                                </div>
                            </div>

                            {/* CUES SECTION */}
                            <div style={{marginTop:'20px', borderTop:'1px solid #333', paddingTop:'20px'}}>
                                <h3 style={{margin:'0 0 10px 0'}}>Cues</h3>
                                <div style={{display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'10px'}}>
                                    {(selectedSong.cues || []).map((cue, idx) => {
                                        const isDragOver = dragOverIndex === idx;
                                        return (
                                        <div 
                                            key={`${selectedSong.id}-${idx}`} // Force re-render on song change
                                            draggable
                                            onDragStart={(e) => {
                                                e.dataTransfer.setData('text/plain', String(idx));
                                                e.dataTransfer.effectAllowed = 'move';
                                            }}
                                            onDragOver={(e) => {
                                                e.preventDefault(); 
                                                e.dataTransfer.dropEffect = 'move';
                                                if (dragOverIndex !== idx) setDragOverIndex(idx);
                                            }}
                                            onDragLeave={() => {
                                                // Optional: Debounce this if it flickers, but for now relying on Enter/Over of other items
                                            }}
                                            onDragEnd={() => setDragOverIndex(null)}
                                            onDrop={(e) => {
                                                e.preventDefault();
                                                setDragOverIndex(null);
                                                const sourceIdxStr = e.dataTransfer.getData('text/plain');
                                                
                                                if (!sourceIdxStr) return;
                                                const sourceIdx = parseInt(sourceIdxStr);
                                                
                                                if (isNaN(sourceIdx) || sourceIdx === idx) return;

                                                const newCues = [...selectedSong.cues];
                                                const [movedCue] = newCues.splice(sourceIdx, 1);
                                                newCues.splice(idx, 0, movedCue);
                                                
                                                handleUpdateSong(selectedSong.id, { cues: newCues });
                                            }}
                                            style={{
                                                background: isDragOver ? '#334444' : '#222', 
                                                padding:'10px', borderRadius:'4px', 
                                                border: isDragOver ? '2px dashed #00ffff' : '1px solid #333',
                                                display:'flex', flexDirection:'column', gap:'5px', cursor: 'grab',
                                                transform: isDragOver ? 'scale(0.98)' : 'scale(1)',
                                                transition: 'all 0.1s ease'
                                            }}
                                        >
                                            {/* HEADER: NAME INPUT & DELETE */}
                                            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                                                <div style={{display:'flex', alignItems:'center', gap:'5px', flex:1}}>
                                                    <span style={{color: isDragOver ? '#00ffff' : '#555', fontSize:'1.2em', cursor:'grab'}}>☰</span>
                                                    <input 
                                                        key={`${selectedSong.id}-${idx}-input`}
                                                        defaultValue={cue.name}
                                                        onBlur={(e) => {
                                                            const newName = e.target.value;
                                                            if(newName !== cue.name) {
                                                                const newCues = [...selectedSong.cues];
                                                                newCues[idx] = { ...newCues[idx], name: newName };
                                                                handleUpdateSong(selectedSong.id, { cues: newCues });
                                                            }
                                                        }}
                                                        onKeyDown={(e) => e.stopPropagation()} 
                                                        onMouseDown={(e) => e.stopPropagation()} 
                                                        style={{
                                                            background:'transparent', border:'none', borderBottom:'1px solid #444',
                                                            color:'white', fontWeight:'bold', width:'100%', marginRight:'10px'
                                                        }}
                                                    />
                                                </div>
                                                <button 
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        if(!confirm(`Delete cue "${cue.name}"?`)) return;
                                                        const newCues = [...(selectedSong.cues || [])];
                                                        newCues.splice(idx, 1);
                                                        handleUpdateSong(selectedSong.id, { cues: newCues });
                                                    }}
                                                    style={{background:'none', border:'none', color:'#800', cursor:'pointer', fontWeight:'bold'}}
                                                >×</button>
                                            </div>

                                            {/* FOOTER: TYPE & BARS */}
                                            <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                                                <div style={{fontSize:'0.8em', color:'#666'}}>{cue.type}</div>
                                                <div style={{display:'flex', alignItems:'center', gap:'5px', marginTop:'5px'}}>
                                                    <span style={{fontSize:'0.75em', color:'#555', fontWeight:'bold'}}>BARS:</span>
                                                    <input 
                                                        type="number"
                                                        defaultValue={cue.bars || 0}
                                                        onBlur={(e) => {
                                                            const val = parseInt(e.target.value);
                                                            if (val !== cue.bars) {
                                                                const newCues = [...selectedSong.cues];
                                                                newCues[idx] = { ...newCues[idx], bars: val };
                                                                handleUpdateSong(selectedSong.id, { cues: newCues });
                                                            }
                                                        }}
                                                        onKeyDown={(e) => e.stopPropagation()}
                                                        onMouseDown={(e) => e.stopPropagation()}
                                                        style={{
                                                            width:'45px', background:'#111', border:'1px solid #444', 
                                                            color:'#00ff00', fontSize:'0.85em', textAlign:'center',
                                                            borderRadius:'3px', padding:'2px'
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                    })}
                                    
                                    {/* Add Cue Button */}
                                    <button 
                                        onClick={() => {
                                            const name = prompt("Cue Name:");
                                            if(name) {
                                                const newCues = [...(selectedSong.cues||[]), { name, type: 'preset', data: '' }];
                                                handleUpdateSong(selectedSong.id, { cues: newCues });
                                            }
                                        }}
                                        style={{
                                            background:'#333', border:'1px dashed #666', color:'#888', 
                                            display:'flex', alignItems:'center', justifyContent:'center', minHeight:'60px',
                                            cursor: 'pointer'
                                        }}
                                    >+ ADD CUE</button>
                                </div>

                                {/* NOTES Section */}
                                <div style={{background:'#2a2a2a', padding:'15px', borderRadius:'6px', marginBottom:'20px'}}>
                                    <h3 style={{marginTop:0, borderBottom:'1px solid #444', paddingBottom:'5px', fontSize:'1em', color:'#ccc'}}>NOTES</h3>
                                    <textarea
                                        key={selectedSong.id} // Re-render when song changes
                                        defaultValue={selectedSong.notes || ''}
                                        onBlur={(e) => handleUpdateSong(selectedSong.id, { notes: e.target.value })}
                                        placeholder="Enter performance notes here (e.g. Start with drum fill)"
                                        style={{width:'100%', height:'60px', background:'#222', color:'white', border:'1px solid #444', padding:'10px', fontFamily:'sans-serif'}}
                                    />
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div style={{display:'flex', alignItems:'center', justifyContent:'center', height:'100%', color:'#444'}}>
                            SELECT A SONG
                        </div>
                    )}
                </div>

                {/* RIGHT: CHART & MUSICIAN ASSIGNMENTS */}
                <div style={{
                    width: assignmentsCollapsed ? '40px' : '600px', 
                    transition: 'width 0.2s ease-in-out',
                    padding: assignmentsCollapsed ? '10px 5px' : '20px', 
                    overflowX: 'hidden',
                    overflowY: 'auto', 
                    background:'#2a2a2a',
                    borderLeft: '1px solid #333',
                    display:'flex',
                    flexDirection:'column'
                }}>
                    {selectedSongId ? (
                        <>
                            <div 
                                onClick={() => setAssignmentsCollapsed(!assignmentsCollapsed)}
                                style={{
                                    display:'flex', 
                                    flexDirection: assignmentsCollapsed ? 'column' : 'row',
                                    alignItems:'center', 
                                    justifyContent:'space-between', 
                                    cursor:'pointer', 
                                    borderBottom: assignmentsCollapsed ? 'none' : '1px solid #444', 
                                    paddingBottom: assignmentsCollapsed ? '0' : '5px',
                                    marginBottom: assignmentsCollapsed ? '10px' : '15px',
                                    gap: '10px'
                                }}
                                title={assignmentsCollapsed ? "Expand Assignments" : "Collapse Assignments"}
                            >
                                <h3 style={{
                                    margin:0, 
                                    fontSize: assignmentsCollapsed ? '0.7em' : '1.17em',
                                    writingMode: assignmentsCollapsed ? 'vertical-rl' : 'horizontal-tb',
                                    whiteSpace: 'nowrap',
                                    color: '#888'
                                }}>
                                    {assignmentsCollapsed ? 'ASSIGNMENTS' : 'BAND ASSIGNMENTS'}
                                </h3>
                                {assignmentsCollapsed ? <ChevronLeft size={20} /> : <ChevronRight size={20} />}
                            </div>
                            
                            {!assignmentsCollapsed && (
                                <div style={{opacity: 1, transition: 'opacity 0.2s'}}>
                            
                            {/* NEW ASSIGNMENT FORM */}
                            <div style={{background:'#222', padding:'15px', borderRadius:'8px', marginBottom:'20px', border:'1px solid #444'}}>
                                <h4 style={{margin:'0 0 10px 0', color:'#ccc'}}>Add Assignment</h4>
                                <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px', marginBottom:'10px'}}>
                                    {/* INPUT SELECTION */}
                                    <div>
                                        <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Musician / Input</label>
                                        <select 
                                            id="assign-input"
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                if(val && data.musicianRouting && data.musicianRouting[val]) {
                                                    document.getElementById('assign-bus').value = data.musicianRouting[val];
                                                }
                                            }}
                                            style={{width:'100%', padding:'5px', background:'#333', color:'white', border:'1px solid #555', borderRadius:'4px'}}
                                        >
                                            <option value="">Select Input...</option>

                                            {config && config.inputs.filter(ch => {
                                                // 1. Filter out inputs already assigned in this song
                                                const assignedIds = (selectedSong.chartAssignments || []).map(a => String(a.inputChannel));
                                                if (assignedIds.includes(String(ch.id))) return false;

                                                // 2. Filter out Right side of Linked Pair (Even channels if linked)
                                                // X32 Links are 1-2, 3-4, etc.
                                                // If ch.id is even (2,4,6...), check if it (or its partner) is linked.
                                                // Actually, usually both report link:true.
                                                // If we find an even channel:
                                                if (ch.id % 2 === 0) {
                                                    // Check link status in x32State
                                                    // Note: We need to check the PREVIOUS odd channel too? 
                                                    // Rule: If Even Channel is linked, it is the slave of the Odd channel.
                                                    if (x32State && x32State[ch.id] && x32State[ch.id].link) {
                                                        return false; // Hide it
                                                    }
                                                }
                                                return true;
                                            }).map(ch => {
                                                // Check for global routing
                                                const routedBus = data.musicianRouting && data.musicianRouting[ch.id];
                                                const routingLabel = routedBus ? ` (-> Bus ${routedBus})` : '';
                                                return <option key={ch.id} value={ch.id}>{ch.id} - {ch.name || ch.id}{routingLabel}</option>;
                                            })}
                                        </select>
                                    </div>
                                    
                                    {/* MONITOR SELECTION */}
                                    <div>
                                        <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Monitor Bus</label>
                                        <select 
                                            id="assign-bus"
                                            style={{width:'100%', padding:'5px', background:'#333', color:'white', border:'1px solid #555', borderRadius:'4px'}}
                                        >
                                            <option value="">Select Monitor...</option>
                                                    {[...Array(12)].map((_, i) => {
                                                        const busId = i + 1;
                                                        const bestName = getBestBusName(busId);
                                                        const label = bestName !== `Bus ${busId}` ? `Bus ${busId} - ${bestName}` : `Bus ${busId}`;
                                                        return <option key={busId} value={busId}>{label}</option>
                                                    })}
                                        </select>
                                    </div>
                                </div>

                                {/* FILE UPLOAD (Optional) */}
                                <div style={{marginBottom:'10px'}}>
                                    <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Chart File (Optional)</label>
                                    <input type="file" id="assign-file" style={{color:'#ccc'}} />
                                </div>

                                <button 
                                    onClick={() => {
                                        const inputId = document.getElementById('assign-input').value;
                                        const busId = document.getElementById('assign-bus').value;
                                        const fileInput = document.getElementById('assign-file');
                                        
                                        if(!inputId || !busId) {
                                            alert("Please select Input and Monitor.");
                                            return;
                                        }

                                        const formData = new FormData();
                                        if (fileInput.files[0]) {
                                            formData.append('chart', fileInput.files[0]);
                                        }
                                        formData.append('songId', selectedSongId);
                                        formData.append('inputChannel', inputId);
                                        formData.append('monitorBus', busId);

                                        axios.post('/api/upload-chart', formData, {
                                            headers: { 'Content-Type': 'multipart/form-data' }
                                        }).then(res => {
                                            if(res.data.success) {
                                                const fileInfo = res.data.file; // Might be null
                                                
                                                // 1. Smart Bus Renaming
                                                const busNum = parseInt(busId);
                                                const busStr = busNum < 10 ? '0' + busNum : busNum;
                                                const inputName = config && config.inputs.find(c => String(c.id) === String(inputId))?.name || "Musician";
                                                
                                                // Check for existing assignments to this bus
                                                let otherInputsOnBus = [];
                                                if (data.musicianRouting) {
                                                    otherInputsOnBus = Object.entries(data.musicianRouting)
                                                        .filter(([k, v]) => String(v) === String(busId) && String(k) !== String(inputId))
                                                        .map(([k]) => k);
                                                }

                                                let nameToUse = inputName;
                                                
                                                // Helper to get best name
                                                const getBestBusName = (bId) => {
                                                    const sId = String(bId);
                                                    if(data.busNames && data.busNames[sId]) return data.busNames[sId];
                                                    
                                                    // Infer from routing?
                                                    // Find all inputs on this bus
                                                    const inputsOnBus = Object.entries(data.musicianRouting || {})
                                                        .filter(([k,v]) => String(v) === sId)
                                                        .map(([k]) => k);
                                                    
                                                    if(inputsOnBus.length === 1) {
                                                        const ch = config.inputs.find(c => String(c.id) === String(inputsOnBus[0]));
                                                        return ch ? ch.name : `Bus ${bId}`; 
                                                    }
                                                    return `Bus ${bId}`;
                                                };

                                                const existingName = getBestBusName(busId);
                                                
                                                if (otherInputsOnBus.length > 0) {
                                                    // Bus is shared! Prompt user.
                                                    const promptName = prompt(
                                                        `Bus ${busId} is already assigned to other inputs (${otherInputsOnBus.length} others).\n` + 
                                                        `Enter a name for this group (e.g. "Horns" or "Backing"):`, 
                                                        existingName
                                                    );
                                                    if (promptName) nameToUse = promptName;
                                                } else {
                                                    // New simple assignment.
                                                    // Default to Input Name, UNLESS we already had a custom name for this bus.
                                                    // If existingName is different from "Bus X", use it?
                                                    // Actually, if I assign Channel "Bass" to "Bus 1" (currently "Vox Ed"), 
                                                    // I probably want to change it to "Bass".
                                                    // But if I assign Channel "Bass" to "Bus 7" (named "Drums"), 
                                                    // I should be warned?
                                                    // The prompt only triggers on SHARED.
                                                    // If I overwrite "Vox Ed" with "Bass" on Bus 1, it's not shared (assuming Vox Ed is removed from bus? No, routing is usually unique).
                                                    // Wait, musicianRouting is 1:1 Input->Bus.
                                                    // If "Vox Ed" is on Bus 1.
                                                    // And I enable "Bass" on Bus 1.
                                                    // "Vox Ed" stays on Bus 1 (unless I remove him).
                                                    // So now Bus 1 has 2 people. "Shared" block triggers.
                                                    // Prompt shows `existingName` ("Vox Ed").
                                                    // User changes to "Rhythm". Correct.
                                                }

                                                // If the name is DIFFERENT from existing default, save it?
                                                // Actually, we should save whatever we decide to use.
                                                
                                                // Send OSC Command (Fire and forget? Or wait?)
                                                axios.post('/api/osc', { 
                                                    address: `/bus/${busStr}/config/name`, 
                                                    args: [{ type: 's', value: nameToUse.substring(0, 12) }] 
                                                }).catch(e => console.error("Failed to rename bus", e));
                                                
                                                // PERSIST NAME then UPDATE SONG
                                                // We chain this to ensure fetchData (called in handleUpdateSong) gets the new name
                                                axios.post('/api/setlist/bus-name', { busId, name: nameToUse })
                                                     .then(() => {
                                                        // 2. Update song data with new assignment
                                                        const currentAssignments = selectedSong.chartAssignments || [];
                                                        const newAssignment = {
                                                            id: Date.now(),
                                                            inputChannel: inputId,
                                                            monitorBus: busId,
                                                            file: fileInfo // Can be null
                                                        };
                                                        handleUpdateSong(selectedSongId, { chartAssignments: [...currentAssignments, newAssignment] });
                                                        
                                                        // Reset form
                                                        document.getElementById('assign-input').value = "";
                                                        document.getElementById('assign-bus').value = "";
                                                        fileInput.value = "";
                                                     });
                                            }
                                        }).catch(err => alert("Assignment failed: " + err.message));
                                    }}
                                    style={{
                                        width:'100%', padding:'8px', background:'#0088ff', color:'white', 
                                        border:'none', borderRadius:'4px', cursor:'pointer', fontWeight:'bold'
                                    }}
                                >
                                    SAVE ASSIGNMENT
                                </button>
                            </div>

                            {/* ASSIGNMENTS LIST */}
                            <div style={{display:'flex', flexDirection:'column', gap:'10px'}}>
                                {(selectedSong.chartAssignments || []).map((assign, idx) => {
                                    const inputName = config && config.inputs.find(c => String(c.id) === String(assign.inputChannel))?.name || assign.inputChannel;
                                    return (
                                        <div key={idx} style={{
                                            background:'#1a1a1a', padding:'10px', borderRadius:'4px', border:'1px solid #333',
                                            display:'flex', justifyContent:'space-between', alignItems:'center'
                                        }}>
                                            <div>
                                                <div style={{fontWeight:'bold', color:'#fff'}}>
                                                    {inputName} <span style={{color:'#666'}}>→</span> Bus {assign.monitorBus}
                                                </div>
                                                <div style={{fontSize:'0.8em', color:'#aaa'}}>
                                                    {assign.file ? (
                                                        <>📄 <a href={`/uploads/${assign.file.filename}`} target="_blank" style={{color:'#00bbff'}}>{assign.file.originalname}</a></>
                                                    ) : (
                                                        <span style={{color:'#666', fontStyle:'italic'}}>No Chart</span>
                                                    )}
                                                </div>
                                            </div>
                                            <button 
                                                onClick={() => {
                                                    if(!confirm("Remove assignment?")) return;
                                                    const newAssigns = [...selectedSong.chartAssignments];
                                                    newAssigns.splice(idx, 1);
                                                    handleUpdateSong(selectedSongId, { chartAssignments: newAssigns });
                                                }}
                                                style={{background:'none', border:'none', color:'#800', cursor:'pointer', fontSize:'1.2em'}}
                                            >×</button>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </>
            ) : (
                <div style={{display:'flex', alignItems:'center', justifyContent:'center', height:'100%', color:'#666'}}>
                    Select a song to manage assignments
                </div>
            )}
        </div>
      </div>
    </div>
   </div>
  );
}


