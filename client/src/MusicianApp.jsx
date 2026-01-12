
import React, { useState, useEffect, Suspense } from 'react';
import LoginScreen from './LoginScreen';
import MusicianLayout from './MusicianLayout';
import ErrorBoundary from './components/ErrorBoundary';
import axios from 'axios';

import MusicianMix from './MusicianMix';
import MusicianSetlist from './MusicianSetlist';
// const LoginScreen = React.lazy(...) // Login is critical, keep eager or lazy? Eager is fine.

const MusicianApp = ({ socket, x32State }) => {
    const [musician, setMusician] = useState(null);
    const [activeView, setActiveView] = useState('monitors');
    const [setlist, setSetlist] = useState(null);

    useEffect(() => {
        // Check session
        const stored = localStorage.getItem('musician_user');
        if (stored) {
            try {
                setMusician(JSON.parse(stored));
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

    const handleLogin = (user) => {
        setMusician(user);
    };

    const handleLogout = () => {
        localStorage.removeItem('musician_token');
        localStorage.removeItem('musician_user');
        setMusician(null);
    };

    if (!musician) {
        return <LoginScreen onLoginSuccess={handleLogin} />;
    }

    return (
        <ErrorBoundary>
            <MusicianLayout 
                user={musician} 
                onLogout={handleLogout}
                activeTab={activeView}
                onTabChange={setActiveView}
            >
                     {activeView === 'monitors' && (
                         <MusicianMix 
                            socket={socket} 
                            x32State={x32State} 
                            user={musician} 
                        />
                     )}

                     {activeView === 'setlist' && (
                         <MusicianSetlist 
                            user={musician}
                            setlist={setlist}
                            socket={socket}
                         />
                     )}
                 
                 {activeView === 'charts' && (
                    <div style={{padding:'20px', color:'#888', textAlign:'center', marginTop:'50px'}}>
                        <h3>Charts View</h3>
                        <p>Access charts via the Setlist tab by clicking on a song.</p>
                    </div>
                 )}
            </MusicianLayout>
        </ErrorBoundary>
    );
};

export default MusicianApp;
