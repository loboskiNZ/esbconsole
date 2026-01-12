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

    // Unified Move Handler
    const handleMove = (clientY) => {
        if (!trackRef.current) return;
        const rect = trackRef.current.getBoundingClientRect();
        const relativeY = clientY - rect.top;
        const h = rect.height;
        let newPct = 1 - (relativeY / h);
        
        if (newPct < 0) newPct = 0;
        if (newPct > 1) newPct = 1;

        setInternalValue(newPct);
        onChange(newPct);
    };

    // Global Listeners for Dragging
    useEffect(() => {
        if (isDragging) {
            const onTouchMove = (e) => {
                // Prevent scrolling while dragging fader (especially vertical)
                if (e.cancelable) e.preventDefault(); 
                handleMove(e.touches[0].clientY);
            };
            const onTouchEnd = () => setIsDragging(false);
            
            const onMouseMove = (e) => {
                e.preventDefault();
                handleMove(e.clientY);
            };
            const onMouseUp = () => setIsDragging(false);

            // Active Listeners (non-passive for touchmove to allow preventDefault)
            window.addEventListener('touchmove', onTouchMove, { passive: false });
            window.addEventListener('touchend', onTouchEnd);
            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);

            return () => {
                window.removeEventListener('touchmove', onTouchMove);
                window.removeEventListener('touchend', onTouchEnd);
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            };
        }
    }, [isDragging]);

    return (
        <div style={{
            display: 'flex', flexDirection: 'column', alignItems: 'center', 
            height: '100%', width: '100%', minWidth: '60px',
            touchAction: 'pan-x' // Allow horizontal scroll (list), Block vertical (fader)
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
                // Start Listeners
                onTouchStart={(e) => {
                    setIsDragging(true);
                    handleMove(e.touches[0].clientY);
                }}
                onMouseDown={(e) => {
                    setIsDragging(true);
                    handleMove(e.clientY);
                }}
                style={{
                    flex: 1, width: '40px', background: '#1a1a1a', borderRadius: '20px',
                    position: 'relative', overflow: 'hidden', cursor: 'pointer',
                    boxShadow: 'inset 0 0 10px rgba(0,0,0,0.5)',
                    touchAction: 'pan-x'
                }}
            >
                {/* Fill Level */}
                <div style={{
                    position: 'absolute', bottom: 0, left: 0, right: 0,
                    height: `${percentage}%`,
                    background: `linear-gradient(to top, ${color}33, ${color}66)`,
                    transition: isDragging ? 'none' : 'height 0.1s linear', 
                    pointerEvents: 'none'
                }} />

                {/* Fader Cap */}
                <div style={{
                    position: 'absolute', bottom: `calc(${percentage}% - 20px)`, left: '2px', right: '2px',
                    height: '40px', background: 'white', borderRadius: '6px',
                    boxShadow: '0 2px 5px rgba(0,0,0,0.5)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    pointerEvents: 'none', 
                    transition: isDragging ? 'none' : 'bottom 0.1s linear'
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
