import React, { useState, useEffect } from 'react';
import axios from 'axios';

const MusiciansManager = ({ onClose }) => {
    const [musicians, setMusicians] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [dirty, setDirty] = useState(false);

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

    return (
        <div style={{
            position:'fixed', top:'10%', left:'10%', width:'80%', height:'80%', 
            background:'#222', zIndex:3100, border:'1px solid #444', 
            borderRadius:'8px', display:'flex', flexDirection:'column',
            boxShadow:'0 0 50px rgba(0,0,0,0.8)'
        }}>
            <div style={{
                padding:'15px', borderBottom:'1px solid #333', background:'#1a1a1a', 
                borderRadius:'8px 8px 0 0', display:'flex', justifyContent:'space-between', alignItems:'center'
            }}>
                <h2 style={{margin:0, color:'#ffaa00'}}>Musicians Roster</h2>
                <div style={{display:'flex', gap:'10px'}}>
                    {dirty && <button onClick={saveMusicians} style={{padding:'5px 15px', background:'#0f0', color:'#000', border:'none', borderRadius:'4px', fontWeight:'bold', cursor:'pointer'}}>SAVE CHANGES</button>}
                    <button onClick={onClose} style={{background:'#f00', color:'#fff', border:'none', padding:'5px 15px', borderRadius:'4px', cursor:'pointer'}}>CLOSE</button>
                </div>
            </div>

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
        </div>
    );
};

export default MusiciansManager;
