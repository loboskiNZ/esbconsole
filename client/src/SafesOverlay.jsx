import React, { useState, useEffect } from 'react';
import axios from 'axios';

const SafesOverlay = ({ onClose }) => {
    const [safes, setSafes] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios.get('/api/config/safes')
            .then(res => {
                setSafes(res.data || []);
                setLoading(false);
            })
            .catch(err => {
                console.error("Failed to load safes", err);
                setLoading(false);
            });
    }, []);

    const toggleSafe = (chId) => {
        const idStr = String(chId);
        let newSafes;
        if (safes.includes(idStr)) {
            newSafes = safes.filter(s => s !== idStr);
        } else {
            newSafes = [...safes, idStr];
        }
        setSafes(newSafes);
    };

    const save = () => {
        axios.post('/api/config/safes', { safes })
            .then(() => onClose())
            .catch(err => alert("Failed to save: " + err.message));
    };

    const gridStyle = {
        display: 'grid',
        gridTemplateColumns: 'repeat(8, 1fr)',
        gap: '10px',
        margin: '20px 0'
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.85)', zIndex: 2000,
            display: 'flex', alignItems: 'center', justifyContent: 'center'
        }}>
            <div style={{
                background: '#222', padding: '30px', borderRadius: '12px',
                width: '800px', maxWidth: '90vw', border: '1px solid #444',
                color: '#fff'
            }}>
                <h2>🛡️ Channel Safes</h2>
                <p style={{color: '#aaa', marginBottom: '20px'}}>
                    Channels marked as SAFE will <strong>NOT</strong> be changed when loading a Scene.
                </p>

                {loading ? <div>Loading...</div> : (
                    <div style={gridStyle}>
                        {Array.from({length: 32}, (_, i) => i + 1).map(ch => {
                            const isSafe = safes.includes(String(ch));
                            return (
                                <div key={ch} 
                                    onClick={() => toggleSafe(ch)}
                                    style={{
                                        border: isSafe ? '2px solid #0f0' : '1px solid #555',
                                        background: isSafe ? '#003300' : '#333',
                                        padding: '15px', borderRadius: '6px',
                                        cursor: 'pointer', textAlign: 'center',
                                        transition: 'all 0.2s'
                                    }}
                                >
                                    <div style={{fontSize: '1.2em', fontWeight: 'bold'}}>CH {ch}</div>
                                    <div style={{fontSize: '0.8em', color: isSafe ? '#0f0' : '#888'}}>
                                        {isSafe ? 'SAFE' : 'Active'}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <div style={{display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px'}}>
                    <button onClick={onClose} style={{
                        padding: '10px 20px', background: '#555', color: '#fff', 
                        border: 'none', borderRadius: '4px', cursor: 'pointer'
                    }}>Cancel</button>
                    <button onClick={save} style={{
                        padding: '10px 20px', background: '#00cc00', color: '#000', fontWeight: 'bold',
                        border: 'none', borderRadius: '4px', cursor: 'pointer'
                    }}>Save Configuration</button>
                </div>
            </div>
        </div>
    );
};

export default SafesOverlay;
