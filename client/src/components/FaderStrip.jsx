import React, { useRef, useEffect } from 'react';

const FaderStrip = ({ 
    label, 
    value,      // 0.0 to 1.0 (from Server)
    onChange,   // (newValue) => void
    isMuted, 
    onMuteToggle, 
    color = '#0088ff',
    height = '300px'
}) => {
    const trackRef = useRef(null);
    const [isDragging, setIsDragging] = React.useState(false);
    const [internalValue, setInternalValue] = React.useState(value); // local optimistic state

    // Sync internal value with prop when NOT dragging
    useEffect(() => {
        if (!isDragging) {
            setInternalValue(value);
        }
    }, [value]); // CRITICAL FIX: Only update when VALUE changes, not when dragging state changes.

    const percentage = Math.max(0, Math.min(100, (internalValue || 0) * 100));

    const handlePointerDown = (e) => {
        if (!trackRef.current) return;
        
        // Capture pointer to track dragging even if it leaves the element
        e.target.setPointerCapture(e.pointerId);
        
        setIsDragging(true);
        updateValueFromPointer(e);
    };

    const handlePointerMove = (e) => {
        if (!isDragging) return;
        updateValueFromPointer(e);
    };

    const handlePointerUp = (e) => {
        setIsDragging(false);
        e.target.releasePointerCapture(e.pointerId);
    };

    const updateValueFromPointer = (e) => {
         if (!trackRef.current) return;
         const rect = trackRef.current.getBoundingClientRect();
         // clientY is standard in PointerEvent
         const relativeY = e.clientY - rect.top;
         const h = rect.height;
         let newPct = 1 - (relativeY / h);
         
         if (newPct < 0) newPct = 0;
         if (newPct > 1) newPct = 1;

         setInternalValue(newPct);
         onChange(newPct);
    };

    return (
        <div style={{
            display: 'flex', flexDirection: 'column', alignItems: 'center', 
            height: '100%', width: '100%', minWidth: '60px',
            touchAction: 'none' // Crucial: Disables browser scrolling on this element
        }}>
            {/* Label Box */}
            <div style={{
                background: '#222', width: '100%', padding: '5px 0', textAlign: 'center',
                borderBottom: `3px solid ${color}`, borderRadius: '4px 4px 0 0',
                marginBottom: '10px'
            }}>
                <div style={{fontWeight:'bold', fontSize:'0.9em', whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis', padding:'0 2px'}}>
                    {label}
                </div>
            </div>

            {/* Fader Track Area */}
            <div 
                className="fader-track"
                ref={trackRef}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={handlePointerUp}
                onPointerCancel={handlePointerUp}
                style={{
                    flex: 1, width: '40px', background: '#1a1a1a', borderRadius: '20px',
                    position: 'relative', overflow: 'hidden', cursor: 'pointer',
                    boxShadow: 'inset 0 0 10px rgba(0,0,0,0.5)',
                    touchAction: 'none' // Double ensure on the interactive element
                }}
            >
                {/* Fill Level */}
                <div style={{
                    position: 'absolute', bottom: 0, left: 0, right: 0,
                    height: `${percentage}%`,
                    background: `linear-gradient(to top, ${color}33, ${color}66)`,
                    transition: isDragging ? 'none' : 'height 0.1s linear', // Instant update while dragging
                    pointerEvents: 'none'
                }} />

                {/* Fader Cap */}
                <div style={{
                    position: 'absolute', bottom: `calc(${percentage}% - 20px)`, left: '2px', right: '2px',
                    height: '40px', background: 'white', borderRadius: '6px',
                    boxShadow: '0 2px 5px rgba(0,0,0,0.5)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    pointerEvents: 'none', 
                    transition: isDragging ? 'none' : 'bottom 0.1s linear' // Instant update while dragging
                }}>
                    <div style={{width:'20px', height:'2px', background:'#ccc'}} />
                </div>
            </div>

            {/* Mute Button */}
            <button 
                onClick={onMuteToggle}
                style={{
                    marginTop: '15px', width: '50px', height: '40px',
                    background: isMuted ? '#ff3333' : '#333',
                    color: 'white', border: isMuted ? 'none' : '1px solid #555',
                    borderRadius: '6px', fontSize: '0.8em', fontWeight: 'bold',
                    cursor: 'pointer', boxShadow: isMuted ? '0 0 10px #ff3333' : 'none',
                    transition: 'all 0.2s'
                }}
            >
                {isMuted ? 'MUTE' : 'ON'}
            </button>
        </div>
    );
};

export default FaderStrip;
