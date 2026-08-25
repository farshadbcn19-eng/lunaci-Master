const { chromium } = require('playwright');

const PAGES = {
  about_en: 'https://lunacibarcelona.com/about-us/',
  about_es: 'https://lunacibarcelona.com/es/about-us-es/',
};

(async () => {
  const browser = await chromium.launch();

  for (const [key, url] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1200);
    await page.screenshot({ path: `/tmp/out/${key}-hero-crop.png` });
    await page.close();
  }

  await browser.close();
  console.log('done');
})();
