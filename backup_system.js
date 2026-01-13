const fs = require('fs');
const path = require('path');

const BACKUP_DIR = path.join(__dirname, 'backups');
const FILES_TO_BACKUP = [
    'presets.json',
    'musicians.json',
    'setlists.json',
    'x32_state.json'
];
const DIRS_TO_BACKUP = [
    'scenes'
];

function performStartupBackup() {
    try {
        // 1. Create backups dir if missing
        if (!fs.existsSync(BACKUP_DIR)) {
            fs.mkdirSync(BACKUP_DIR);
        }

        // 2. Create timestamped folder
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const sessionDir = path.join(BACKUP_DIR, `boot_${timestamp}`);
        fs.mkdirSync(sessionDir);

        console.log(`🛡️  Performing Startup Backup to: ${sessionDir}`);

        // 3. Copy Files
        FILES_TO_BACKUP.forEach(file => {
            const src = path.join(__dirname, file);
            if (fs.existsSync(src)) {
                fs.copyFileSync(src, path.join(sessionDir, file));
            }
        });

        // 4. Copy Directories (Recursive-ish for scenes)
        DIRS_TO_BACKUP.forEach(dir => {
            const srcDir = path.join(__dirname, dir);
            const destDir = path.join(sessionDir, dir);
            if (fs.existsSync(srcDir)) {
                fs.mkdirSync(destDir);
                fs.readdirSync(srcDir).forEach(file => {
                     // Simple shallow copy for scenes (no subdirs expected)
                     if(file.endsWith('.json')) {
                         fs.copyFileSync(path.join(srcDir, file), path.join(destDir, file));
                     }
                });
            }
        });

        // 5. Cleanup (Keep last 10 backups)
        const backups = fs.readdirSync(BACKUP_DIR)
            .filter(f => f.startsWith('boot_'))
            .map(f => ({ name: f, time: fs.statSync(path.join(BACKUP_DIR, f)).mtime.getTime() }))
            .sort((a, b) => b.time - a.time);

        if (backups.length > 10) {
            backups.slice(10).forEach(b => {
                const p = path.join(BACKUP_DIR, b.name);
                console.log(`🧹 Cleaning old backup: ${b.name}`);
                fs.rmSync(p, { recursive: true, force: true });
            });
        }

    } catch (e) {
        console.error("⚠️  Backup Failed:", e);
    }
}

module.exports = { performStartupBackup };
