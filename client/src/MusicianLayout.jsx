import React, { useState } from 'react';
import { Sliders, ListMusic, FileText, LogOut, Save } from 'lucide-react';

const MusicianLayout = ({ children, user, onLogout, onSave, activeTab, onTabChange }) => {
    // Navigation Items
    const mixLabel = user && user.mixBusId ? `Bus ${user.mixBusId}` : 'Mix';
    
    const navItems = [
        { id: 'monitors', icon: <Sliders size={24} />, label: mixLabel },
        { id: 'setlist', icon: <ListMusic size={24} />, label: 'Setlist' },
        { id: 'save', icon: <Save size={24} />, label: 'Save', action: onSave }, // New Save Action
        { id: 'charts', icon: <FileText size={24} />, label: 'Charts' },
        { id: 'logout', icon: <LogOut size={24} />, label: 'Logout', action: onLogout }
    ];

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            height: '100dvh', width: '100%',
            display: 'flex', flexDirection: 'column', 
            background: '#121212', color: 'white', fontFamily: 'sans-serif',
            overflow: 'hidden'
        }}>
            
            {/* MAIN CONTENT AREA */}
            <div style={{
                flex: 1, display: 'flex', flexDirection: 'column', 
                position: 'relative', overflow: 'hidden',
                paddingBottom: '70px' // Use padding to clear the fixed nav
            }}>
                
                {/* NO HEADERS - Content fills area */}
                <div style={{
                    flex: 1, overflowY: 'hidden', 
                    display: 'flex', flexDirection: 'column',
                    background: 'linear-gradient(to bottom, #121212, #000)',
                }}>
                    {children}
                </div>
            </div>

            {/* NAVIGATION AREA (Floating Fixed Bottom) */}
            <div style={{
                position: 'fixed', bottom: 0, left: 0, width: '100%',
                background: '#1a1a1a', display: 'flex', flexDirection: 'row',
                boxShadow: '0 -5px 20px rgba(0,0,0,0.5)',
                zIndex: 1000,
                height: '70px',
                borderTop: '1px solid #333',
                // Handle safe area for iPhone X+
                paddingBottom: 'env(safe-area-inset-bottom)'
            }}>
                {navItems.map(item => (
                    <div 
                        key={item.id}
                        onClick={() => item.action ? item.action() : onTabChange(item.id)}
                        style={{
                            flex: 1, height: '100%',
                            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                            color: activeTab === item.id ? '#ffffff' : '#666666',
                            background: activeTab === item.id ? '#333333' : 'transparent',
                            cursor: 'pointer', transition: 'all 0.2s',
                            position: 'relative',
                            padding: '5px' // Ensure padding for touch
                        }}
                    >
                        <div style={{marginBottom: '0px'}}>{item.icon}</div>
                        {/* Label removed as requested */}
                        
                        {/* Active Indicator (Dot or Line?) User said "highlighted". */}
                        {/* Let's keep the line or maybe just color change is enough? */}
                        {/* The logic above sets color to white vs #666. */}
                        {/* Let's keep the line for clarity but make it subtle? */}
                        {activeTab === item.id && (
                            <div style={{
                                position: 'absolute',
                                left: '50%', transform: 'translateX(-50%)',
                                bottom: '5px',
                                width: '4px', height: '4px', borderRadius: '50%',
                                background: '#0088ff' // Small dot indicator instead of line?
                            }} />
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default MusicianLayout;
