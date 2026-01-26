const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
    const TARGET_URL = 'https://loboski-djtuygtid.local:3000';
    const PUBLIC_DIR = path.join(__dirname, '../client/public/manuals');

    console.log("🚀 Launching Browser (Admin)...");
    const browser = await puppeteer.launch({
        headless: "new",
        args: [
            '--no-sandbox', 
            '--disable-setuid-sandbox', 
            '--ignore-certificate-errors',
            '--allow-insecure-localhost'
        ],
        ignoreHTTPSErrors: true,
        defaultViewport: { width: 1440, height: 900 }
    });
    
    const page = await browser.newPage();
    const wait = (ms) => new Promise(r => setTimeout(r, ms));

    try {
        // 1. NAVIGATE TO ROOT (ADMIN LOGIN)
        console.log(`🌍 Navigating to ${TARGET_URL}...`);
        await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded' });
        await wait(3000); // Wait for splash

        // 2. CAPTURE LOGIN SCREEN
        console.log("📸 Capturing Admin Login...");
        await page.waitForSelector('input[type="password"]');
        await page.screenshot({ path: path.join(PUBLIC_DIR, 'admin_login.png') });

        // 3. ENTER PASSWORD & LOGIN
        console.log("🔑 Logging in...");
        await page.type('input[type="password"]', 'otokia', { delay: 50 });
        await page.click('button[type="submit"]');
        
        // Wait for unlock
        await wait(2000); 

        // 4. CAPTURE SCENES LIST (Main View)
        console.log("📸 Capturing Scenes List...");
        // Ensure we are on the main dashboard
        await page.waitForSelector('.app-container'); 
        await wait(1000); // Wait/Animation
        await page.screenshot({ path: path.join(PUBLIC_DIR, 'admin_scenes_list.png') });

        // 5. NAVIGATE TO STAGE PLOT (Click 'MUSICIANS' -> 'STAGE PLOT')
        console.log("👆 Navigation to Stage Plot...");
        
        // Find 'MUSICIANS' button using evaluate
        const musiciansBtn = await page.evaluateHandle(() => {
            const buttons = Array.from(document.querySelectorAll('button'));
            return buttons.find(b => b.textContent.includes('MUSICIANS'));
        });

        if (musiciansBtn) {
            await musiciansBtn.click();
            await wait(1000); // Modal open animation

            // Find 'STAGE PLOT' tab
            const stageTab = await page.evaluateHandle(() => {
                const divs = Array.from(document.querySelectorAll('div'));
                return divs.find(d => d.textContent === 'STAGE PLOT');
            });

            if (stageTab) {
                await stageTab.click();
                await wait(1000); // Tab switch
                
                console.log("📸 Capturing Stage Plot...");
                await page.screenshot({ path: path.join(PUBLIC_DIR, 'admin_stage_plot.png') });
            } else {
                console.error("❌ Could not find STAGE PLOT tab");
            }
        } else {
            console.error("❌ Could not find MUSICIANS button");
        }

        console.log("✅ Admin Capture Complete!");

    } catch (e) {
        console.error("❌ Error during capture:", e);
        await page.screenshot({ path: path.join(PUBLIC_DIR, 'error_admin.png') });
    } finally {
        await browser.close();
    }
})();
