const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();

  const pages = [
    { url: 'shipping', file: 'shipping-fullpage.png' },
    { url: 'privacy-policy', file: 'privacy-policy-fullpage.png' },
  ];

  for (const p of pages) {
    const page = await browser.newPage({ viewport: { width: 1400, height: 1000 } });
    await page.route('**/*', route => {
      const headers = { ...route.request().headers(), 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' };
      route.continue({ headers });
    });
    await page.goto(`https://lunacibarcelona.com/${p.url}/?nocache=${Date.now()}`, { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: p.file, fullPage: true });
    console.log('Saved ' + p.file);
    await page.close();
  }

  await browser.close();
})();
