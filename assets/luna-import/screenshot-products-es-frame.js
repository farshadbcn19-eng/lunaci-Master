const { chromium } = require('playwright');

const VIEWPORTS = {
  desktop: { width: 1920, height: 1080 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 390, height: 844 },
};

const URLS = {
  es: 'https://lunacibarcelona.com/es/productos/',
  en: 'https://lunacibarcelona.com/products/',
};

async function inspect(page) {
  return page.evaluate(() => {
    const result = {};
    result.viewport = { width: window.innerWidth, height: window.innerHeight };
    result.documentWidth = document.documentElement.scrollWidth;
    result.bodyWidth = document.body.scrollWidth;
    result.htmlOverflowX = getComputedStyle(document.documentElement).overflowX;

    const target = document.querySelector('.elementor-element-0329089');
    const innerChild = target ? target.querySelector(':scope > .e-con-inner') : null;

    if (target) {
      const r = target.getBoundingClientRect();
      const cs = getComputedStyle(target);
      result.target = {
        rect: { x: r.x, width: r.width },
        maxWidth: cs.maxWidth, width: cs.width,
      };
    } else {
      result.target = null;
    }

    if (innerChild) {
      const r2 = innerChild.getBoundingClientRect();
      const cs2 = getComputedStyle(innerChild);
      result.innerChild = {
        rect: { x: r2.x, width: r2.width },
        maxWidth: cs2.maxWidth, width: cs2.width,
      };
    } else {
      result.innerChild = null;
    }

    result.hasFixRuleInAnyInlineStyle = Array.from(document.querySelectorAll('style')).some(s => s.textContent.includes('elementor-element-0329089'));

    // Check the nav/header too - is it fixed/full-width and could visually clash
    const nav = document.querySelector('.lp-nav, nav');
    if (nav) {
      const rn = nav.getBoundingClientRect();
      result.nav = { rect: { x: rn.x, width: rn.width }, position: getComputedStyle(nav).position };
    }

    return result;
  });
}

(async () => {
  const browser = await chromium.launch();
  const output = {};

  for (const [lang, url] of Object.entries(URLS)) {
    output[lang] = {};
    for (const [vpName, vp] of Object.entries(VIEWPORTS)) {
      const page = await browser.newPage({ viewport: vp });
      await page.goto(url + '?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
      const info = await inspect(page);
      output[lang][vpName] = info;
      await page.screenshot({ path: `/tmp/out/${lang}-${vpName}.png`, fullPage: false });
      await page.close();
    }
  }

  require('fs').writeFileSync('/tmp/out/frame-info.json', JSON.stringify(output, null, 2));
  console.log(JSON.stringify(output, null, 2));

  await browser.close();
})();
