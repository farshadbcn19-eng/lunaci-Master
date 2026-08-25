const { chromium } = require('playwright');
const fs = require('fs');

const VIEWPORTS = {
  mobile: { width: 390, height: 844 },
  tablet: { width: 768, height: 1024 },
};

const PAGES = {
  home_en: 'https://lunacibarcelona.com/',
  home_es: 'https://lunacibarcelona.com/es/',
  about_en: 'https://lunacibarcelona.com/about-us/',
  about_es: 'https://lunacibarcelona.com/es/sobre-nosotros/',
  products_en: 'https://lunacibarcelona.com/products/',
  products_es: 'https://lunacibarcelona.com/es/productos/',
  shop_en: 'https://lunacibarcelona.com/shop/',
  contact_en: 'https://lunacibarcelona.com/contact/',
};

async function inspect(page) {
  return page.evaluate(() => {
    const result = {};
    result.viewport = { width: window.innerWidth, height: window.innerHeight };
    result.documentWidth = document.documentElement.scrollWidth;
    result.bodyWidth = document.body.scrollWidth;
    result.htmlOverflowX = getComputedStyle(document.documentElement).overflowX;

    const imgs = Array.from(document.querySelectorAll('img'));
    result.images = imgs.slice(0, 40).map((img) => {
      const r = img.getBoundingClientRect();
      const cs = getComputedStyle(img);
      return {
        src: (img.currentSrc || img.src || '').split('/').pop(),
        naturalWidth: img.naturalWidth,
        naturalHeight: img.naturalHeight,
        rect: { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) },
        display: cs.display,
        visibility: cs.visibility,
        objectFit: cs.objectFit,
        overflowParent: (() => {
          let p = img.parentElement;
          for (let i = 0; i < 3 && p; i++, p = p.parentElement) {
            const pcs = getComputedStyle(p);
            if (pcs.overflow === 'hidden' || pcs.overflowX === 'hidden' || pcs.overflowY === 'hidden') {
              return true;
            }
          }
          return false;
        })(),
        offscreenRight: r.x + r.width > window.innerWidth + 2,
        zeroSize: r.width === 0 || r.height === 0,
      };
    });

    return result;
  });
}

(async () => {
  const browser = await chromium.launch();
  const output = {};

  for (const [key, url] of Object.entries(PAGES)) {
    output[key] = {};
    for (const [vpName, vp] of Object.entries(VIEWPORTS)) {
      const page = await browser.newPage({ viewport: vp });
      try {
        await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(800);
        const info = await inspect(page);
        output[key][vpName] = info;
        await page.screenshot({ path: `/tmp/out/${key}-${vpName}.png`, fullPage: true });
      } catch (e) {
        output[key][vpName] = { error: String(e) };
      }
      await page.close();
    }
  }

  fs.writeFileSync('/tmp/out/mobile-audit-info.json', JSON.stringify(output, null, 2));
  console.log(JSON.stringify(output, null, 2));

  await browser.close();
})();
