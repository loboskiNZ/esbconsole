import React, { useState, useEffect, useMemo } from 'react';
import { ChevronLeft, ChevronRight, FileText, Wifi, Lock } from 'lucide-react';

const MusicianCharts = ({ user, setlist, socket, activePart }) => {
    // 1. Flatten Setlist to workable Playlist
    const playlist = useMemo(() => {
        if (!setlist || !setlist.songs) return [];
        
        let order = [];
        const setlistData = setlist.setlists?.[setlist.activeSetlistId || 'default'];
        
        if (setlistData && setlistData.songOrder && setlistData.songOrder.length > 0) {
            // Ordered Setlist
            order = setlistData.songOrder.map(id => setlist.songs[id]).filter(Boolean);
        } else {
            // Fallback: All songs (shouldn't happen in live mode usually)
            order = Object.values(setlist.songs);
        }
        return order;
    }, [setlist]);

    // 2. State
    const [currentIndex, setCurrentIndex] = useState(0);
    const [autoSync, setAutoSync] = useState(true);

    // 3. Current Song & Chart Resolution
    const currentSong = playlist[currentIndex];
    
    const currentChartUrl = useMemo(() => {
        if (!currentSong || !user) return null;

        // Use the API endpoint which handles resolution + 404 safety
        // Route: /api/charts/:songId/:role?busId=...&channelId=...
        // We pass both mixBusId and the first linked channel to increase match probability
        const role = user.role ? user.role.replace(/[^a-z0-9]/gi, '_') : 'default';
        const busIdFromUser = user.mixBusId;
        const channelIdFromUser = user.linkedChannels?.[0];

        // We check if an assignment exists LOCALLY first to decide if we should even show the viewer
        // This preserves the UI logic (Show Upload button if no chart)
        if (currentSong.chartAssignments) {
             const match = currentSong.chartAssignments.find(assign => {
                const busMatch = busIdFromUser && String(assign.monitorBus) === String(busIdFromUser);
                const chMatch = channelIdFromUser && String(assign.inputChannel) === String(channelIdFromUser);
                return busMatch || chMatch;
             });

             if (match) {
                 // Return the Safe API URL
                 // Timestamp to bust cache on re-upload
                 return `/api/charts/${currentSong.id}/${role}?busId=${busIdFromUser}&channelId=${channelIdFromUser}&t=${Date.now()}`;
             }
        }
        
        return null;
    }, [currentSong, user]);

    // 4. Input Handlers
    const handleNext = () => {
        if (currentIndex < playlist.length - 1) {
            setCurrentIndex(prev => prev + 1);
            setAutoSync(false); // User took control
        }
    };

    const handlePrev = () => {
        if (currentIndex > 0) {
            setCurrentIndex(prev => prev - 1);
            setAutoSync(false);
        }
    };
    
    // NEW: Upload Handler
    const handleCheckAndUpload = () => {
        document.getElementById('chart-upload-input').click();
    };
    
    const handleFileChange = async (e) => {
        const file = e.target.files[0];
        if (!file || !currentSong) return;
        
        // Use primary linked channel (user.linkedChannels[0]) or allow selection?
        // User said: "a chart is linked to a channel which can be matxched to the channels of the musician"
        // Minimal config: Just use the first linked channel.
        const inputChannel = user.linkedChannels?.[0];
        if (!inputChannel) {
            alert("Error: You have no linked channels.");
            return;
        }

        const formData = new FormData();
        // IMPORTANT: Append text fields BEFORE file so Multer can read them in destination()
        formData.append('songId', currentSong.id);
        formData.append('inputChannel', inputChannel);
        formData.append('monitorBus', user.mixBusId);
        
        // Pass 'role' for filename generation in backend (chartStorage)
        // Sanitizing user role or falling back to channel
        const uploadRole = user.role || `channel_${inputChannel}`;
        formData.append('role', uploadRole);

        formData.append('chart', file);
        
        try {
            // Import axios locally or ensure it's available? 
            // Better to assume global axios or fetch.
            // Let's use fetch for zero-dep or assume axios.
            // index.js expects 'chart' field.
            
            // Using standard fetch logic to avoid broken import if axios missing in this file scope
            // (Though MusicianApp uses axios, so it likely is available if imported)
            // Let's add import axios at top if needed, or use fetch.
            // We'll use fetch for safety here.
            
            const res = await fetch('/api/charts/assign', {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            
            if (json.success) {
                // Force Reload?
                alert("Chart Uploaded!");
                // We need to trigger a re-computation of 'currentChartUrl'.
                // Ideally we update the 'setlist' prop or force a reload.
                // Since 'setlist' comes from parent, we can't easily mutate it.
                // Quickest fix: Reload page? Or callback?
                window.location.reload(); 
            } else {
                alert("Upload Failed: " + json.error);
            }
        } catch (err) {
            console.error(err);
            alert("Upload Error");
        }
    };

    // 5. Sync from Server (Admin triggers)
    useEffect(() => {
        if (activePart && activePart.songId && autoSync) {
            const idx = playlist.findIndex(s => String(s.id) === String(activePart.songId));
            if (idx !== -1) {
                setCurrentIndex(idx);
            }
        }
    }, [activePart, playlist, autoSync]);

    if (playlist.length === 0) {
        return (
            <div style={{
                height: '100%', display: 'flex', flexDirection: 'column', 
                alignItems: 'center', justifyContent: 'center', color: '#666'
            }}>
                <FileText size={48} style={{marginBottom: '20px', opacity: 0.5}}/>
                <h2>No Songs in Setlist</h2>
            </div>
        );
    }

    return (
        <div style={{
            height: '100%', display: 'flex', flexDirection: 'column', 
            background: '#000', color: 'white'
        }}>
            {/* HIDDEN INPUT */}
            <input 
                type="file" 
                id="chart-upload-input" 
                style={{display:'none'}} 
                accept="application/pdf,image/*"
                onChange={handleFileChange} 
            />

            {/* TOOLBAR */}
            <div style={{
                height: '60px', background: '#111', borderBottom: '1px solid #333',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                padding: '0 20px'
            }}>
                <div style={{display:'flex', gap:'10px', alignItems:'center'}}>
                    <button 
                        onClick={handlePrev} disabled={currentIndex === 0}
                        style={{
                            background: 'transparent', border: '1px solid #444', color: 'white',
                            padding: '8px', borderRadius: '4px', cursor: currentIndex === 0 ? 'default' : 'pointer',
                            opacity: currentIndex === 0 ? 0.3 : 1
                        }}
                    >
                        <ChevronLeft size={24} />
                    </button>
                    
                    <div>
                        <div style={{fontWeight: 'bold', fontSize: '1.1em'}}>
                            {currentSong ? currentSong.title : "Loading..."}
                        </div>
                        <div style={{fontSize: '0.8em', color: '#888'}}>
                            Song {currentIndex + 1} of {playlist.length}
                            {currentSong?.artist && ` • ${currentSong.artist}`}
                        </div>
                    </div>
                </div>

                <div style={{display:'flex', gap:'15px', alignItems:'center'}}>
                    {/* UPLOAD BUTTON (Small) */}
                    <button
                        onClick={handleCheckAndUpload}
                        style={{
                            background: '#333', border: '1px solid #444', color: '#ccc',
                            padding: '6px 12px', borderRadius: '4px', cursor: 'pointer',
                            display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8em'
                        }}
                    >
                        <FileText size={14}/> Replace
                    </button>

                    <button
                        onClick={() => setAutoSync(!autoSync)}
                        style={{
                            background: autoSync ? 'rgba(0, 200, 0, 0.2)' : 'transparent',
                            border: autoSync ? '1px solid #00bb00' : '1px solid #444',
                            color: autoSync ? '#00ff00' : '#888',
                            padding: '6px 12px', borderRadius: '4px', cursor: 'pointer',
                            display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.9em'
                        }}
                    >
                        {autoSync ? <Wifi size={16}/> : <Lock size={16}/>}
                        {autoSync ? "SYNC ON" : "SYNC OFF"}
                    </button>

                    <button 
                        onClick={handleNext} disabled={currentIndex === playlist.length - 1}
                        style={{
                            background: '#222', border: '1px solid #444', color: 'white',
                            padding: '8px 12px', borderRadius: '4px', cursor: currentIndex === playlist.length - 1 ? 'default' : 'pointer',
                            opacity: currentIndex === playlist.length - 1 ? 0.3 : 1,
                            display: 'flex', alignItems: 'center', gap: '5px'
                        }}
                    >
                        Next <ChevronRight size={20} />
                    </button>
                </div>
            </div>

            {/* VIEWER */}
            <div style={{flex: 1, position: 'relative', overflow: 'hidden', background: '#222'}}>
                {currentChartUrl ? (
                    <iframe 
                        key={currentChartUrl} // Force re-render on change
                        src={currentChartUrl}
                        style={{width: '100%', height: '100%', border: 'none', background: 'white'}}
                        title="Chart Viewer"
                    />
                ) : (
                    <div style={{
                        height: '100%', display: 'flex', flexDirection: 'column', 
                        alignItems: 'center', justifyContent: 'center', color: '#666'
                    }}>
                        <FileText size={64} style={{marginBottom: '20px', opacity: 0.3}}/>
                        <h3>No Chart Found</h3>
                        <p style={{maxWidth: '300px', textAlign: 'center', fontSize: '0.9em', marginBottom:'20px'}}>
                            No chart assigned to your channels for this song. 
                            (Channels: {user.linkedChannels.join(', ')})
                        </p>
                        
                        {/* BIG UPLOAD BUTTON */}
                        <button 
                            onClick={handleCheckAndUpload}
                            style={{
                                background: '#2266aa', color: 'white', border: 'none',
                                padding: '12px 24px', borderRadius: '4px', fontSize: '1.1em',
                                cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '10px'
                            }}
                        >
                            <FileText size={20}/> Upload Chart
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MusicianCharts;
