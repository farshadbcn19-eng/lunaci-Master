const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 500, height: 1000 } });
  await page.goto('https://lunacibarcelona.com/shop/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForTimeout(1200);

  const fs = require('fs');
  await page.screenshot({ path: '/tmp/out/shop-all-products.png', fullPage: true });

  await browser.close();
})();
