const fs = require('fs');
const path = require('path');

const FILE_PATH = path.join(__dirname, 'setlists.json');

class SetlistManager {
    constructor() {
        this.data = {
            activeSetlistId: 'default',
            setlists: {
                default: { id: 'default', name: 'Default Set', songOrder: [] }
            },
            songs: {},
            musicianRouting: {}, 
            busNames: {} 
        };
        // Runtime State (Not Saved)
        this.runtime = {
            activeSongId: null,
            activePartIndex: null,
            learnMode: false,
            flashIndex: null,
            learnedBuffer: {} // { songId: { partIdx: bars } }
        };
        this.io = null; // Socket IO instance
        this.load();
    }

    // Dependency Injection for Socket.IO
    init(io) {
        this.io = io;
    }

    load() {
        try {
            if (fs.existsSync(FILE_PATH)) {
                const loaded = JSON.parse(fs.readFileSync(FILE_PATH, 'utf8'));
                // Merge loaded data with structure to ensure new fields exist
                this.data = { ...this.data, ...loaded };
                if(!this.data.musicianRouting) this.data.musicianRouting = {};
                if(!this.data.busNames) this.data.busNames = {};
                console.log('📄 Setlists Loaded');
            } else {
                this.save(); // Init file
            }
        } catch (e) {
            console.error('❌ Failed to load setlists:', e);
        }
    }
    
    // ... existing save/getters/setters ...

    save() {
        try {
            fs.writeFileSync(FILE_PATH, JSON.stringify(this.data, null, 2));
        } catch (e) {
            console.error('❌ Failed to save setlists:', e);
        }
    }

    // --- GETTERS ---
    getAll() { return this.data; }
    
    getSetlist(id) { return this.data.setlists[id]; }
    
    // --- ACTIONS ---
    createSong(song) {
        const id = song.id || Date.now().toString();
        this.data.songs[id] = { ...song, id, cues: song.cues || [], charts: song.charts || {} };
        this.save();
        return this.data.songs[id];
    }
    
    // ... updateSong, deleteSong, updateSetlistOrder, updateSetlist ...
    updateSong(id, updates) {
        if (!this.data.songs[id]) return null;
        this.data.songs[id] = { ...this.data.songs[id], ...updates };
        
        // Sync Global Routing if assignments changed
        if (updates.chartAssignments) {
            updates.chartAssignments.forEach(assign => {
                if (assign.inputChannel && assign.monitorBus) {
                    this.data.musicianRouting[assign.inputChannel] = assign.monitorBus;
                }
            });
        }
        
        this.save();
        if (this.io) this.io.emit('song_updated', { id, song: this.data.songs[id] });
        return this.data.songs[id];
    }

    deleteSong(id) {
        if (!this.data.songs[id]) return false;
        
        // Remove from all setlists
        Object.values(this.data.setlists).forEach(setlist => {
            setlist.songOrder = setlist.songOrder.filter(sId => sId !== id);
        });
        
        delete this.data.songs[id];
        this.save();
        return true;
    }

    updateSetlistOrder(setlistId, songOrder) {
        if (!this.data.setlists[setlistId]) return false;
        this.data.setlists[setlistId].songOrder = songOrder;
        this.save();
        return true;
    }

    updateSetlist(id, updates) {
        if (!this.data.setlists[id]) return false;
        this.data.setlists[id] = { ...this.data.setlists[id], ...updates };
        this.save();
        return this.data.setlists[id];
    }

    addChartAssignment(songId, assignment) {
        if (!this.data.songs[songId]) return false;
        if (!this.data.songs[songId].chartAssignments) this.data.songs[songId].chartAssignments = [];
        this.data.songs[songId].chartAssignments.push(assignment);
        
        // Update Global Routing
        if (assignment.inputChannel && assignment.monitorBus) {
            this.data.musicianRouting[assignment.inputChannel] = assignment.monitorBus;
        }
        
        this.save();
        return true;
    }
    
    removeChartAssignment(songId, index) {
        if (!this.data.songs[songId]) return false;
        if (!this.data.songs[songId].chartAssignments) return false;
        this.data.songs[songId].chartAssignments.splice(index, 1);
        this.save();
        return true;
    }
    
    setBusName(busId, name) {
        if (!this.data.busNames) this.data.busNames = {};
        this.data.busNames[String(busId)] = name;
        this.save();
        return this.data.busNames;
    }

    // --- AUTOMATION & RUNTIME ---

    // Select Song by ID
    setActiveSong(songId) {
        // Auto-Learn if missing
        if (songId && !this.data.songs[songId]) {
            if (this.runtime.learnMode) {
                console.log(`🧠 Learn Mode: Creating Song ${songId}`);
                this.createSong({ id: songId, title: `New Song ${songId}` });
            } else {
                return false;
            }
        }
        
        this.runtime.activeSongId = songId;
        this.runtime.activePartIndex = 0; // Reset to first part on song change
        
        // Broadcast
        if (this.io) {
            this.io.emit('setlist_active', { songId: this.runtime.activeSongId });
            console.log(`🎵 Setlist Active Song: ${songId || 'None'}`);
        }
        return true;
    }

    setLearnMode(enabled) {
        this.runtime.learnMode = !!enabled;
        console.log(`🧠 Learn Mode is now: ${this.runtime.learnMode ? 'ON' : 'OFF'}`);
        
        if (this.io) {
            this.io.emit('learn_mode_changed', { enabled: this.runtime.learnMode });
            this.broadcastLearnStatus(); // Refresh status on toggle
        }
    }

    recordLearnedBars(songId, partIdx, bars) {
        if (!this.runtime.learnedBuffer[songId]) this.runtime.learnedBuffer[songId] = {};
        this.runtime.learnedBuffer[songId][partIdx] = bars;
        console.log(`🧠 Buffered Learning: ${songId} #[${partIdx}] -> ${bars} bars`);
        this.broadcastLearnStatus();
    }

    broadcastLearnStatus() {
        if (!this.io) return;
        const songCount = Object.keys(this.runtime.learnedBuffer).length;
        let totalCues = 0;
        Object.values(this.runtime.learnedBuffer).forEach(songBuf => {
            totalCues += Object.keys(songBuf).length;
        });
        this.io.emit('learn_status', { 
            hasData: songCount > 0,
            songCount,
            totalCues 
        });
    }

    applyLearnedBars() {
        const songsInPool = Object.keys(this.runtime.learnedBuffer);
        if (songsInPool.length === 0) return;

        console.log(`🧠 Committing Buffered Learning for ${songsInPool.length} songs...`);
        
        for (const [songId, cuesMap] of Object.entries(this.runtime.learnedBuffer)) {
            const song = this.data.songs[songId];
            if (!song || !song.cues) continue;

            let songUpdated = false;
            const newCues = [...song.cues];
            
            for (const [partIdx, bars] of Object.entries(cuesMap)) {
                const idx = parseInt(partIdx);
                // OVERWRITE: As requested, learning now replaces existing data
                if (newCues[idx]) {
                    newCues[idx] = { ...newCues[idx], bars };
                    songUpdated = true;
                }
            }
            
            if (songUpdated) {
                console.log(`✅ Learned ${songId} - Applying data.`);
                this.updateSong(songId, { cues: newCues });
            }
        }
        
        // Clear buffer after commit
        this.runtime.learnedBuffer = {};
    }

    getActivePartMetadata() {
        const song = this.data.songs[this.runtime.activeSongId];
        if (!song || this.runtime.activePartIndex === null) return null;
        
        const part = song.cues && song.cues[this.runtime.activePartIndex];
        return part || null;
    }

    // Select Specific Part (Cue)
    setActivePart(songId, partIndex) {
        // Ensure song is active
        if (this.runtime.activeSongId !== songId) {
            this.setActiveSong(songId);
        }

        const song = this.data.songs[songId];
        if (!song) return false;

        // Auto-Learn Cue if missing
        if (!song.cues) song.cues = [];
        if (partIndex >= song.cues.length) {
            if (this.runtime.learnMode) {
                console.log(`🧠 Learn Mode: Creating Cue ${partIndex + 1} for Song ${songId}`);
                const newCues = [...song.cues];
                // Fill gaps
                while (newCues.length <= partIndex) {
                    newCues.push({ name: `Cue ${newCues.length + 1}`, type: 'auto', bars: 0 });
                }
                this.updateSong(songId, { cues: newCues });
            } else {
                return false;
            }
        }

        const part = song.cues[partIndex];
        const partName = part ? part.name : `Cue ${partIndex + 1}`;

        this.runtime.activePartIndex = partIndex;

        const payload = {
            songId,
            partIndex,
            partName,
            bars: part ? part.bars : 0
        };

        // Broadcast to 'active_part' channel
        if (this.io) {
            this.io.emit('active_part', payload);
            console.log(`🎵 Setlist Active Part: ${songId} / ${partName} (${payload.bars} bars)`);
        }
        return true;
    }

    // Select Song by Index (for MIDI PC)
    // Uses the currently active setlist's order
    setActiveIndex(index) {
        const setlist = this.data.setlists[this.data.activeSetlistId];
        if (!setlist) return false;
        if (!setlist.songOrder) setlist.songOrder = [];
        
        // --- LEARN MODE: Autonomous Setlist Growth ---
        if (this.runtime.learnMode && index >= setlist.songOrder.length) {
            console.log(`🧠 Learn Mode: Expanding Setlist to index ${index}`);
            // Fill gaps if needed (e.g., PC 10 received when only 2 songs exist)
            while (setlist.songOrder.length <= index) {
                const newSongId = `song_learn_${Date.now()}_${setlist.songOrder.length}`;
                this.createSong({ id: newSongId, title: `Learned Song ${setlist.songOrder.length + 1}` });
                setlist.songOrder.push(newSongId);
            }
            this.save();
        }

        if (index < 0 || index >= setlist.songOrder.length) return false;
        
        const songId = setlist.songOrder[index];
        return this.setActiveSong(songId);
    }

    // Flash a Cue (for UI Warning)
    flashCue(forceIndex = null) {
        // If index provided, flash that. If null, maybe flash "Next"?
        // For now, simple implementation: Broadcast Flash Event
        if (this.io) {
            this.io.emit('setlist_flash', { index: forceIndex });
        }
    }
}

module.exports = new SetlistManager();
