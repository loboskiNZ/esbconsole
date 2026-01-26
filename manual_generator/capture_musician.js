const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
  const TARGET_URL = 'https://loboski-djtuygtid.local:3000/musician';
  const PUBLIC_DIR = path.join(__dirname, '../client/public/manuals');

  console.log("🚀 Launching Browser...");
  const browser = await puppeteer.launch({
      headless: "new",
      args: [
        '--no-sandbox', 
        '--disable-setuid-sandbox', 
        '--ignore-certificate-errors',
        '--allow-insecure-localhost'
      ],
      ignoreHTTPSErrors: true,
      defaultViewport: { width: 1280, height: 720, isMobile: true, hasTouch: true }
  });
  
  const page = await browser.newPage();

  // Helper to wait
  const wait = (ms) => new Promise(r => setTimeout(r, ms));

  try {
      console.log(`🌍 Navigating to ${TARGET_URL}...`);
      await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded' });
      
      // 1. Wait out the Splash Screen (2s + buffer)
      console.log("⏳ Waiting for splash...");
      await wait(3000);

      // 2. Enter Email
      console.log("⌨️ Entering Email...");
      await page.waitForSelector('input[type="email"]');
      await page.type('input[type="email"]', 'ed@loboski.nz', { delay: 50 });
      await page.click('button[type="submit"]');

      // 3. Enter Password
      console.log("⌨️ Entering Password...");
      await page.waitForSelector('input[type="password"]');
      await wait(500); // Animation buffer
      await page.type('input[type="password"]', 'otokia', { delay: 50 });
      await page.click('button[type="submit"]');

      // 4. Wait for Login (~2s wait for splash fade out again?)
      console.log("⏳ Waiting for login...");
      await page.waitForNetworkIdle({ timeout: 5000 }).catch(() => {}); // Wait for network or timeout
      await wait(2000); // Explicit wait for React render

      // 5. Capture Mixer (Default View)
      console.log("📸 Capturing Mixer...");
      await page.screenshot({ path: path.join(PUBLIC_DIR, 'musician_mixer.png') });

      // 6. Click 'Charts' Tab (3rd item)
      console.log("👆 Clicking Charts Tab...");
      // The nav bar is at the bottom fixed.
      // We can find the container by looking for fixed bottom elements
      const navButtons = await page.$$('div[style*="bottom: 0"] > div[style*="flex: 1"]');
      
      if (navButtons.length >= 3) {
          await navButtons[2].click();
          await wait(1000); // Transition wait
          console.log("📸 Capturing Charts...");
          await page.screenshot({ path: path.join(PUBLIC_DIR, 'musician_charts.png') });
      } else {
          console.error("❌ Could not find nav buttons!", navButtons.length);
      }

      console.log("✅ Musician Capture Complete!");

  } catch (e) {
      console.error("❌ Error during capture:", e);
      // Capture error state
      await page.screenshot({ path: path.join(PUBLIC_DIR, 'error_capture.png') });
  } finally {
      await browser.close();
  }
})();
