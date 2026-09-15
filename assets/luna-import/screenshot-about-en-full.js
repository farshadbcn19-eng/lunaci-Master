const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
  await page.route('**/*', route => {
    const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
    route.continue({ headers });
  });
  await page.goto('https://lunacibarcelona.com/about-us/?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1500);
  const height = await page.evaluate(() => document.body.scrollHeight);
  console.log('EN about-us body.scrollHeight: ' + height);
  await page.screenshot({ path: 'about-en-desktop-fullpage.png', fullPage: true });
  console.log('Saved about-en-desktop-fullpage.png');
  await browser.close();
})();
