const { chromium } = require('playwright');

const PAGES = {
  about_en: 'https://lunacibarcelona.com/about-us/',
  about_es: 'https://lunacibarcelona.com/es/about-us-es/',
};

(async () => {
  const browser = await chromium.launch();

  for (const [key, url] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: { width: 900, height: 900 } });
    await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
    await page.waitForTimeout(1200);
    const storyImg = await page.$('.lna-story__img img');
    if (storyImg) {
      await storyImg.scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      const src = await storyImg.getAttribute('src');
      console.log(key, 'story img src:', src);
    } else {
      console.log(key, 'story img NOT FOUND');
    }
    await page.screenshot({ path: `/tmp/out/${key}-story.png` });
    await page.close();
  }

  await browser.close();
  console.log('done');
})();
