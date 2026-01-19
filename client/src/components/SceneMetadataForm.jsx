
import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import html2canvas from 'html2canvas';

// Import Visual Components for Capture
import StagePlot from './StagePlot';
import InputList from './InputList';

const CategorySection = ({ title, categoryKey, data, updateField }) => (
    <div style={{ marginBottom: '15px', padding: '10px', background: '#333', borderRadius: '4px' }}>
        <div style={{ color: '#ffaa00', marginBottom: '8px', fontSize: '0.9em', fontWeight: 'bold' }}>
            {title}
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
            <input 
                placeholder="Name"
                value={data[categoryKey]?.name || ''}
                onChange={e => updateField(categoryKey, 'name', e.target.value)}
                style={{ flex: 1, padding: '5px', background: '#222', border: '1px solid #444', color: 'white' }}
            />
            <input 
                placeholder="Email"
                value={data[categoryKey]?.email || ''}
                onChange={e => updateField(categoryKey, 'email', e.target.value)}
                style={{ flex: 1, padding: '5px', background: '#222', border: '1px solid #444', color: 'white' }}
            />
            <input 
                placeholder="Phone"
                value={data[categoryKey]?.phone || ''}
                onChange={e => updateField(categoryKey, 'phone', e.target.value)}
                style={{ width: '120px', padding: '5px', background: '#222', border: '1px solid #444', color: 'white' }}
            />
        </div>
    </div>
);

const SceneMetadataForm = ({ sceneName, initialData, onSave, onCancel }) => {
    const [generating, setGenerating] = useState(false);
    
    // Default Structure
    const [data, setData] = useState({
        venue: '',
        date: new Date().toISOString().split('T')[0],
        promoter: { name: '', email: '', phone: '' },
        sceneTech: { name: '', email: '', phone: '' },
        stageTech: { name: '', email: '', phone: '' },
        soundEng: { name: '', email: '', phone: '' },
        ...initialData
    });

    // Helper to update nested fields
    const updateField = (category, field, value) => {
        if (!category) {
            setData(prev => ({ ...prev, [field]: value }));
        } else {
            setData(prev => ({
                ...prev,
                [category]: { ...prev[category], [field]: value }
            }));
        }
    };

    const [captureData, setCaptureData] = useState(null);
    const stagePlotRef = useRef(null);
    // Removed inputListRef as we are using native table now

    const handleGenerateRider = async () => {
        if (!sceneName) {
            alert("Please save the scene first before generating a rider.");
            return;
        }
        setGenerating(true);
        try {
            // 1. Fetch Scene Data
            const sceneRes = await axios.get(`/api/scenes/${sceneName}`);
            const sceneRaw = sceneRes.data;

            // 2. Process Data for Components
            // Inputs: Convert scene 'chXX' format to InputList expected format
            // InputList expects { ch1: { ... }, ... }
            const inputsData = {};
            // We also need consoleParams if available? 
            // The sceneRaw has keys like "ch01", "sceneMetadata".
            // Let's iterate 1-32
            for (let i = 1; i <= 32; i++) {
                const idZero = `ch${i.toString().padStart(2, '0')}`;
                const idSimple = `ch${i}`;
                if (sceneRaw[idZero]) {
                    inputsData[idSimple] = {
                        mic: sceneRaw[idZero].inputList?.mic || '', // Assuming standard structure or checking specific fields
                        stand: sceneRaw[idZero].inputList?.stand || 'no',
                        phantom: sceneRaw[idZero].inputList?.phantom || false,
                        di: sceneRaw[idZero].inputList?.di || false,
                        // Fallback: if inputList obj missing, try to infer? 
                        // If no inputList metadata exists in scene, it will correspond to defaults.
                    };
                    // Note: If the user hasn't saved Input List data into the scene explicitly
                    // via our new InputList component (which likely saves to 'musicians.json' logic or 'input-list' endpoint?),
                    // then this might be empty.
                    // BUT, if we want to support "Current Global Input List" as fallback:
                    // We could fetch global input list if scene doesn't have it?
                    // User said "put all metadata of the scene... + input list".
                    // Let's assume for now we use what is in the scene OR defaults.
                }
            }

            // StagePlot Items
            // If scene has stagePlot items, use them. Else, current Global?
            // "sceneData.stagePlot"?
            const plotItems = sceneRaw.stagePlot?.items || null; 

            // 3. Render Capture Components
            setCaptureData({
                inputs: inputsData,
                plotItems: plotItems
            });

            // Wait for render (short timeout)
            await new Promise(r => setTimeout(r, 1000));

            // 4. Capture Stage Plot Only
            let plotImg = null;

            if (stagePlotRef.current) {
                const canvas = await html2canvas(stagePlotRef.current, { backgroundColor: '#ffffff' }); // White BG
                plotImg = canvas.toDataURL('image/png');
            }

            // 5. Send to API
            const res = await axios.post('/api/rider/generate', { 
                name: sceneName, 
                metadata: data,
                stagePlotImage: plotImg,
                // inputListImage: listImg // Removed
            });

            if (res.data.success) {
                window.open(res.data.path, '_blank');
            }
        } catch (err) {
            console.error(err);
            alert("Failed to generate rider: " + (err.response?.data?.error || err.message));
        } finally {
            setGenerating(false);
            setCaptureData(null); // Cleanup
        }
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.85)', zIndex: 10000,
            display: 'flex', alignItems: 'center', justifyContent: 'center'
        }}>
           {/* HIDDEN CAPTURE AREA */}
           {captureData && (
                <div style={{position:'absolute', top:'-9999px', left:'-9999px'}}>
                    {/* Stage Plot Capture */}
                    <div ref={stagePlotRef} style={{width:'1200px', height:'900px', background:'white', padding:'0px'}}>
                        <div style={{width:'100%', height:'900px', position:'relative', border:'none'}}>
                             {/* Use overrideItems. Enable Print Mode */}
                            <StagePlot overrideItems={captureData.plotItems} printMode={true} />
                        </div>
                    </div>
                </div>
           )}

            <div style={{
                background: '#1a1a1a', padding: '25px', borderRadius: '8px',
                width: '600px', maxHeight: '90vh', overflowY: 'auto',
                border: '1px solid #444', boxShadow: '0 0 50px rgba(0,0,0,0.8)'
            }}>
                <h2 style={{ color: 'white', marginTop: 0, borderBottom: '1px solid #555', paddingBottom: '10px' }}>
                    Scene Details
                </h2>

                {/* BASIC INFO */}
                <div style={{ display: 'flex', gap: '15px', marginBottom: '20px' }}>
                    <div style={{ flex: 1 }}>
                        <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>VENUE</label>
                        <input 
                            value={data.venue || ''}
                            onChange={e => updateField(null, 'venue', e.target.value)}
                            style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white', fontSize: '1.1em' }}
                            placeholder="e.g. The Grand Hall"
                            autoFocus
                        />
                    </div>
                    <div style={{ display:'flex', gap:'5px' }}>
                        <div style={{ width: '140px' }}>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>DATE</label>
                            <input 
                                type="date"
                                value={data.date || ''}
                                onChange={e => updateField(null, 'date', e.target.value)}
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                        <div style={{ width: '100px' }}>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>TIME</label>
                            <input 
                                type="time"
                                value={data.time || ''}
                                onChange={e => updateField(null, 'time', e.target.value)}
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                    </div>
                </div>
                
                {/* SCHEDULE */}
                <div style={{ marginBottom: '20px', padding: '15px', background: '#252525', borderRadius: '4px' }}>
                     <label style={{ display: 'block', color: '#ffaa00', fontSize: '0.9em', marginBottom: '10px', textTransform:'uppercase', letterSpacing:'1px', borderBottom:'1px solid #444', paddingBottom:'5px' }}>
                        Schedule
                    </label>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
                        <div>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>LOAD-IN</label>
                            <input 
                                type="time"
                                value={data.loadIn || ''}
                                onChange={e => updateField(null, 'loadIn', e.target.value)}
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                        <div>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>SOUNDCHECK</label>
                            <input 
                                value={data.soundCheck || ''}
                                onChange={e => updateField(null, 'soundCheck', e.target.value)}
                                placeholder="e.g. 60 mins"
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                         <div>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>PERFORMANCE</label>
                            <input 
                                type="time"
                                value={data.performance || ''}
                                onChange={e => updateField(null, 'performance', e.target.value)}
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                         <div>
                            <label style={{ display: 'block', color: '#888', fontSize: '0.8em', marginBottom: '5px' }}>PACK-DOWN</label>
                            <input 
                                value={data.packDown || ''}
                                onChange={e => updateField(null, 'packDown', e.target.value)}
                                placeholder="e.g. 30 mins"
                                style={{ width: '100%', padding: '8px', background: '#333', border: '1px solid #555', color: 'white' }}
                            />
                        </div>
                    </div>
                </div>

                {/* CONTACTS */}
                <CategorySection title="PROMOTER" categoryKey="promoter" data={data} updateField={updateField} />
                <CategorySection title="SCENE TECH ASSIST" categoryKey="sceneTech" data={data} updateField={updateField} />
                <CategorySection title="STAGE TECH ASSIST" categoryKey="stageTech" data={data} updateField={updateField} />
                <CategorySection title="SOUND ENGINEER" categoryKey="soundEng" data={data} updateField={updateField} />

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px', borderTop:'1px solid #333', paddingTop:'15px' }}>
                    <button 
                        onClick={handleGenerateRider}
                        disabled={generating}
                        style={{ background: '#4466aa', color: 'white', border: 'none', padding: '10px 20px', borderRadius: '4px', cursor: 'pointer', marginRight:'auto', opacity: generating ? 0.5 : 1 }}
                    >
                        {generating ? 'Generating PDF...' : '📄 Generate PDF Rider'}
                    </button>

                    <button 
                        onClick={onCancel}
                        style={{ background: 'transparent', color: '#888', border: 'none', cursor: 'pointer', fontSize: '1em' }}
                    >
                        Cancel
                    </button>
                    <button 
                        onClick={() => onSave(data)}
                        style={{ background: '#00cc66', color: 'black', border: 'none', padding: '10px 30px', borderRadius: '4px', cursor: 'pointer', fontWeight: 'bold', fontSize: '1em' }}
                    >
                        Save Metadata
                    </button>
                </div>
            </div>
        </div>
    );
};

export default SceneMetadataForm;
