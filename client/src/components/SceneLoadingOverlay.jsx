import React from 'react';

const SceneLoadingOverlay = ({ progress, onClose }) => {
    // progress: { step: number, label: string, detail?: string }
    // Steps 1-5
    const steps = [
        "Names & Colors",
        "Channel Settings (Gain, EQ, Dyn)",
        "Effects & Mix Sends",
        "Mutes & Levels",
        "Granular Verification"
    ];

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh',
            background: 'rgba(0,0,0,0.92)', zIndex: 99999,
            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
            color: 'white', fontFamily: 'monospace'
        }}>
            {/* Background Logo */}
            <img src="/esb_logo.png" style={{
                position: 'absolute', width: '600px', opacity: 0.15,
                animation: 'spin 10s linear infinite', pointerEvents: 'none'
            }} />
            <style>{`@keyframes spin { 100% { transform: rotate(360deg); } }`}</style>
            
            <div style={{ zIndex: 2, background: '#111', padding: '40px', borderRadius: '15px', border: '1px solid #333', minWidth: '400px' }}>
                <h2 style={{ textAlign: 'center', borderBottom: '1px solid #444', paddingBottom: '15px', marginTop: 0 }}>
                    SCENE SYNC
                </h2>
                
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', margin: '20px 0' }}>
                    {steps.map((text, idx) => {
                        const stepNum = idx + 1;
                        const isDone = progress.step > stepNum || (progress.step === 6 && stepNum === 5); // 6=Complete
                        const isCurrent = progress.step === stepNum;
                        
                        return (
                            <div key={idx} style={{ 
                                display: 'flex', alignItems: 'center', gap: '15px',
                                opacity: isDone || isCurrent ? 1 : 0.3,
                                color: isCurrent ? '#4fecff' : (isDone ? '#0f0' : '#888')
                            }}>
                                <div style={{ 
                                    width: '24px', height: '24px', 
                                    border: `2px solid ${isCurrent ? '#4fecff' : '#555'}`,
                                    borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    background: isDone ? '#0f0' : 'transparent'
                                }}>
                                    {isDone && <span style={{color:'black', fontWeight:'bold'}}>✓</span>}
                                    {isCurrent && <span className="spinner" style={{fontSize:'12px'}}>↻</span>}
                                </div>
                                <span style={{ fontSize: '1.2em', fontWeight: isCurrent ? 'bold' : 'normal' }}>
                                    {text}
                                </span>
                            </div>
                        )
                    })}
                </div>

                {/* Detail Text for Verification */}
                <div style={{ 
                    height: '30px', textAlign: 'center', color: '#666', fontSize: '0.9em',
                    borderTop: '1px solid #444', paddingTop: '15px' 
                }}>
                    {progress.detail || progress.label || "Waiting..."}
                </div>
            </div>
        </div>
    );
};

export default SceneLoadingOverlay;
