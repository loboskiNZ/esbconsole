import React, { useState, useEffect, useRef } from 'react';
import { FileText, Upload, X, ChevronDown, ChevronRight, Music } from 'lucide-react';
import axios from 'axios';

// Responsive Helper
const useIsMobile = () => {
    const [isMobile, setIsMobile] = useState(window.innerWidth < 768);
    useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);
    return isMobile;
};

const SongDetails = ({ song, isMobile, activePart, onViewChart }) => {
    const isActiveSong = activePart?.songId && String(activePart.songId) === String(song.id);

    return (
    <div style={{ padding: '15px', borderTop: isMobile ? '1px solid #333' : 'none', background: isMobile ? '#151515' : 'transparent' }}>
        {/* Desktop Title (if split view) */}
        {!isMobile && <h2 style={{marginTop:0, fontSize:'1.8em', borderBottom:'1px solid #444', paddingBottom:'10px'}}>{song.title}</h2>}
    
        {/* META */}
        <div style={{ marginBottom: '15px', display: 'flex', gap: '10px', fontSize: '0.9em', color: '#aaa', flexWrap: 'wrap', alignItems:'center' }}>
            {song.artist && <span>{song.artist}</span>}
            {song.key && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>Key: {song.key}</span>}
            {song.bpm && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>{song.bpm} BPM</span>}
            {song.duration && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>{song.duration}</span>}
            
            {/* Chart Trigger as Badge */}
            <span 
                onClick={() => onViewChart(song.id)}
                style={{
                    background:'#2266aa', color:'white', padding:'2px 8px', borderRadius:'4px', 
                    cursor:'pointer', display:'flex', alignItems:'center', gap:'5px', fontWeight:'bold'
                }}
            >
                <FileText size={12} /> Chart
            </span>
        </div>

        {/* NOTES */}
        {song.notes && (
            <div style={{marginBottom:'15px', background:'#222', padding:'10px', borderRadius:'5px', fontSize:'0.9em', whiteSpace: 'pre-line', color:'#ccc'}}>
                {song.notes}
            </div>
        )}
        
        {/* CUES (Optional) */}
        {song.parts && song.parts.length > 0 && (
            <div>
                <div style={{fontSize:'0.8em', color:'#666', marginBottom:'5px', textTransform:'uppercase'}}>Cues / Parts</div>
                <div style={{display:'flex', flexWrap:'wrap', gap:'8px'}}>
                    {song.parts.map((p, i) => {
                        // Priority: Index Match (if available) -> Name Match (Fallback)
                        const isCueActive = isActiveSong && (
                            (activePart?.partIndex !== undefined) 
                                ? activePart.partIndex === i 
                                : activePart?.partName === p.name
                        );
                        return (
                            <div key={i} style={{
                                background: isCueActive ? '#00bb00' : '#222', 
                                color: isCueActive ? 'black' : 'white',
                                fontWeight: isCueActive ? 'bold' : 'normal',
                                padding:'8px 12px', borderRadius:'4px', 
                                fontSize:'0.95em', border: isCueActive ? '1px solid #00ff00' : '1px solid #333',
                                display:'flex', alignItems:'center', gap:'5px',
                                transition: 'all 0.2s ease'
                            }}>
                                <Music size={14} color={isCueActive ? 'black' : '#666'}/>
                                {p.name}
                            </div>
                        );
                    })}
                </div>
            </div>
        )}
    </div>
    );
};

const ChartModalWithUpload = ({ viewingChart, onClose, onUpload, isMobile }) => {
    const [loadError, setLoadError] = useState(false);
    const [isRef, setIsRef] = useState(Date.now()); // Force refresh iframe

    const handleFile = (e) => {
        onUpload(e, viewingChart.songId);
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100%', height: '100%',
            background: 'rgba(0,0,0,0.95)', zIndex: 2000,
            display: 'flex', flexDirection: 'column'
        }}>
            {/* Header */}
            <div style={{
                height: '60px', background: '#111', borderBottom: '1px solid #333',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 20px'
            }}>
                <span style={{fontWeight:'bold', fontSize:'1.1em'}}>Chart Viewer</span>
                
                <div style={{display:'flex', gap:'15px', alignItems:'center'}}>
                     {/* Header Upload Action */}
                    <label style={{
                        padding: '6px 12px', background: 'transparent', border: '1px solid #666', borderRadius: '4px',
                        color: 'white', display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize:'0.9em'
                    }} className="hover:bg-gray-800">
                        <Upload size={16} /> <span style={{display: isMobile ? 'none' : 'inline'}}>Replace Chart</span>
                        <input type="file" style={{display:'none'}} accept="application/pdf,image/*" onChange={handleFile} />
                    </label>

                    <button onClick={onClose} style={{background:'transparent', border:'none', color:'white', cursor:'pointer'}}>
                        <X size={28} />
                    </button>
                </div>
            </div>

            {/* Content */}
            <div style={{flex: 1, overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center', position:'relative'}}>
                {!loadError ? (
                    <iframe 
                        key={isRef}
                        src={viewingChart.url} 
                        style={{width: '100%', height: '100%', border: 'none', background:'white'}} 
                        title="Chart"
                        onError={() => setLoadError(true)} 
                    />
                ) : (
                     <div style={{textAlign:'center', color:'#888'}}>
                        <FileText size={48} style={{marginBottom:'20px', opacity:0.5}} />
                        <h3>Chart Not Found</h3>
                        <p>Upload a PDF or Image for this song.</p>
                     </div>
                )}
            </div>
        </div>
    );
};

const MusicianSetlist = ({ user, setlist, socket }) => {
    const isMobile = useIsMobile();
    const [selectedSongId, setSelectedSongId] = useState(null); // For Desktop Master-Detail
    const [expandedSong, setExpandedSong] = useState(null); // For Mobile Accordion
    const [activePart, setActivePart] = useState(null); // { songId, partName }
    
    const [viewingChart, setViewingChart] = useState(null); // { url, songId }
    
    // Refs for scrolling
    const songRefs = useRef({});

    // Listen for Active Part & Auto-Scroll
    const [flashIndex, setFlashIndex] = useState(null); // Automation Flash
    
    // Listen for Active Part & Setlist Automation
    useEffect(() => {
        if (!socket) return;
        
        // 1. Manual/Internal Cue Trigger
        const onActivePart = (data) => {
            console.log("🔥 [MusicianSetlist] RECEIVED active_part:", data);
            setActivePart(data);
            handleSongSelection(data.songId);
        };

        // 2. Automation: Song Select (MIDI/OSC)
        const onSetlistActive = (data) => {
             // data.songId
             handleSongSelection(data.songId);
        };

        // 3. Automation: Flash Cue (OSC)
        const onSetlistFlash = (data) => {
            // data.index (Index in sorted list)
            setFlashIndex(data.index);
            // Auto-clear
            setTimeout(() => setFlashIndex(null), 500);
        };
        
        // Helper to sync view
        const handleSongSelection = (sId) => {
            if (!sId) return;
            
            // Update State
            if (isMobile) {
                 setExpandedSong(sId);
            } else {
                 setSelectedSongId(sId);
            }

            // Auto-Scroll
            setTimeout(() => {
                const el = songRefs.current[sId];
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        };
        
        socket.on('active_part', onActivePart);
        socket.on('setlist_active', onSetlistActive);
        socket.on('setlist_flash', onSetlistFlash);
        
        return () => {
            socket.off('active_part', onActivePart);
            socket.off('setlist_active', onSetlistActive);
            socket.off('setlist_flash', onSetlistFlash);
        };
    }, [socket, isMobile]);


    
    // Data Helpers
    const effectiveSetlist = setlist || { songs: [] };
    const songs = Array.isArray(effectiveSetlist.songs) ? effectiveSetlist.songs : [];
    
    // Logging for Debug
    if (setlist && !Array.isArray(effectiveSetlist.songs)) {
        console.warn("MusicianSetlist: 'songs' is not an array:", effectiveSetlist.songs);
    }
    
    // Defensive Order sort
    const hasOrder = Array.isArray(effectiveSetlist.order) && effectiveSetlist.order.length > 0;
    const sortedSongs = hasOrder
        ? effectiveSetlist.order.map(id => songs.find(s => s.id === id)).filter(Boolean)
        : songs;

    // Handlers
    const handleViewChart = (songId) => {
         // Determine URL. Assuming /api/charts/:songId/default for now or derived.
         // Actually, let's just assume we can fetch it or we assume a standard path.
         // For now, let's point to the API that serves it.
         // If we don't know the file extension, we might need to check.
         // But for the viewer, let's assume we try a common one or the API handles it.
         // Revised: We'll use a generic "file existence" check or just try to load.
         // Let's assume user role 'musician' for now.
         setViewingChart({ url: `/api/charts/${songId}/musician`, songId });
    };

    const handleUpload = async (e, songId) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('songId', songId);
        formData.append('role', 'musician'); // Hardcoded role for now

        try {
            await axios.post('/api/upload-chart', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            // Refresh chart view
            setViewingChart({ url: `/api/charts/${songId}/musician?t=${Date.now()}`, songId });
        } catch (err) {
            console.error("Upload failed", err);
            alert("Failed to upload chart");
        }
    };

    // ... (rest of logic preserved implicitly by removal of this block)

    // Listen for Ableton Time
    const [abletonTime, setAbletonTime] = useState(null);

    useEffect(() => {
        if (!socket) return;
        
        const onAbletonTime = (data) => {
             // New Payload: data.relativeBar, data.relativeBeat, data.totalBars, data.signature
             const { relativeBar, relativeBeat, totalBars, signature } = data;
             
             if (relativeBar === undefined) return;

             setAbletonTime({
                 bar: relativeBar,
                 beat: relativeBeat,
                 totalBars: totalBars,
                 signature: signature,
                 formatted: totalBars > 0 ? `Bar ${relativeBar} of ${totalBars}` : `Bar ${relativeBar}.${relativeBeat}`,
                 isDownbeat: relativeBeat === 1
             });
        };

        socket.on('ableton_time', onAbletonTime);
        return () => {
            socket.off('ableton_time', onAbletonTime);
        };
    }, [socket]);


    // LAYOUT 1: SPLIT VIEW (Desktop/Landscape)
    if (!isMobile) {
        // Fix: songs is an array, not an object/map. Use .find() 
        // AND handle type mismatch (String vs Number)
        const selectedIndex = sortedSongs.findIndex(s => String(s.id) === String(selectedSongId));
        const selectedSong = sortedSongs[selectedIndex] || sortedSongs[0];
        
        // Calculate "Up Next"
        let nextLabel = null;
        let nextType = null; // 'CUE' or 'SONG'

        // 1. Try Next Cue in Active Song (if we are playing it)
        if (activePart && String(activePart.songId) === String(selectedSong.id)) {
            const currentPartIdx = activePart.partIndex;
            if (currentPartIdx !== undefined && selectedSong.parts && selectedSong.parts[currentPartIdx + 1]) {
                nextLabel = selectedSong.parts[currentPartIdx + 1].name;
                nextType = 'CUE';
            }
        }

        // 2. If no next cue, try Next Song
        if (!nextLabel) {
            const nextSong = sortedSongs[selectedIndex + 1];
            if (nextSong) {
                nextLabel = nextSong.title;
                nextType = 'SONG';
            }
        }

        return (
            <div style={{ height: '100%', display: 'flex', flexDirection: 'column', overflow:'hidden' }}>
                {/* Global Header */}
                <div style={{ padding: '20px 30px', borderBottom: '1px solid #333', background: '#111', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '1.5em' }}>{effectiveSetlist?.name || 'Setlist'} <span style={{fontSize:'0.6em', opacity:0.5}}>({user?.name})</span></h2>
                        <div style={{ fontSize: '0.9em', color: '#666' }}>{sortedSongs.length} Songs</div>
                    </div>
                    
                    {/* ABLETON BAR COUNT (CENTER) */}
                    <div style={{
                        display: 'flex', flexDirection: 'column', alignItems: 'center',
                        minWidth: '160px', padding: '5px 15px', borderRadius: '8px',
                        background: (abletonTime && abletonTime.isDownbeat) ? '#222' : 'transparent',
                        border: (abletonTime && abletonTime.isDownbeat) ? '1px solid #444' : '1px solid transparent',
                        opacity: abletonTime ? 1 : 0.4
                    }}>
                         <div style={{fontSize:'0.8em', color:'#666', textTransform:'uppercase'}}>Ableton Status</div>
                         <div style={{
                             fontSize:'1.8em', fontFamily:'monospace', fontWeight:'bold',
                             color: (abletonTime && abletonTime.isDownbeat) ? '#00ff00' : '#ccc',
                             whiteSpace: 'nowrap'
                         }}>
                             {abletonTime ? abletonTime.formatted : 'WAITING...'}
                         </div>
                    </div>

                    {/* NEXT INDICATOR */}
                    {nextLabel && (
                        <div style={{textAlign:'right', opacity:0.8}}>
                            <div style={{fontSize:'0.8em', color: nextType === 'CUE' ? '#00bb00' : '#888', textTransform:'uppercase', letterSpacing:'1px', fontWeight:'bold'}}>
                                {nextType === 'CUE' ? 'Next Cue' : 'Up Next'}
                            </div>
                            <div style={{fontSize:'1.2em', color:'#fff', fontWeight:'bold'}}>
                                {nextLabel}
                            </div>
                        </div>
                    )}
                </div>
                
                <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
                    {/* LEFT PANEL: SONG LIST */}
                    <div style={{ width: '350px', background: '#1a1a1a', borderRight: '1px solid #333', overflowY: 'auto' }}>
                        {sortedSongs.map((song, index) => {
                            const isSelected = selectedSongId && String(selectedSongId) === String(song.id);
                            const isFlashing = flashIndex === index;
                            
                            return (
                                <div 
                                    key={song.id} 
                                    ref={el => songRefs.current[song.id] = el}
                                    onClick={() => setSelectedSongId(song.id)}
                                    style={{
                                        padding: '12px', borderBottom: '1px solid #333', cursor: 'pointer',
                                        background: isFlashing ? '#ffff00' : (isSelected ? '#1ea1f2' : 'transparent'),
                                        color: isFlashing ? 'black' : (isSelected ? 'white' : '#ccc'),
                                        transition: 'background 0.1s',
                                        fontWeight: isSelected ? 'bold' : 'normal'
                                    }}
                                >
                                    <span style={{ color: '#555', marginRight: '15px', fontWeight: 'bold', minWidth: '20px' }}>{index + 1}</span>
                                    <div>
                                        <div style={{ fontWeight: selectedSongId === song.id ? 'bold' : 'normal', color: selectedSongId === song.id ? 'white' : '#ccc' }}>
                                            {song.title}
                                        </div>
                                        <div style={{ fontSize: '0.8em', color: '#666' }}>{song.artist}</div>
                                    </div>
                                </div>

                        );
                        })}
                    </div>

                    {/* RIGHT PANEL: DETAILS */}
                    <div style={{ flex: 1, overflowY: 'auto', padding: '30px', background: '#0e0e0e' }}>
                         {selectedSong ? (
                             <SongDetails 
                                song={selectedSong} 
                                isMobile={isMobile}
                                activePart={activePart}
                                onViewChart={handleViewChart}
                            />
                         ) : (
                             <div style={{display:'flex', alignItems:'center', justifyContent:'center', height:'100%', color:'#444'}}>
                                Select a song
                             </div>
                         )}
                    </div>
                </div>

                {/* CHART MODAL (Global) */}
                {viewingChart && (
                   <ChartModalWithUpload 
                       viewingChart={viewingChart} 
                       onClose={() => setViewingChart(null)} 
                       onUpload={(e, id) => {
                           handleUpload(e, id);
                       }}
                       isMobile={isMobile}
                   />
                )}
            </div>
        );
    }
    
    // LAYOUT 2: ACCORDION (Mobile)
    return (
        <div style={{ padding: '20px', paddingBottom: '100px', color: 'white', overflowY: 'auto' }}>
             <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>
                <h2 style={{ margin:0 }}>Setlist ({user?.name || 'Musician'})</h2>
                {abletonTime && (
                     <div style={{
                         fontFamily: 'monospace', fontWeight: 'bold', fontSize: '1.2em',
                         color: abletonTime.isDownbeat ? '#00ff00' : '#888'
                     }}>
                         {abletonTime.formatted}
                     </div>
                )}
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                {sortedSongs.map((song, index) => {
                    const isExpanded = expandedSong && String(expandedSong) === String(song.id);
                    const isFlashing = flashIndex === index;

                    return (
                    <div 
                        key={song.id} 
                        ref={el => songRefs.current[song.id] = el} 
                        style={{
                            background: isFlashing ? '#ffff00' : '#1a1a1a', 
                            color: isFlashing ? 'black' : 'white',
                            borderRadius: '8px', overflow: 'hidden',
                            border: isExpanded ? '1px solid #0088ff' : '1px solid #333',
                            transition: 'background 0.1s'
                        }}
                    >
                        {/* Header */}
                        <div 
                            onClick={() => setExpandedSong(isExpanded ? null : song.id)}
                            style={{
                                padding: '15px', display: 'flex', alignItems: 'center', cursor: 'pointer',
                                background: isExpanded ? '#222' : 'transparent'
                            }}
                        >
                            <span style={{ color: '#666', marginRight: '15px', fontWeight: 'bold', minWidth: '20px' }}>{index + 1}</span>
                            <div style={{ flex: 1, fontWeight: 'bold', fontSize: '1.1em' }}>{song.title}</div>
                            
                            {/* Mobile Header Icons Removed */}
                            <div style={{ color: '#666' }}>
                                {isExpanded ? <ChevronDown size={20} /> : <ChevronRight size={20} />}
                            </div>
                        </div>

                        {/* Expanded Content */}
                        {expandedSong === song.id && (
                             <SongDetails 
                                song={song} 
                                isMobile={isMobile}
                                activePart={activePart}
                                onViewChart={handleViewChart}
                            />
                        )}
                    </div>
                    );
                })}
            </div>

            {/* CHART MODAL (Global) */}
            {viewingChart && (
               <ChartModalWithUpload 
                   viewingChart={viewingChart} 
                   onClose={() => setViewingChart(null)} 
                   onUpload={(e, id) => {
                       handleUpload(e, id);
                       // Quick hack: close modal to force refresh or we implement refresh logic
                       // Better: handleUpload is async.
                   }}
                   isMobile={isMobile}
               />
            )}
        </div>
    );
};
// Remove old ChartModal outside


export default MusicianSetlist;


