const { Client } = require("@microsoft/microsoft-graph-client");
require('isomorphic-fetch');
const serverAuthManager = require('./serverAuthManager');
const fs = require('fs');
const path = require('path');
const setlistManager = require('./setlistManager');

/**
 * Sends charts to a musician via Microsoft Graph.
 * @param {object} musician - The musician object (must have email and role).
 * @param {string} setlistId - The ID of the setlist to send charts for.
 */
async function sendChartsEmail(musician, setlistId) {
    if (!musician.email) throw new Error("Musician has no email address.");

    // 1. Get Access Token (Application Permission)
    const token = await serverAuthManager.getAppToken();

    // 2. Initialize Graph Client
    const client = Client.init({
        authProvider: (done) => done(null, token)
    });

    // 3. Prepare Data
    const setlistData = setlistManager.getAll();
    const setlist = setlistData.setlists[setlistId || setlistData.activeSetlistId];
    if (!setlist) throw new Error("Setlist not found.");

    const attachments = [];
    const missingfiles = [];

    // 4. Collect Charts for Musician's Role
    // We iterate through the setlist to find charts assigned to their role
    // NOTE: This logic mirrors sharePointOrganizor but we need linkedChannels mapping
    
    // We need to know which Input Channels map to this Musician
    const musicianChannels = (musician.linkedChannels || []).map(ch => String(ch));

    if (musicianChannels.length === 0) {
        throw new Error("Musician has no linked channels to identify role/charts.");
    }

    setlist.songOrder.forEach((songId, index) => {
        const song = setlistData.songs[songId];
        if (!song || !song.chartAssignments) return;

        // Dedup: Track latest assignment per input channel
        const latestAssignments = {}; // { inputChannel: assignment }

        song.chartAssignments.forEach(assign => {
            if (!assign.inputChannel || !assign.file) return;

            // Check if this assignment belongs to the musician
            if (musicianChannels.includes(String(assign.inputChannel))) {
                // Check for Placeholder
                if (assign.file.filename === 'noChart.txt' || assign.file.originalname === 'noChart.txt') return;

                // Overwrite ensures we keep the latest one (assuming push order)
                latestAssignments[String(assign.inputChannel)] = assign;
            }
        });

        // Add attachments for unique latest assignments
        // ALSO Dedup by File Path (don't send same PDF twice for different channels)
        const attachedPaths = new Set();

        Object.values(latestAssignments).forEach(assign => {
             const sourcePath = path.resolve(process.cwd(), assign.file.path);
             
             // 1. Dedup by Path
             if (attachedPaths.has(sourcePath)) return;

             if (fs.existsSync(sourcePath)) {
                // 2. Check for empty file
                const stats = fs.statSync(sourcePath);
                if (stats.size === 0) return;

                // Read file as base64 for attachment
                const fileContent = fs.readFileSync(sourcePath).toString('base64');
                
                const orderPrefix = String(index + 1).padStart(2, '0');
                const cleanTitle = song.title.replace(/[^a-zA-Z0-9 ]/g, "").trim();
                
                // Add role/instrument to filename if available, to distinguish valid variants (e.g. Trumpet vs Tenor)
                // But since we dedup by path, variants are distinct files.
                // Let's keep filename clean.
                const fileName = `${orderPrefix}_${cleanTitle}.pdf`;

                attachments.push({
                    "@odata.type": "#microsoft.graph.fileAttachment",
                    "name": fileName,
                    "contentBytes": fileContent,
                    "contentType": "application/pdf"
                });
                
                attachedPaths.add(sourcePath);
            } else {
                missingfiles.push(song.title);
            }
        });
    });

    if (attachments.length === 0) {
        throw new Error("No charts found for your role in this setlist.");
    }

    // 5. Compose Email
    const mail = {
        message: {
            subject: `Setlist Charts: ${setlist.name}`,
            body: {
                contentType: "HTML",
                content: `
                    <h3>Hi ${musician.name},</h3>
                    <p>Here are your charts for the setlist: <strong>${setlist.name}</strong>.</p>
                    <p>Attached: ${attachments.length} file(s).</p>
                    ${missingfiles.length > 0 ? `<p style="color:red">Note: Missing source files for: ${missingfiles.join(', ')}</p>` : ''}
                    <p>Good luck!</p>
                `
            },
            toRecipients: [
                {
                    emailAddress: {
                        address: musician.email
                    }
                }
            ],
            attachments: attachments
        },
        saveToSentItems: false
    };

    // 6. Send
    // API: POST /users/{sender-email}/sendMail  OR  POST /users/{id}/sendMail
    // Since we are in App-Only context, we must send "AS" a user in the tenant.
    // We will assume the user wants to send from the Admin account associated with the App?
    // OR we can just use the first user we find, or a configured "Sender Email".
    // Wait, Application Permissions allow sending AS any user. Use "ed@loboski.nz" from musicians config? 
    // Or just "me" doesn't work in App context.
    
    // Hardcoding sender for now to the 'Machines' user (Ed) or finding the first admin?
    // Let's rely on finding 'Ed Lobo' in musicians?
    // Better: Allow specifying a sender, or default to the recipient (sending to self)?
    // Sending to self is safest. "From: ed@loboski.nz To: ed@loboski.nz"
    
    // Let's default sender to the recipient for "Email Me My Charts" unless we want it from "Admin".
    // Actually, sending FROM the user TO the user is a good pattern for "Email Me".
    const senderEmail = musician.email; 

    try {
        await client.api(`/users/${senderEmail}/sendMail`).post(mail);
        return { success: true, count: attachments.length };
    } catch (e) {
        console.error("Graph Send Error:", e);
        // Fallback: Try sending from the specific Admin User if 'senderEmail' fails (e.g. if musician account doesn't exist in Tenant)
        // If the musician is an EXTERNAL address (gmail), we CANNOT send FROM them.
        // We MUST send FROM a valid user in the tenant.
        
        // We need a specific SENDER address in the tenant.
        // Let's assume the ADMIN (Ed) is the sender.
        const adminEmail = "ed@loboski.nz"; // Hardcoded for safety based on musicians.json
        if (senderEmail !== adminEmail) {
            console.log(`Retrying send from Admin (${adminEmail})...`);
            await client.api(`/users/${adminEmail}/sendMail`).post(mail);
            return { success: true, count: attachments.length, sentFrom: adminEmail };
        }
        throw e;
    }
}

/**
 * Sends a password reset email to the admin.
 * @param {string} targetEmail - The email to send the new password to.
 * @param {string} newPassword - The new system password.
 */
async function sendPasswordReset(targetEmail, newPassword) {
    // 1. Auth
    const token = await serverAuthManager.getAppToken();
    const client = Client.init({
        authProvider: (done) => done(null, token)
    });

    // 2. Compose Email
    const mail = {
        message: {
            subject: `ACTION REQUIRED: Console Password Reset`,
            body: {
                contentType: "HTML",
                content: `
                    <h3>Password Reset Request</h3>
                    <p>The Admin Console password has been reset.</p>
                    <p><strong>New Password:</strong> <span style="font-family:monospace; font-size:1.2em; background:#eee; padding:2px 5px;">${newPassword}</span></p>
                    <p>Please log in and optionally change this password in the System Settings.</p>
                    <p><em>If you did not request this, please secure your system immediately.</em></p>
                `
            },
            toRecipients: [
                { emailAddress: { address: targetEmail } }
            ]
        },
        saveToSentItems: false
    };

    // 3. Send
    // Send FROM the admin email to ensure it looks legitimate and is in the tenant
    const senderEmail = "ed@loboski.nz"; 
    
    console.log(`Sending Password Reset email to ${targetEmail} from ${senderEmail}...`);
    
    await client.api(`/users/${senderEmail}/sendMail`).post(mail);
    return { success: true };
}

module.exports = { sendChartsEmail, sendPasswordReset };
