import React, { useState } from 'react';
import FaderStrip from './components/FaderStrip';

const MusicianMix = ({ socket, x32State, user }) => {
    // Show all 32 channels + Aux if needed. For now 1-32.
    const channels = Array.from({length: 32}, (_, i) => String(i + 1));
    const mixBusId = user?.mixBusId || 1; // Default to 1 if missing safely

    // --- OSC HELPERS ---
    const sendOsc = (address, value) => {
        if (!socket) return;
        socket.emit('osc', { address, args: [value] });
    };

    const handleLevelChange = (chId, val) => {
        const chStr = chId.toString().padStart(2, '0');
        const busStr = mixBusId.toString().padStart(2, '0');
        const address = `/ch/${chStr}/mix/${busStr}/level`;
        sendOsc(address, val);
    };

    const handleMuteToggle = (chId, currentMute) => {
        const chStr = chId.toString().padStart(2, '0');
        const busStr = mixBusId.toString().padStart(2, '0');
        const address = `/ch/${chStr}/mix/${busStr}/on`;
        const newVal = currentMute ? 1 : 0; // Toggle logic (1=On/Unmuted)
        sendOsc(address, newVal);
    };

    const getChannelData = (chId) => {
        if (!x32State) return null;
        // Search by Strong (mostly used) or Number
        if (x32State[String(chId)]) return x32State[String(chId)];
        if (x32State[Number(chId)]) return x32State[Number(chId)];
        return null;
    };

    // --- MASTER HANDLERS ---
    const handleMasterLevel = (val) => {
         const busStr = mixBusId.toString().padStart(2, '0');
         sendOsc(`/bus/${busStr}/mix/fader`, val);
    };

    const handleMasterMute = (currentMute) => {
         const busStr = mixBusId.toString().padStart(2, '0');
         sendOsc(`/bus/${busStr}/mix/on`, currentMute ? 1 : 0);
    };

    return (
        <div style={{
            height: '100%', // Changed from 100dvh to fill parent Layout container
            display: 'flex', 
            flexDirection: 'column', 
            background: '#121212', 
            color: 'white', 
            overflow: 'hidden'
        }}>
            
            {/* NO Header - Handled by Layout or removed as requested */}

            {/* 3. SCROLLABLE FADER AREA */}
            <div style={{
                flex: 1, 
                overflowX: 'auto', 
                overflowY: 'hidden', 
                display: 'flex', 
                flexDirection: 'row',
                alignItems: 'stretch',
                padding: '10px',
                gap: '12px',
                // Webkit Scrollbar Styling
                scrollbarWidth: 'thin',
                scrollbarColor: '#444 #222'
            }}>
                 {/* MASTER BUS FADER - Always First */}
                 {(() => {
                    // Need to fetch Master Data. 
                    // Usually this is bus master fader.
                    // Bus ID = mixBusId.
                    // Address: /bus/{id}/mix/fader
                    // Data might be in x32State.buses[mixBusId] ?
                    // But our state structure is flat or channels only?
                    // Let's assume we might need to fetch it or rely on optimistic first.
                    // Let's look for "bus" state if available, otherwise just control it.
                    
                    // Note: The previous handleMasterLevel used /bus/{id}/mix/*
                    
                    // We need a way to get the current Master Value if possible.
                    // If not in state, it might default to 0 until we get an update.
                    
                    // Quick check on State Keys...
                    // Assuming state.buses exists? Or maybe we just use local state for now?
                    // Let's use 0 or safe default.
                    
                    return (
                        <div style={{
                            flex: '0 0 80px', height: '100%',
                            display: 'flex', flexDirection: 'column',
                            borderRight: '1px solid #333', paddingRight: '12px', marginRight: '5px'
                        }}>
                             <FaderStrip 
                                label="MASTER"
                                color="#ffaa00" // Orange for Master
                                value={0.75} // Placeholder default, ideally fetch from state
                                isMuted={false}
                                onChange={handleMasterLevel}
                                onMuteToggle={() => handleMasterMute(false)} 
                            />
                        </div>
                    );
                 })()}

                 {channels.map(chId => {
                    const data = getChannelData(chId);
                    
                    // Loading State
                    if (!data) return (
                        <div key={chId} style={{
                            flex: '0 0 80px', height:'100%', 
                            background:'#1a1a1a', borderRadius:'8px', 
                            display:'flex', alignItems:'center', justifyContent:'center',
                            border: '1px dashed #333'
                        }}>
                            <span style={{color:'#444', fontSize:'0.7em'}}>Loading {chId}</span>
                        </div>
                    );

                    // Calculations
                    const mixBusKey = user.mixBusId.toString();
                    const sendData = data.mixSends ? data.mixSends[mixBusKey] : {};
                    const sendLevel = sendData?.level !== undefined ? sendData.level : 0; 
                    const isMuted = sendData?.on === 0;

                    return (
                        <div key={chId} style={{
                            flex: '0 0 80px', // Fixed width, don't shrink
                            height: '100%',
                            display: 'flex', flexDirection: 'column'
                        }}>
                            <FaderStrip 
                                label={data.name || `Ch ${chId}`}
                                color={data.color || '#00e5ff'}
                                value={sendLevel}
                                isMuted={isMuted}
                                onChange={(val) => handleLevelChange(chId, val)}
                                onMuteToggle={() => handleMuteToggle(chId, isMuted)}
                            />
                        </div>
                    );
                })}

                {/* Connection/Data Error Overlay */}
                {(!x32State || Object.keys(x32State).length < 2) && (
                     <div style={{
                         position:'absolute', top:'50%', left:'50%', transform:'translate(-50%, -50%)',
                         textAlign:'center', background:'rgba(0,0,0,0.9)', padding:'20px', borderRadius:'10px', zIndex:100, border: '1px solid #444', minWidth: '200px'
                     }}>
                        <h3 style={{color:'#0088ff', margin:'0 0 10px 0'}}>Connecting...</h3>
                        <div style={{color:'#666', fontSize:'0.8em', marginBottom:'15px'}}>Waiting for Console Data</div>
                        <button 
                            onClick={() => socket.emit('request_state')}
                            style={{marginTop:'10px', padding:'8px 16px', background:'#0088ff', border:'none', color:'white', borderRadius:'4px', cursor:'pointer'}}
                        >
                            Retry Connection
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MusicianMix;
