const fs = require('fs');
const path = require('path');
const authManager = require('./authManager');
const setlistManager = require('./setlistManager');

/**
 * Organizes charts in SharePoint based on the active Setlist.
 * 
 * Structure:
 * [Setlist Name] / [Role] / [Order]_[SongName]_[Role].pdf
 * 
 * @param {string} token - MSAL Access Token
 * @param {string} setlistId - ID of setlist to export (default: active)
 * @param {object} musiciansData - Global musicians data (for roles)
 * @param {object} sharePointConfig - Global SharePoint config
 */
async function organizeSharePoint(token, setlistId, musiciansData, sharePointConfig) {
    console.log(`📂 Starting SharePoint Organization for Setlist: ${setlistId || 'Active'}`);
    
    // 1. Data Prep
    const setlistData = setlistManager.getAll();
    const activeSetlistId = setlistId || setlistData.activeSetlistId;
    const setlist = setlistData.setlists[activeSetlistId];
    
    if (!setlist) throw new Error("Setlist not found");
    
    // Use raw name to match user-created folders (SharePoint supports spaces)
    const setlistName = setlist.name || "Untitled Setlist";
    const driveId = sharePointConfig.driveId;
    const rootFolderId = sharePointConfig.folderId; // Configured parent folder (or null for root)

    const client = authManager.getGraphClient(token);

    // Helper: Map InputChannel -> Role
    const channelToRole = {};
    musiciansData.musicians.forEach(m => {
        if (m.linkedChannels) {
            m.linkedChannels.forEach(ch => {
                channelToRole[String(ch)] = sanitize(m.role || "Unknown");
            });
        }
    });

    // 2. Create/Get Top Level Folder
    // If rootFolderId is set, we look inside it. Else we look in root.
    const parentPath = (rootFolderId && driveId)
        ? `/drives/${driveId}/items/${rootFolderId}/children`
        : (rootFolderId ? `/me/drive/items/${rootFolderId}/children` : `/me/drive/root/children`);

    // Check if folder exists or create it
    let topFolderId = await ensureFolder(client, parentPath, setlistName);
    console.log(`   -> Setlist Folder: ${setlistName} (ID: ${topFolderId})`);

    // 3. Process Songs
    const log = [];
    
    for (let i = 0; i < setlist.songOrder.length; i++) {
        const songId = setlist.songOrder[i];
        const song = setlistData.songs[songId];
        if (!song || !song.chartAssignments) continue;

        const orderPrefix = String(i + 1).padStart(2, '0');
        const songTitle = sanitize(song.title);

        for (const assign of song.chartAssignments) {
            if (!assign.inputChannel || !assign.file) continue;

            const role = channelToRole[String(assign.inputChannel)];
            if (!role) continue; // Skip if no musician mapped

            // Resolve Source File
            // assignment.file.path is relative to root usually (e.g. "uploads/...")
            // We need absolute path.
            let sourcePath = path.resolve(process.cwd(), assign.file.path);
            if (!fs.existsSync(sourcePath)) {
                log.push(`⚠️ Missing File: ${assign.file.originalname} for ${songTitle} (${role})`);
                continue;
            }

            // Target Name: 01_SongTitle_Role.pdf
            const ext = path.extname(assign.file.originalname);
            const targetName = `${orderPrefix}_${songTitle}_${role}${ext}`;

            // 4. Ensure Role Subfolder Exists
            // We search inside the setlist folder
            // API: GET /drives/{drive-id}/items/{item-id}/children
            const setlistChildrenPath = driveId 
                ? `/drives/${driveId}/items/${topFolderId}/children`
                : `/me/drive/items/${topFolderId}/children`;
            const roleFolderId = await ensureFolder(client, setlistChildrenPath, role);

            // 5. Upload File
            // API: PUT /drives/{drive-id}/items/{parent-id}:/{filename}:/content
            const uploadEndpoint = driveId
                ? `/drives/${driveId}/items/${roleFolderId}:/${targetName}:/content`
                : `/me/drive/items/${roleFolderId}:/${targetName}:/content`;
            
            console.log(`   -> Uploading: ${targetName}`);
            try {
                const fileStream = fs.createReadStream(sourcePath);
                await client.api(uploadEndpoint).put(fileStream);
                log.push(`✅ Uploaded: ${role}/${targetName}`);
            } catch (err) {
                console.error(`Error uploading ${targetName}:`, err.message);
                log.push(`❌ Unknown Error: ${targetName} - ${err.message}`);
            }
        }
    }

    return { success: true, log };
}

// Reuseable Folder Creator
// Checks if folder with 'name' exists in 'parentEndpoint' children
// If not, creates it. Returns ID.
async function ensureFolder(client, parentChildrenEndpoint, folderName) {
    try {
        // 1. Search
        // OData filter? Graph API sometimes picky.
        // Let's just list and find (assuming small folder counts < 100 usually)
        const res = await client.api(parentChildrenEndpoint).select('id,name,folder').get();
        const existing = res.value.find(f => f.name === folderName && f.folder);
        
        if (existing) return existing.id;

        // 2. Create
        const newFolder = {
            name: folderName,
            folder: {},
            "@microsoft.graph.conflictBehavior": "rename"
        };
        // POST to children endpoint creates
        const createRes = await client.api(parentChildrenEndpoint).post(newFolder);
        return createRes.id;
    } catch (e) {
        console.error(`Ensure Folder Error (${folderName}):`, e.message);
        throw e;
    }
}

function sanitize(str) {
    return str.replace(/[^a-z0-9]/gi, '_').replace(/_+/g, '_');
}

module.exports = { organizeSharePoint };
