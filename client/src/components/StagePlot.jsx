import React, { useState, useEffect } from 'react';
import { 
  DndContext, 
  useDraggable, 
  useDroppable,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay
} from '@dnd-kit/core';
import { 
  Mic, 
  Music, 
  Laptop, 
  Headphones, 
  Trash2,
  Settings
} from 'lucide-react';
import { 
  FaDrum, 
  FaGuitar 
} from "react-icons/fa6";
import { 
  GiTrumpet, 
  GiSaxophone, 
  GiTrombone, 
  GiDrumKit, 
  GiSpeaker,
  GiTuba  
} from "react-icons/gi";
import { BsSpeakerFill } from "react-icons/bs";
import { PiPianoKeysFill, PiSpeakerHifiFill } from "react-icons/pi";
import axios from 'axios';

// --- ICONS MAPPING ---
// Maps generic icon keys to Specific Components
const ICONS = {
    'mic': Mic,
    'speaker': BsSpeakerFill,
    'drum': GiDrumKit,
    'keyboard': PiPianoKeysFill,
    'laptop': Laptop,
    'headphones': Headphones,
    'monitor': GiSpeaker,
    'music': Music,
    'trumpet': GiTrumpet,
    'trombone': GiTrombone,
    'sax': GiSaxophone,
    'guitar': FaGuitar,
    'amp': PiSpeakerHifiFill,
    'sousaphone': GiTuba 
};

// --- PALETTE CONFIGURATION ---
const PALETTE_ITEMS = [
    { type: 'mic', label: 'Mic', icon: 'mic' },
    { type: 'iem', label: 'IEM', icon: 'headphones' },
    { type: 'monitor', label: 'Monitor', icon: 'monitor' },
    { type: 'drum', label: 'Drum Kit', icon: 'drum' },
    { type: 'amp', label: 'Amp', icon: 'amp' },
    { type: 'guitar', label: 'Guitar', icon: 'guitar' },
    { type: 'keyboard', label: 'Keys', icon: 'keyboard' },
    { type: 'laptop', label: 'Laptop', icon: 'laptop' },
    // HORNS SECTION
    { type: 'trumpet', label: 'Trumpet', icon: 'trumpet' },
    { type: 'trombone', label: 'Trombone', icon: 'trombone' },
    { type: 'sax', label: 'Sax', icon: 'sax' },
    { type: 'sousaphone', label: 'Sousaphone', icon: 'sousaphone' },
    // OTHERS
    { type: 'generic', label: 'Other', icon: 'music' }
];

// --- DRAGGABLE ITEM COMPONENT ---
const DraggableItem = ({ id, type, x, y, label, isOverlay, onClick, iconKey, scale = 1, channelIds, printMode = false }) => {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: id,
        data: { type, isNew: !x, label: label, icon: iconKey } 
    });
    
    // Lookup Icon
    let IconComponent = Music;
    if (iconKey && ICONS[iconKey]) IconComponent = ICONS[iconKey];
    else {
        const def = PALETTE_ITEMS.find(p => p.type === type);
        if (def && ICONS[def.icon]) IconComponent = ICONS[def.icon];
    }

    const style = {
        transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
        position: x !== undefined ? 'absolute' : 'relative',
        left: x ? `${x}px` : undefined,
        top: y ? `${y}px` : undefined,
        zIndex: isDragging ? 100 : 1,
        touchAction: 'none',
        cursor: printMode ? 'default' : 'grab',
        opacity: isDragging ? 0.8 : 1,
    };

    const bgColor = isOverlay ? '#ffaa00' : (printMode ? 'transparent' : '#333');
    const borderColor = printMode ? 'none' : '1px solid #555';
    const fgColor = printMode ? 'black' : (isOverlay ? 'black' : 'white');
    const labelColor = isOverlay ? 'black' : (printMode ? 'black' : '#ccc');

    return (
        <div ref={setNodeRef} style={style} {...(printMode ? {} : listeners)} {...attributes} onClick={printMode ? undefined : onClick}>
            <div style={{
                display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center',
                background: bgColor, 
                border: borderColor, borderRadius:'44px', padding:'5px',
                width:'60px', /* approx size */
                transform: `scale(${scale})`, transformOrigin:'top left'
            }}>
                <IconComponent size={24} color={fgColor} />
                <span style={{color: labelColor, fontSize:'0.7em', marginTop:'2px', textAlign:'center', lineHeight:'1em'}}>
                    {label || type}
                </span>
                {channelIds && channelIds.length > 0 && (
                     <span style={{fontSize:'0.6em', color: printMode?'#444':'#f90', marginTop:'1px', textAlign:'center'}}>
                        {channelIds.join(',')}
                    </span>
                )}
            </div>
        </div>
    );
};

// --- STAGE PLOT MAIN COMPONENT ---
const StagePlot = ({ musicians, overrideItems = null, printMode = false }) => {
    const [items, setItems] = useState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [activeDragId, setActiveDragId] = useState(null);

    const sensors = useSensors(useSensor(PointerSensor, {
        activationConstraint: { distance: 5 } 
    }));

    useEffect(() => {
        if (overrideItems) {
            setItems(overrideItems);
        } else if (!printMode) { // Only fetch if not printing? Or fetch always?
             axios.get('/api/stage-plot').then(res => {
                if (res.data.items) setItems(res.data.items);
            }).catch(err => console.error("Failed to load stage plot", err));
        }
    }, [overrideItems, printMode]);
    
    // ... save logic ...
    
    // Adjust visuals for Print Mode
    const canvasBg = printMode ? 'transparent' : '#222';
    const iconColor = printMode ? 'black' : 'white';
    const textColor = printMode ? 'black' : '#ccc';
    
    // ... render return ...

    const handleDragStart = (event) => {
        setActiveDragId(event.active.id);
    };

    const handleDragEnd = (event) => {
        const { active, over, delta } = event;
        setActiveDragId(null);

        if (!over) return; 

        // 1. NEW ITEM FROM PALETTE
        // Use simpler logic: if it's a new item, calculate position relative to stage container
        if (active.data.current?.isNew) {
            const { type, label, icon } = active.data.current;
            
            // Get Stage Canvas Bounds
            const stageNode = document.getElementById('stage-canvas-area');
            let finalX = 350; // Fallback
            let finalY = 250;

            if (stageNode && active.rect.current?.translated) {
                const stageRect = stageNode.getBoundingClientRect();
                const itemRect = active.rect.current.translated;
                
                // Calculate relative position
                finalX = itemRect.left - stageRect.left;
                finalY = itemRect.top - stageRect.top;

                // Adjust for center-origin if needed, but DraggableItem uses top/left so top/left is correct.
                // However, the "ghost" drag overlay might be centered. 
                // Let's assume top/left alignment is fine.
            } else {
                // Fallback if rects missing (rare)
                finalX = 350 + (delta.x || 0);
                finalY = 250 + (delta.y || 0);
            }

            const newItem = {
                id: Date.now().toString(),
                type: type,
                x: finalX,
                y: finalY,
                channelIds: [],
                customLabel: label, 
                icon: icon,
                scale: 1 
            };
            savePlot([...items, newItem]);
        } 
        // 2. MOVING EXISTING ITEM
        else {
            const newItems = items.map(item => {
                if (item.id === active.id) {
                    return {
                        ...item,
                        x: item.x + delta.x,
                        y: item.y + delta.y
                    };
                }
                return item;
            });
            savePlot(newItems);
        }
    };

    const updateItem = (id, field, val) => {
        const newItems = items.map(i => i.id === id ? { ...i, [field]: val } : i);
        savePlot(newItems);
    };

    const selectedItem = items.find(i => i.id === selectedId);

    return (
        <DndContext 
            sensors={sensors} 
            onDragStart={handleDragStart} 
            onDragEnd={handleDragEnd}
        >
            <div style={{display:'flex', height:'100%', flexDirection:'row', overflow:'hidden', background: printMode ? 'transparent' : '#222'}}>
                
                {/* TOOLBAR (Palette) - Hide in Print Mode */}
                {!printMode && (
                <div style={{
                    width:'100px', background:'#1a1a1a', borderRight:'1px solid #444', 
                    padding:'10px', display:'flex', flexDirection:'column', gap:'10px', alignItems:'center',
                    overflowY: 'auto'
                }}>
                    <div style={{color:'#888', fontSize:'0.7em', marginBottom:'10px'}}>PALETTE</div>
                    {PALETTE_ITEMS.map(p => (
                        <DraggableItem 
                            key={p.type} 
                            id={`palette-${p.type}`} 
                            type={p.type} 
                            label={p.label} 
                            iconKey={p.icon} 
                        />
                    ))}

                    <div style={{height:'1px', background:'#444', width:'100%', margin:'10px 0'}}></div>
                    
                    {/* ACTIONS */}
                    <button 
                        onClick={() => {
                            // Rescue Logic: Bring items within 0-800, 0-600 bounds
                            const recovered = items.map((item, i) => {
                                let { x, y } = item;
                                const isLost = x < 0 || y < 0 || x > 1000 || y > 800;
                                if (isLost) {
                                    return { 
                                        ...item, 
                                        x: 50 + (i * 20) % 200, 
                                        y: 50 + Math.floor(i / 10) * 50 
                                    };
                                }
                                return item;
                            });
                            savePlot(recovered);
                            alert("Brought off-screen items back to the top-left corner!");
                        }}
                        style={{
                            background:'#444', color:'white', border:'none', fontSize:'0.7em', 
                            padding:'5px', borderRadius:'4px', cursor:'pointer', width:'100%'
                        }}
                    >
                        Rescue Lost Items
                    </button>

                     <button 
                        onClick={() => {
                            if(confirm("Clear ENTIRE Stage? This cannot be undone.")) {
                                savePlot([]);
                            }
                        }}
                        style={{
                            background:'#522', color:'#fcc', border:'none', fontSize:'0.7em', 
                            padding:'5px', borderRadius:'4px', cursor:'pointer', width:'100%'
                        }}
                    >
                        Clear Stage
                    </button>
                </div>
                )}

                {/* STAGE CANVAS */}
                <div style={{
                    flex:1, position:'relative', 
                    background: printMode ? 'transparent' : '#222', 
                    overflow:'hidden',
                    border: printMode ? '1px solid #ccc' : 'none' 
                }}>
                    {/* DROPPABLE AREA */}
                    <StageCanvas items={items} onSelect={printMode ? () => {} : setSelectedId} printMode={printMode} />
                    
                    {/* GRID OVERLAY */}
                    <div style={{
                        position:'absolute', top:0, left:0, right:0, bottom:0, pointerEvents:'none',
                        backgroundImage: 'linear-gradient(#333 1px, transparent 1px), linear-gradient(90deg, #333 1px, transparent 1px)',
                        backgroundSize: '100px 100px', opacity: 0.5
                    }}></div>
                    
                    {/* FRONT OF STAGE INDICATOR */}
                    <div style={{
                        position:'absolute', bottom:0, left:0, right:0, height:'30px', 
                        background:'linear-gradient(to top, rgba(0,0,0,0.8), transparent)',
                        display:'flex', alignItems:'center', justifyContent:'center', color:'#555', fontSize:'0.8em',
                        pointerEvents:'none'
                    }}>
                        FRONT OF STAGE
                    </div>
                </div>

                {/* PROPERTIES PANEL */}
                {selectedItem && (
                    <div style={{
                        width:'250px', background:'#1a1a1a', borderLeft:'1px solid #444', 
                        padding:'20px', display:'flex', flexDirection:'column', gap:'15px'
                    }}>
                        <h3 style={{margin:0, color:'#ffaa00'}}>Properties</h3>
                        
                        {/* LABEL */}
                        <div>
                            <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Label</label>
                            <input 
                                value={selectedItem.customLabel || ''} 
                                onChange={e => updateItem(selectedItem.id, 'customLabel', e.target.value)}
                                style={{width:'100%', background:'#333', border:'1px solid #555', color:'white', padding:'5px'}} 
                            />
                        </div>

                        {/* LINK MUSICIAN */}
                        <div>
                            <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Link Musician</label>
                            <select 
                                value={selectedItem.linkedMusicianId || ''}
                                onChange={e => {
                                    const mId = e.target.value;
                                    const mus = musicians.find(m => m.id === mId);
                                    const updates = { linkedMusicianId: mId };
                                    if(mus) {
                                        updates.channelIds = mus.linkedChannels || [];
                                        // updates.customLabel = mus.name; // User requested to keep Icon Label
                                    }
                                    const newItems = items.map(i => i.id === selectedId ? { ...i, ...updates } : i);
                                    savePlot(newItems);
                                }}
                                style={{width:'100%', background:'#333', border:'1px solid #555', color:'white', padding:'5px'}} 
                            >
                                <option value="">-- None --</option>
                                {musicians.map(m => (
                                    <option key={m.id} value={m.id}>{m.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* SIZE / SCALE */}
                        <div>
                             <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Size: {selectedItem.scale || 1}x</label>
                             <input 
                                type="range" 
                                min="0.5" max="3" step="0.1"
                                value={selectedItem.scale || 1}
                                onChange={e => updateItem(selectedItem.id, 'scale', parseFloat(e.target.value))}
                                style={{width:'100%'}}
                             />
                        </div>

                        {/* CHANNELS */}
                        <div>
                            <label style={{display:'block', color:'#888', fontSize:'0.8em'}}>Channels (e.g. 1-8)</label>
                            <input 
                                value={selectedItem.channelIds ? selectedItem.channelIds.join(', ') : ''} 
                                onChange={e => {
                                   const parts = e.target.value.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                                   updateItem(selectedItem.id, 'channelIds', parts);
                                }}
                                placeholder="1, 2, 3"
                                style={{width:'100%', background:'#333', border:'1px solid #555', color:'white', padding:'5px'}} 
                            />
                        </div>

                        {/* DELETE */}
                        <div style={{marginTop:'auto'}}>
                            <button 
                                onClick={() => {
                                    if(confirm("Delete item?")) {
                                        setItems(prev => prev.filter(i => i.id !== selectedId));
                                        setSelectedId(null);
                                        const remaining = items.filter(i => i.id !== selectedId);
                                        savePlot(remaining);
                                    }
                                }}
                                style={{width:'100%', background:'#522', color:'#fcc', border:'none', padding:'8px', cursor:'pointer'}}
                            >
                                <Trash2 size={16} style={{verticalAlign:'middle', marginRight:'5px'}} />
                                Delete Item
                            </button>
                        </div>
                    </div>
                )}
            </div>

            <DragOverlay>
                {activeDragId ? (
                    <div style={{
                        width:'60px', height:'60px', background:'#ffaa00', borderRadius:'4px', opacity:0.8,
                        display:'flex', alignItems:'center', justifyContent:'center'
                    }}>
                        item
                    </div>
                ) : null}
            </DragOverlay>

        </DndContext>
    );
};

// --- STAGE CANVAS --- 
const StageCanvas = ({ items, onSelect, printMode = false }) => {
    const { setNodeRef } = useDroppable({ id: 'stage-canvas' });

    return (
        <div 
            id="stage-canvas-area"
            ref={setNodeRef} 
            style={{width:'100%', height:'100%', position:'relative'}}
        >
            {items.map(item => (
                <DraggableItem 
                    key={item.id} 
                    {...item} 
                    label={item.customLabel}
                    onClick={() => onSelect(item.id)} 
                    printMode={printMode}
                />
            ))}
        </div>
    );
};

export default StagePlot;
