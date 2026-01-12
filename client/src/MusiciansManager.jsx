import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { QRCodeSVG } from 'qrcode.react';

const MusiciansManager = ({ onClose }) => {
    const [musicians, setMusicians] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [dirty, setDirty] = useState(false);
    
    // New State for Modals
    const [showQR, setShowQR] = useState(false);
    const [showPasswordPrompt, setShowPasswordPrompt] = useState(false);
    const [newPassword, setNewPassword] = useState("");

    useEffect(() => {
        fetchMusicians();
    }, []);

    // Safe ID generator
    const generateId = () => Date.now().toString(36) + Math.random().toString(36).substr(2);

    const fetchMusicians = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/musicians');
            // Ensure IDs
            const data = (res.data || []).map(m => ({...m, id: m.id || generateId() }));
            setMusicians(data);
            setDirty(false);
        } catch (e) {
            console.error(e);
            setError(e.message || "Failed to load");
        } finally {
            setLoading(false);
        }
    };

    const saveMusicians = async () => {
        setLoading(true);
        try {
            await axios.post('/api/musicians', musicians);
            setDirty(false);
            alert("Saved!");
        } catch (e) {
            setError(e.message);
        } finally {
            setLoading(false);
        }
    };

    const updateMusician = (id, field, value) => {
        setMusicians(prev => prev.map(m => m.id === id ? { ...m, [field]: value } : m));
        setDirty(true);
    };

    const addMusician = () => {
        setMusicians([...musicians, {
            id: generateId(),
            name: 'New Musician',
            role: 'Role',
            email: '',
            mixBusId: 0,
            linkedChannels: []
        }]);
        setDirty(true);
    };

    const removeMusician = (id) => {
        if(!confirm("Delete this musician?")) return;
        setMusicians(prev => prev.filter(m => m.id !== id));
        setDirty(true);
    };

    // Helper to parse channels array
    const handleChannelsChange = (id, str) => {
        // "1, 2, 3" -> [1,2,3]
        const arr = str.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
        updateMusician(id, 'linkedChannels', arr);
    };

    // Password Logic
    const handlePasswordSave = async () => {
        if (!newPassword) return;
        try {
            await axios.post('/api/system/password', { password: newPassword });
            setShowPasswordPrompt(false);
            alert("Password Updated!");
        } catch (e) {
            alert("Failed to update password");
        }
    };

    const musicianUrl = window.location.protocol + "//" + window.location.hostname + ":" + window.location.port + "/musician";

    return (
        <div style={{
            position: 'fixed', top: 50, left: 50, right: 50, bottom: 50,
            background: '#222', border: '1px solid #444', borderRadius: '8px',
            display: 'flex', flexDirection: 'column', zIndex: 2000, boxShadow: '0 0 50px rgba(0,0,0,0.8)'
        }}>
            {/* Header */}
            <div style={{padding: '20px', borderBottom: '1px solid #444', display:'flex', justifyContent:'space-between', alignItems:'center', background:'#1a1a1a', borderRadius:'8px 8px 0 0'}}>
                <h2 style={{margin:0, color:'#ffaa00'}}>Musicians Roster</h2>
                <div style={{display:'flex', gap:'10px'}}>
                     <button 
                        onClick={() => setShowQR(true)}
                        style={{background:'#0088ff', color:'white', border:'none', padding:'8px 15px', borderRadius:'4px', cursor:'pointer'}}
                    >
                        📱 Show QR
                    </button>
                    <button 
                        onClick={() => {
                            axios.get('/api/system/password').then(res => setNewPassword(res.data.password));
                            setShowPasswordPrompt(true);
                        }}
                        style={{background:'#e67e22', color:'white', border:'none', padding:'8px 15px', borderRadius:'4px', cursor:'pointer'}}
                    >
                        🔒 Set Password
                    </button>
                    {dirty && <button onClick={saveMusicians} style={{padding:'8px 15px', background:'#0f0', color:'#000', border:'none', borderRadius:'4px', fontWeight:'bold', cursor:'pointer'}}>SAVE CHANGES</button>}
                    <button onClick={onClose} style={{background:'#555', color:'white', border:'none', padding:'8px 15px', borderRadius:'4px', cursor:'pointer'}}>Close</button>
                </div>
            </div>

            {/* Content Table */}
            <div style={{flex:1, overflowY:'auto', padding:'20px'}}>
                {loading && <div style={{color:'#888'}}>Loading...</div>}
                
                <table style={{width:'100%', borderCollapse:'collapse', color:'#ddd'}}>
                    <thead>
                        <tr style={{borderBottom:'1px solid #444', textAlign:'left'}}>
                            <th style={{padding:'10px'}}>Name</th>
                            <th style={{padding:'10px'}}>Role</th>
                            <th style={{padding:'10px'}}>Email</th>
                            <th style={{padding:'10px'}}>MixBus ID</th>
                            <th style={{padding:'10px'}}>Channels</th>
                            <th style={{padding:'10px'}}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {musicians.map(m => (
                            <tr key={m.id} style={{borderBottom:'1px solid #333'}}>
                                <td style={{padding:'5px'}}>
                                    <input value={m.name} onChange={e => updateMusician(m.id, 'name', e.target.value)} style={{background:'#111', border:'1px solid #333', color:'white', padding:'5px', width:'100%'}} />
                                </td>
                                <td style={{padding:'5px'}}>
                                    <input value={m.role} onChange={e => updateMusician(m.id, 'role', e.target.value)} style={{background:'#111', border:'1px solid #333', color:'white', padding:'5px', width:'100%'}} />
                                </td>
                                <td style={{padding:'5px'}}>
                                    <input value={m.email} onChange={e => updateMusician(m.id, 'email', e.target.value)} style={{background:'#111', border:'1px solid #333', color:'white', padding:'5px', width:'100%'}} />
                                </td>
                                <td style={{padding:'5px'}}>
                                    <input type="number" value={m.mixBusId} onChange={e => updateMusician(m.id, 'mixBusId', parseInt(e.target.value))} style={{background:'#111', border:'1px solid #333', color:'white', padding:'5px', width:'60px'}} />
                                </td>
                                <td style={{padding:'5px'}}>
                                    <input 
                                        defaultValue={m.linkedChannels ? m.linkedChannels.join(', ') : ''} 
                                        onBlur={e => handleChannelsChange(m.id, e.target.value)}
                                        placeholder="1, 2..."
                                        style={{background:'#111', border:'1px solid #333', color:'white', padding:'5px', width:'100%'}} 
                                    />
                                </td>
                                <td style={{padding:'5px'}}>
                                    <button onClick={() => removeMusician(m.id)} style={{color:'#f55', background:'transparent', border:'none', cursor:'pointer'}}>🗑️</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                
                <button onClick={addMusician} style={{marginTop:'20px', padding:'10px 20px', background:'#333', color:'white', border:'1px solid #555', borderRadius:'4px', cursor:'pointer'}}>
                    + Add Musician
                </button>
            </div>

            {/* QR OVERLAY */}
            {showQR && (
                <div style={{
                    position:'fixed', top:0, left:0, right:0, bottom:0, background:'rgba(0,0,0,0.9)', 
                    display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', zIndex: 3000
                }} onClick={() => setShowQR(false)}>
                    <div style={{background:'white', padding:'40px', borderRadius:'20px', textAlign:'center'}} onClick={e => e.stopPropagation()}>
                        <h2 style={{color:'black', marginTop:0}}>Scan to Join Studio</h2>
                        <QRCodeSVG value={musicianUrl} size={300} />
                        <p style={{color:'#333', fontSize:'1.2em', marginTop:'20px', fontFamily:'monospace'}}>{musicianUrl}</p>
                        <button onClick={() => setShowQR(false)} style={{marginTop:'20px', padding:'10px 30px', background:'#333', color:'white', border:'none', borderRadius:'6px', cursor:'pointer'}}>Close</button>
                    </div>
                </div>
            )}

            {/* PASSWORD PROMPT MODAL */}
            {showPasswordPrompt && (
                <div style={{
                    position:'fixed', top:0, left:0, right:0, bottom:0, background:'rgba(0,0,0,0.8)', 
                    display:'flex', alignItems:'center', justifyContent:'center', zIndex: 3000
                }}>
                    <div style={{background:'#333', padding:'30px', borderRadius:'8px', width:'300px', border:'1px solid #555'}}>
                        <h3 style={{marginTop:0, color:'#e67e22'}}>Set Studio Password</h3>
                        <p style={{color:'#aaa', fontSize:'0.9em'}}>Shared password for all musicians</p>
                        <input 
                            type="text" 
                            value={newPassword} 
                            onChange={e => setNewPassword(e.target.value)}
                            style={{width:'100%', padding:'10px', marginTop:'10px', background:'#222', border:'1px solid #444', color:'white', fontSize:'1.2em', boxSizing:'border-box'}}
                        />
                        <div style={{marginTop:'20px', display:'flex', justifyContent:'flex-end', gap:'10px'}}>
                            <button onClick={() => setShowPasswordPrompt(false)} style={{background:'transparent', color:'#aaa', border:'none', cursor:'pointer'}}>Cancel</button>
                            <button onClick={handlePasswordSave} style={{background:'#e67e22', color:'white', border:'none', padding:'8px 15px', borderRadius:'4px', cursor:'pointer'}}>Save</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default MusiciansManager;
