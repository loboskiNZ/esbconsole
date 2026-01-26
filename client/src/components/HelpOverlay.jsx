import React, { useState } from 'react';
import { BookOpen, X, Headphones, Settings } from 'lucide-react';

const HelpOverlay = ({ onClose }) => {
    const [activeTab, setActiveTab] = useState('musician'); // 'musician' | 'admin'

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.9)', zIndex: 11000, // Very high z-index
            display: 'flex', alignItems: 'center', justifyContent: 'center'
        }}>
            <div style={{
                position: 'relative',
                width: '90%', maxWidth: '1000px', height: '85vh',
                background: '#1a1a1a', borderRadius: '12px', border: '1px solid #444',
                display: 'flex', flexDirection: 'column',
                boxShadow: '0 0 50px rgba(0,0,0,0.8)', overflow: 'hidden'
            }}>
                {/* HEADER */}
                <div style={{
                    padding: '20px', borderBottom: '1px solid #333',
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    background: '#222'
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
                        <div style={{ background: '#4466aa', padding: '10px', borderRadius: '8px' }}>
                            <BookOpen size={24} color="white" />
                        </div>
                        <div>
                            <h2 style={{ margin: 0, color: 'white' }}>User Manual</h2>
                            <span style={{ color: '#888', fontSize: '0.9em' }}>Interactive Guide & Documentation</span>
                        </div>
                    </div>
                    <button 
                        onClick={onClose}
                        style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#888' }}
                    >
                        <X size={32} />
                    </button>
                </div>

                {/* TABS */}
                <div style={{ display: 'flex', background: '#111', borderBottom: '1px solid #333' }}>
                    <Tab 
                        id="musician" 
                        label="Musician Guide" 
                        icon={<Headphones size={18} />} 
                        active={activeTab === 'musician'} 
                        onClick={() => setActiveTab('musician')} 
                    />
                    <Tab 
                        id="admin" 
                        label="Sound Engineer / Admin" 
                        icon={<Settings size={18} />} 
                        active={activeTab === 'admin'} 
                        onClick={() => setActiveTab('admin')} 
                    />
                </div>

                {/* CONTENT AREA */}
                <div style={{ flex: 1, overflowY: 'auto', padding: '30px', color: '#ddd', lineHeight: '1.6' }}>
                    
                    {activeTab === 'musician' && (
                        <div style={{ maxWidth: '800px', margin: '0 auto' }}>
                            <h1 style={{ color: '#44aaff' }}>Musicians Guide</h1>
                            <p style={{ fontSize: '1.1em', color:'#aaa' }}>
                                Welcome to the X32 Controller. This tool allows you to control your own monitor mix, launch scenes, and view live charts.
                            </p>
                            
                            <hr style={{ borderColor: '#333', margin: '30px 0' }} />

                            <Section title="1. Getting Started">
                                <p>To start using the system, check your email for your personal login credentials. Enter your email and password to access your secure monitor mix.</p>
                            </Section>

                            <Section title="2. Your Mix">
                                <p>Once logged in, you will see your personal mix faders. These control what you hear in your In-Ears or Monitor Wedge.</p>
                                <img 
                                    src="/manuals/musician_mixer.png" 
                                    alt="Musician Mixer View" 
                                    style={{ width: '100%', borderRadius: '8px', border: '1px solid #444', marginTop: '10px' }} 
                                />
                            </Section>

                             <Section title="3. Live Charts">
                                <p>The "Charts" tab displays the current song's chord chart or sheet music, automatically synced with the band leader.</p>
                                <img 
                                    src="/manuals/musician_charts.png" 
                                    alt="Musician Charts View" 
                                    style={{ width: '100%', borderRadius: '8px', border: '1px solid #444', marginTop: '10px' }} 
                                />
                            </Section>
                        </div>
                    )}

                    {activeTab === 'admin' && (
                        <div style={{ maxWidth: '800px', margin: '0 auto' }}>
                            <h1 style={{ color: '#ffaa00' }}>Sound Engineer & Admin Guide</h1>
                            <p style={{ fontSize: '1.1em', color:'#aaa' }}>
                                Advanced controls for configuring the system, network, scenes, and tech riders.
                            </p>

                            <hr style={{ borderColor: '#333', margin: '30px 0' }} />

                            <Section title="1. Admin Login">
                                <p>Access the Admin console by clicking the "Admin" link on the login page and entering the system password.</p>
                                <img 
                                    src="/manuals/admin_login.png" 
                                    alt="Admin Login" 
                                    style={{ width: '100%', borderRadius: '8px', border: '1px solid #444', marginTop: '10px' }} 
                                />
                            </Section>

                            <Section title="2. Scene Management">
                                <p>The main dashboard displays your setlists and songs. Use the top bar to save, load, and edit scenes.</p>
                                <img 
                                    src="/manuals/admin_scenes_list.png" 
                                    alt="Scenes List" 
                                    style={{ width: '100%', borderRadius: '8px', border: '1px solid #444', marginTop: '10px' }} 
                                />
                            </Section>

                            <Section title="3. Stage Plot Design">
                                <p>Navigate to "MUSICIANS" → "STAGE PLOT" to drag and drop instruments onto the virtual stage. This configures the layout used for the detailed Technical Rider.</p>
                                <img 
                                    src="/manuals/admin_stage_plot.png" 
                                    alt="Stage Plot Editor" 
                                    style={{ width: '100%', borderRadius: '8px', border: '1px solid #444', marginTop: '10px' }} 
                                />
                            </Section>
                        </div>
                    )}

                </div>
            </div>

            {/* Inline Styles for Placeholder Images */}
            <style>{`
                .placeholder-image {
                    width: 100%;
                    height: 200px;
                    background: #252525;
                    border: 2px dashed #444;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #666;
                    font-family: monospace;
                    margin: 20px 0;
                }
            `}</style>
        </div>
    );
};

const Tab = ({ id, label, icon, active, onClick }) => (
    <button 
        onClick={onClick}
        style={{
            flex: 1, padding: '20px', background: active ? '#1a1a1a' : '#222', border: 'none',
            borderBottom: active ? '3px solid #4466aa' : '3px solid transparent',
            color: active ? 'white' : '#666', cursor: 'pointer',
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px',
            fontSize: '1em', fontWeight: active ? 'bold' : 'normal', transition: 'all 0.2s'
        }}
    >
        {icon}
        {label}
    </button>
);

const Section = ({ title, children }) => (
    <div style={{ marginBottom: '40px' }}>
        <h3 style={{ color: 'white', borderLeft: '4px solid #555', paddingLeft: '15px', marginBottom: '15px' }}>
            {title}
        </h3>
        {children}
    </div>
);

export default HelpOverlay;
