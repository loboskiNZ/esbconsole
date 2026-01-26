const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

(async () => {
  // Config
  const TARGET_URL = 'https://loboski-djtuygtid.local:3000';
  const OUTPUT_DIR = path.join(__dirname, '../client/dist/manuals'); 
  const PUBLIC_DIR = path.join(__dirname, '../client/public/manuals');

  if (!fs.existsSync(PUBLIC_DIR)) {
      console.log(`Creating directory: ${PUBLIC_DIR}`);
      fs.mkdirSync(PUBLIC_DIR, { recursive: true });
  }

  console.log("🚀 Launching Browser...");
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
  await page.setViewport({ width: 1280, height: 720 });

  console.log(`🌍 Navigating to ${TARGET_URL}...`);
  try {
      await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded' });
      // Wait a bit for React to hydrate
      await new Promise(r => setTimeout(r, 2000));
      
      console.log("📸 Taking Screenshot: test_capture.png");
      await page.screenshot({ path: path.join(PUBLIC_DIR, 'test_capture.png') });
      
      console.log("✅ Success! Screenshot saved to client/public/manuals/test_capture.png");

  } catch (e) {
      console.error("❌ Error during capture:", e);
  } finally {
      await browser.close();
  }
})();
