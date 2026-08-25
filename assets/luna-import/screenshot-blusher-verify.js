const { chromium } = require('playwright');

const PAGES = {
  products_en: 'https://lunacibarcelona.com/products/',
  products_es: 'https://lunacibarcelona.com/es/productos/',
};

(async () => {
  const browser = await chromium.launch();

  for (const [key, url] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: { width: 900, height: 900 } });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1200);
    const imgs = await page.$$('img.prod-img');
    let target = null;
    for (const img of imgs) {
      const alt = await img.getAttribute('alt');
      if (alt && alt.toLowerCase().includes('blusher')) {
        target = img;
        break;
      }
    }
    if (target) {
      await target.scrollIntoViewIfNeeded();
      await page.waitForTimeout(400);
      const src = await target.getAttribute('src');
      console.log(key, 'blusher img src:', src);
    } else {
      console.log(key, 'blusher img NOT FOUND');
    }
    await page.screenshot({ path: `/tmp/out/${key}-blusher.png` });
    await page.close();
  }

  await browser.close();
  console.log('done');
})();
