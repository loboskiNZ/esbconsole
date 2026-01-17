import React, { useState, useEffect, useRef } from 'react';
import { FileText, Upload, X, ChevronDown, ChevronRight, Music, Clock, Scissors, Copy, Link, Trash2, StickyNote } from 'lucide-react';

 


import axios from 'axios';
import SnippetMaker from './SnippetMaker';

const PulseStyle = () => (
    <style>{`
        @keyframes pulse-amber {
            0% { background-color: rgba(255, 170, 0, 0.1); box-shadow: 0 0 5px rgba(255, 170, 0, 0.2); }
            50% { background-color: rgba(255, 170, 0, 0.4); box-shadow: 0 0 15px rgba(255, 170, 0, 0.6); }
            100% { background-color: rgba(255, 170, 0, 0.1); box-shadow: 0 0 5px rgba(255, 170, 0, 0.2); }
        }
        .pulse-warning {
            animation: pulse-amber 0.5s infinite;
            border: 1px solid #ffaa00 !important;
            color: #ffaa00 !important;
        }
    `}</style>
);

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

const SongDetails = ({ song, isMobile, activePart, onViewChart, user, visualActiveIndex }) => {
    const isActiveSong = activePart?.songId && String(activePart.songId) === String(song.id);
    const [copyMode, setCopyMode] = useState(null); // { type: 'TO'|'FROM', index: number }
    const [showNotes, setShowNotes] = useState(false);

    const performCopy = async (sourceIdx, targetIdx) => {
        try {
            const role = user?.role ? user.role.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'default';
            await axios.post('/api/charts/snippet/copy', {
                songId: song.id,
                sourcePartIndex: sourceIdx,
                targetPartIndex: targetIdx,
                role: role
            });
            setCopyMode(null); // Reset UI
            // Updates happen via socket, so no need to manual refresh
        } catch (err) {
            console.error(err);
            alert("Copy Failed");
        }
    };

    const handleDeleteSnippet = async (index, songId) => {
        if (!confirm("Are you sure you want to delete this snippet?")) return;
        
        try {
            const role = user?.role ? String(user.role).replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'default';
            
            await axios.delete('/api/charts/snippet', {
                data: {
                    songId: songId || song.id,
                    cueIndex: index,
                    role
                }
            });
            // Success handled by socket update
        } catch (err) {
            console.error("Delete Error:", err);
            alert("Failed to delete snippet: " + (err.response?.data?.error || err.message));
        }
    };

    return (
    <div style={{ padding: '15px', borderTop: isMobile ? '1px solid #333' : 'none', background: isMobile ? '#151515' : 'transparent' }}>
        {/* Helper for Copy UI */}
        {copyMode && (
            <div style={{
                background: '#0088aa', color: 'white', padding: '10px', borderRadius: '4px', marginBottom: '10px',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center'
            }}>
                <span>
                    {copyMode.type === 'TO' ? 'Select an EMPTY cue to paste image.' : 'Select a SOURCE cue to copy image from.'}
                </span>
                <X size={16} onClick={() => setCopyMode(null)} style={{cursor:'pointer'}} />
            </div>
        )}

        {/* Desktop Title Removed (User Request to save space) */}
        {/* {!isMobile && <h2 style={{marginTop:0, fontSize:'1.8em', borderBottom:'1px solid #444', paddingBottom:'10px'}}>{song.title}</h2>} */}
    
        {/* META */}
        <div style={{ marginBottom: '15px', display: 'flex', gap: '10px', fontSize: '0.9em', color: '#aaa', flexWrap: 'wrap', alignItems:'center' }}>
            {song.artist && <span>{song.artist}</span>}
            {song.key && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>Key: {song.key}</span>}
            {song.bpm && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>{song.bpm} BPM</span>}
            {song.duration && <span style={{background:'#333', padding:'2px 6px', borderRadius:'4px'}}>{song.duration}</span>}
            
            {/* Chart Trigger as Badge */}
            {!isMobile && (
            <span 
                onClick={() => onViewChart(song)}
                style={{
                    background:'#2266aa', color:'white', padding:'2px 8px', borderRadius:'4px', 
                    cursor:'pointer', display:'flex', alignItems:'center', gap:'5px', fontWeight:'bold'
                }}
            >
                <FileText size={12} /> Chart
            </span>
            )}
            {/* Notes Trigger */}
            {song.notes && (
            <span 
                onClick={() => setShowNotes(true)}
                style={{
                    background:'#444', color:'#ddd', padding:'2px 8px', borderRadius:'4px', 
                    cursor:'pointer', display:'flex', alignItems:'center', gap:'5px', fontWeight:'bold'
                }}
            >
                <StickyNote size={12} /> Notes
            </span>
            )}
        </div>


        {/* NOTES OVERLAY */}
        {showNotes && (
            <div style={{
                position: 'fixed', top: '15%', left: '50%', transform: 'translateX(-50%)',
                width: '90%', maxWidth: '500px', maxHeight: '60vh',
                background: '#222', border: '1px solid #555', borderRadius: '8px',
                zIndex: 2000, boxShadow: '0 10px 30px rgba(0,0,0,0.8)',
                display: 'flex', flexDirection: 'column'
            }}>
                <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', padding:'15px', borderBottom:'1px solid #333', background:'#1a1a1a', borderRadius:'8px 8px 0 0'}}>
                    <h3 style={{margin:0, display:'flex', alignItems:'center', gap:'10px'}}><StickyNote size={18} /> Notes</h3>
                    <X size={20} onClick={() => setShowNotes(false)} style={{cursor:'pointer'}} />
                </div>
                <div style={{padding:'20px', overflowY:'auto', whiteSpace: 'pre-line', fontSize:'1.1em', color:'#ddd', lineHeight:'1.5'}}>
                    {song.notes}
                </div>
                <div style={{padding:'10px', textAlign:'center', borderTop:'1px solid #333'}}>
                    <button onClick={() => setShowNotes(false)} style={{
                        background:'#444', color:'white', border:'none', padding:'8px 20px', borderRadius:'4px', cursor:'pointer'
                    }}>Close</button>
                </div>
            </div>
        )}
        
        {/* CUES (Optional) */}
        {song.parts && song.parts.length > 0 && (
            <div>
                <div style={{fontSize:'0.8em', color:'#666', marginBottom:'5px', textTransform:'uppercase'}}>Cues / Parts</div>
                <div style={{display:'flex', flexWrap:'wrap', gap:'8px'}}>
                    {song.parts.map((p, i) => {
                        // Use VISUAL index for rendering to allow anticipatory transitions
                        // Fallback to activePart.partIndex if visual index is somehow null
                        const currentVisualIndex = visualActiveIndex !== null ? visualActiveIndex : activePart?.partIndex;
                        
                        // Priority: Index Match (if available) -> Name Match (Fallback)
                        const isCueActive = isActiveSong && (
                            (currentVisualIndex !== undefined && currentVisualIndex !== null) 
                                ? currentVisualIndex === i 
                                : activePart?.partName === p.name
                        );
                        
                        // Determine if this is the "Next" cue (for preview)
                        const isNextCue = isActiveSong && currentVisualIndex !== undefined && i === currentVisualIndex + 1;

                        // Layout Logic:
                        // Active & Next -> 100% Width (Performance View)
                        // Others -> Auto width (Chips)
                        const isWide = isCueActive || isNextCue;

                        // Snippet Logic for Copy/Paste
                        let role = 'default';
                        if (user && user.role) {
                            role = String(user.role).replace(/[^a-z0-9]/gi, '_').toLowerCase();
                        }

                        // FIX BLEED: Strict role check. Do NOT fallback to global visualSnippet if user has a specific role.
                        // FIX CRASH: Defensive check for p (part) being valid
                        if (!p) return null;

                        // FIX BLEED: Strict role check. Do NOT fallback to global visualSnippet if user has a specific role.
                        const hasSnippet = role === 'default' 
                            ? ((p.visualSnippets?.['default']) || p.visualSnippet)
                            : (p.visualSnippets?.[role]);

                        // Selection Mode Logic
                        const isCandidate = copyMode && (
                            (copyMode.type === 'TO' && !hasSnippet) || // Candidate target
                            (copyMode.type === 'FROM' && hasSnippet)   // Candidate source
                        );
                        
                        const handleInteraction = () => {
                            if (!copyMode) return;
                            if (copyMode.type === 'TO' && i !== copyMode.index) performCopy(copyMode.index, i); // Src -> Target
                            if (copyMode.type === 'FROM' && i !== copyMode.index) performCopy(i, copyMode.index); // Source -> Target
                        };

                        return (
                            <div key={i} style={{ 
                                display:'flex', flexDirection:'column', alignItems:'center',
                                width: isWide ? '100%' : 'auto', // Force full width for focus items
                                order: i, // Maintain order
                            }}>
                                <div 
                                    onClick={isCandidate ? handleInteraction : undefined}
                                    style={{
                                    background: isCandidate ? '#00bbcc' : (isCueActive ? '#00bb00' : (isNextCue ? '#444' : '#222')), 
                                    color: isCandidate ? 'white' : (isCueActive ? 'black' : 'white'),
                                    fontWeight: isCueActive ? 'bold' : 'normal',
                                    padding:'8px 12px', borderRadius:'4px', 
                                    fontSize:'0.95em', 
                                    border: isCueActive ? '1px solid #00ff00' : (isNextCue ? '1px solid #666' : '1px solid #333'), // distinct border for next
                                    display:'flex', alignItems:'center', gap:'5px',
                                    transition: 'all 0.2s ease', 
                                    cursor: isCandidate ? 'pointer' : 'default',
                                    width: isWide ? '100%' : 'auto', // Ensure container fills width
                                    justifyContent: isWide ? 'center' : 'flex-start'
                                }}>
                                    <Music size={14} color={isCueActive ? 'black' : '#666'}/>
                                    {p.name} {isNextCue && <span style={{fontSize:'0.8em', opacity:0.7, marginLeft:'10px'}}>(NEXT)</span>}
                                    
                                    {/* Edit Controls (Only visible when no mode active) */}
                                    {!copyMode && (
                                        <div style={{marginLeft:'10px', display:'flex', gap:'5px'}}>
                                            {/* If NO snippet -> Copy From option */}
                                            {!hasSnippet && (
                                                <Copy 
                                                    size={14} className="hover-btn" style={{cursor:'pointer', opacity:0.5}} 
                                                    onClick={(e) => { e.stopPropagation(); setCopyMode({ type: 'FROM', index: i }); }}
                                                    title="Copy image FROM another cue"
                                                />
                                            )}
                                            {/* If YES snippet -> Copy To option */}
                                            {hasSnippet && (
                                                <>
                                                <Link 
                                                    size={14} className="hover-btn" style={{cursor:'pointer', opacity:0.5}} 
                                                    onClick={(e) => { e.stopPropagation(); setCopyMode({ type: 'TO', index: i }); }}
                                                    title="Copy image TO another cue"
                                                />
                                                <Trash2 
                                                    size={14} className="hover-btn-danger" style={{cursor:'pointer', opacity:0.5, color: '#ff4444', marginLeft:'5px'}} 
                                                    onClick={(e) => { e.stopPropagation(); handleDeleteSnippet(i, song.id); }}
                                                    title="Delete Snippet"
                                                />
                                                </>
                                            )}

                                        </div>
                                    )}
                                </div>
                                {/* VISUAL SNIPPET (Animated) */}
                                {(() => {
                                    const snippet = hasSnippet;
                                    
                                    const showInfo = isCueActive || isNextCue;

                                    if (!snippet) {
                                        // User Request: Show Placeholder if Active/Next but no snippet found
                                        if (showInfo) {
                                            return (
                                                <div style={{
                                                    marginTop: '5px', width: '100%',
                                                    height: '100px', // Fixed height for placeholder
                                                    background: '#333', color: '#ffaa00',
                                                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                                                    gap: '10px', borderRadius: '4px',
                                                    border: isCueActive ? '2px solid #0f0' : '1px dashed #666',
                                                    opacity: isNextCue ? 0.7 : 1.0,
                                                    transition: 'all 0.5s ease-in-out'
                                                }}>
                                                    <Scissors size={24} />
                                                    <span style={{fontWeight:'bold'}}>NO SNIPPET FOUND</span>
                                                </div>
                                            );
                                        }
                                        return null; 
                                    }
                                    
                                    // FIX FLICKER: Use stable timestamp instead of Date.now()
                                    // This prevents re-fetching on every render frame
                                    const cacheBuster = snippet.timestamp ? `?t=${snippet.timestamp}` : ''; 
                                    const imgSrc = `${snippet.path}${cacheBuster}`;

                                    return (
                                        <div style={{
                                            // Animation Properties
                                            maxHeight: showInfo ? '800px' : '0px',
                                            opacity: showInfo ? (isNextCue ? 0.7 : 1.0) : 0,
                                            marginTop: showInfo ? '5px' : '0px',
                                            padding: 0,
                                            overflow: 'hidden',
                                            transition: 'all 0.5s ease-in-out', // Smooth slide/fade
                                            
                                            // Layout
                                            // Fix: When inactive, force width/maxWidth to 0 to prevent expanding the parent 'auto' width container
                                            // This solves the issue where inactive cues with snippets were stacking vertically instead of flowing
                                            width: showInfo ? '100%' : '0px',
                                            maxWidth: showInfo ? '100%' : '0px',
                                            
                                            border: showInfo ? (isCueActive ? '2px solid #0f0' : '1px dashed #666') : 'none', 
                                            borderRadius:'4px',
                                        }}>
                                            <img 
                                                src={imgSrc} 
                                                style={{width:'100%', height:'auto', display:'block'}} 
                                                alt="Cue Visual" 
                                                onError={(e) => {
                                                    console.warn("Image Load Failed:", snippet.path);
                                                    e.target.style.opacity = 0.3; 
                                                }}
                                            />
                                        </div>
                                    );
                                })()}
                            </div>
                        );
                    })}
                </div>
                
                {/* PRE-LOADER: Optimized to only load NEXT cue */}
                <div style={{display:'none'}}>
                     {isActiveSong && (() => {
                         // Only pre-load the IMMEDIATE NEXT cue to save memory
                         // Fallback to activePart.partIndex if visual index is somehow null
                        const currentVisualIndex = visualActiveIndex !== null ? visualActiveIndex : activePart?.partIndex;
                        if (currentVisualIndex === undefined) return null;

                        const nextIndex = currentVisualIndex + 1;
                        if (nextIndex >= song.parts.length) return null;

                        const p = song.parts[nextIndex];
                        
                        // Strict Role Check (Copy from main logic)
                        let role = 'default';
                        if (user && user.role) {
                            role = String(user.role).replace(/[^a-z0-9]/gi, '_').toLowerCase();
                        }
                         const snippet = role === 'default' 
                            ? ((p.visualSnippets?.['default']) || p.visualSnippet)
                            : (p.visualSnippets?.[role]);

                        if (!snippet) return null;

                        // Use stable timestamp
                        const cacheBust = snippet.timestamp ? `?t=${snippet.timestamp}` : '';
                        return <img src={`${snippet.path}${cacheBust}`} alt="preload-next" />;
                     })()}
                </div>
            </div>
        )}
    </div>
    );
};

const ChartModalWithUpload = ({ viewingChart, song, allSongs, user, onClose, onUpload, isMobile }) => {
    const [loadError, setLoadError] = useState(false);
    const [isRef, setIsRef] = useState(Date.now()); // Force refresh iframe
    const [showSnipper, setShowSnipper] = useState(false);

    // Robust Song Resolution
    let activeSong = song;
    if ((!activeSong || Object.keys(activeSong).length === 0) && allSongs) {
        // Fallback: Find by ID from master list
        const targetIdString = String(viewingChart.songId);
        
        console.warn("⚠️ [ChartModal] Song prop empty. Searching master list of", allSongs.length, "songs for ID:", targetIdString);
        console.log("🔥 [ChartModal] Available IDs:", allSongs.map(s => String(s.id)));
        
        if (activeSong) {
            console.log("✅ [ChartModal] Found song via fallback:", activeSong.title);
        } else {
            console.error("❌ [ChartModal] Retrieval FAILED. Song not in master list.");
            // DIAGNOSTIC FORCE FEED
            if (allSongs.length > 0) {
                console.warn("⚠️ [ChartModal] DEBUG: Force-feeding first song from list to verify data presence.");
                activeSong = allSongs[0];
                // Mark title to show it's forced
                activeSong = { ...activeSong, title: `[DEBUG: FORCED] ${activeSong.title}` };
            }
        }
    }

    const handleFile = (e) => {
        onUpload(e, viewingChart.songId);
    };

    if (showSnipper) {
        // Pass FULL URL to ensure busId/channelId params reach the backend for legacy lookups
        return <SnippetMaker 
            key={viewingChart.url.split('?')[0]} // Stable key based on path only
            fileUrl={viewingChart.url} 
            song={activeSong} 
            user={user} 
            onClose={() => setShowSnipper(false)} 
            onSave={() => {
                // User Request: Stay in snippet view after save
                // onClose(); 
            }}
            targetId={viewingChart.songId}
        />;
    }

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
                    
                    {/* SNIPPER TOGGLE */}
                     <button onClick={() => setShowSnipper(true)} style={{
                        padding: '6px 12px', background: 'transparent', border: '1px solid #ffaa00', borderRadius: '4px',
                        color: '#ffaa00', display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize:'0.9em'
                    }} className="hover:bg-gray-800">
                        <Scissors size={16} /> <span style={{display: isMobile ? 'none' : 'inline'}}>Digital Scissors</span>
                    </button>

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
    // PERSISTENCE: Load last selected song from local storage
    const [selectedSongId, setSelectedSongId] = useState(() => {
        try {
            const saved = localStorage.getItem('musician_lastSongId');
            return saved ? saved : null;
        } catch (e) {
            return null;
        }
    }); 
    const [expandedSong, setExpandedSong] = useState(null); // For Mobile Accordion
    const [activePart, setActivePart] = useState(null); // { songId, partName }
    
    const [viewingChart, setViewingChart] = useState(null); // { url, songId }
    
    // PERSISTENCE: Save on change
    useEffect(() => {
        if (selectedSongId) {
            localStorage.setItem('musician_lastSongId', selectedSongId);
        }
    }, [selectedSongId]);

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
    // Handle both Array (Legacy/Converted) and Object (Server default)
    const rawSongs = Array.isArray(effectiveSetlist.songs) 
        ? effectiveSetlist.songs 
        : Object.values(effectiveSetlist.songs || {});

    // SCHEMA NORMALIZE: Ensure 'parts' alias exists (backend uses 'cues')
    const songs = rawSongs.map(s => ({
        ...s,
        parts: s.parts || s.cues || []
    }));
    
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
    const handleViewChart = (songOrId) => {
         let songObj = songOrId;
         
         // Fallback: If passed ID string, find the object in master list
         if (typeof songOrId === 'string' || typeof songOrId === 'number') {
             console.warn("⚠️ [MusicianSetlist] handleViewChart received ID, performing lookup:", songOrId);
             songObj = songs.find(s => String(s.id) === String(songOrId));
         }

         if (!songObj) {
             console.error("❌ [MusicianSetlist] Could not resolve song for chart view:", songOrId);
             alert("Error: Song data not found.");
             return;
         }

         const songId = songObj.id;
         // DEBUG ID
         console.log("🔥 [MusicianSetlist] Viewing Chart for ID:", songId, "Object:", songObj);

         const role = user.role ? user.role.replace(/[^a-z0-9]/gi, '_') : 'musician';
         const busId = user.mixBusId;
         const chId = user.linkedChannels?.[0];
         
         const url = `/api/charts/${songId}/${role}?busId=${busId}&channelId=${chId}&t=${Date.now()}`;
         // Pass the full song object into state to avoid re-lookup failures
         setViewingChart({ url, songId, songObject: songObj });
    };

    const handleUpload = async (e, songId) => {
        const file = e.target.files[0];
        if (!file) return;

        const inputChannel = user.linkedChannels?.[0];
        if (!inputChannel) {
            alert("Error: You have no linked channels.");
            return;
        }

        const formData = new FormData();
        formData.append('songId', songId);
        formData.append('inputChannel', inputChannel);
        formData.append('monitorBus', user.mixBusId);
        
        // Pass 'role' for filename generation
        const uploadRole = user.role || `channel_${inputChannel}`;
        formData.append('role', uploadRole);
        
        formData.append('chart', file); // Use 'chart' field name to match backend

        try {
            await axios.post('/api/charts/assign', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            // Refresh chart view
            handleViewChart(songId);
        } catch (err) {
            console.error("Upload failed", err);
            alert("Failed to upload chart: " + (err.response?.data?.error || err.message));
        }
    };

    // ... (rest of logic preserved implicitly by removal of this block)

    // Listen for Ableton Time
    const [abletonTime, setAbletonTime] = useState(null);
    const [approachState, setApproachState] = useState({ isApproaching: false, beatsRemaining: null });
    const [visualActiveIndex, setVisualActiveIndex] = useState(null); // Look-ahead active state

    useEffect(() => {
        if (!socket) return;
        
        const onAbletonTime = (data) => {
             // New Payload: data.relativeBar, data.relativeBeat, data.totalBars, data.signature, data.tempo
             const { relativeBar, relativeBeat, totalBars, signature, tempo } = data;
             
             if (relativeBar === undefined) return;

             // 1. Basic State
             setAbletonTime({
                 bar: relativeBar,
                 beat: relativeBeat,
                 totalBars: totalBars,
                 signature: signature,
                 bpm: tempo || 120,
                 formatted: totalBars > 0 ? `Bar ${relativeBar} of ${totalBars}` : `Bar ${relativeBar}.${relativeBeat}`,
                 isDownbeat: relativeBeat === 1
             });

             // 2. Anticipatory Transition Logic
             // Calculate time remaining in current cue section
             if (totalBars && tempo && activePart?.partIndex !== undefined) {
                 const barsLeft = totalBars - relativeBar; // e.g. 0.5 bars left
                 const beatsPerBar = signature?.numerator || 4;
                 const secondsPerBeat = 60 / (tempo || 120);
                 const secondsLeft = barsLeft * beatsPerBar * secondsPerBeat; // Approximate

                 // If less than 1.5s remaining, trigger visual switch to NEXT cue (User Request: earlier transition)
                 // But only if we aren't already visually ahead
                 if (secondsLeft < 1.5 && secondsLeft > 0) {
                     setVisualActiveIndex(activePart.partIndex + 1);
                 } else if (secondsLeft >= 1.5) {
                     // Reset to actual if we are far enough away (handling loops or pauses)
                     setVisualActiveIndex(activePart.partIndex);
                 }
             } else {
                 // Fallback if no timing data
                 if (activePart?.partIndex !== undefined) {
                     setVisualActiveIndex(activePart.partIndex);
                 }
             }

             // 3. Proactive Cue Logic (2-Bar Warning)
             if (totalBars > 0 && relativeBar > (totalBars - 2)) {
                 // We are in the last 2 bars (e.g. Bar 7 or 8 of 8)
                 const sigNum = signature?.num || 4;
                 const globalBeatInSong = ((relativeBar - 1) * sigNum) + relativeBeat;
                 const totalBeatsInSong = totalBars * sigNum;
                 const remaining = totalBeatsInSong - globalBeatInSong + 1; // +1 because we are ON the current beat
                 
                 setApproachState({ 
                     isApproaching: true, 
                     beatsRemaining: remaining > 0 ? remaining : 0 
                 });
             } else {
                 setApproachState({ isApproaching: false, beatsRemaining: null });
             }
        };

        socket.on('ableton_time', onAbletonTime);
        return () => {
            socket.off('ableton_time', onAbletonTime);
        };
    }, [socket, activePart]);


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
                <PulseStyle />
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
                        <div className={approachState.isApproaching ? "pulse-warning" : ""} style={{
                            textAlign: 'right', opacity: 0.8,
                            padding: '5px 10px', borderRadius: '6px',
                            transition: 'all 0.1s'
                        }}>
                            <div style={{
                                fontSize: '0.8em', 
                                color: approachState.isApproaching ? '#ffaa00' : (nextType === 'CUE' ? '#00bb00' : '#888'), 
                                textTransform: 'uppercase', letterSpacing: '1px', fontWeight: 'bold',
                                display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '8px'
                            }}>
                                {approachState.isApproaching && <Clock size={14} />}
                                {nextType === 'CUE' ? 'Next Cue' : 'Up Next'}
                            </div>
                            
                            <div style={{display:'flex', alignItems:'baseline', justifyContent:'flex-end', gap:'10px'}}>
                                <div style={{fontSize: '1.2em', color: '#fff', fontWeight: 'bold'}}>
                                    {nextLabel}
                                </div>
                                {approachState.isApproaching && (
                                    <div style={{
                                        fontSize: '1.5em', fontWeight: 'bold', color: '#ffaa00',
                                        fontFamily: 'monospace'
                                    }}>
                                        {approachState.beatsRemaining}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
                
                <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
                    {/* LEFT PANEL: SONG LIST */}
                    <div style={{ width: '220px', background: '#1a1a1a', borderRight: '1px solid #333', overflowY: 'auto' }}>
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
                                user={user}
                                visualActiveIndex={visualActiveIndex}
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
                       song={viewingChart.songObject || {}}
                       allSongs={songs} // Pass master list for fallback lookup
                       onClose={() => setViewingChart(null)} 
                       onUpload={(e, id) => {
                           handleUpload(e, id);
                       }}
                       user={user}
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
                                user={user}
                                visualActiveIndex={visualActiveIndex}
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
                    song={viewingChart.songObject || {}}
                    allSongs={songs} // Pass master list for fallback lookup
                    user={user}
                    onClose={() => setViewingChart(null)} 
                    onUpload={handleUpload}
                    isMobile={isMobile}
                />
            )}
        </div>
    );
};
// Remove old ChartModal outside


export default MusicianSetlist;


