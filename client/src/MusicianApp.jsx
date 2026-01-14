
import React, { useState, useEffect, Suspense, useCallback } from 'react';
import LoginScreen from './LoginScreen';
import MusicianLayout from './MusicianLayout';
import ErrorBoundary from './components/ErrorBoundary';
import axios from 'axios';

import MusicianMix from './MusicianMix';
import MusicianSetlist from './MusicianSetlist';
import MusicianCharts from './MusicianCharts';
import WelcomeSplash from './components/WelcomeSplash';

const MusicianApp = ({ socket, x32State }) => {
    const [musician, setMusician] = useState(null);
    const [activeView, setActiveView] = useState(() => localStorage.getItem('musician_last_view') || 'monitors');
    const [isGroupMode, setIsGroupMode] = useState(() => localStorage.getItem('musician_group_mode') === 'true'); 
    const [setlist, setSetlist] = useState(null);
    const [showSplash, setShowSplash] = useState(false);

    useEffect(() => {
        // Check session
        const stored = localStorage.getItem('musician_user');
        if (stored) {
            try {
                setMusician(JSON.parse(stored));
                setShowSplash(true); 
            } catch (e) {
                console.error("Invalid stored musician", e);
                localStorage.removeItem('musician_user');
            }
        }
        
        // Fetch Setlist
        axios.get('/api/setlist')
            .then(res => setSetlist(res.data))
            .catch(err => console.error("Failed to fetch setlist", err));

    }, []);

    // PERSIST VIEW STATE
    useEffect(() => {
        localStorage.setItem('musician_last_view', activeView);
    }, [activeView]);

    // PERSIST GROUP MODE
    useEffect(() => {
        localStorage.setItem('musician_group_mode', isGroupMode);
    }, [isGroupMode]);

    const handleLogin = (user) => {
        setMusician(user);
        setShowSplash(true); // Trigger splash on login
    };

    const handleLogout = () => {
        localStorage.removeItem('musician_token');
        localStorage.removeItem('musician_user');
        setMusician(null);
    };

    // UseCallback to prevent infinite splash loops
    const restoreMix = useCallback(() => {
        if (!musician) return;
        console.log("🔄 Restoring Mix for:", musician.name);
        
        // 1. Try LocalStorage
        const localMix = localStorage.getItem(`musician_mix_${musician.name}`);
        if (localMix) {
            try {
                const mixData = JSON.parse(localMix);
                console.log("📤 Sending Saved Mix to Console:", mixData);
                // Emit to Server with Bus ID
                if (socket) socket.emit('restore_musician_mix', { 
                    mixData, 
                    mixBusId: musician.mixBusId 
                });
            } catch (e) {
                console.error("Failed to parse saved mix", e);
            }
        }
    }, [musician, socket]);

    const handleSaveMix = () => {
        if (!x32State || !musician) return;

        console.log("💾 Saving Mix for:", musician.name);
        
        // Channels 1-32
        const channels = Array.from({length: 32}, (_, i) => String(i + 1));
        const mixSnapshot = {};
        
        channels.forEach(chId => {
             // Logic grabbed from MusicianMix
             // We need to access x32State safely
             let data = null;
             if (x32State[String(chId)]) data = x32State[String(chId)];
             else if (x32State[Number(chId)]) data = x32State[Number(chId)];
             
             if (data && data.mixSends) {
                 const mixBusKey = musician.mixBusId.toString();
                 if (data.mixSends[mixBusKey]) {
                     mixSnapshot[chId] = {
                         level: data.mixSends[mixBusKey].level,
                         on: data.mixSends[mixBusKey].on
                     };
                 }
             }
        });
        
        // 2. Save to LocalStorage
        try {
            localStorage.setItem(`musician_mix_${musician.name}`, JSON.stringify(mixSnapshot));
            alert("Mix Saved! 💾");
        } catch (e) {
            console.error("Save failed", e);
            alert("Failed to save mix.");
        }
    };

    const handleSplashComplete = useCallback(() => {
        setShowSplash(false);
    }, []);

    // --- SCREEN WAKE LOCK ---
    useEffect(() => {
        // Only attempt if musician is logged in
        if (!musician) return;

        let wakeLock = null;

        const requestWakeLock = async () => {
            if ('wakeLock' in navigator) {
                try {
                    wakeLock = await navigator.wakeLock.request('screen');
                    console.log('✅ Screen Wake Lock active');
                    
                    wakeLock.addEventListener('release', () => {
                        console.log('Screen Wake Lock released');
                    });
                } catch (err) {
                    console.error(`❌ Wake Lock Error: ${err.name}, ${err.message}`);
                }
            }
        };

        // Initial Request
        requestWakeLock();

        // Re-request on visibility change (if lost due to tab switch)
        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                requestWakeLock();
            }
        };
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
             document.removeEventListener('visibilitychange', handleVisibilityChange);
             if (wakeLock) wakeLock.release().catch(e => console.error(e));
        };
    }, [musician]);

    // Global Active Part Listener (for Sync)
    const [activePart, setActivePart] = useState(null);
    useEffect(() => {
        if (!socket) return;
        const onActivePart = (data) => {
            console.log("🔥 [MusicianApp] Active Part:", data);
            setActivePart(data);
        };
        socket.on('active_part', onActivePart);
        return () => socket.off('active_part', onActivePart);
    }, [socket]);

    if (!musician) {
        return <LoginScreen onLoginSuccess={handleLogin} />;
    }

    return (
        <ErrorBoundary>
            {showSplash && (
                <WelcomeSplash 
                    name={musician.name} 
                    onRestore={restoreMix}
                    onComplete={handleSplashComplete} 
                />
            )}
            <MusicianLayout 
                user={musician} 
                onLogout={handleLogout}
                onSave={handleSaveMix} 
                activeTab={activeView}
                onTabChange={setActiveView}
                isGroupMode={isGroupMode} // NEW
                onToggleGroupMode={() => setIsGroupMode(!isGroupMode)} // NEW
            >
                     {activeView === 'monitors' && (
                         <MusicianMix 
                            socket={socket} 
                            x32State={x32State} 
                            user={musician} 
                            isGroupMode={isGroupMode} // NEW
                        />
                     )}

                     {activeView === 'setlist' && (
                         <MusicianSetlist 
                            user={musician}
                            setlist={setlist}
                            socket={socket}
                            // Pass activePart if we want to centralize logic later
                         />
                     )}
                 
                 {activeView === 'charts' && (
                    <MusicianCharts
                        user={musician}
                        setlist={setlist}
                        socket={socket}
                        activePart={activePart} // Syncs with Admin
                    />
                 )}
            </MusicianLayout>
        </ErrorBoundary>
    );
};

export default MusicianApp;
