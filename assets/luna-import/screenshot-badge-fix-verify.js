const { chromium } = require('playwright');

const PAGES = {
  home_en: 'https://lunacibarcelona.com/',
  home_es: 'https://lunacibarcelona.com/es/',
};

(async () => {
  const browser = await chromium.launch();

  for (const [key, url] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1200);
    // Scroll the badges row into view (right below the hero)
    const badges = await page.$('.ln-badges');
    if (badges) {
      await badges.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
    }
    await page.screenshot({ path: `/tmp/out/${key}-badges.png` });
    await page.close();
  }

  await browser.close();
  console.log('done');
})();
