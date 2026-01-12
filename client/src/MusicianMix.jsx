import React, { useState } from 'react';
import FaderStrip from './components/FaderStrip';

const MusicianMix = ({ socket, x32State, user, isGroupMode }) => {
    // Show all 32 channels + Aux if needed. For now 1-32.
    const channels = Array.from({length: 32}, (_, i) => String(i + 1));
    const mixBusId = user?.mixBusId || 1; // Default to 1 if missing safely
    
    // Admin Groups Map
    const [adminGroups, setAdminGroups] = React.useState({});
    const [lastOscCmd, setLastOscCmd] = React.useState("");
    
    React.useEffect(() => {
        // Fetch Admin Groups (SOLO_GROUPS)
        fetch('/api/groups')
            .then(res => res.json())
            .then(data => setAdminGroups(data))
            .catch(err => console.error("Failed to load groups", err));
    }, []);

    // --- OSC HELPERS ---
    const sendOsc = (address, value) => {
        if (!socket) return;
        setLastOscCmd(`${address}  [${typeof value}:${Number(value).toFixed(2)}]`);
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
            height: '100%', width: '100%',
            minWidth: 0, // Fix horizontal scroll clipping
            display: 'flex', 
            flexDirection: 'column', 
            background: '#121212', 
            color: 'white', 
            overflow: 'hidden'
        }}>
            
            {/* NO Header - Handled by Layout or removed as requested */}

            {/* DEBUG: Last OSC Command */}
            <div style={{
                position: 'fixed', bottom: 0, left: 0, right: 0, 
                background: 'rgba(50,0,0,0.8)', color: 'yellow', 
                fontSize: '10px', padding: '2px', pointerEvents: 'none', zIndex: 9999
            }}>
                DEBUG OSC: {lastOscCmd || "None"}
            </div>

            {/* 3. SCROLLABLE FADER AREA */}
            <div style={{
                flex: 1, 
                width: '100%',
                overflowX: 'auto', 
                overflowY: 'hidden', 
                display: 'flex', 
                flexDirection: 'row',
                alignItems: 'stretch',
                padding: '10px 10px 10px 10px', // Uniform padding
                gap: '12px',
                // Webkit Scrollbar Styling
                webkitOverflowScrolling: 'touch', // Critical for iOS momentum
                scrollbarWidth: 'thin',
                scrollbarColor: '#444 #222'
            }}>
                 {/* MASTER BUS FADER - Always First */}
                 {(() => {
                    // Fetch Master Bus Data
                    const busKey = 'bus' + mixBusId;
                    const busData = x32State && x32State[busKey] ? x32State[busKey] : { level: 0, on: 0 };
                    
                    // State Mapping:
                    // X32 'on': 1 = Active/Unmuted, 0 = Muted
                    // We want 'isMuted' which is opposite of 'on'
                    // Safety check: if busData.on is undefined, assume Unmuted (1)
                    const isOn = busData.on !== undefined ? busData.on : 1; 
                    const isMuted = isOn === 0;

                    const level = busData.level !== undefined ? busData.level : 0;
                    
                    return (
                        <div style={{
                            flex: '0 0 80px', height: '100%',
                            display: 'flex', flexDirection: 'column',
                            borderRight: '1px solid #333', paddingRight: '12px', marginRight: '5px'
                        }}>
                             <FaderStrip 
                                label="MASTER"
                                color="#ffaa00"
                                value={level}
                                isMuted={isMuted}
                                onChange={handleMasterLevel}
                                onMuteToggle={() => handleMasterMute(isMuted)} 
                            />
                        </div>
                    );
                 })()}

                 {isGroupMode ? (
                     // --- GROUP VIEW ---
                     Object.values(
                         channels.reduce((acc, chId) => {
                             const data = getChannelData(chId);
                             if (!data) return acc;
                             
                             // Group Key: Color (or 'No Color')
                             // X32 colors are usually indexed 1-16 or handled as hex strings.
                             // data.color is Hex String (e.g. '#FF0000') or undefined.
                             const groupKey = data.color || '#333333';
                             
                             if (!acc[groupKey]) {
                                 acc[groupKey] = {
                                     id: groupKey,
                                     name: '', // Will infer name? Or just use color?
                                     color: groupKey,
                                     members: []
                                 };
                             }
                             acc[groupKey].members.push(chId);
                             return acc;
                         }, {})
                     ).map(group => {
                         // Calculate Group Values
                         // Level = Max of members?
                         const memberLevels = group.members.map(chId => {
                             const d = getChannelData(chId);
                             const send = d?.mixSends?.[user.mixBusId.toString()] || {};
                             return send.level !== undefined ? send.level : 0;
                         });
                         const maxLevel = Math.max(...memberLevels, 0);

                         // Mute = If ANY is unmuted (0), Group is Unmuted.
                         // Only if ALL are muted is Group Muted.
                         const memberMutes = group.members.map(chId => {
                             const d = getChannelData(chId);
                             const send = d?.mixSends?.[user.mixBusId.toString()] || {};
                             return send.on !== undefined ? send.on : 1; // 1=On
                         });
                         const isGroupUnmuted = memberMutes.some(on => on === 1);
                         
                         // Determine Label
                         let groupLabel = `${group.members.length} Chs`;
                         
                         // 1. Check Admin Groups (Exact Match or Subset?)
                         // SOLO_GROUPS e.g. { drums: ['1'...'8'] }
                         // We have current group members e.g. ['2','3','4'...].
                         // If we find a key where members are part of the set, use it.
                         // OR if the majority of the members belong to a group?
                         // Let's look for a key where most members belong.
                         
                         let bestMatch = null;
                         Object.entries(adminGroups).forEach(([name, ids]) => {
                             // Check overlap
                             const overlap = group.members.filter(id => ids.includes(id));
                             if (overlap.length > 0 && overlap.length >= group.members.length * 0.5) {
                                 // If >50% of this color group belongs to named group, use it.
                                 bestMatch = name;
                             }
                         });
                         
                         if (bestMatch) {
                             groupLabel = bestMatch.toUpperCase();
                         } else {
                             // 2. Fallback Heuristic: Longest Common Prefix
                             const names = group.members.map(chId => {
                                 const d = getChannelData(chId);
                                 return d && d.name ? d.name.trim() : `Ch ${chId}`;
                             });
                             
                             if (names.length > 0) {
                                 groupLabel = names[0];
                                 if (names.length > 1) {
                                     const sorted = [...names].sort();
                                     const first = sorted[0];
                                     const last = sorted[sorted.length - 1];
                                     let i = 0;
                                     while (i < first.length && first.charAt(i) === last.charAt(i)) i++;
                                     const prefix = first.substring(0, i).trim();
                                     if (prefix.length >= 3) groupLabel = prefix.replace(/[-_0-9]*$/, '').trim() || prefix;
                                 }
                             }
                         }

                         return (
                             <div key={group.id} style={{
                                flex: '0 0 80px', height: '100%',
                                display: 'flex', flexDirection: 'column'
                             }}>
                                 <FaderStrip 
                                     label={groupLabel}
                                     color={group.color}
                                     value={maxLevel}
                                     isMuted={!isGroupUnmuted}
                                     onChange={(val) => {
                                         // Proportional or Max-Lock? 
                                         // Max-Lock: simplest for MVP.
                                         // If dragging group fader, set ALL members to this level relative to their own?
                                         // Or just set ALL to this level?
                                         // User said "control all of them at same time".
                                         // VCA behavior (Scaling) is best.
                                         // Ratio = val / currentMax.
                                         // If currentMax is 0, add Delta?
                                         
                                         // COMPLEXITY: Stateless scaling is hard.
                                         // Let's implement ABSOLUTE SET for now as fallback, 
                                         // OR simple Delta if we had previous value.
                                         // But we don't have 'prevVal'.
                                         // Let's try: Calculate scale factor from *current* maxLevel.
                                         
                                         let scale = 0;
                                         if (maxLevel > 0.001) {
                                             scale = val / maxLevel;
                                         }
                                         
                                         group.members.forEach(chId => {
                                             const d = getChannelData(chId);
                                             const send = d?.mixSends?.[user.mixBusId.toString()] || {};
                                             const current = send.level !== undefined ? send.level : 0;
                                             
                                             let next;
                                             if (maxLevel <= 0.001) {
                                                 // Raising from silence: Set all to target val (Absolute)
                                                 next = val; 
                                             } else {
                                                 // Scaling
                                                 next = Math.min(1, Math.max(0, current * scale));
                                             }
                                             handleLevelChange(chId, next);
                                         });
                                     }}
                                     onMuteToggle={() => {
                                         // Toggle Force
                                         const shouldMute = isGroupUnmuted; // If unmuted, go to Mute.
                                         const targetOn = shouldMute ? 0 : 1;
                                         
                                         group.members.forEach(chId => {
                                             const chStr = chId.toString().padStart(2, '0');
                                             const busStr = mixBusId.toString().padStart(2, '0');
                                             sendOsc(`/ch/${chStr}/mix/${busStr}/on`, targetOn);
                                         });
                                     }}
                                 />
                             </div>
                         );
                     })
                 
                 ) : (
                     // --- STANDARD VIEW ---
                     channels.map(chId => {
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
                })
                )}

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
