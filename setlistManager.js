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
            musicianRouting: {}, // { "inputChannelId": "monitorBusId" }
            busNames: {} // { "busId": "My Group Name" }
        };
        this.load();
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
}

module.exports = new SetlistManager();
