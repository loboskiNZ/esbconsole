const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
  const TARGET_URL = 'https://loboski-djtuygtid.local:3000';
  const PUBLIC_DIR = path.join(__dirname, '../client/public/manuals');

  console.log("🚀 Launching Browser (Debug)...");
  const browser = await puppeteer.launch({
      headless: "new",
      args: [
        '--no-sandbox', 
        '--disable-setuid-sandbox', 
        '--ignore-certificate-errors',
        '--allow-insecure-localhost'
      ],
      ignoreHTTPSErrors: true
  });
  
  const page = await browser.newPage();
  const wait = (ms) => new Promise(r => setTimeout(r, ms));

  try {
      console.log(`🌍 Navigating to ${TARGET_URL}...`);
      await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded' });
      await wait(4000); // Wait long enough for Splash to pass

      // Dump HTML
      const html = await page.content();
      fs.writeFileSync(path.join(__dirname, 'debug_dump.html'), html);
      console.log("📄 Saved HTML dump to manual_generator/debug_dump.html");

      // Screenshot
      await page.screenshot({ path: path.join(PUBLIC_DIR, 'debug_state.png') });
      console.log("📸 Saved screenshot to debug_state.png");

  } catch (e) {
      console.error("❌ Error:", e);
  } finally {
      await browser.close();
  }
})();
