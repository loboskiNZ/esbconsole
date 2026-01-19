import React, { useState, useEffect } from 'react';
import axios from 'axios';

const InputList = ({ overrideData = null }) => {
    const [inputs, setInputs] = useState({});
    const [consoleParams, setConsoleParams] = useState({});
    const [loading, setLoading] = useState(!overrideData);
    const [saving, setSaving] = useState(false);

    // Define all channels/busses we want to list
    const CHANNELS = [];
    for(let i=1; i<=32; i++) CHANNELS.push({ id: `ch${i}`, name: `Channel ${i}` });
    for(let i=1; i<=8; i++)  CHANNELS.push({ id: `aux${i}`, name: `Aux ${i}` });
    for(let i=1; i<=16; i++) CHANNELS.push({ id: `bus${i}`, name: `Bus ${i}` });

    useEffect(() => {
        if (overrideData) {
            // Normalize overrideData if needed, or assume it matches 'inputs' structure
            // If the scene file is passed raw, we might need to process it.
            // For now, assume render-er handles processing or passes compatible obj
            setInputs(overrideData.inputs || overrideData);
            setConsoleParams(overrideData.consoleParams || {});
            setLoading(false);
        } else {
            loadData();
        }
    }, [overrideData]);

    const loadData = async () => {
        try {
            const res = await axios.get('/api/input-list');
            // Support both old (direct object) and new ({inputs, consoleParams}) formats during transition
            if (res.data.inputs) {
                setInputs(res.data.inputs);
                setConsoleParams(res.data.consoleParams || {});
            } else {
                setInputs(res.data || {});
            }
            setLoading(false);
        } catch (e) {
            console.error("Failed to load input list", e);
            setLoading(false);
        }
    };

    const saveData = async (newInputs) => {
        setSaving(true);
        try {
            await axios.post('/api/input-list', newInputs);
        } catch (e) {
            console.error("Failed to save input list", e);
        } finally {
            setSaving(false);
        }
    };

    const handleChange = (id, field, value) => {
        /* Unused legacy generic handler */
    };

    const handleTextBlur = (id, field, value) => {
         const newData = {
            ...inputs,
            [id]: {
                ...(inputs[id] || {}),
                [field]: value
            }
        };
        setInputs(newData);
        saveData(newData);
    };

    const handleCheckChange = (id, field, checked) => {
         const newData = {
            ...inputs,
            [id]: {
                ...(inputs[id] || {}),
                [field]: checked
            }
        };
        setInputs(newData);
        saveData(newData);
    };

    if (loading) return <div style={{color:'white', padding:'20px'}}>Loading...</div>;

    return (
        <div style={{height:'100%', display:'flex', flexDirection:'column', overflow:'hidden', background:'#222'}}>
            <div style={{padding:'20px', overflowY:'auto', flex:1}}>
                <table style={{width:'100%', borderCollapse:'collapse', color:'white', fontSize:'0.9em'}}>
                    <thead>
                        <tr style={{borderBottom:'2px solid #555', textAlign:'left'}}>
                            <th style={{padding:'10px'}}>Channel</th>
                            <th style={{padding:'10px'}}>Label (Console)</th>
                            <th style={{padding:'10px'}}>Mic / Source</th>
                            <th style={{padding:'10px'}}>Stand</th>
                            <th style={{padding:'10px'}}>+48V</th>
                            <th style={{padding:'10px'}}>DI</th>
                        </tr>
                    </thead>
                    <tbody>
                        {CHANNELS.map(ch => {
                            const item = inputs[ch.id] || {};
                            const consoleLabel = consoleParams[ch.id]?.name || '-';
                            
                            // Pseudo-zebra striping
                            const bg = ch.id.includes('bus') ? '#1a221a' : (ch.id.includes('aux') ? '#2a1a1a' : 'transparent');
                            
                            return (
                                <tr key={ch.id} style={{borderBottom:'1px solid #333', background: bg}}>
                                    <td style={{padding:'8px', color:'#888', width:'80px'}}>{ch.name}</td>
                                    
                                    {/* Console Label (READ ONLY) */}
                                    <td style={{padding:'8px', width:'150px', color:'#ffaa00', fontWeight:'bold'}}>
                                        {consoleLabel}
                                    </td>

                                    {/* Mic */}
                                    <td style={{padding:'8px'}}>
                                        <input 
                                            defaultValue={item.mic || ''}
                                            onBlur={e => handleTextBlur(ch.id, 'mic', e.target.value)}
                                            placeholder="e.g. SM58"
                                            style={{background:'#333', border:'none', color:'#aaf', padding:'4px', width:'100%'}}
                                        />
                                    </td>

                                    {/* Stand */}
                                    <td style={{padding:'8px', width:'100px'}}>
                                        <select 
                                            value={item.stand || 'no'}
                                            onChange={e => handleCheckChange(ch.id, 'stand', e.target.value)}
                                            style={{background:'#333', border:'none', color:'white', padding:'4px', width:'100%'}}
                                        >
                                            <option value="no">None</option>
                                            <option value="short">Short</option>
                                            <option value="tall">Tall</option>
                                            <option value="clip">Clip</option>
                                        </select>
                                    </td>

                                    {/* 48V */}
                                    <td style={{padding:'8px', textAlign:'center', width:'60px'}}>
                                        <input 
                                            type="checkbox"
                                            checked={!!item.phantom}
                                            onChange={e => handleCheckChange(ch.id, 'phantom', e.target.checked)}
                                            style={{accentColor:'#ff4444', transform:'scale(1.2)'}}
                                        />
                                    </td>

                                    {/* DI */}
                                    <td style={{padding:'8px', textAlign:'center', width:'50px'}}>
                                        <input 
                                            type="checkbox"
                                            checked={!!item.di}
                                            onChange={e => handleCheckChange(ch.id, 'di', e.target.checked)}
                                            style={{accentColor:'#44ff44', transform:'scale(1.2)'}}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {saving && <div style={{color:'#888', fontSize:'0.7em', textAlign:'right', padding:'5px'}}>Saving...</div>}
        </div>
    );
};

export default InputList;
