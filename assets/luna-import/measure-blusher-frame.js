const { chromium } = require('playwright');

const PAGES = {
  products_en_desktop: { url: 'https://lunacibarcelona.com/products/', viewport: { width: 1440, height: 900 } },
  products_en_mobile: { url: 'https://lunacibarcelona.com/products/', viewport: { width: 390, height: 844 } },
};

(async () => {
  const browser = await chromium.launch();
  const output = {};

  for (const [key, cfg] of Object.entries(PAGES)) {
    const page = await browser.newPage({ viewport: cfg.viewport });
    await page.goto(cfg.url + '?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
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
      const info = await target.evaluate((img) => {
        const wrap = img.closest('.prod-img-wrap') || img.parentElement;
        const imgRect = img.getBoundingClientRect();
        const wrapRect = wrap ? wrap.getBoundingClientRect() : null;
        const imgCS = getComputedStyle(img);
        const wrapCS = wrap ? getComputedStyle(wrap) : null;
        return {
          naturalWidth: img.naturalWidth,
          naturalHeight: img.naturalHeight,
          imgRect: { width: imgRect.width, height: imgRect.height },
          wrapRect: wrapRect ? { width: wrapRect.width, height: wrapRect.height } : null,
          imgStyle: { width: imgCS.width, height: imgCS.height, objectFit: imgCS.objectFit, objectPosition: imgCS.objectPosition, display: imgCS.display },
          wrapStyle: wrapCS ? { width: wrapCS.width, height: wrapCS.height, padding: wrapCS.padding, display: wrapCS.display, aspectRatio: wrapCS.aspectRatio } : null,
          wrapClassName: wrap ? wrap.className : null,
        };
      });
      output[key] = info;
      await target.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
    } else {
      output[key] = { error: 'blusher img not found' };
    }
    await page.screenshot({ path: `/tmp/out/${key}.png` });
    await page.close();
  }

  const fs = require('fs');
  fs.writeFileSync('/tmp/out/measure-info.json', JSON.stringify(output, null, 2));
  console.log(JSON.stringify(output, null, 2));

  await browser.close();
})();
